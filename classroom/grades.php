<?php
// =============================================
// Smart Classroom — Grades Page (Teacher grading view)
// =============================================
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/layout.php';
requireLogin();

$user      = currentUser();
$classId   = (int)($_GET['class_id'] ?? 0);
$assignId  = (int)($_GET['assign'] ?? 0);
$isTeacher = ($user['role'] === 'teacher');

if (!$classId) redirect(BASE_URL . '/index.php');

// Handle grade submission (teacher)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $isTeacher) {
    $subId    = (int)($_POST['submission_id'] ?? 0);
    $grade    = min(100, max(0, (int)($_POST['grade'] ?? 0)));
    $feedback = trim($_POST['feedback'] ?? '');

    $upd = $pdo->prepare("UPDATE submissions SET grade=?, feedback=?, status='graded', graded_at=NOW() WHERE id=?");
    $upd->execute([$grade, $feedback, $subId]);

    // Notify student
    $sub = $pdo->prepare("SELECT s.*,a.title,a.points FROM submissions s JOIN assignments a ON a.id=s.assignment_id WHERE s.id=?");
    $sub->execute([$subId]);
    $subData = $sub->fetch();
    if ($subData) {
        $notif = $pdo->prepare("INSERT INTO notifications (user_id, title, message, type) VALUES (?,?,?,?)");
        $notif->execute([$subData['student_id'], 'Assignment Graded! ⭐', "'{$subData['title']}' has been graded: {$grade}/{$subData['points']}", 'success']);
    }
    redirect(BASE_URL . "/classroom/grades.php?class_id={$classId}&assign={$assignId}&graded=1");
}

$cls = $pdo->prepare("SELECT * FROM classes WHERE id=?");
$cls->execute([$classId]);
$class = $cls->fetch();

// Assignments for this class
$assignments = $pdo->prepare("SELECT a.*,(SELECT COUNT(*) FROM submissions WHERE assignment_id=a.id) as sub_count,(SELECT COUNT(*) FROM submissions WHERE assignment_id=a.id AND grade IS NOT NULL) as graded_count FROM assignments a WHERE a.class_id=? ORDER BY a.created_at DESC");
$assignments->execute([$classId]);
$assignList = $assignments->fetchAll();

// Submissions for selected assignment
$submissions = [];
if ($assignId) {
    $subs = $pdo->prepare("SELECT s.*,u.name as student_name FROM submissions s JOIN users u ON u.id=s.student_id WHERE s.assignment_id=? ORDER BY s.submitted_at");
    $subs->execute([$assignId]);
    $submissions = $subs->fetchAll();

    $selAssign = $pdo->prepare("SELECT * FROM assignments WHERE id=?");
    $selAssign->execute([$assignId]);
    $selectedAssign = $selAssign->fetch();
}

renderHead('Grades · ' . ($class['name'] ?? ''));
?>
<body>
<div class="app-wrapper">
<?php renderSidebar($user); ?>
<div class="main-content">
<?php renderTopbar('Grades', $user); ?>

