<?php
// =============================================
// Smart Classroom — Offline Assignment Submission
// =============================================
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/layout.php';
requireLogin();

$user      = currentUser();
$isTeacher = ($user['role'] === 'teacher');
$studentId = $user['id'];

// Get all offline-allowed assignments for student
$myClasses = $pdo->prepare("SELECT c.id FROM classes c JOIN class_members cm ON cm.class_id=c.id WHERE cm.user_id=? AND (c.status='active' OR c.status IS NULL)");
$myClasses->execute([$studentId]);
$classIds = array_column($myClasses->fetchAll(), 'id');

// Step tracking
$step = (int)($_GET['step'] ?? 1);
$assignId = (int)($_GET['assign_id'] ?? 0);

// Load available assignments for offline
$offlineAssignments = [];
if (!empty($classIds)) {
    $placeholders = implode(',', array_fill(0, count($classIds), '?'));
    $oa = $pdo->prepare("SELECT a.*,c.name as class_name FROM assignments a JOIN classes c ON c.id=a.class_id WHERE a.class_id IN ({$placeholders}) AND a.allow_offline=1 AND a.id NOT IN (SELECT assignment_id FROM submissions WHERE student_id=?) ORDER BY a.due_date ASC");
    $oa->execute(array_merge($classIds, [$studentId]));
    $offlineAssignments = $oa->fetchAll();
}

// Load my tokens
$myTokens = $pdo->prepare("SELECT ot.*,a.title as assignment_title,c.name as class_name FROM offline_tokens ot JOIN assignments a ON a.id=ot.assignment_id JOIN classes c ON c.id=a.class_id WHERE ot.student_id=? ORDER BY ot.created_at DESC");
$myTokens->execute([$studentId]);
$tokenList = $myTokens->fetchAll();

// Teacher: verify token
$verifyResult = null;
if ($isTeacher && isset($_GET['verify'])) {
    $token = trim($_GET['verify'] ?? '');
    $tv = $pdo->prepare("SELECT ot.*,u.name as student_name,a.title,c.name as class_name FROM offline_tokens ot JOIN users u ON u.id=ot.student_id JOIN assignments a ON a.id=ot.assignment_id JOIN classes c ON c.id=a.class_id WHERE ot.token=?");
    $tv->execute([$token]);
    $verifyResult = $tv->fetch();
}

// Generate token (student POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$isTeacher) {
    $aId = (int)($_POST['assignment_id'] ?? 0);
    if ($aId) {
        $existing = $pdo->prepare("SELECT id FROM offline_tokens WHERE assignment_id=? AND student_id=?");
        $existing->execute([$aId, $studentId]);
        if (!$existing->fetch()) {
            $token = strtoupper(bin2hex(random_bytes(8)));
            $ins = $pdo->prepare("INSERT INTO offline_tokens (assignment_id, student_id, token) VALUES (?,?,?)");
            $ins->execute([$aId, $studentId, $token]);
        }
        redirect(BASE_URL . '/offline/submission.php?step=3&assign_id=' . $aId);
    }
}

// Teacher verify POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $isTeacher && isset($_POST['verify_token'])) {
    $token = trim($_POST['token'] ?? '');
    $upd = $pdo->prepare("UPDATE offline_tokens SET status='verified', submitted_at=NOW() WHERE token=?");
    $upd->execute([$token]);

    // Create submission record
    $tv = $pdo->prepare("SELECT * FROM offline_tokens WHERE token=?");
    $tv->execute([$token]);
    $tok = $tv->fetch();
    if ($tok) {
        $check = $pdo->prepare("SELECT id FROM submissions WHERE assignment_id=? AND student_id=?");
        $check->execute([$tok['assignment_id'], $tok['student_id']]);
        if (!$check->fetch()) {
            $ins = $pdo->prepare("INSERT INTO submissions (assignment_id, student_id, status, is_offline, offline_token) VALUES (?,?,'submitted',1,?)");
            $ins->execute([$tok['assignment_id'], $tok['student_id'], $token]);
        }
    }
    redirect(BASE_URL . '/offline/submission.php?verified=1&token=' . $token);
}

// Get selected assignment for token generation
$selectedAssign = null;
if ($assignId) {
    $sa = $pdo->prepare("SELECT a.*,c.name as class_name FROM assignments a JOIN classes c ON c.id=a.class_id WHERE a.id=?");
    $sa->execute([$assignId]);
    $selectedAssign = $sa->fetch();
}
$myToken = null;
if ($assignId && !$isTeacher) {
    $mt = $pdo->prepare("SELECT * FROM offline_tokens WHERE assignment_id=? AND student_id=?");
    $mt->execute([$assignId, $studentId]);
    $myToken = $mt->fetch();
}

