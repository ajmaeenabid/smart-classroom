<?php
// =============================================
// Smart Classroom — Performance Analytics Dashboard
// =============================================
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/layout.php';
requireLogin();

$user      = currentUser();
$role      = $user['role'];
$studentId = (int)($_GET['student_id'] ?? ($role === 'student' ? $user['id'] : 0));
$classId   = (int)($_GET['class_id'] ?? 0);

// Determine which student to show
$targetStudent = null;
if ($studentId) {
    $s = $pdo->prepare("SELECT * FROM users WHERE id=? AND role='student'");
    $s->execute([$studentId]);
    $targetStudent = $s->fetch();
}

// Guardian validation
if ($role === 'guardian' && $targetStudent) {
    $check = $pdo->prepare("SELECT id FROM guardian_links WHERE guardian_id=? AND student_id=? AND status='approved'");
    $check->execute([$user['id'], $studentId]);
    if (!$check->fetch()) redirect(BASE_URL . '/dashboard/guardian.php');
}

if (!$targetStudent && $role === 'student') {
    $targetStudent = $user;
    $studentId     = $user['id'];
}

// Enrolled classes
$enrolledClasses = [];
if ($targetStudent) {
    $ec = $pdo->prepare("SELECT c.*,u.name as teacher_name FROM classes c JOIN class_members cm ON cm.class_id=c.id JOIN users u ON u.id=c.teacher_id WHERE cm.user_id=? " . ($classId ? "AND c.id={$classId}" : ""));
    $ec->execute([$studentId]);
    $enrolledClasses = $ec->fetchAll();
}

// Per-class analytics
$classAnalytics = [];
foreach ($enrolledClasses as $cls) {
    $cid = $cls['id'];

    // Grades per assignment
    $grades = $pdo->prepare("SELECT a.title,a.points,s.grade,s.status,a.created_at FROM assignments a LEFT JOIN submissions s ON s.assignment_id=a.id AND s.student_id=? WHERE a.class_id=? ORDER BY a.created_at");
    $grades->execute([$studentId, $cid]);
    $gradeRows = $grades->fetchAll();

    // Attendance
    $att = $pdo->prepare("SELECT status, COUNT(*) c FROM attendance WHERE class_id=? AND student_id=? GROUP BY status");
    $att->execute([$cid, $studentId]);
    $attData = ['present'=>0,'absent'=>0,'late'=>0,'excused'=>0];
    foreach ($att->fetchAll() as $r) $attData[$r['status']] = $r['c'];
    $attTotal = array_sum($attData);
    $attRate  = $attTotal > 0 ? round($attData['present'] / $attTotal * 100, 1) : 100;

    // Quiz
    $quizAvg = $pdo->prepare("SELECT AVG(qr.score/qr.total_points*100) as avg FROM quiz_responses qr JOIN quizzes q ON q.id=qr.quiz_id WHERE q.class_id=? AND qr.student_id=? AND qr.total_points > 0");
    $quizAvg->execute([$cid, $studentId]);
    $quizScore = round($quizAvg->fetchColumn() ?? 0, 1);

    $submittedGrades = array_filter($gradeRows, fn($r) => $r['grade'] !== null);
    $avgGrade = count($submittedGrades) > 0 ? round(array_sum(array_column($submittedGrades, 'grade')) / count($submittedGrades), 1) : 0;

    $classAnalytics[] = [
        'class'      => $cls,
        'grades'     => $gradeRows,
        'avg_grade'  => $avgGrade,
        'att_rate'   => $attRate,
        'att_data'   => $attData,
        'quiz_score' => $quizScore,
    ];
}

// Overall stats
$overallGrade = count($classAnalytics) > 0 ? round(array_sum(array_column($classAnalytics, 'avg_grade')) / count($classAnalytics), 1) : 0;
$overallAtt   = count($classAnalytics) > 0 ? round(array_sum(array_column($classAnalytics, 'att_rate'))  / count($classAnalytics), 1) : 100;
$overallQuiz  = count($classAnalytics) > 0 ? round(array_sum(array_column($classAnalytics, 'quiz_score'))/ count($classAnalytics), 1) : 0;

// Chart data
$classNames    = json_encode(array_column($enrolledClasses, 'name'));
$classGrades   = json_encode(array_column($classAnalytics, 'avg_grade'));
$classAttRates = json_encode(array_column($classAnalytics, 'att_rate'));

// Time series data (last 6 submissions)
$timelineData = $pdo->prepare("SELECT a.title, s.grade, s.submitted_at FROM submissions s JOIN assignments a ON a.id=s.assignment_id JOIN class_members cm ON cm.class_id=a.class_id WHERE cm.user_id=? AND s.student_id=? AND s.grade IS NOT NULL ORDER BY s.submitted_at DESC LIMIT 8");
$timelineData->execute([$studentId, $studentId]);
$tlRows = array_reverse($timelineData->fetchAll());
$tlLabels = json_encode(array_map(fn($r) => mb_strimwidth($r['title'],0,15,'…'), $tlRows));
$tlValues  = json_encode(array_column($tlRows, 'grade'));