<div class="page-content animate-up">
  <div style="display:flex;align-items:center;gap:0.5rem;margin-bottom:1.25rem;font-size:0.875rem;color:var(--text-muted)">
    <a href="<?= BASE_URL ?>/classroom/index.php?id=<?= $classId ?>&tab=grades">← Back to <?= e($class['name'] ?? '') ?></a>
  </div>

  <?php if (isset($_GET['graded'])): ?>
  <div class="auto-dismiss" style="background:rgba(16,185,129,0.1);border:1px solid rgba(16,185,129,0.3);border-radius:var(--radius-sm);padding:0.875rem 1rem;color:var(--success);display:flex;align-items:center;gap:0.5rem;margin-bottom:1rem">
    <i class="fas fa-check-circle"></i> Grade saved and student notified!
  </div>
  <?php endif; ?>

  <div style="display:grid;grid-template-columns:260px 1fr;gap:1.5rem">

    <!-- Assignment list -->
    <div>
      <div class="card">
        <div class="card-title" style="margin-bottom:0.875rem"><i class="fas fa-file-alt" style="color:var(--primary)"></i> Assignments</div>
        <?php foreach ($assignList as $a): ?>
        <a href="?class_id=<?= $classId ?>&assign=<?= $a['id'] ?>" style="display:flex;flex-direction:column;gap:0.25rem;padding:0.75rem;border-radius:var(--radius-sm);text-decoration:none;background:<?= $assignId===$a['id']?'rgba(99,102,241,0.12)':'transparent' ?>;border:1px solid <?= $assignId===$a['id']?'var(--primary)':'transparent' ?>;transition:all 0.2s;margin-bottom:0.375rem" onmouseover="this.style.background='var(--bg-hover)'" onmouseout="this.style.background='<?= $assignId===$a['id']?'rgba(99,102,241,0.12)':'transparent' ?>'">
          <div style="font-size:0.875rem;font-weight:600;color:var(--text-primary)"><?= e(mb_strimwidth($a['title'],0,28,'…')) ?></div>
          <div style="display:flex;justify-content:space-between;font-size:0.72rem;color:var(--text-muted)">
            <span><?= $a['sub_count'] ?> submitted</span>
            <span style="color:<?= $a['graded_count'] >= $a['sub_count'] && $a['sub_count']>0 ? 'var(--success)' : 'var(--warning)' ?>"><?= $a['graded_count'] ?> graded</span>
          </div>
          <div class="progress-bar" style="height:4px;margin-top:0.25rem">
            <div class="progress-fill <?= $a['graded_count'] >= $a['sub_count'] && $a['sub_count']>0 ? 'success' : 'warning' ?>" style="width:<?= $a['sub_count']>0?round($a['graded_count']/$a['sub_count']*100):0 ?>%"></div>
          </div>
        </a>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- Submissions -->
    <div>
      <?php if ($assignId && !empty($submissions)): ?>
      <div class="card">
        <div class="card-header">
          <div class="card-title"><i class="fas fa-inbox" style="color:var(--info)"></i> Submissions — <?= e($selectedAssign['title'] ?? '') ?></div>
          <div style="display:flex;gap:0.5rem">
            <span class="badge badge-info"><?= count($submissions) ?> total</span>
            <span class="badge badge-success"><?= count(array_filter($submissions, fn($s) => $s['grade'] !== null)) ?> graded</span>
          </div>
        </div>
        <div style="display:flex;flex-direction:column;gap:1.25rem">
          <?php foreach ($submissions as $sub): ?>
          <div style="background:var(--bg-surface);border:1px solid var(--border);border-radius:var(--radius-md);padding:1.125rem">
            <!-- Student Info -->
            <div style="display:flex;align-items:center;gap:0.875rem;margin-bottom:1rem;padding-bottom:0.875rem;border-bottom:1px solid var(--border)">
              <div class="avatar" style="background:linear-gradient(135deg,var(--primary),var(--purple))"><?= strtoupper($sub['student_name'][0]) ?></div>
              <div style="flex:1">
                <div style="font-weight:700"><?= e($sub['student_name']) ?></div>
                <div style="font-size:0.75rem;color:var(--text-muted)">Submitted <?= timeAgo($sub['submitted_at']) ?></div>
              </div>
              <div style="display:flex;align-items:center;gap:0.5rem">
                <?php if ($sub['is_offline']): ?><span class="badge badge-info"><i class="fas fa-wifi-slash"></i> Offline</span><?php endif; ?>
                <span class="badge badge-<?= ['submitted'=>'info','graded'=>'success','late'=>'warning','missing'=>'danger'][$sub['status']] ?>"><?= $sub['status'] ?></span>
                <?php if ($sub['grade'] !== null): ?><span style="font-weight:800;font-size:1rem;color:var(--<?= $sub['grade'] >= 70 ? 'success' : ($sub['grade'] >= 50 ? 'warning' : 'danger') ?>)"><?= $sub['grade'] ?>%</span><?php endif; ?>
              </div>
            </div>

            <!-- Submission Content -->
            <?php if ($sub['text_content']): ?>
            <div style="background:var(--bg-base);border-radius:var(--radius-sm);padding:0.875rem;font-size:0.875rem;color:var(--text-secondary);white-space:pre-wrap;max-height:150px;overflow-y:auto;margin-bottom:0.875rem"><?= e($sub['text_content']) ?></div>
            <?php endif; ?>
            <?php if ($sub['file_path']): ?>
            <div style="margin-bottom:0.875rem">
              <a href="<?= BASE_URL ?>/uploads/submissions/<?= e($sub['file_path']) ?>" target="_blank" class="btn btn-ghost btn-sm"><i class="fas fa-paperclip"></i> Download Attachment</a>
            </div>
            <?php endif; ?>

            <!-- Grade Form -->
            <?php if ($isTeacher): ?>
            <form method="POST" style="display:flex;gap:0.875rem;align-items:flex-end;flex-wrap:wrap">
              <input type="hidden" name="submission_id" value="<?= $sub['id'] ?>">
              <div class="form-group" style="flex:0 0 120px">
                <label class="form-label">Grade (0–<?= $selectedAssign['points'] ?? 100 ?>)</label>
                <input type="number" name="grade" id="grade-<?= $sub['id'] ?>" class="form-control" value="<?= $sub['grade'] ?? '' ?>" min="0" max="<?= $selectedAssign['points'] ?? 100 ?>" placeholder="Score">
              </div>
              <div class="form-group" style="flex:1;min-width:200px">
                <label class="form-label">Feedback</label>
                <input type="text" name="feedback" id="feedback-<?= $sub['id'] ?>" class="form-control" value="<?= e($sub['feedback'] ?? '') ?>" placeholder="Optional feedback...">
              </div>
              <button type="submit" class="btn btn-success btn-sm"><i class="fas fa-check"></i> <?= $sub['grade'] !== null ? 'Update' : 'Grade' ?></button>
              <?php if (!empty($sub['text_content'])): ?>
              <button type="button" class="btn btn-secondary btn-sm" id="ai-btn-<?= $sub['id'] ?>" onclick="aiGrade(<?= $sub['id'] ?>, <?= $selectedAssign['id'] ?>)">
                <i class="fas fa-robot"></i> AI Suggest
              </button>
              <?php endif; ?>
            </form>
            <?php if (!empty($sub['text_content'])): ?>
            <div id="ai-hint-<?= $sub['id'] ?>" style="display:none;font-size:0.72rem;color:var(--text-muted);margin-top:0.4rem">
              <i class="fas fa-info-circle" style="color:var(--warning)"></i> AI suggestion — please review before saving.
            </div>
            <?php endif; ?>
            <?php endif; ?>
          </div>
          <?php endforeach; ?>
        </div>
      </div>

      <?php elseif ($assignId): ?>
      <div class="empty-state card"><div class="empty-icon"><i class="fas fa-inbox"></i></div><div class="empty-title">No submissions yet</div><div class="empty-sub">Students haven't submitted this assignment yet</div></div>
      <?php else: ?>
      <div class="empty-state card"><div class="empty-icon"><i class="fas fa-mouse-pointer"></i></div><div class="empty-title">Select an assignment</div><div class="empty-sub">Click on an assignment from the sidebar to view and grade submissions</div></div>
      <?php endif; ?>
    </div>
  </div>