renderHead('Offline Submission System');
?>
<body>
<div class="app-wrapper">
<?php renderSidebar($user); ?>
<div class="main-content">
<?php renderTopbar('Offline Assignment Submission', $user); ?>

<div class="page-content animate-up">

  <?php if (isset($_GET['verified'])): ?>
  <div class="auto-dismiss" style="background:rgba(16,185,129,0.1);border:1px solid rgba(16,185,129,0.3);border-radius:var(--radius-sm);padding:0.875rem 1rem;color:var(--success);display:flex;align-items:center;gap:0.5rem;margin-bottom:1.25rem">
    <i class="fas fa-check-circle fa-lg"></i> <strong>Token verified!</strong> Submission recorded successfully.
  </div>
  <?php endif; ?>

  <!-- Page Header -->
  <div class="page-header">
    <div>
      <div class="page-title">📡 Offline Submission System</div>
      <div class="page-subtitle">Submit assignments without internet using QR token codes</div>
    </div>
  </div>

  <?php if ($isTeacher): ?>
  <!-- ── TEACHER VIEW: Verify tokens ─────────── -->
  <div style="display:grid;grid-template-columns:1fr 1fr;gap:1.5rem">
    <!-- Verify Form -->
    <div class="card">
      <div class="card-header">
        <div class="card-title"><i class="fas fa-qrcode" style="color:var(--primary)"></i> Verify Offline Token</div>
      </div>
      <p style="font-size:0.875rem;color:var(--text-secondary);margin-bottom:1rem">Enter the student's offline submission token to verify and record their submission.</p>
      <form method="POST">
        <input type="hidden" name="verify_token" value="1">
        <div class="form-group" style="margin-bottom:1rem">
          <label class="form-label">Offline Token</label>
          <input type="text" name="token" class="form-control" placeholder="e.g., ABCD1234EFGH5678" required style="text-transform:uppercase;font-size:1rem;letter-spacing:0.1em;font-weight:700;text-align:center">
        </div>
        <button type="submit" class="btn btn-primary btn-full"><i class="fas fa-check"></i> Verify Token</button>
      </form>

      <?php if (isset($_GET['verify']) && $verifyResult): ?>
      <div style="margin-top:1.25rem;background:rgba(16,185,129,0.1);border:1px solid rgba(16,185,129,0.3);border-radius:var(--radius-md);padding:1rem">
        <div style="color:var(--success);font-weight:700;margin-bottom:0.5rem"><i class="fas fa-check-circle"></i> Valid Token</div>
        <div style="font-size:0.875rem;display:flex;flex-direction:column;gap:0.25rem">
          <div><strong>Student:</strong> <?= e($verifyResult['student_name']) ?></div>
          <div><strong>Assignment:</strong> <?= e($verifyResult['title']) ?></div>
          <div><strong>Class:</strong> <?= e($verifyResult['class_name']) ?></div>
          <div><strong>Status:</strong> <span class="badge badge-<?= $verifyResult['status']==='verified'?'success':'warning' ?>"><?= $verifyResult['status'] ?></span></div>
        </div>
      </div>
      <?php elseif (isset($_GET['verify'])): ?>
      <div style="margin-top:1rem;background:rgba(239,68,68,0.1);border:1px solid rgba(239,68,68,0.3);border-radius:var(--radius-sm);padding:0.875rem;color:var(--danger);font-size:0.875rem">
        <i class="fas fa-times-circle"></i> Token not found or invalid.
      </div>
      <?php endif; ?>
    </div>

    <!-- Pending offline tokens -->
    <div class="card">
      <div class="card-header">
        <div class="card-title"><i class="fas fa-list" style="color:var(--warning)"></i> Pending Offline Submissions</div>
      </div>
      <?php
        $pending = $pdo->prepare("SELECT ot.*,u.name as student_name,a.title,c.name as class_name FROM offline_tokens ot JOIN users u ON u.id=ot.student_id JOIN assignments a ON a.id=ot.assignment_id JOIN classes c ON c.id=a.class_id WHERE ot.status='pending' ORDER BY ot.created_at DESC LIMIT 20");
        $pending->execute();
        $pendingTokens = $pending->fetchAll();
      ?>
      <?php if (empty($pendingTokens)): ?>
      <div class="empty-state"><div class="empty-icon"><i class="fas fa-check"></i></div><div class="empty-title">All caught up!</div></div>
      <?php else: ?>
      <div class="table-wrap">
        <table>
          <thead><tr><th>Student</th><th>Assignment</th><th>Token</th><th>Action</th></tr></thead>
          <tbody>
          <?php foreach ($pendingTokens as $t): ?>
          <tr>
            <td style="font-size:0.85rem;font-weight:500"><?= e($t['student_name']) ?></td>
            <td style="font-size:0.8rem;color:var(--text-muted)"><?= e(mb_strimwidth($t['title'],0,25,'…')) ?></td>
            <td><code style="background:var(--bg-overlay);padding:0.2rem 0.5rem;border-radius:4px;font-size:0.75rem;letter-spacing:0.08em"><?= e($t['token']) ?></code></td>
            <td>
              <form method="POST" style="display:inline">
                <input type="hidden" name="verify_token" value="1">
                <input type="hidden" name="token" value="<?= $t['token'] ?>">
                <button class="btn btn-success btn-sm"><i class="fas fa-check"></i> Verify</button>
              </form>
            </td>
          </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php endif; ?>
    </div>
  </div>

  <?php else: ?>
  <!-- ── STUDENT VIEW ─────────────────────────── -->

  <!-- Step Progress -->
  <div class="card" style="margin-bottom:1.5rem">
    <div class="steps">
      <div class="step <?= $step >= 1 ? ($step > 1 ? 'done' : 'active') : '' ?>">
        <div class="step-circle"><?= $step > 1 ? '<i class="fas fa-check"></i>' : '1' ?></div>
        <div class="step-label">Select Assignment</div>
      </div>
      <div class="step-line <?= $step > 1 ? 'done' : '' ?>"></div>
      <div class="step <?= $step >= 2 ? ($step > 2 ? 'done' : 'active') : '' ?>">
        <div class="step-circle"><?= $step > 2 ? '<i class="fas fa-check"></i>' : '2' ?></div>
        <div class="step-label">Generate Token</div>
      </div>
      <div class="step-line <?= $step > 2 ? 'done' : '' ?>"></div>
      <div class="step <?= $step >= 3 ? 'active' : '' ?>">
        <div class="step-circle">3</div>
        <div class="step-label">Show to Teacher</div>
      </div>
      <div class="step-line"></div>
      <div class="step">
        <div class="step-circle">4</div>
        <div class="step-label">Teacher Confirms</div>
      </div>
    </div>
  </div>

  <div style="display:grid;grid-template-columns:1fr 320px;gap:1.5rem">
    <div>
      <!-- Step 1: Select -->
      <?php if ($step === 1 || !$assignId): ?>
      <div class="card">
        <div class="card-header">
          <div class="card-title"><i class="fas fa-file-alt" style="color:var(--primary)"></i> Step 1: Select Assignment</div>
        </div>
        <?php if (empty($offlineAssignments)): ?>
        <div class="empty-state"><div class="empty-icon"><i class="fas fa-tasks"></i></div><div class="empty-title">No offline-eligible assignments</div><div class="empty-sub">All your assignments are submitted or none allow offline submission</div></div>
        <?php else: ?>
        <div style="display:flex;flex-direction:column;gap:0.75rem">
          <?php foreach ($offlineAssignments as $a): ?>
          <a href="?step=2&assign_id=<?= $a['id'] ?>" class="assignment-card" style="text-decoration:none">
            <div class="assignment-icon" style="background:rgba(99,102,241,0.15);color:var(--primary)"><i class="fas fa-file-alt"></i></div>
            <div class="assignment-info">
              <div class="assignment-title"><?= e($a['title']) ?></div>
              <div class="assignment-meta">
                <?= e($a['class_name']) ?>
                <?php if ($a['due_date']): ?> · Due <?= date('M d', strtotime($a['due_date'])) ?><?php endif; ?>
              </div>
            </div>
            <i class="fas fa-arrow-right text-muted"></i>
          </a>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
      </div>

      <!-- Step 2: Generate Token -->
      <?php elseif ($step === 2 && $selectedAssign): ?>
      <div class="card">
        <div class="card-header">
          <div class="card-title"><i class="fas fa-key" style="color:var(--warning)"></i> Step 2: Generate Offline Token</div>
        </div>
        <div style="background:var(--bg-surface);border-radius:var(--radius-md);padding:1rem;margin-bottom:1rem">
          <div style="font-weight:700"><?= e($selectedAssign['title']) ?></div>
          <div style="font-size:0.8rem;color:var(--text-muted)"><?= e($selectedAssign['class_name']) ?></div>
        </div>
        <p style="font-size:0.875rem;color:var(--text-secondary);margin-bottom:1rem">Click the button below to generate a unique offline token. Show this token to your teacher physical class to record your submission.</p>
        <form method="POST">
          <input type="hidden" name="assignment_id" value="<?= $assignId ?>">
          <button type="submit" class="btn btn-warning btn-full btn-lg" onclick="setLoading(this)"><i class="fas fa-key"></i> Generate My Token</button>
        </form>
      </div>

      <!-- Step 3: Show Token -->
      <?php elseif ($step === 3 && $selectedAssign && $myToken): ?>
      <div class="card" style="text-align:center">
        <div class="card-header" style="justify-content:center">
          <div class="card-title" style="color:var(--success)"><i class="fas fa-check-circle"></i> Step 3: Show This to Your Teacher</div>
        </div>
        <div style="margin:1.5rem auto">
          <div style="font-size:0.75rem;text-transform:uppercase;letter-spacing:0.15em;color:var(--text-muted);margin-bottom:0.5rem">Your Offline Token</div>
          <div style="font-size:2rem;font-weight:900;letter-spacing:0.25em;color:var(--warning);background:rgba(245,158,11,0.1);border:2px dashed rgba(245,158,11,0.4);border-radius:var(--radius-lg);padding:1.25rem 2rem;display:inline-block;cursor:pointer" onclick="SCS.copyText('<?= $myToken['token'] ?>','Token copied!')">
            <?= chunk_split($myToken['token'], 4, ' ') ?>
          </div>
          <div style="font-size:0.75rem;color:var(--text-muted);margin-top:0.5rem">Click to copy · <span style="color:var(--primary)">Token #<?= $myToken['id'] ?></span></div>
        </div>

        <!-- Fake QR Code representation -->
        <div style="margin:0 auto 1.5rem;display:inline-block;padding:1rem;background:white;border-radius:var(--radius-md);box-shadow:var(--shadow-md)">
          <div style="display:grid;grid-template-columns:repeat(8,24px);gap:2px">
            <?php
            srand(crc32($myToken['token']));
            for ($i = 0; $i < 64; $i++) {
                $dark = rand(0,1);
                echo "<div style='width:24px;height:24px;background:" . ($dark ? '#1a1a2e' : '#ffffff') . ";border-radius:2px'></div>";
            }
            ?>
          </div>
        </div>

        <div style="font-size:0.875rem;color:var(--text-secondary);margin-bottom:1.25rem">
          <strong style="color:var(--text-primary)"><?= e($selectedAssign['title']) ?></strong><br>
          <?= e($selectedAssign['class_name']) ?>
          <?php if ($selectedAssign['due_date']): ?> · Due <?= date('M d, Y', strtotime($selectedAssign['due_date'])) ?><?php endif; ?>
        </div>

        <span class="badge badge-<?= $myToken['status']==='verified'?'success':($myToken['status']==='submitted'?'info':'warning') ?>" style="font-size:0.875rem;padding:0.4rem 1rem">
          Status: <?= strtoupper($myToken['status']) ?>
        </span>
      </div>
      <?php endif; ?>
    </div>

    <!-- Sidebar: My Tokens -->
    <div style="display:flex;flex-direction:column;gap:1.5rem">
      <div class="card">
        <div class="card-header">
          <div class="card-title"><i class="fas fa-history" style="color:var(--info)"></i> My Tokens</div>
        </div>
        <?php if (empty($tokenList)): ?>
        <div class="empty-state" style="padding:1.5rem"><div class="empty-icon" style="width:48px;height:48px;font-size:1.25rem"><i class="fas fa-key"></i></div><div class="empty-title" style="font-size:0.875rem">No tokens yet</div></div>
        <?php else: ?>
        <?php foreach ($tokenList as $tk): ?>
        <div style="padding:0.75rem 0;border-bottom:1px solid var(--border)">
          <div style="font-size:0.8rem;font-weight:600;margin-bottom:0.25rem"><?= e(mb_strimwidth($tk['assignment_title'],0,28,'…')) ?></div>
          <div style="font-size:0.7rem;color:var(--text-muted);margin-bottom:0.375rem"><?= e($tk['class_name']) ?></div>
          <div style="display:flex;align-items:center;justify-content:space-between">
            <code style="font-size:0.7rem;background:var(--bg-overlay);padding:0.2rem 0.4rem;border-radius:4px;letter-spacing:0.05em"><?= substr($tk['token'],0,8) ?>…</code>
            <span class="badge badge-<?= $tk['status']==='verified'?'success':($tk['status']==='submitted'?'info':'warning') ?>"><?= $tk['status'] ?></span>
          </div>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
      </div>

      <div class="card" style="background:var(--bg-surface)">
        <div style="font-size:0.875rem;color:var(--text-secondary)">
          <div style="font-weight:700;margin-bottom:0.75rem;color:var(--text-primary)"><i class="fas fa-info-circle" style="color:var(--info)"></i> How It Works</div>
          <?php foreach (['Select the assignment you want to submit offline','Generate a unique secure token','Show the token/QR to your teacher','Teacher scans/enters token to verify your submission'] as $i => $step): ?>
          <div style="display:flex;gap:0.625rem;margin-bottom:0.5rem">
            <span style="width:20px;height:20px;background:var(--primary);border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:0.65rem;font-weight:700;flex-shrink:0"><?= $i+1 ?></span>
            <span style="font-size:0.8rem"><?= $step ?></span>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
  </div>
  <?php endif; ?>
</div>
</div></div>
<?php renderFooter(); ?>
</body></html>
