<?php
// =============================================
// Smart Classroom — Student Dashboard
// =============================================
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/layout.php';
requireLogin();
if (userRole() !== 'student') redirect(BASE_URL . '/index.php');

$user      = currentUser();
$studentId = $user['id'];

// My Classes
$classes = $pdo->prepare("SELECT c.*,u.name as teacher_name,(SELECT COUNT(*) FROM class_members WHERE class_id=c.id) as member_count FROM classes c JOIN class_members cm ON cm.class_id=c.id JOIN users u ON u.id=c.teacher_id WHERE cm.user_id=? AND (c.status='active' OR c.status IS NULL) ORDER BY cm.joined_at DESC");
$classes->execute([$studentId]);
$classList = $classes->fetchAll();
$classIds  = array_column($classList, 'id');

// Pending assignments
$pendingAssign = $pdo->prepare("SELECT a.*,c.name as class_name FROM assignments a JOIN classes c ON c.id=a.class_id JOIN class_members cm ON cm.class_id=c.id WHERE cm.user_id=? AND a.id NOT IN (SELECT assignment_id FROM submissions WHERE student_id=?) AND (a.due_date IS NULL OR a.due_date >= NOW()) ORDER BY a.due_date ASC LIMIT 8");
$pendingAssign->execute([$studentId, $studentId]);
$pendingList = $pendingAssign->fetchAll();

// My grades summary
$gradesSummary = $pdo->prepare("SELECT AVG(s.grade) as avg_grade, COUNT(s.id) as total_graded FROM submissions s JOIN assignments a ON a.id=s.assignment_id JOIN class_members cm ON cm.class_id=a.class_id WHERE cm.user_id=? AND s.student_id=? AND s.grade IS NOT NULL");
$gradesSummary->execute([$studentId, $studentId]);
$gradeAvg  = $gradesSummary->fetch();
$avgGrade  = round($gradeAvg['avg_grade'] ?? 0, 1);

// Attendance rate
$attRate = 0;
if (!empty($classIds)) {
    $attStmt = $pdo->prepare("SELECT COUNT(*) FROM attendance WHERE student_id=? AND status='present'");
    $attStmt->execute([$studentId]);
    $present = $attStmt->fetchColumn();
    $attTotal = $pdo->prepare("SELECT COUNT(*) FROM attendance WHERE student_id=?");
    $attTotal->execute([$studentId]);
    $total = $attTotal->fetchColumn();
    $attRate = $total > 0 ? round($present / $total * 100, 1) : 100;
}

// Quiz scores
$quizAvg = $pdo->prepare("SELECT AVG(qr.score/qr.total_points*100) as avg FROM quiz_responses qr WHERE qr.student_id=? AND qr.total_points > 0");
$quizAvg->execute([$studentId]);
$quizScore = round($quizAvg->fetchColumn() ?? 0, 1);

// Timeline / Recent activity
$timeline = $pdo->prepare("SELECT 'announcement' as type, a.content as text, a.created_at, c.name as class_name, u.name as author FROM announcements a JOIN classes c ON c.id=a.class_id JOIN users u ON u.id=a.author_id JOIN class_members cm ON cm.class_id=c.id WHERE cm.user_id=? UNION ALL SELECT 'assignment' as type, CONCAT('New assignment: ',a.title) as text, a.created_at, c.name as class_name, 'Teacher' as author FROM assignments a JOIN classes c ON c.id=a.class_id JOIN class_members cm ON cm.class_id=c.id WHERE cm.user_id=? ORDER BY created_at DESC LIMIT 10");
$timeline->execute([$studentId, $studentId]);
$timelineList = $timeline->fetchAll();

// Performance chart data per class
$perf = [];
foreach ($classList as $cls) {
    $s = $pdo->prepare("SELECT AVG(s.grade) as avg FROM submissions s JOIN assignments a ON a.id=s.assignment_id WHERE a.class_id=? AND s.student_id=? AND s.grade IS NOT NULL");
    $s->execute([$cls['id'], $studentId]);
    $perf[$cls['name']] = round($s->fetchColumn() ?? 0, 1);
}
$perfLabels = json_encode(array_keys($perf));
$perfValues = json_encode(array_values($perf));

renderHead('Student Dashboard');
?>
<body>
<div class="app-wrapper">
<?php renderSidebar($user, 'student.php'); ?>
<div class="main-content">
<?php renderTopbar('My Dashboard', $user, [['icon'=>'fa-sign-in-alt','label'=>'Join Class','onclick'=>"openModal('join-class-modal')"]]); ?>

