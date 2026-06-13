<?php
// =============================================
// Smart Classroom — Video Meet
// =============================================
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/layout.php';
requireLogin();

$user      = currentUser();
$classId   = (int)($_GET['class_id'] ?? 0);
$isTeacher = ($user['role'] === 'teacher');

if (!$classId) redirect(BASE_URL . '/index.php');

$cls = $pdo->prepare("SELECT * FROM classes WHERE id=?");
$cls->execute([$classId]);
$class = $cls->fetch();

// Load sessions
$sessions = $pdo->prepare("SELECT m.*,u.name as creator_name FROM meet_sessions m JOIN users u ON u.id=m.created_by WHERE m.class_id=? ORDER BY m.created_at DESC");
$sessions->execute([$classId]);
$sessionList = $sessions->fetchAll();

// Create session (teacher)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $isTeacher) {
    $title     = trim($_POST['title'] ?? '');
    $meetLink  = trim($_POST['meet_link'] ?? '');
    $scheduled = $_POST['scheduled_at'] ? date('Y-m-d H:i:s', strtotime($_POST['scheduled_at'])) : null;
    $status    = $_POST['status'] ?? 'scheduled';

    if ($title) {
        $ins = $pdo->prepare("INSERT INTO meet_sessions (class_id, title, meet_link, scheduled_at, status, created_by) VALUES (?,?,?,?,?,?)");
        $ins->execute([$classId, $title, $meetLink, $scheduled, $status, $user['id']]);

        // Notify students
        $members = $pdo->prepare("SELECT user_id FROM class_members WHERE class_id=?");
        $members->execute([$classId]);
        foreach ($members->fetchAll() as $m) {
            $notif = $pdo->prepare("INSERT INTO notifications (user_id, title, message, type) VALUES (?,?,?,?)");
            $notif->execute([$m['user_id'], '📹 Video Meet', "'{$title}' scheduled in {$class['name']}", 'info']);
        }
        redirect(BASE_URL . "/classroom/video_meet.php?class_id={$classId}&created=1");
    }
}

renderHead('Video Meet · ' . ($class['name'] ?? ''));
?>
<body>
<div class="app-wrapper">
<?php renderSidebar($user); ?>
<div class="main-content">
<?php renderTopbar('Video Meet', $user, $isTeacher ? [['icon'=>'fa-plus','label'=>'Schedule Meet','onclick'=>"openModal('create-meet-modal')"]] : []); ?>

