<?php
// =============================================
// Smart Classroom — Guardian Dashboard
// =============================================
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/layout.php';
requireLogin();
if (userRole() !== 'guardian') redirect(BASE_URL . '/index.php');

$user       = currentUser();
$guardianId = $user['id'];

// Linked students
$linked = $pdo->prepare("SELECT u.*, gl.status as link_status, gl.id as link_id FROM users u JOIN guardian_links gl ON gl.student_id=u.id WHERE gl.guardian_id=? AND gl.status='approved'");
$linked->execute([$guardianId]);
$students = $linked->fetchAll();

// Pending links
$pending = $pdo->prepare("SELECT gl.*,u.name,u.email FROM guardian_links gl JOIN users u ON u.id=gl.student_id WHERE gl.guardian_id=? AND gl.status='pending'");
$pending->execute([$guardianId]);
$pendingLinks = $pending->fetchAll();

// Aggregate stats for all linked students
$allStats = [];
foreach ($students as $student) {
    $sid = $student['id'];

    $classes = $pdo->prepare("SELECT c.* FROM classes c JOIN class_members cm ON cm.class_id=c.id WHERE cm.user_id=? AND (c.status='active' OR c.status IS NULL)");
    $classes->execute([$sid]);
    $studentClasses = $classes->fetchAll();

    $gradeStmt = $pdo->prepare("SELECT AVG(s.grade) FROM submissions s JOIN assignments a ON a.id=s.assignment_id JOIN class_members cm ON cm.class_id=a.class_id WHERE cm.user_id=? AND s.student_id=? AND s.grade IS NOT NULL");
    $gradeStmt->execute([$sid, $sid]);
    $avgGrade = round($gradeStmt->fetchColumn() ?? 0, 1);

    $attStmt = $pdo->prepare("SELECT COUNT(*) FROM attendance WHERE student_id=? AND status='present'");
    $attStmt->execute([$sid]);
    $present = $attStmt->fetchColumn();
    $attTotal = $pdo->prepare("SELECT COUNT(*) FROM attendance WHERE student_id=?");
    $attTotal->execute([$sid]);
    $total = $attTotal->fetchColumn();
    $attRate = $total > 0 ? round($present / $total * 100, 1) : 100;

    $allStats[$sid] = [
        'student'    => $student,
        'classes'    => $studentClasses,
        'avg_grade'  => $avgGrade,
        'att_rate'   => $attRate,
        'class_count'=> count($studentClasses),
    ];
}

renderHead('Guardian Dashboard');
?>
<body>
<div class="app-wrapper">
<?php renderSidebar($user, 'guardian.php'); ?>
<div class="main-content">
<?php renderTopbar('Guardian Dashboard', $user, [['icon'=>'fa-link','label'=>'Link Student','onclick'=>"openModal('link-student-modal')"]]); ?>

