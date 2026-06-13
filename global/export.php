<?php
// =============================================
// Smart Classroom — Export & Reports
// =============================================
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/layout.php';
requireLogin();

$user    = currentUser();
$uid     = $user['id'];
$classId = (int)($_GET['class_id'] ?? 0);

// Get teacher's classes
$myClasses = [];
if ($user['role'] === 'teacher') {
    $mc = $pdo->prepare("SELECT * FROM classes WHERE teacher_id=? AND (status='active' OR status IS NULL) ORDER BY name");
    $mc->execute([$uid]);
    $myClasses = $mc->fetchAll();
}

// Handle CSV export
if (isset($_GET['export']) && $classId) {
    $type = $_GET['export'];
    header('Content-Type: text/csv');
    $dt = date('Y-m-d');

    if ($type === 'grades') {
        header("Content-Disposition: attachment; filename=grades_{$dt}.csv");
        echo "Student,Assignment,Grade,Status,Submitted\n";
        $rows = $pdo->prepare("SELECT u.name,a.title,s.grade,s.status,s.submitted_at FROM submissions s JOIN users u ON u.id=s.student_id JOIN assignments a ON a.id=s.assignment_id WHERE a.class_id=?");
        $rows->execute([$classId]);
        foreach ($rows->fetchAll() as $r) echo '"'.implode('","', [$r['name'],$r['title'],$r['grade'],$r['status'],$r['submitted_at']])."\"\n";
        exit;
    }

    if ($type === 'attendance') {
        header("Content-Disposition: attachment; filename=attendance_{$dt}.csv");
        echo "Student,Date,Status\n";
        $rows = $pdo->prepare("SELECT u.name,a.date,a.status FROM attendance a JOIN users u ON u.id=a.student_id WHERE a.class_id=?");
        $rows->execute([$classId]);
        foreach ($rows->fetchAll() as $r) echo '"'.implode('","', array_values($r))."\"\n";
        exit;
    }

    if ($type === 'students') {
        header("Content-Disposition: attachment; filename=students_{$dt}.csv");
        echo "Name,Email,Joined\n";
        $rows = $pdo->prepare("SELECT u.name,u.email,cm.joined_at FROM class_members cm JOIN users u ON u.id=cm.user_id WHERE cm.class_id=?");
        $rows->execute([$classId]);
        foreach ($rows->fetchAll() as $r) echo '"'.implode('","', array_values($r))."\"\n";
        exit;
    }
}

renderHead('Export & Reports');
?>
<body>
<div class="app-wrapper">
<?php renderSidebar($user, 'export.php'); ?>
<div class="main-content">
<?php renderTopbar('Export & Reports', $user); ?>

