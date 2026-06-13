<?php
// =============================================
// Smart Classroom — Guardian: Download Report
// =============================================
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/layout.php';
requireLogin();

$user       = currentUser();
$guardianId = $user['id'];
$role       = $user['role'];
$studentId  = (int)($_GET['student_id'] ?? ($role === 'student' ? $user['id'] : 0));

// Validate access
$targetStudent = null;
if ($studentId) {
    if ($role === 'guardian') {
        $check = $pdo->prepare("SELECT gl.*, u.* FROM guardian_links gl JOIN users u ON u.id=gl.student_id WHERE gl.guardian_id=? AND gl.student_id=? AND gl.status='approved'");
        $check->execute([$guardianId, $studentId]);
        $targetStudent = $check->fetch();
    } elseif ($role === 'student' && $studentId === $user['id']) {
        $targetStudent = $user;
    } elseif ($role === 'teacher') {
        $s = $pdo->prepare("SELECT * FROM users WHERE id=?");
        $s->execute([$studentId]);
        $targetStudent = $s->fetch();
    }
}

if (!$targetStudent) redirect(BASE_URL . '/index.php');

// Gather data
$classes = $pdo->prepare("SELECT c.*,u.name as teacher_name FROM classes c JOIN class_members cm ON cm.class_id=c.id JOIN users u ON u.id=c.teacher_id WHERE cm.user_id=? AND (c.status='active' OR c.status IS NULL)");
$classes->execute([$studentId]);
$classList = $classes->fetchAll();

$classStats = [];
foreach ($classList as $cls) {
    $g = $pdo->prepare("SELECT AVG(s.grade) avg,COUNT(s.id) graded FROM submissions s JOIN assignments a ON a.id=s.assignment_id WHERE a.class_id=? AND s.student_id=? AND s.grade IS NOT NULL");
    $g->execute([$cls['id'], $studentId]);
    $gr = $g->fetch();

    $a = $pdo->prepare("SELECT COUNT(*) total, SUM(status='present') present FROM attendance WHERE class_id=? AND student_id=?");
    $a->execute([$cls['id'], $studentId]);
    $at = $a->fetch();

    $attRate = $at['total'] > 0 ? round($at['present'] / $at['total'] * 100, 1) : 100;
    $classStats[] = ['class' => $cls, 'avg' => round($gr['avg'] ?? 0, 1), 'graded' => $gr['graded'] ?? 0, 'att_rate' => $attRate, 'att_total' => $at['total'] ?? 0];
}

$overallGrade = count($classStats) ? round(array_sum(array_column($classStats,'avg')) / count($classStats), 1) : 0;
$overallAtt   = count($classStats) ? round(array_sum(array_column($classStats,'att_rate')) / count($classStats), 1) : 100;