<div class="page-content animate-up">

  <!-- Header Banner -->
  <div style="background:linear-gradient(135deg,rgba(245,158,11,0.15),rgba(239,68,68,0.1));border:1px solid rgba(245,158,11,0.2);border-radius:var(--radius-lg);padding:1.5rem 2rem;margin-bottom:1.5rem">
    <h2 style="font-size:1.4rem;font-weight:800">Guardian View 👪</h2>
    <p style="color:var(--text-secondary);margin-top:0.25rem">Monitor your student's academic performance in real-time.</p>
    <?php if (!empty($pendingLinks)): ?>
    <div style="margin-top:0.875rem;background:rgba(245,158,11,0.1);border:1px solid rgba(245,158,11,0.3);border-radius:var(--radius-sm);padding:0.5rem 1rem;font-size:0.875rem;color:var(--warning);display:inline-flex;align-items:center;gap:0.5rem">
      <i class="fas fa-clock"></i> <?= count($pendingLinks) ?> pending link request(s) awaiting student approval
    </div>
    <?php endif; ?>
  </div>

  <?php if (empty($students)): ?>
  <!-- No students linked -->
  <div class="card" style="max-width:520px;margin:0 auto">
    <div class="empty-state">
      <div class="empty-icon" style="width:80px;height:80px;font-size:2.5rem"><i class="fas fa-user-graduate"></i></div>
      <div class="empty-title">No Students Linked</div>
      <div class="empty-sub">Link your child's account to start monitoring their academic progress</div>
      <button class="btn btn-warning" onclick="openModal('link-student-modal')"><i class="fas fa-link"></i> Link a Student</button>
    </div>
  </div>
  <?php else: ?>

  <!-- Stats Overview -->
  <div class="grid grid-4 gap-4 mb-4">
    <div class="stat-card" style="border-left:3px solid var(--warning)">
      <div class="stat-icon" style="background:rgba(245,158,11,0.15);color:var(--warning)"><i class="fas fa-user-graduate"></i></div>
      <div class="stat-info"><div class="stat-value" style="color:var(--warning)"><?= count($students) ?></div><div class="stat-label">Linked Students</div></div>
    </div>
    <div class="stat-card" style="border-left:3px solid var(--primary)">
      <div class="stat-icon" style="background:rgba(99,102,241,0.15);color:var(--primary)"><i class="fas fa-chalkboard"></i></div>
      <div class="stat-info"><div class="stat-value" style="color:var(--primary)"><?= array_sum(array_column($allStats,'class_count')) ?></div><div class="stat-label">Total Classes</div></div>
    </div>
    <div class="stat-card" style="border-left:3px solid var(--success)">
      <div class="stat-icon" style="background:rgba(16,185,129,0.15);color:var(--success)"><i class="fas fa-star"></i></div>
      <div class="stat-info"><div class="stat-value" style="color:var(--success)"><?= round(array_sum(array_column($allStats,'avg_grade')) / max(1,count($allStats)),1) ?>%</div><div class="stat-label">Avg Grade</div></div>
    </div>
    <div class="stat-card" style="border-left:3px solid var(--info)">
      <div class="stat-icon" style="background:rgba(6,182,212,0.15);color:var(--info)"><i class="fas fa-calendar-check"></i></div>
      <div class="stat-info"><div class="stat-value" style="color:var(--info)"><?= round(array_sum(array_column($allStats,'att_rate')) / max(1,count($allStats)),1) ?>%</div><div class="stat-label">Avg Attendance</div></div>
    </div>
  </div>

  <!-- Per Student Cards -->
  <?php foreach ($allStats as $sid => $data): ?>
  <?php $s = $data['student']; ?>
  <div class="card" style="margin-bottom:1.5rem">
    <!-- Student Header -->
    <div style="display:flex;align-items:center;gap:1rem;padding-bottom:1.25rem;border-bottom:1px solid var(--border);margin-bottom:1.25rem">
      <div class="avatar avatar-lg" style="background:linear-gradient(135deg,var(--warning),var(--primary))">
        <?= strtoupper($s['name'][0]) ?>
      </div>
      <div style="flex:1">
        <div style="font-size:1.1rem;font-weight:800"><?= e($s['name']) ?></div>
        <div style="font-size:0.8rem;color:var(--text-muted)"><?= e($s['email']) ?></div>
        <div style="margin-top:0.25rem;display:flex;gap:0.5rem">
          <span class="badge badge-success"><?= $data['class_count'] ?> classes</span>
          <span class="badge badge-<?= $data['avg_grade'] >= 70 ? 'success' : ($data['avg_grade'] >= 50 ? 'warning' : 'danger') ?>"><?= $data['avg_grade'] ?>% avg</span>
          <span class="badge badge-<?= $data['att_rate'] >= 80 ? 'success' : 'warning' ?>"><?= $data['att_rate'] ?>% attendance</span>
        </div>
      </div>
      <div style="display:flex;gap:0.5rem">
        <a href="<?= BASE_URL ?>/analytics/performance.php?student_id=<?= $sid ?>" class="btn btn-primary btn-sm"><i class="fas fa-chart-line"></i> Full Analytics</a>
        <a href="<?= BASE_URL ?>/guardian/download.php?student_id=<?= $sid ?>" class="btn btn-secondary btn-sm"><i class="fas fa-download"></i> Report</a>
      </div>
    </div>

    <!-- Performance Bars -->
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:1.5rem;margin-bottom:1.25rem">
      <div>
        <div style="display:flex;justify-content:space-between;margin-bottom:0.375rem;font-size:0.8rem"><span>Grade Average</span><strong style="color:var(--success)"><?= $data['avg_grade'] ?>%</strong></div>
        <div class="progress-bar"><div class="progress-fill success" data-width="<?= $data['avg_grade'] ?>" style="width:0"></div></div>
      </div>
      <div>
        <div style="display:flex;justify-content:space-between;margin-bottom:0.375rem;font-size:0.8rem"><span>Attendance Rate</span><strong style="color:var(--primary)"><?= $data['att_rate'] ?>%</strong></div>
        <div class="progress-bar"><div class="progress-fill" data-width="<?= $data['att_rate'] ?>" style="width:0"></div></div>
      </div>
    </div>

    <!-- Classes Table (read-only) -->
    <?php if (!empty($data['classes'])): ?>
    <div class="table-wrap">
      <table>
        <thead><tr><th>Class</th><th>Subject</th><th>Section</th><th>Grade</th><th>Actions</th></tr></thead>
        <tbody>
        <?php foreach ($data['classes'] as $cls): ?>
        <?php
          $cg = $pdo->prepare("SELECT AVG(s.grade) FROM submissions s JOIN assignments a ON a.id=s.assignment_id WHERE a.class_id=? AND s.student_id=? AND s.grade IS NOT NULL");
          $cg->execute([$cls['id'], $sid]);
          $cGrade = round($cg->fetchColumn() ?? 0, 1);
        ?>
        <tr>
          <td style="font-weight:600"><?= e($cls['name']) ?></td>
          <td style="color:var(--text-muted)"><?= e($cls['subject'] ?? '—') ?></td>
          <td><?= e($cls['section'] ?? '—') ?></td>
          <td>
            <span style="font-weight:700;color:var(--<?= $cGrade >= 70 ? 'success' : ($cGrade >= 50 ? 'warning' : 'danger') ?>)"><?= $cGrade ?>%</span>
          </td>
          <td>
            <a href="<?= BASE_URL ?>/guardian/records.php?class_id=<?= $cls['id'] ?>&student_id=<?= $sid ?>" class="btn btn-ghost btn-sm"><i class="fas fa-eye"></i> View</a>
          </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>
  </div>
  <?php endforeach; ?>

  <?php endif; ?>

  <!-- Quick Links -->
  <div class="card">
    <div class="card-title" style="margin-bottom:1rem"><i class="fas fa-bolt" style="color:var(--warning)"></i> Guardian Tools</div>
    <div style="display:flex;gap:0.75rem;flex-wrap:wrap">
      <button class="btn btn-warning" onclick="openModal('link-student-modal')"><i class="fas fa-link"></i> Link Student</button>
      <a href="<?= BASE_URL ?>/guardian/records.php" class="btn btn-secondary"><i class="fas fa-folder-open"></i> Class Records</a>
      <a href="<?= BASE_URL ?>/analytics/performance.php" class="btn btn-secondary"><i class="fas fa-chart-line"></i> Performance</a>
      <a href="<?= BASE_URL ?>/guardian/download.php" class="btn btn-secondary"><i class="fas fa-download"></i> Download Report</a>
      <a href="<?= BASE_URL ?>/global/notifications.php" class="btn btn-secondary"><i class="fas fa-bell"></i> Notifications</a>
    </div>
  </div>
</div>

<!-- Link Student Modal -->
<div class="modal-overlay" id="link-student-modal">
  <div class="modal" style="max-width:420px">
    <div class="modal-header">
      <div class="modal-title"><i class="fas fa-link" style="color:var(--warning)"></i> Link to Student</div>
      <button class="modal-close">✕</button>
    </div>
    <div class="modal-body">
      <p style="color:var(--text-secondary);font-size:0.875rem;margin-bottom:1rem">Enter your student's registered email address. They will receive an approval request.</p>
      <form method="POST" action="<?= BASE_URL ?>/guardian/link.php">
        <input type="hidden" name="action" value="link">
        <div class="form-group">
          <label class="form-label">Student Email</label>
          <div class="input-group">
            <i class="fas fa-envelope input-icon"></i>
            <input type="email" name="student_email" class="form-control" placeholder="student@example.com" required>
          </div>
        </div>
        <button type="submit" class="btn btn-warning btn-full btn-lg" style="margin-top:1rem"><i class="fas fa-paper-plane"></i> Send Link Request</button>
      </form>
    </div>
  </div>
</div>

</div></div>
<?php renderFooter(); ?>
</body></html>