<div class="page-content animate-up">
  <div style="display:flex;align-items:center;gap:0.5rem;margin-bottom:1.25rem;font-size:0.875rem;color:var(--text-muted)">
    <a href="<?= BASE_URL ?>/classroom/index.php?id=<?= $classId ?>">← Back to <?= e($class['name'] ?? '') ?></a>
  </div>

  <?php if (isset($_GET['created'])): ?>
  <div class="auto-dismiss" style="background:rgba(16,185,129,0.1);border:1px solid rgba(16,185,129,0.3);border-radius:var(--radius-sm);padding:0.75rem 1rem;color:var(--success);display:flex;align-items:center;gap:0.5rem;margin-bottom:1rem">
    <i class="fas fa-check-circle"></i> Meet session created & students notified!
  </div>
  <?php endif; ?>

  <div style="display:grid;grid-template-columns:1fr 300px;gap:1.5rem">
    <div>
      <!-- Live / Upcoming Sessions -->
      <?php $liveSessions = array_filter($sessionList, fn($s) => $s['status'] === 'live'); ?>
      <?php if (!empty($liveSessions)): ?>
      <div style="background:rgba(239,68,68,0.08);border:1px solid rgba(239,68,68,0.3);border-radius:var(--radius-lg);padding:1.25rem;margin-bottom:1.5rem">
        <div style="display:flex;align-items:center;gap:0.75rem;margin-bottom:1rem">
          <span class="live-badge">LIVE</span>
          <span style="font-weight:700">Active Session</span>
        </div>
        <?php foreach ($liveSessions as $s): ?>
        <div style="display:flex;align-items:center;justify-content:space-between;gap:1rem">
          <div>
            <div style="font-size:1.1rem;font-weight:800"><?= e($s['title']) ?></div>
            <div style="font-size:0.8rem;color:var(--text-muted)">Started by <?= e($s['creator_name']) ?></div>
          </div>
          <?php if ($s['meet_link']): ?>
          <a href="<?= e($s['meet_link']) ?>" target="_blank" class="btn btn-danger btn-lg animate-pulse">
            <i class="fas fa-video"></i> Join Now
          </a>
          <?php endif; ?>
        </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>

      <!-- Session List -->
      <div class="card">
        <div class="card-header">
          <div class="card-title"><i class="fas fa-video" style="color:var(--primary)"></i> All Sessions</div>
          <?php if ($isTeacher): ?>
          <button class="btn btn-primary btn-sm" onclick="openModal('create-meet-modal')"><i class="fas fa-plus"></i> Schedule</button>
          <?php endif; ?>
        </div>

        <?php if (empty($sessionList)): ?>
        <div class="empty-state">
          <div class="empty-icon"><i class="fas fa-video"></i></div>
          <div class="empty-title">No meet sessions yet</div>
          <div class="empty-sub"><?= $isTeacher ? 'Schedule a video meet for your class' : 'Your teacher has not scheduled any sessions' ?></div>
          <?php if ($isTeacher): ?>
          <button class="btn btn-primary" onclick="openModal('create-meet-modal')"><i class="fas fa-plus"></i> Schedule Meet</button>
          <?php endif; ?>
        </div>
        <?php else: ?>
        <div style="display:flex;flex-direction:column;gap:0.75rem">
          <?php foreach ($sessionList as $s): ?>
          <div style="display:flex;align-items:center;gap:1rem;padding:1rem;background:var(--bg-surface);border:1px solid var(--border);border-radius:var(--radius-md);transition:all 0.2s" onmouseover="this.style.borderColor='rgba(99,102,241,0.3)'" onmouseout="this.style.borderColor='var(--border)'">
            <div style="width:48px;height:48px;border-radius:var(--radius-md);background:<?= $s['status']==='live'?'rgba(239,68,68,0.15)':($s['status']==='ended'?'var(--bg-overlay)':'rgba(99,102,241,0.15)') ?>;display:flex;align-items:center;justify-content:center;font-size:1.2rem;color:<?= $s['status']==='live'?'var(--danger)':($s['status']==='ended'?'var(--text-muted)':'var(--primary)') ?>">
              <i class="fas fa-video"></i>
            </div>
            <div style="flex:1">
              <div style="font-weight:700;font-size:0.95rem"><?= e($s['title']) ?></div>
              <div style="font-size:0.8rem;color:var(--text-muted)">
                <?= $s['scheduled_at'] ? '📅 ' . date('M d, Y g:i A', strtotime($s['scheduled_at'])) : 'Instant meet' ?>
                · by <?= e($s['creator_name']) ?>
              </div>
            </div>
            <div style="display:flex;align-items:center;gap:0.75rem">
              <span class="badge badge-<?= $s['status']==='live'?'danger':($s['status']==='ended'?'warning':'info') ?>"><?= $s['status'] ?></span>
              <?php if ($s['meet_link'] && $s['status'] !== 'ended'): ?>
              <a href="<?= e($s['meet_link']) ?>" target="_blank" class="btn btn-<?= $s['status']==='live'?'danger':'primary' ?> btn-sm">
                <i class="fas fa-video"></i> <?= $s['status']==='live' ? 'Join' : 'Open Link' ?>
              </a>
              <?php endif; ?>
              <?php if ($isTeacher): ?>
              <form method="POST" action="<?= BASE_URL ?>/api/classes.php" style="display:inline">
                <input type="hidden" name="action" value="end_meet">
                <input type="hidden" name="meet_id" value="<?= $s['id'] ?>">
                <?php if ($s['status'] === 'live'): ?>
                <button type="submit" class="btn btn-secondary btn-sm"><i class="fas fa-stop"></i> End</button>
                <?php elseif ($s['status'] === 'scheduled'): ?>
                <button type="submit" name="set_status" value="live" class="btn btn-success btn-sm"><i class="fas fa-play"></i> Start</button>
                <?php endif; ?>
              </form>
              <?php endif; ?>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
      </div>
    </div>

    <!-- Info Sidebar -->
    <div style="display:flex;flex-direction:column;gap:1.5rem">
      <div class="card" style="background:linear-gradient(135deg,rgba(99,102,241,0.12),rgba(168,85,247,0.08));border-color:rgba(99,102,241,0.25)">
        <div style="font-size:2.5rem;margin-bottom:0.875rem">📹</div>
        <div style="font-weight:800;font-size:1rem;margin-bottom:0.5rem">Smart Video Meet</div>
        <p style="font-size:0.8rem;color:var(--text-secondary)">Schedule or start instant video meeting sessions for your class. Students will be notified automatically.</p>
      </div>

      <div class="card">
        <div class="card-title" style="margin-bottom:0.875rem"><i class="fas fa-info-circle" style="color:var(--info)"></i> Quick Stats</div>
        <div style="display:flex;flex-direction:column;gap:0.5rem;font-size:0.875rem">
          <div style="display:flex;justify-content:space-between"><span style="color:var(--text-muted)">Total Sessions</span><strong><?= count($sessionList) ?></strong></div>
          <div style="display:flex;justify-content:space-between"><span style="color:var(--text-muted)">Live Now</span><strong style="color:var(--danger)"><?= count(array_filter($sessionList, fn($s) => $s['status']==='live')) ?></strong></div>
          <div style="display:flex;justify-content:space-between"><span style="color:var(--text-muted)">Upcoming</span><strong style="color:var(--primary)"><?= count(array_filter($sessionList, fn($s) => $s['status']==='scheduled')) ?></strong></div>
          <div style="display:flex;justify-content:space-between"><span style="color:var(--text-muted)">Completed</span><strong style="color:var(--text-muted)"><?= count(array_filter($sessionList, fn($s) => $s['status']==='ended')) ?></strong></div>
        </div>
      </div>

      <div class="card">
        <div class="card-title" style="margin-bottom:0.875rem"><i class="fas fa-external-link-alt" style="color:var(--success)"></i> Supported Platforms</div>
        <div style="display:flex;flex-direction:column;gap:0.625rem;font-size:0.875rem">
          <?php foreach ([['Google Meet','fab fa-google','#4285F4'],['Zoom','fas fa-video','#2D8CFF'],['Microsoft Teams','fab fa-microsoft','#6264A7'],['Jitsi Meet','fas fa-comments','#97979A'],['YouTube Live','fab fa-youtube','#FF0000']] as [$name,$icon,$color]): ?>
          <div style="display:flex;align-items:center;gap:0.625rem">
            <i class="<?= $icon ?>" style="color:<?= $color ?>;width:16px;text-align:center"></i>
            <span><?= $name ?></span>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Create Meet Modal -->