<div class="page-content animate-up">

  <!-- Welcome -->
  <div style="background:linear-gradient(135deg,rgba(16,185,129,0.15),rgba(14,165,233,0.1));border:1px solid rgba(16,185,129,0.2);border-radius:var(--radius-lg);padding:1.5rem 2rem;margin-bottom:1.5rem;display:flex;align-items:center;justify-content:space-between;gap:1rem">
    <div>
      <h2 style="font-size:1.4rem;font-weight:800">Hey, <?= e(explode(' ',$user['name'])[0]) ?>! 🎓</h2>
      <p style="color:var(--text-secondary);margin-top:0.25rem">You have <strong style="color:var(--danger)"><?= count($pendingList) ?></strong> pending assignment<?= count($pendingList) !== 1 ? 's' : '' ?>. Keep it up!</p>
    </div>
    <div style="text-align:right">
      <div style="font-size:2rem;font-weight:900;color:var(--success)"><?= $avgGrade ?: '—' ?>%</div>
      <div style="font-size:0.75rem;color:var(--text-muted)">Overall Average</div>
    </div>
  </div>

  <!-- Stats -->
  <div class="grid grid-4 gap-4 mb-4">
    <div class="stat-card" style="border-left:3px solid var(--primary)">
      <div class="stat-icon" style="background:rgba(99,102,241,0.15);color:var(--primary)"><i class="fas fa-chalkboard"></i></div>
      <div class="stat-info"><div class="stat-value" style="color:var(--primary)"><?= count($classList) ?></div><div class="stat-label">Enrolled Classes</div></div>
    </div>
    <div class="stat-card" style="border-left:3px solid var(--warning)">
      <div class="stat-icon" style="background:rgba(245,158,11,0.15);color:var(--warning)"><i class="fas fa-tasks"></i></div>
      <div class="stat-info"><div class="stat-value" style="color:var(--warning)"><?= count($pendingList) ?></div><div class="stat-label">Pending Tasks</div></div>
    </div>
    <div class="stat-card" style="border-left:3px solid var(--success)">
      <div class="stat-icon" style="background:rgba(16,185,129,0.15);color:var(--success)"><i class="fas fa-calendar-check"></i></div>
      <div class="stat-info"><div class="stat-value" style="color:var(--success)"><?= $attRate ?>%</div><div class="stat-label">Attendance Rate</div></div>
    </div>
    <div class="stat-card" style="border-left:3px solid var(--purple)">
      <div class="stat-icon" style="background:rgba(168,85,247,0.15);color:var(--purple)"><i class="fas fa-question-circle"></i></div>
      <div class="stat-info"><div class="stat-value" style="color:var(--purple)"><?= $quizScore ?>%</div><div class="stat-label">Quiz Average</div></div>
    </div>
  </div>

  <div style="display:grid;grid-template-columns:1fr 340px;gap:1.5rem">

    <!-- Main -->
    <div style="display:flex;flex-direction:column;gap:1.5rem">

      <!-- My Classes -->
      <div class="card">
        <div class="card-header">
          <div class="card-title"><i class="fas fa-book-open" style="color:var(--primary)"></i> My Classes</div>
          <button class="btn btn-success btn-sm" onclick="openModal('join-class-modal')"><i class="fas fa-plus"></i> Join Class</button>
        </div>
        <?php if (empty($classList)): ?>
          <div class="empty-state">
            <div class="empty-icon"><i class="fas fa-door-open"></i></div>
            <div class="empty-title">Not enrolled in any class</div>
            <div class="empty-sub">Ask your teacher for the class code</div>
            <button class="btn btn-success" onclick="openModal('join-class-modal')"><i class="fas fa-sign-in-alt"></i> Join Class</button>
          </div>
        <?php else: ?>
        <div class="grid grid-4 gap-4">
          <?php foreach ($classList as $cls):
            $colors = ['#4f46e5','#0ea5e9','#10b981','#f59e0b','#a855f7','#ef4444'];
            $col = $cls['cover_color'] ?? $colors[$cls['id'] % count($colors)];
          ?>
          <div class="class-card" onclick="window.location='<?= BASE_URL ?>/classroom/index.php?id=<?= $cls['id'] ?>'">
            <div class="class-cover" style="background:<?= $cls['logo'] ? 'url('.BASE_URL.'/uploads/logos/'.$cls['logo'].') center/cover' : 'linear-gradient(135deg,'.$col.','.$col.'99)' ?>">
              <i class="fas fa-graduation-cap class-cover-icon"></i>
              <div class="class-cover-name"><?= e($cls['name']) ?></div>
              <div class="class-cover-section"><?= e($cls['section'] ?? '') ?></div>
            </div>
            <div class="class-body">
              <div class="class-meta"><i class="fas fa-chalkboard-teacher"></i> <?= e($cls['teacher_name']) ?></div>
              <div class="class-meta" style="margin-top:0.25rem"><i class="fas fa-users"></i> <?= $cls['member_count'] ?> classmates</div>
            </div>
            <div class="class-actions">
              <a href="<?= BASE_URL ?>/classroom/index.php?id=<?= $cls['id'] ?>" class="btn btn-primary btn-sm" onclick="event.stopPropagation()">Open</a>
              <a href="<?= BASE_URL ?>/classroom/index.php?id=<?= $cls['id'] ?>&tab=classwork" class="btn btn-secondary btn-sm" onclick="event.stopPropagation()">Work</a>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
      </div>

      <!-- Performance Chart -->
      <?php if (!empty($perf)): ?>
      <div class="card">
        <div class="card-header">
          <div class="card-title"><i class="fas fa-chart-bar" style="color:var(--success)"></i> My Performance by Class</div>
          <a href="<?= BASE_URL ?>/analytics/performance.php" class="btn btn-ghost btn-sm">Full Analytics</a>
        </div>
        <div class="chart-wrapper" style="height:200px">
          <canvas id="grade-chart" data-labels='<?= $perfLabels ?>' data-values='<?= $perfValues ?>'></canvas>
        </div>
      </div>
      <?php endif; ?>

      <!-- Timeline -->
      <div class="card" id="timeline">
        <div class="card-header">
          <div class="card-title"><i class="fas fa-stream" style="color:var(--info)"></i> Activity Feed</div>
        </div>
        <?php if (empty($timelineList)): ?>
          <div class="empty-state"><div class="empty-icon"><i class="fas fa-stream"></i></div><div class="empty-title">No recent activity</div></div>
        <?php else: ?>
        <?php foreach ($timelineList as $item): ?>
        <div class="feed-item">
          <div class="feed-dot" style="background:<?= $item['type']==='announcement' ? 'rgba(99,102,241,0.15)' : 'rgba(245,158,11,0.15)' ?>;color:<?= $item['type']==='announcement' ? 'var(--primary)' : 'var(--warning)' ?>">
            <i class="fas <?= $item['type']==='announcement' ? 'fa-bullhorn' : 'fa-file-alt' ?>"></i>
          </div>
          <div class="feed-content">
            <div class="feed-header">
              <span class="feed-name"><?= e($item['class_name']) ?></span>
              <span class="feed-time"><?= timeAgo($item['created_at']) ?></span>
            </div>
            <div class="feed-text"><?= e(mb_strimwidth($item['text'], 0, 120, '…')) ?></div>
          </div>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div>

    <!-- Right Sidebar -->
    <div style="display:flex;flex-direction:column;gap:1.5rem">

      <!-- Pending Assignments -->
      <div class="card">
        <div class="card-header">
          <div class="card-title"><i class="fas fa-tasks" style="color:var(--warning)"></i> Pending Work</div>
          <span class="badge badge-warning"><?= count($pendingList) ?></span>
        </div>
        <?php if (empty($pendingList)): ?>
          <div class="empty-state"><div class="empty-icon"><i class="fas fa-check-circle"></i></div><div class="empty-title" style="color:var(--success)">All caught up! 🎉</div></div>
        <?php else: ?>
        <div style="display:flex;flex-direction:column;gap:0.625rem">
          <?php foreach ($pendingList as $a): ?>
          <div class="assignment-card" onclick="window.location='<?= BASE_URL ?>/classroom/index.php?id=<?= $a['class_id'] ?>&tab=classwork&item=<?= $a['id'] ?>'">
            <div class="assignment-icon" style="background:rgba(245,158,11,0.15);color:var(--warning)"><i class="fas fa-file-alt"></i></div>
            <div class="assignment-info">
              <div class="assignment-title"><?= e(mb_strimwidth($a['title'],0,26,'…')) ?></div>
              <div class="assignment-meta">
                <span class="text-muted" style="font-size:0.75rem"><?= e($a['class_name']) ?></span>
                <?php if ($a['due_date']): ?>
                <span class="badge badge-<?= strtotime($a['due_date']) - time() < 86400 ? 'danger' : 'warning' ?>" style="font-size:0.65rem">
                  Due <?= date('M d', strtotime($a['due_date'])) ?>
                </span>
                <?php endif; ?>
              </div>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
      </div>

      <!-- Progress Tracker -->
      <div class="card">
        <div class="card-title" style="margin-bottom:1rem"><i class="fas fa-tachometer-alt" style="color:var(--primary)"></i> Personal Tracker</div>
        <div style="display:flex;flex-direction:column;gap:0.875rem">
          <div>
            <div style="display:flex;justify-content:space-between;margin-bottom:0.375rem;font-size:0.8rem">
              <span>Overall Grade</span><span style="font-weight:700;color:var(--success)"><?= $avgGrade ?>%</span>
            </div>
            <div class="progress-bar"><div class="progress-fill success" data-width="<?= $avgGrade ?>" style="width:0"></div></div>
          </div>
          <div>
            <div style="display:flex;justify-content:space-between;margin-bottom:0.375rem;font-size:0.8rem">
              <span>Attendance</span><span style="font-weight:700;color:var(--primary)"><?= $attRate ?>%</span>
            </div>
            <div class="progress-bar"><div class="progress-fill" data-width="<?= $attRate ?>" style="width:0"></div></div>
          </div>
          <div>
            <div style="display:flex;justify-content:space-between;margin-bottom:0.375rem;font-size:0.8rem">
              <span>Quiz Score</span><span style="font-weight:700;color:var(--purple)"><?= $quizScore ?>%</span>
            </div>
            <div class="progress-bar"><div class="progress-fill" data-width="<?= $quizScore ?>" style="background:linear-gradient(90deg,var(--purple),#c084fc)"></div></div>
          </div>
        </div>
      </div>

      <!-- Quick Links -->
      <div class="card">
        <div class="card-title" style="margin-bottom:1rem"><i class="fas fa-link" style="color:var(--info)"></i> Quick Access</div>
        <div style="display:flex;flex-direction:column;gap:0.5rem">
          <a href="<?= BASE_URL ?>/classroom/grades.php" class="btn btn-secondary btn-full"><i class="fas fa-star"></i> My Grades</a>
          <a href="<?= BASE_URL ?>/classroom/attendance.php" class="btn btn-secondary btn-full"><i class="fas fa-calendar-check"></i> Attendance</a>
          <a href="<?= BASE_URL ?>/classroom/quiz.php" class="btn btn-secondary btn-full"><i class="fas fa-question-circle"></i> Quizzes</a>
          <a href="<?= BASE_URL ?>/analytics/performance.php" class="btn btn-secondary btn-full"><i class="fas fa-chart-line"></i> My Progress</a>
          <a href="<?= BASE_URL ?>/offline/submission.php" class="btn btn-secondary btn-full"><i class="fas fa-wifi-slash"></i> Offline Submit</a>
          <a href="<?= BASE_URL ?>/global/calendar.php" class="btn btn-secondary btn-full"><i class="fas fa-calendar"></i> Calendar</a>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Join Class Modal -->
<div class="modal-overlay" id="join-class-modal">
  <div class="modal" style="max-width:420px">
    <div class="modal-header">
      <div class="modal-title"><i class="fas fa-sign-in-alt" style="color:var(--success)"></i> Join a Class</div>
      <button class="modal-close">✕</button>
    </div>
    <div class="modal-body">
      <p style="color:var(--text-secondary);font-size:0.875rem;margin-bottom:1.25rem">Enter the class code provided by your teacher to enroll.</p>
      <form id="join-class-form">
        <div class="form-group">
          <label class="form-label">Class Code</label>
          <input type="text" id="class-code-input" class="form-control" placeholder="e.g., CSE479A" required maxlength="10" style="text-transform:uppercase;font-size:1.2rem;font-weight:700;letter-spacing:0.15em;text-align:center">
        </div>
        <button type="submit" class="btn btn-success btn-full btn-lg" style="margin-top:1rem"><i class="fas fa-sign-in-alt"></i> Join Class</button>
      </form>
    </div>
  </div>
</div>

</div></div>
<?php renderFooter(); ?>
</body></html>