renderHead('Performance Analytics');
?>
<body>
<div class="app-wrapper">
<?php renderSidebar($user, 'performance.php'); ?>
<div class="main-content">
<?php renderTopbar('Performance Analytics', $user); ?>

<div class="page-content animate-up">

  <!-- Student header (if viewing other student) -->
  <?php if ($targetStudent && $targetStudent['id'] !== $user['id']): ?>
  <div style="background:var(--bg-card);border:1px solid var(--border);border-radius:var(--radius-lg);padding:1.25rem 1.5rem;margin-bottom:1.5rem;display:flex;align-items:center;gap:1rem">
    <div class="avatar avatar-lg" style="background:linear-gradient(135deg,var(--primary),var(--purple))"><?= strtoupper($targetStudent['name'][0]) ?></div>
    <div>
      <div style="font-size:1.1rem;font-weight:800"><?= e($targetStudent['name']) ?></div>
      <div style="font-size:0.8rem;color:var(--text-muted)"><?= e($targetStudent['email']) ?></div>
    </div>
    <?php if ($role === 'guardian'): ?>
    <div style="margin-left:auto"><a href="<?= BASE_URL ?>/guardian/download.php?student_id=<?= $studentId ?>" class="btn btn-primary btn-sm"><i class="fas fa-download"></i> Download Report</a></div>
    <?php endif; ?>
  </div>
  <?php endif; ?>

  <!-- Overall Stats -->
  <div class="grid grid-4 gap-4 mb-4">
    <div class="stat-card" style="border-left:3px solid var(--primary)">
      <div class="stat-icon" style="background:rgba(99,102,241,0.15);color:var(--primary)"><i class="fas fa-chalkboard"></i></div>
      <div class="stat-info"><div class="stat-value" style="color:var(--primary)"><?= count($enrolledClasses) ?></div><div class="stat-label">Classes</div></div>
    </div>
    <div class="stat-card" style="border-left:3px solid var(--success)">
      <div class="stat-icon" style="background:rgba(16,185,129,0.15);color:var(--success)"><i class="fas fa-star"></i></div>
      <div class="stat-info">
        <div class="stat-value" style="color:var(--success)"><?= $overallGrade ?>%</div>
        <div class="stat-label">Overall Grade</div>
        <div class="stat-change" style="color:<?= $overallGrade >= 70 ? 'var(--success)' : 'var(--danger)' ?>">
          <?= $overallGrade >= 90 ? '🌟 Excellent' : ($overallGrade >= 70 ? '✅ Good' : ($overallGrade >= 50 ? '⚠️ Average' : '❌ Needs Work')) ?>
        </div>
      </div>
    </div>
    <div class="stat-card" style="border-left:3px solid var(--warning)">
      <div class="stat-icon" style="background:rgba(245,158,11,0.15);color:var(--warning)"><i class="fas fa-calendar-check"></i></div>
      <div class="stat-info"><div class="stat-value" style="color:var(--warning)"><?= $overallAtt ?>%</div><div class="stat-label">Attendance Rate</div></div>
    </div>
    <div class="stat-card" style="border-left:3px solid var(--purple)">
      <div class="stat-icon" style="background:rgba(168,85,247,0.15);color:var(--purple)"><i class="fas fa-question-circle"></i></div>
      <div class="stat-info"><div class="stat-value" style="color:var(--purple)"><?= $overallQuiz ?>%</div><div class="stat-label">Quiz Average</div></div>
    </div>
  </div>

  <!-- Charts Row -->
  <div style="display:grid;grid-template-columns:1fr 1fr;gap:1.5rem;margin-bottom:1.5rem">
    <!-- Grade by class bar chart -->
    <div class="card">
      <div class="card-header">
        <div class="card-title"><i class="fas fa-chart-bar" style="color:var(--primary)"></i> Grade by Class</div>
      </div>
      <div class="chart-wrapper" style="height:220px">
        <canvas id="grade-chart" data-labels='<?= $classNames ?>' data-values='<?= $classGrades ?>'></canvas>
      </div>
    </div>

    <!-- Attendance Doughnut -->
    <div class="card">
      <div class="card-header">
        <div class="card-title"><i class="fas fa-chart-pie" style="color:var(--success)"></i> Attendance Breakdown</div>
      </div>
      <div class="chart-wrapper" style="height:220px">
        <?php
          $totalAtt = array_column($classAnalytics, 'att_data');
          $merged   = ['present'=>0,'absent'=>0,'late'=>0,'excused'=>0];
          foreach ($totalAtt as $a) foreach ($a as $k=>$v) $merged[$k] += $v;
        ?>
        <canvas id="att-doughnut"
          class="doughnut-chart"
          data-labels='<?= json_encode(array_keys($merged)) ?>'
          data-values='<?= json_encode(array_values($merged)) ?>'></canvas>
      </div>
    </div>
  </div>

  <!-- Timeline Chart -->
  <?php if (!empty($tlRows)): ?>
  <div class="card" style="margin-bottom:1.5rem">
    <div class="card-header">
      <div class="card-title"><i class="fas fa-chart-line" style="color:var(--warning)"></i> Grade Progress Over Time</div>
      <span class="badge badge-<?= end($tlRows)['grade'] >= $tlRows[0]['grade'] ? 'success' : 'danger' ?>">
        <i class="fas fa-<?= end($tlRows)['grade'] >= $tlRows[0]['grade'] ? 'arrow-up' : 'arrow-down' ?>"></i>
        <?= end($tlRows)['grade'] >= $tlRows[0]['grade'] ? 'Improving' : 'Declining' ?>
      </span>
    </div>
    <div class="chart-wrapper" style="height:200px">
      <canvas id="timeline-chart" data-labels='<?= $tlLabels ?>' data-values='<?= $tlValues ?>'></canvas>
    </div>
  </div>
  <?php endif; ?>

  <!-- Per-Class Breakdown -->
  <?php foreach ($classAnalytics as $ca): ?>
  <div class="card" style="margin-bottom:1.5rem">
    <div class="card-header">
      <div class="card-title" style="font-size:1rem">
        <i class="fas fa-chalkboard" style="color:var(--primary)"></i> <?= e($ca['class']['name']) ?>
        <span style="font-size:0.75rem;color:var(--text-muted);font-weight:400">· <?= e($ca['class']['teacher_name']) ?></span>
      </div>
      <div style="display:flex;gap:0.5rem">
        <span class="badge badge-<?= $ca['avg_grade'] >= 70 ? 'success' : ($ca['avg_grade'] >= 50 ? 'warning' : 'danger') ?>"><?= $ca['avg_grade'] ?>% avg</span>
        <span class="badge badge-<?= $ca['att_rate'] >= 80 ? 'success' : 'warning' ?>"><?= $ca['att_rate'] ?>% att.</span>
      </div>
    </div>

    <!-- Progress bars -->
    <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:1rem;margin-bottom:1rem">
      <div>
        <div style="display:flex;justify-content:space-between;font-size:0.8rem;margin-bottom:0.3rem"><span>Grade</span><strong style="color:var(--success)"><?= $ca['avg_grade'] ?>%</strong></div>
        <div class="progress-bar"><div class="progress-fill success" data-width="<?= $ca['avg_grade'] ?>" style="width:0"></div></div>
      </div>
      <div>
        <div style="display:flex;justify-content:space-between;font-size:0.8rem;margin-bottom:0.3rem"><span>Attendance</span><strong style="color:var(--primary)"><?= $ca['att_rate'] ?>%</strong></div>
        <div class="progress-bar"><div class="progress-fill" data-width="<?= $ca['att_rate'] ?>" style="width:0"></div></div>
      </div>
      <div>
        <div style="display:flex;justify-content:space-between;font-size:0.8rem;margin-bottom:0.3rem"><span>Quiz</span><strong style="color:var(--purple)"><?= $ca['quiz_score'] ?>%</strong></div>
        <div class="progress-bar"><div class="progress-fill" data-width="<?= $ca['quiz_score'] ?>" style="background:linear-gradient(90deg,var(--purple),#c084fc);width:0"></div></div>
      </div>
    </div>

    <!-- Assignment table -->
    <?php if (!empty($ca['grades'])): ?>
    <div class="table-wrap">
      <table>
        <thead><tr><th>Assignment</th><th>Max Pts</th><th>Grade</th><th>Status</th></tr></thead>
        <tbody>
        <?php foreach ($ca['grades'] as $g): ?>
        <tr>
          <td style="font-weight:500;font-size:0.875rem"><?= e($g['title']) ?></td>
          <td><?= $g['points'] ?></td>
          <td style="font-weight:700;color:var(--<?= ($g['grade'] ?? 0) >= 70 ? 'success' : (($g['grade'] ?? 0) >= 50 ? 'warning' : 'danger') ?>)"><?= $g['grade'] !== null ? $g['grade'].'%' : '—' ?></td>
          <td><span class="badge badge-<?= ['submitted'=>'info','graded'=>'success','late'=>'warning','missing'=>'danger'][$g['status'] ?? 'missing'] ?? 'warning' ?>"><?= $g['status'] ?? 'not submitted' ?></span></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>
  </div>
  <?php endforeach; ?>

  <!-- No data state -->
  <?php if (empty($classAnalytics)): ?>
  <div class="empty-state">
    <div class="empty-icon"><i class="fas fa-chart-line"></i></div>
    <div class="empty-title">No analytics data yet</div>
    <div class="empty-sub">Enroll in classes and complete assignments to see your performance</div>
  </div>
  <?php endif; ?>

</div>
</div></div>
<?php renderFooter(); ?>
</body></html>