</div>
</div></div>
<?php renderFooter(); ?>
<script>
async function aiGrade(subId, assignId) {
  const btn      = document.getElementById('ai-btn-' + subId);
  const gradeIn  = document.getElementById('grade-' + subId);
  const feedIn   = document.getElementById('feedback-' + subId);
  const hint     = document.getElementById('ai-hint-' + subId);

  const orig = btn.innerHTML;
  btn.disabled = true;
  btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Thinking...';

  try {
    const fd = new FormData();
    fd.append('submission_id', subId);
    fd.append('assignment_id', assignId);

    const res  = await fetch('<?= BASE_URL ?>/api/ai_grade.php', { method: 'POST', body: fd });
    const data = await res.json();

    if (data.error) {
      alert('AI Error: ' + data.error);
    } else {
      // Animate the fill
      gradeIn.value = '';
      feedIn.value  = '';
      gradeIn.style.transition = 'background 0.4s';
      feedIn.style.transition  = 'background 0.4s';

      gradeIn.value = data.grade;
      feedIn.value  = data.feedback;

      gradeIn.style.background = 'rgba(99,102,241,0.15)';
      feedIn.style.background  = 'rgba(99,102,241,0.15)';
      setTimeout(() => {
        gradeIn.style.background = '';
        feedIn.style.background  = '';
      }, 1400);

      if (hint) hint.style.display = 'block';
    }
  } catch (e) {
    alert('Network error. Could not reach AI service.');
  } finally {
    btn.disabled = false;
    btn.innerHTML = orig;
  }
}
</script>
</body></html>