<div class="modal-overlay" id="create-meet-modal">
  <div class="modal" style="max-width:500px">
    <div class="modal-header">
      <div class="modal-title"><i class="fas fa-video" style="color:var(--primary)"></i> Schedule Video Meet</div>
      <button class="modal-close">✕</button>
    </div>
    <form method="POST">
      <div class="modal-body" style="display:flex;flex-direction:column;gap:1rem">
        <div class="form-group"><label class="form-label">Session Title *</label><input type="text" name="title" class="form-control" placeholder="e.g., Week 5 Lecture" required></div>
        <div class="form-group"><label class="form-label">Meeting Link</label><input type="url" name="meet_link" class="form-control" placeholder="https://meet.google.com/..."></div>
        <div class="form-group"><label class="form-label">Schedule Date & Time</label><input type="datetime-local" name="scheduled_at" class="form-control"></div>
        <div class="form-group">
          <label class="form-label">Status</label>
          <select name="status" class="form-control">
            <option value="scheduled">Scheduled (notify students)</option>
            <option value="live">Live Now (start immediately)</option>
          </select>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary modal-close">Cancel</button>
        <button type="submit" class="btn btn-primary"><i class="fas fa-paper-plane"></i> Create Session</button>
      </div>
    </form>
  </div>
</div>

</div></div>
<?php renderFooter(); ?>
</body></html>
