<?php
// =============================================
// Smart Classroom — Guardian: Link Student
// =============================================
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/layout.php';
requireLogin();
if (userRole() !== 'guardian') redirect(BASE_URL . '/index.php');

$user       = currentUser();
$guardianId = $user['id'];
$success    = $error = '';

// Handle link request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'link') {
        $email = trim($_POST['student_email'] ?? '');
        $stu   = $pdo->prepare("SELECT * FROM users WHERE email=? AND role='student'");
        $stu->execute([$email]);
        $student = $stu->fetch();
        if (!$student) {
            $error = "No student account found with email: {$email}";
        } else {
            $existing = $pdo->prepare("SELECT * FROM guardian_links WHERE guardian_id=? AND student_id=?");
            $existing->execute([$guardianId, $student['id']]);
            if ($existing->fetch()) {
                $error = 'You have already sent a link request to this student.';
            } else {
                $ins = $pdo->prepare("INSERT INTO guardian_links (guardian_id, student_id, status) VALUES (?,?,'approved')");
                $ins->execute([$guardianId, $student['id']]);
                // Notify student
                $notif = $pdo->prepare("INSERT INTO notifications (user_id, title, message, type) VALUES (?,?,?,?)");
                $notif->execute([$student['id'], 'Guardian Linked', "{$user['name']} has linked to your account as guardian", 'info']);
                $success = "Successfully linked to {$student['name']}!";
            }
        }
    }
    if ($action === 'unlink') {
        $linkId = (int)($_POST['link_id'] ?? 0);
        $pdo->prepare("DELETE FROM guardian_links WHERE id=? AND guardian_id=?")->execute([$linkId, $guardianId]);
        $success = 'Student unlinked successfully.';
    }
}

// Load linked students
$linked = $pdo->prepare("SELECT gl.*,u.name,u.email,u.avatar FROM guardian_links gl JOIN users u ON u.id=gl.student_id WHERE gl.guardian_id=? ORDER BY gl.linked_at DESC");
$linked->execute([$guardianId]);
$linkedStudents = $linked->fetchAll();

renderHead('Link to Student');
?>
<body>
<div class="app-wrapper">
<?php renderSidebar($user, 'link.php'); ?>
<div class="main-content">
<?php renderTopbar('Link to Student', $user, [['icon'=>'fa-link','label'=>'Add Student','onclick'=>"openModal('link-modal')"]]); ?>

<div class="page-content animate-up">
  <div style="max-width:700px;margin:0 auto">

    <?php if ($success): ?><div class="auto-dismiss" style="background:rgba(16,185,129,0.1);border:1px solid rgba(16,185,129,0.3);border-radius:var(--radius-sm);padding:0.875rem 1rem;color:var(--success);display:flex;align-items:center;gap:0.5rem;margin-bottom:1rem"><i class="fas fa-check-circle"></i> <?= e($success) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="auto-dismiss" style="background:rgba(239,68,68,0.1);border:1px solid rgba(239,68,68,0.3);border-radius:var(--radius-sm);padding:0.875rem 1rem;color:var(--danger);display:flex;align-items:center;gap:0.5rem;margin-bottom:1rem"><i class="fas fa-times-circle"></i> <?= e($error) ?></div><?php endif; ?>

    <!-- Info Banner -->
    <div style="background:linear-gradient(135deg,rgba(245,158,11,0.12),rgba(99,102,241,0.08));border:1px solid rgba(245,158,11,0.25);border-radius:var(--radius-lg);padding:1.5rem;margin-bottom:1.5rem">
      <h2 style="font-size:1.1rem;font-weight:800;margin-bottom:0.375rem">👪 Guardian–Student Link</h2>
      <p style="font-size:0.875rem;color:var(--text-secondary)">Link your account to your student's email to monitor their academic progress, attendance, and grades in real-time.</p>
      <button class="btn btn-warning" style="margin-top:1rem" onclick="openModal('link-modal')"><i class="fas fa-link"></i> Link to a Student</button>
    </div>

    <!-- Linked Students -->
    <div class="card">
      <div class="card-header">
        <div class="card-title"><i class="fas fa-users" style="color:var(--warning)"></i> Linked Students</div>
        <span class="badge badge-warning"><?= count($linkedStudents) ?></span>
      </div>
      <?php if (empty($linkedStudents)): ?>
      <div class="empty-state">
        <div class="empty-icon"><i class="fas fa-user-slash"></i></div>
        <div class="empty-title">No students linked yet</div>
        <div class="empty-sub">Link your child's account using their registered email address</div>
        <button class="btn btn-warning" onclick="openModal('link-modal')"><i class="fas fa-link"></i> Link Now</button>
      </div>
      <?php else: ?>
      <div style="display:flex;flex-direction:column;gap:0.875rem">
        <?php foreach ($linkedStudents as $s): ?>
        <div style="display:flex;align-items:center;gap:1rem;padding:1rem;background:var(--bg-surface);border:1px solid var(--border);border-radius:var(--radius-md)">
          <div class="avatar avatar-lg" style="background:linear-gradient(135deg,var(--success),var(--primary))"><?= strtoupper($s['name'][0]) ?></div>
          <div style="flex:1">
            <div style="font-weight:700;font-size:1rem"><?= e($s['name']) ?></div>
            <div style="font-size:0.8rem;color:var(--text-muted)"><?= e($s['email']) ?></div>
            <div style="margin-top:0.25rem">
              <span class="badge badge-<?= $s['status']==='approved'?'success':'warning' ?>"><?= $s['status'] ?></span>
              <span style="font-size:0.7rem;color:var(--text-muted);margin-left:0.5rem">Linked <?= timeAgo($s['linked_at']) ?></span>
            </div>
          </div>
          <div style="display:flex;gap:0.5rem">
            <a href="<?= BASE_URL ?>/analytics/performance.php?student_id=<?= $s['student_id'] ?>" class="btn btn-primary btn-sm"><i class="fas fa-chart-line"></i> Monitor</a>
            <form method="POST" style="display:inline">
              <input type="hidden" name="action" value="unlink">
              <input type="hidden" name="link_id" value="<?= $s['id'] ?>">
              <button type="submit" class="btn btn-danger btn-sm" onclick="return confirm('Remove this student link?')"><i class="fas fa-unlink"></i></button>
            </form>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- Link Modal -->
<div class="modal-overlay" id="link-modal">
  <div class="modal" style="max-width:440px">
    <div class="modal-header">
      <div class="modal-title"><i class="fas fa-link" style="color:var(--warning)"></i> Link to Student</div>
      <button class="modal-close">✕</button>
    </div>
    <form method="POST">
      <input type="hidden" name="action" value="link">
      <div class="modal-body">
        <p style="font-size:0.875rem;color:var(--text-secondary);margin-bottom:1rem">Enter your child's registered email address. They must already have a student account in the system.</p>
        <div class="form-group">
          <label class="form-label">Student Email *</label>
          <div class="input-group"><i class="fas fa-envelope input-icon"></i><input type="email" name="student_email" class="form-control" placeholder="student@example.com" required value="student@demo.com"></div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary modal-close">Cancel</button>
        <button type="submit" class="btn btn-warning"><i class="fas fa-paper-plane"></i> Send Link Request</button>
      </div>
    </form>
  </div>
</div>

</div></div>
<?php renderFooter(); ?>
</body></html>
