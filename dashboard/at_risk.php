<?php
// =============================================
// Smart Classroom — At-Risk Student Dashboard
// =============================================
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/layout.php';
requireLogin();
if (userRole() !== 'teacher') redirect(BASE_URL . '/index.php');

$user    = currentUser();
$teacher = $user['id'];

// Filter by class
$filterClass = (int)($_GET['class_id'] ?? 0);

// Load teacher's classes for filter dropdown
$classListStmt = $pdo->prepare("SELECT id, name FROM classes WHERE teacher_id=? AND (status='active' OR status IS NULL) ORDER BY name");
$classListStmt->execute([$teacher]);
$classList = $classListStmt->fetchAll();

// Main risk query
$whereClass = $filterClass ? "AND c.id = {$filterClass}" : '';
$riskStmt = $pdo->prepare("
    SELECT
        u.id   AS student_id,
        u.name AS student_name,
        c.id   AS class_id,
        c.name AS class_name,
        COALESCE((SELECT COUNT(*) FROM attendance a
                  WHERE a.class_id=c.id AND a.student_id=u.id AND a.status='present'), 0) AS present_count,
        COALESCE((SELECT COUNT(*) FROM qr_attendance_sessions qs
                  WHERE qs.class_id=c.id AND (qs.is_active=0 OR qs.expires_at < NOW())), 0) AS total_sessions,
        (SELECT ROUND(AVG(sb.grade),0)
         FROM submissions sb JOIN assignments an ON an.id=sb.assignment_id
         WHERE an.class_id=c.id AND sb.student_id=u.id AND sb.grade IS NOT NULL) AS avg_grade,
        GREATEST(0,
            COALESCE((SELECT COUNT(*) FROM assignments an2
                      WHERE an2.class_id=c.id AND an2.due_date IS NOT NULL AND an2.due_date < NOW()), 0) -
            COALESCE((SELECT COUNT(*) FROM submissions sb2 JOIN assignments an3 ON an3.id=sb2.assignment_id
                      WHERE an3.class_id=c.id AND sb2.student_id=u.id), 0)
        ) AS missing_count
    FROM users u
    JOIN class_members cm ON cm.user_id=u.id
    JOIN classes c ON c.id=cm.class_id
    WHERE c.teacher_id=? AND (c.status='active' OR c.status IS NULL) {$whereClass}
    ORDER BY c.name, u.name
");
$riskStmt->execute([$teacher]);
$rows = $riskStmt->fetchAll();

// Compute risk level per student
$students = [];
$countRed = $countYellow = $countGreen = 0;
foreach ($rows as $row) {
    $attRate  = $row['total_sessions'] > 0 ? round($row['present_count'] / $row['total_sessions'] * 100) : null;
    $avgGrade = $row['avg_grade'] !== null ? (int)$row['avg_grade'] : null;
    $missing  = (int)$row['missing_count'];

    $flags = [];
    if ($attRate !== null && $attRate < 70) $flags[] = "Low attendance ({$attRate}%)";
    if ($avgGrade !== null && $avgGrade < 50) $flags[] = "Avg grade {$avgGrade}%";
    if ($missing >= 3) $flags[] = "{$missing} missing assignments";

    // Determine level
    if (count($flags) >= 2) {
        $risk = 'red';
        $countRed++;
    } else {
        // Check borderline yellow
        $yellow = false;
        if ($attRate !== null && $attRate < 80) { $flags[] = "Attendance borderline ({$attRate}%)"; $yellow = true; }
        elseif ($avgGrade !== null && $avgGrade < 65) { $flags[] = "Grade borderline ({$avgGrade}%)"; $yellow = true; }
        elseif ($missing >= 1) { $flags[] = "{$missing} missing assignment"; $yellow = true; }

        if (!empty($flags)) {
            $risk = count($flags) >= 2 ? 'red' : ($yellow || count($flags) === 1 ? 'yellow' : 'green');
            if ($risk === 'red') $countRed++; else $countYellow++;
        } else {
            $risk = 'green';
            $countGreen++;
        }
    }

    $students[] = $row + [
        'att_rate'  => $attRate,
        'avg_grade' => $avgGrade,
        'missing'   => $missing,
        'risk'      => $risk,
        'flags'     => $flags,
    ];
}

// Sort: red first, then yellow, then green
usort($students, fn($a,$b) => ['red'=>0,'yellow'=>1,'green'=>2][$a['risk']] <=> ['red'=>0,'yellow'=>1,'green'=>2][$b['risk']]);

renderHead('At-Risk Students');
?>
<body>
<div class="app-wrapper">
<?php renderSidebar($user, 'teacher.php'); ?>
<div class="main-content">
<?php renderTopbar('At-Risk Students', $user); ?>

<div class="page-content animate-up">

  <div style="display:flex;align-items:center;gap:0.5rem;margin-bottom:1.25rem;font-size:0.875rem;color:var(--text-muted)">
    <a href="<?= BASE_URL ?>/dashboard/teacher.php">← Teacher Dashboard</a>
  </div>

  <!-- Summary Cards -->
  <div class="grid grid-4 gap-4 mb-4" style="grid-template-columns:repeat(3,1fr) auto">
    <div class="stat-card" style="border-left:3px solid var(--danger)">
      <div class="stat-icon" style="background:rgba(239,68,68,0.15);color:var(--danger)"><i class="fas fa-exclamation-circle"></i></div>
      <div class="stat-info">
        <div class="stat-value" style="color:var(--danger)"><?= $countRed ?></div>
        <div class="stat-label">High Risk</div>
      </div>
    </div>
    <div class="stat-card" style="border-left:3px solid var(--warning)">
      <div class="stat-icon" style="background:rgba(245,158,11,0.15);color:var(--warning)"><i class="fas fa-exclamation-triangle"></i></div>
      <div class="stat-info">
        <div class="stat-value" style="color:var(--warning)"><?= $countYellow ?></div>
        <div class="stat-label">Needs Attention</div>
      </div>
    </div>
    <div class="stat-card" style="border-left:3px solid var(--success)">
      <div class="stat-icon" style="background:rgba(16,185,129,0.15);color:var(--success)"><i class="fas fa-check-circle"></i></div>
      <div class="stat-info">
        <div class="stat-value" style="color:var(--success)"><?= $countGreen ?></div>
        <div class="stat-label">On Track</div>
      </div>
    </div>
  </div>

  <!-- Filters -->
  <div style="display:flex;align-items:center;gap:0.875rem;margin-bottom:1.25rem;flex-wrap:wrap">
    <a href="?" class="btn btn-<?= $filterClass ? 'secondary' : 'primary' ?> btn-sm">All Classes</a>
    <?php foreach ($classList as $cl): ?>
    <a href="?class_id=<?= $cl['id'] ?>" class="btn btn-<?= $filterClass === $cl['id'] ? 'primary' : 'secondary' ?> btn-sm"><?= e($cl['name']) ?></a>
    <?php endforeach; ?>
  </div>

  <!-- Student Table -->
  <div class="card">
    <div class="card-header">
      <div class="card-title"><i class="fas fa-user-shield" style="color:var(--danger)"></i> Student Risk Overview</div>
      <span class="badge badge-info"><?= count($students) ?> students</span>
    </div>

    <?php if (empty($students)): ?>
    <div class="empty-state">
      <div class="empty-icon"><i class="fas fa-users"></i></div>
      <div class="empty-title">No students found</div>
      <div class="empty-sub">Students will appear here once they join your classes</div>
    </div>
    <?php else: ?>
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>Student</th>
            <th>Class</th>
            <th>Attendance</th>
            <th>Avg Grade</th>
            <th>Missing</th>
            <th>Risk Level</th>
            <th>Issues</th>
            <th>Action</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($students as $s):
          $riskColors = ['red' => 'danger', 'yellow' => 'warning', 'green' => 'success'];
          $riskLabels = ['red' => 'High Risk', 'yellow' => 'Attention', 'green' => 'On Track'];
          $riskIcons  = ['red' => 'fa-exclamation-circle', 'yellow' => 'fa-exclamation-triangle', 'green' => 'fa-check-circle'];
          $rowBg = $s['risk'] === 'red' ? 'rgba(239,68,68,0.04)' : ($s['risk'] === 'yellow' ? 'rgba(245,158,11,0.04)' : 'transparent');
        ?>
        <tr style="background:<?= $rowBg ?>;border-left:3px solid var(--<?= $riskColors[$s['risk']] ?>)">
          <td>
            <div style="display:flex;align-items:center;gap:0.5rem">
              <div class="avatar" style="width:32px;height:32px;font-size:0.75rem;background:linear-gradient(135deg,var(--primary),var(--purple))"><?= strtoupper($s['student_name'][0]) ?></div>
              <span style="font-weight:600;font-size:0.875rem"><?= e($s['student_name']) ?></span>
            </div>
          </td>
          <td style="font-size:0.8rem;color:var(--text-muted)"><?= e($s['class_name']) ?></td>
          <td>
            <?php if ($s['att_rate'] !== null): ?>
            <div style="display:flex;align-items:center;gap:0.5rem">
              <div class="progress-bar" style="width:60px;height:6px">
                <div class="progress-fill <?= $s['att_rate'] >= 80 ? 'success' : ($s['att_rate'] >= 70 ? 'warning' : 'danger') ?>" style="width:<?= $s['att_rate'] ?>%"></div>
              </div>
              <span style="font-size:0.8rem;font-weight:600;color:var(--<?= $s['att_rate'] >= 80 ? 'success' : ($s['att_rate'] >= 70 ? 'warning' : 'danger') ?>)"><?= $s['att_rate'] ?>%</span>
            </div>
            <?php else: ?><span style="font-size:0.75rem;color:var(--text-muted)">No sessions</span><?php endif; ?>
          </td>
          <td>
            <?php if ($s['avg_grade'] !== null): ?>
            <span style="font-weight:700;color:var(--<?= $s['avg_grade'] >= 70 ? 'success' : ($s['avg_grade'] >= 50 ? 'warning' : 'danger') ?>)"><?= $s['avg_grade'] ?>%</span>
            <?php else: ?><span style="font-size:0.75rem;color:var(--text-muted)">No grades</span><?php endif; ?>
          </td>
          <td>
            <span style="font-weight:700;color:var(--<?= $s['missing'] >= 3 ? 'danger' : ($s['missing'] >= 1 ? 'warning' : 'success') ?>)"><?= $s['missing'] ?></span>
          </td>
          <td>
            <span class="badge badge-<?= $riskColors[$s['risk']] ?>">
              <i class="fas <?= $riskIcons[$s['risk']] ?>"></i> <?= $riskLabels[$s['risk']] ?>
            </span>
          </td>
          <td style="font-size:0.75rem;color:var(--text-muted);max-width:180px">
            <?= $s['flags'] ? implode(', ', array_map('e', $s['flags'])) : '<span style="color:var(--success)">None</span>' ?>
          </td>
          <td>
            <a href="<?= BASE_URL ?>/global/messages.php?to=<?= $s['student_id'] ?>" class="btn btn-secondary btn-sm"><i class="fas fa-comment"></i> Message</a>
          </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>
  </div>

  <!-- Legend -->
  <div class="card" style="margin-top:1rem;padding:1rem">
    <div style="font-size:0.8rem;font-weight:600;color:var(--text-muted);margin-bottom:0.625rem">Risk Criteria</div>
    <div style="display:flex;gap:2rem;flex-wrap:wrap;font-size:0.8rem;color:var(--text-secondary)">
      <div><span class="badge badge-danger">High Risk</span> 2+ of: attendance &lt;70%, avg grade &lt;50%, 3+ missing</div>
      <div><span class="badge badge-warning">Attention</span> 1 of: attendance &lt;80%, avg grade &lt;65%, 1+ missing</div>
      <div><span class="badge badge-success">On Track</span> All metrics within acceptable range</div>
    </div>
  </div>

</div>
</div></div>
<?php renderFooter(); ?>
</body></html>