// PDF/Print Report
if (isset($_GET['print'])):
?>
<!DOCTYPE html>
<html>
<head>
  <meta charset="UTF-8">
  <title>Progress Report — <?= e($targetStudent['name']) ?></title>
  <style>
    * { box-sizing:border-box; margin:0; padding:0; }
    body { font-family: 'Segoe UI', Arial, sans-serif; padding:2cm; color:#1a1a2e; background:white; }
    .header { text-align:center; border-bottom:3px solid #6366f1; padding-bottom:1.25rem; margin-bottom:1.5rem; }
    .header h1 { font-size:1.6rem; color:#6366f1; }
    .header h2 { font-size:1.1rem; color:#333; margin-top:0.25rem; }
    .meta { display:flex; gap:2rem; margin-bottom:1.5rem; background:#f5f5f5; padding:1rem 1.25rem; border-radius:8px; }
    .meta-item label { font-size:0.7rem; font-weight:700; text-transform:uppercase; color:#888; }
    .meta-item div { font-size:1rem; font-weight:700; }
    table { width:100%; border-collapse:collapse; margin-bottom:1.5rem; }
    th { background:#6366f1; color:white; padding:0.625rem 1rem; text-align:left; font-size:0.8rem; }
    td { padding:0.625rem 1rem; border-bottom:1px solid #eee; font-size:0.875rem; }
    tr:nth-child(even) td { background:#fafafa; }
    .grade-cell { font-weight:800; }
    .good { color:#10b981; }
    .avg  { color:#f59e0b; }
    .low  { color:#ef4444; }
    .footer { text-align:center; font-size:0.75rem; color:#aaa; margin-top:2rem; border-top:1px solid #eee; padding-top:1rem; }
    @media print { body { padding:1cm; } }
  </style>
</head>
<body onload="window.print()">
  <div class="header">
    <h1>🎓 Smart Classroom System</h1>
    <h2>Student Progress Report</h2>
  </div>
  <div class="meta">
    <div class="meta-item"><label>Student Name</label><div><?= e($targetStudent['name']) ?></div></div>
    <div class="meta-item"><label>Email</label><div><?= e($targetStudent['email']) ?></div></div>
    <div class="meta-item"><label>Report Date</label><div><?= date('F d, Y') ?></div></div>
    <div class="meta-item"><label>Overall Grade</label><div class="grade-cell <?= $overallGrade>=70?'good':($overallGrade>=50?'avg':'low') ?>"><?= $overallGrade ?>%</div></div>
    <div class="meta-item"><label>Attendance</label><div class="grade-cell <?= $overallAtt>=80?'good':($overallAtt>=60?'avg':'low') ?>"><?= $overallAtt ?>%</div></div>
  </div>
  <table>
    <thead><tr><th>Class</th><th>Teacher</th><th>Avg Grade</th><th>Attendance</th><th>Assessment</th></tr></thead>
    <tbody>
    <?php foreach ($classStats as $cs): ?>
    <tr>
      <td><?= e($cs['class']['name']) ?></td>
      <td><?= e($cs['class']['teacher_name']) ?></td>
      <td class="grade-cell <?= $cs['avg']>=70?'good':($cs['avg']>=50?'avg':'low') ?>"><?= $cs['avg'] ?>%</td>
      <td class="grade-cell <?= $cs['att_rate']>=80?'good':($cs['att_rate']>=60?'avg':'low') ?>"><?= $cs['att_rate'] ?>% (<?= $cs['att_total'] ?> days)</td>
      <td><?= $cs['avg']>=90?'🌟 Excellent':($cs['avg']>=70?'✅ Good':($cs['avg']>=50?'⚠️ Average':'❌ Needs Improvement')) ?></td>
    </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <div class="footer">Generated by Smart Classroom System v3.8 · <?= date('d M Y H:i') ?> · For official records only</div>
</body>
</html>
<?php exit; endif; ?>

<?php renderHead('Download Report'); ?>
<body>
<div class="app-wrapper">
<?php renderSidebar($user); ?>
<div class="main-content">
<?php renderTopbar('Download Progress Report', $user); ?>

<div class="page-content animate-up">
  <div style="max-width:700px;margin:0 auto">

    <!-- Student Card -->
    <div class="card" style="margin-bottom:1.5rem;background:linear-gradient(135deg,rgba(245,158,11,0.1),rgba(99,102,241,0.08))">
      <div style="display:flex;align-items:center;gap:1.25rem">
        <div class="avatar avatar-xl" style="background:linear-gradient(135deg,var(--warning),var(--primary))"><?= strtoupper($targetStudent['name'][0]) ?></div>
        <div>
          <div style="font-size:1.3rem;font-weight:900"><?= e($targetStudent['name']) ?></div>
          <div style="font-size:0.8rem;color:var(--text-muted)"><?= e($targetStudent['email']) ?></div>
          <div style="margin-top:0.5rem;display:flex;gap:0.75rem;flex-wrap:wrap">
            <span class="badge badge-<?= $overallGrade >= 70 ? 'success' : ($overallGrade >= 50 ? 'warning' : 'danger') ?>" style="font-size:0.8rem">Overall: <?= $overallGrade ?>%</span>
            <span class="badge badge-<?= $overallAtt >= 80 ? 'success' : 'warning' ?>" style="font-size:0.8rem">Attendance: <?= $overallAtt ?>%</span>
            <span class="badge badge-primary" style="font-size:0.8rem"><?= count($classList) ?> Classes</span>
          </div>
        </div>
      </div>
    </div>

    <!-- Summary Table -->
    <div class="card" style="margin-bottom:1.5rem">
      <div class="card-header"><div class="card-title"><i class="fas fa-list" style="color:var(--primary)"></i> Performance Summary</div></div>
      <div class="table-wrap">
        <table>
          <thead><tr><th>Class</th><th>Teacher</th><th>Avg Grade</th><th>Attendance</th><th>Status</th></tr></thead>
          <tbody>
          <?php foreach ($classStats as $cs): ?>
          <tr>
            <td style="font-weight:600"><?= e($cs['class']['name']) ?></td>
            <td style="color:var(--text-muted)"><?= e($cs['class']['teacher_name']) ?></td>
            <td style="font-weight:800;color:var(--<?= $cs['avg']>=70?'success':($cs['avg']>=50?'warning':'danger') ?>)"><?= $cs['avg'] ?>%</td>
            <td><span class="badge badge-<?= $cs['att_rate']>=80?'success':'warning' ?>"><?= $cs['att_rate'] ?>%</span></td>
            <td><?= $cs['avg']>=90?'🌟 Excellent':($cs['avg']>=70?'✅ Good':($cs['avg']>=50?'⚠️ Average':'❌ Needs Work')) ?></td>
          </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Download Buttons -->
    <div class="card">
      <div class="card-header"><div class="card-title"><i class="fas fa-download" style="color:var(--success)"></i> Download Options</div></div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem">
        <div class="card" style="padding:1.25rem;text-align:center">
          <div style="font-size:2.5rem;margin-bottom:0.75rem">📄</div>
          <div style="font-weight:700;margin-bottom:0.375rem">Print / PDF</div>
          <div style="font-size:0.8rem;color:var(--text-muted);margin-bottom:1rem">Opens print dialog for PDF save</div>
          <a href="?student_id=<?= $studentId ?>&print=1" target="_blank" class="btn btn-primary btn-full"><i class="fas fa-print"></i> Generate PDF</a>
        </div>
        <div class="card" style="padding:1.25rem;text-align:center">
          <div style="font-size:2.5rem;margin-bottom:0.75rem">📊</div>
          <div style="font-weight:700;margin-bottom:0.375rem">View Analytics</div>
          <div style="font-size:0.8rem;color:var(--text-muted);margin-bottom:1rem">Full interactive charts</div>
          <a href="<?= BASE_URL ?>/analytics/performance.php?student_id=<?= $studentId ?>" class="btn btn-secondary btn-full"><i class="fas fa-chart-line"></i> Open Analytics</a>
        </div>
      </div>
    </div>
  </div>
</div>
</div></div>
<?php renderFooter(); ?>
</body></html>