<div class="page-content animate-up">
  <div class="page-header">
    <div><div class="page-title">📤 Export & Reports</div><div class="page-subtitle">Download data in CSV format or generate PDF reports</div></div>
  </div>

  <div style="display:grid;grid-template-columns:1fr 300px;gap:1.5rem">
    <div>
      <?php if ($user['role'] === 'teacher' && !empty($myClasses)): ?>

      <!-- Class Selector -->
      <div class="card" style="margin-bottom:1.5rem">
        <div class="card-header"><div class="card-title"><i class="fas fa-chalkboard" style="color:var(--primary)"></i> Select Class</div></div>
        <div class="grid grid-auto gap-3">
          <?php foreach ($myClasses as $cls): ?>
          <a href="?class_id=<?= $cls['id'] ?>" class="card" style="padding:1rem;cursor:pointer;text-decoration:none;border-color:<?= $classId===$cls['id']?'var(--primary)':'var(--border)' ?>;background:<?= $classId===$cls['id']?'rgba(99,102,241,0.08)':'var(--bg-card)' ?>">
            <div style="font-weight:700;font-size:0.875rem"><?= e($cls['name']) ?></div>
            <div style="font-size:0.75rem;color:var(--text-muted)"><?= e($cls['subject'] ?? '') ?> · <?= e($cls['section'] ?? '') ?></div>
          </a>
          <?php endforeach; ?>
        </div>
      </div>

      <?php if ($classId): ?>
      <!-- Export Options -->
      <div class="card">
        <div class="card-header"><div class="card-title"><i class="fas fa-file-csv" style="color:var(--success)"></i> Export Options</div></div>
        <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:1rem">
          <?php foreach ([['grades','Grades Report','fa-star','success','Student grades for all assignments'],['attendance','Attendance Report','fa-calendar-check','warning','Daily attendance records'],['students','Student List','fa-users','primary','Enrolled students with emails']] as [$type,$label,$icon,$color,$desc]): ?>
          <div class="card" style="padding:1.25rem;text-align:center;">
            <div style="width:52px;height:52px;border-radius:var(--radius-md);background:rgba(var(--<?= $color ?>-r,128),128,255,0.15);margin:0 auto 0.875rem;display:flex;align-items:center;justify-content:center;font-size:1.4rem;color:var(--<?= $color ?>)">
              <i class="fas <?= $icon ?>"></i>
            </div>
            <div style="font-weight:700;margin-bottom:0.375rem"><?= $label ?></div>
            <div style="font-size:0.75rem;color:var(--text-muted);margin-bottom:0.875rem"><?= $desc ?></div>
            <a href="?class_id=<?= $classId ?>&export=<?= $type ?>" class="btn btn-<?= $color ?> btn-sm btn-full"><i class="fas fa-download"></i> Download CSV</a>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
      <?php else: ?>
      <div class="empty-state" style="background:var(--bg-card);border:1px solid var(--border);border-radius:var(--radius-lg);padding:3rem">
        <div class="empty-icon"><i class="fas fa-mouse-pointer"></i></div>
        <div class="empty-title">Select a class above</div>
        <div class="empty-sub">Choose a class to see available export options</div>
      </div>
      <?php endif; ?>

      <?php elseif ($user['role'] === 'student'): ?>
      <!-- Student Export -->
      <div class="card">
        <div class="card-header"><div class="card-title"><i class="fas fa-file-download" style="color:var(--primary)"></i> My Reports</div></div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem">
          <div class="card" style="padding:1.25rem;text-align:center">
            <div style="font-size:2rem;margin-bottom:0.75rem">📊</div>
            <div style="font-weight:700;margin-bottom:0.5rem">Progress Report</div>
            <div style="font-size:0.75rem;color:var(--text-muted);margin-bottom:0.875rem">Your overall academic performance</div>
            <a href="<?= BASE_URL ?>/guardian/download.php?student_id=<?= $uid ?>" class="btn btn-primary btn-sm btn-full"><i class="fas fa-download"></i> Download PDF</a>
          </div>
          <div class="card" style="padding:1.25rem;text-align:center">
            <div style="font-size:2rem;margin-bottom:0.75rem">📅</div>
            <div style="font-weight:700;margin-bottom:0.5rem">Attendance Record</div>
            <div style="font-size:0.75rem;color:var(--text-muted);margin-bottom:0.875rem">Full attendance history</div>
            <a href="<?= BASE_URL ?>/analytics/performance.php" class="btn btn-secondary btn-sm btn-full"><i class="fas fa-chart-line"></i> View Online</a>
          </div>
        </div>
      </div>
      <?php else: ?>
      <div class="empty-state card"><div class="empty-icon"><i class="fas fa-file-export"></i></div><div class="empty-title">No export options</div><div class="empty-sub">Export options are available for teachers and students</div></div>
      <?php endif; ?>
    </div>

    <!-- Info Sidebar -->
    <div style="display:flex;flex-direction:column;gap:1.5rem">
      <div class="card" style="background:linear-gradient(135deg,rgba(16,185,129,0.12),rgba(6,182,212,0.08));border-color:rgba(16,185,129,0.2)">
        <div style="font-size:2rem;margin-bottom:0.75rem">📋</div>
        <div style="font-weight:800;margin-bottom:0.5rem">Smart Export System</div>
        <p style="font-size:0.8rem;color:var(--text-secondary)">Export your classroom data as CSV files for analysis in Excel, Google Sheets, or any spreadsheet application.</p>
      </div>
      <div class="card">
        <div class="card-title" style="margin-bottom:0.875rem"><i class="fas fa-info-circle" style="color:var(--info)"></i> Export Formats</div>
        <?php foreach ([['CSV','Spreadsheet compatible','.csv','success'],['PDF','Printable report','.pdf','danger'],['JSON','Developer format','.json','primary']] as [$fmt,$desc,$ext,$c]): ?>
        <div style="display:flex;align-items:center;justify-content:space-between;padding:0.625rem 0;border-bottom:1px solid var(--border);font-size:0.875rem">
          <div><strong><?= $fmt ?></strong><div style="font-size:0.75rem;color:var(--text-muted)"><?= $desc ?></div></div>
          <span class="badge badge-<?= $c ?>"><?= $ext ?></span>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</div>
</div></div>
<?php renderFooter(); ?>
</body></html>
