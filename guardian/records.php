<?php
// =============================================
// Smart Classroom — Guardian: Class Records (Read Only)
// =============================================
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/layout.php';
requireLogin();

$user       = currentUser();
$guardianId = $user['id'];
$role       = $user['role'];
$studentId  = (int)($_GET['student_id'] ?? 0);
$classId    = (int)($_GET['class_id'] ?? 0);

// Validate guardian access
$linkedStudents = [];
if ($role === 'guardian') {
    $ls = $pdo->prepare("SELECT gl.*,u.id as sid,u.name,u.email FROM guardian_links gl JOIN users u ON u.id=gl.student_id WHERE gl.guardian_id=? AND gl.status='approved'");
    $ls->execute([$guardianId]);
    $linkedStudents = $ls->fetchAll();
    if (!$studentId && !empty($linkedStudents)) $studentId = $linkedStudents[0]['sid'];

    // Check permission
    $allowed = array_column($linkedStudents, 'sid');
    if ($studentId && !in_array($studentId, $allowed)) redirect(BASE_URL . '/dashboard/guardian.php');
}

if (!$studentId) redirect(BASE_URL . '/dashboard/guardian.php');

$s = $pdo->prepare("SELECT * FROM users WHERE id=?");
$s->execute([$studentId]);
$student = $s->fetch();

// Student's classes
$classes = $pdo->prepare("SELECT c.*,u.name as teacher_name FROM classes c JOIN class_members cm ON cm.class_id=c.id JOIN users u ON u.id=c.teacher_id WHERE cm.user_id=? AND (c.status='active' OR c.status IS NULL)");
$classes->execute([$studentId]);
$classList = $classes->fetchAll();

// Active class details
$activeClass = null;
$assignments = [];
$submissions = [];
$attendance  = [];
if ($classId) {
    $ac = $pdo->prepare("SELECT c.*,u.name as teacher_name FROM classes c JOIN users u ON u.id=c.teacher_id WHERE c.id=? AND (c.status='active' OR c.status IS NULL)");
    $ac->execute([$classId]);
    $activeClass = $ac->fetch();

    $asgn = $pdo->prepare("SELECT a.*,(SELECT s.grade FROM submissions s WHERE s.assignment_id=a.id AND s.student_id=?) as student_grade,(SELECT s.status FROM submissions s WHERE s.assignment_id=a.id AND s.student_id=?) as sub_status FROM assignments a WHERE a.class_id=? ORDER BY a.created_at DESC");
    $asgn->execute([$studentId, $studentId, $classId]);
    $assignments = $asgn->fetchAll();

    $att = $pdo->prepare("SELECT * FROM attendance WHERE class_id=? AND student_id=? ORDER BY date DESC LIMIT 30");
    $att->execute([$classId, $studentId]);
    $attendance = $att->fetchAll();
}

renderHead('Class Records');
?>
<body>
<div class="app-wrapper">
<?php renderSidebar($user); ?>
<div class="main-content">
<?php renderTopbar('Class Records (Read Only)', $user); ?>

<div class="page-content animate-up">

  <!-- Readonly notice -->
  <div style="background:rgba(245,158,11,0.08);border:1px solid rgba(245,158,11,0.25);border-radius:var(--radius-sm);padding:0.75rem 1rem;font-size:0.875rem;color:var(--warning);display:flex;align-items:center;gap:0.5rem;margin-bottom:1.25rem">
    <i class="fas fa-eye"></i> <strong>Read-Only View</strong> — You are viewing your student's class records as a guardian.
  </div>

  <!-- Student selector (if multiple linked) -->
  <?php if (count($linkedStudents) > 1): ?>
  <div class="card" style="margin-bottom:1.25rem">
    <div class="card-title" style="margin-bottom:0.75rem">Select Student</div>
    <div style="display:flex;gap:0.5rem;flex-wrap:wrap">
      <?php foreach ($linkedStudents as $ls): ?>
      <a href="?student_id=<?= $ls['sid'] ?>" class="btn btn-<?= $studentId===$ls['sid']?'primary':'secondary' ?> btn-sm">
        <div class="avatar" style="width:24px;height:24px;font-size:0.65rem"><?= strtoupper($ls['name'][0]) ?></div>
        <?= e($ls['name']) ?>
      </a>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>

  <div style="display:grid;grid-template-columns:240px 1fr;gap:1.5rem">

    <!-- Class List -->
    <div>
      <div class="card">
        <div class="card-title" style="margin-bottom:0.875rem"><i class="fas fa-book" style="color:var(--primary)"></i> Classes</div>
        <?php if (empty($classList)): ?>
        <div class="empty-state" style="padding:1.25rem"><div class="empty-icon" style="width:40px;height:40px"><i class="fas fa-chalkboard"></i></div><div class="empty-title" style="font-size:0.875rem">No classes</div></div>
        <?php else: ?>
        <div style="display:flex;flex-direction:column;gap:0.375rem">
          <?php foreach ($classList as $cls): ?>
          <a href="?student_id=<?= $studentId ?>&class_id=<?= $cls['id'] ?>" style="display:block;padding:0.75rem;border-radius:var(--radius-sm);text-decoration:none;background:<?= $classId===$cls['id']?'rgba(99,102,241,0.12)':'transparent' ?>;border:1px solid <?= $classId===$cls['id']?'var(--primary)':'transparent' ?>;transition:all 0.2s" onmouseover="this.style.background='var(--bg-hover)'" onmouseout="this.style.background='<?= $classId===$cls['id']?'rgba(99,102,241,0.12)':'transparent' ?>'">
            <div style="font-size:0.875rem;font-weight:600;color:var(--text-primary)"><?= e($cls['name']) ?></div>
            <div style="font-size:0.72rem;color:var(--text-muted)"><?= e($cls['teacher_name']) ?></div>
          </a>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
      </div>
    </div>

    <!-- Class Details -->
    <div>
      <?php if ($activeClass): ?>
      <div class="card" style="margin-bottom:1.25rem;background:linear-gradient(135deg,<?= $activeClass['cover_color'] ?? '#4f46e5' ?>,<?= $activeClass['cover_color'] ?? '#4f46e5' ?>88);border:none">
        <div style="color:white">
          <div style="font-size:1.1rem;font-weight:800"><?= e($activeClass['name']) ?></div>
          <div style="font-size:0.8rem;opacity:0.8;margin-top:0.25rem"><?= e($activeClass['teacher_name']) ?> · <?= e($activeClass['subject'] ?? '') ?></div>
        </div>
      </div>

      <!-- Assignments -->
      <div class="card" style="margin-bottom:1.25rem">
        <div class="card-header"><div class="card-title"><i class="fas fa-tasks" style="color:var(--warning)"></i> Assignments</div></div>
        <?php if (empty($assignments)): ?>
        <div class="empty-state" style="padding:1.25rem"><div class="empty-icon" style="width:40px;height:40px"><i class="fas fa-tasks"></i></div><div class="empty-title" style="font-size:0.875rem">No assignments</div></div>
        <?php else: ?>
        <div class="table-wrap">
          <table>
            <thead><tr><th>Assignment</th><th>Max Pts</th><th>Grade</th><th>Status</th></tr></thead>
            <tbody>
            <?php foreach ($assignments as $a): ?>
            <tr>
              <td style="font-weight:500;font-size:0.875rem"><?= e($a['title']) ?></td>
              <td><?= $a['points'] ?></td>
              <td style="font-weight:800;color:var(--<?= ($a['student_grade'] ?? 0) >= 70 ? 'success' : (($a['student_grade'] ?? 0) >= 50 ? 'warning' : 'danger') ?>)"><?= $a['student_grade'] !== null ? $a['student_grade'].'%' : '—' ?></td>
              <td><span class="badge badge-<?= ['submitted'=>'info','graded'=>'success','late'=>'warning','missing'=>'danger'][$a['sub_status'] ?? 'missing'] ?? 'warning' ?>"><?= $a['sub_status'] ?? 'not submitted' ?></span></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <?php endif; ?>
      </div>

      <!-- Recent Attendance -->
      <div class="card">
        <div class="card-header"><div class="card-title"><i class="fas fa-calendar-check" style="color:var(--success)"></i> Recent Attendance</div></div>
        <?php if (empty($attendance)): ?>
        <div class="empty-state" style="padding:1.25rem"><div class="empty-title" style="font-size:0.875rem">No attendance records</div></div>
        <?php else: ?>
        <div class="table-wrap">
          <table>
            <thead><tr><th>Date</th><th>Status</th></tr></thead>
            <tbody>
            <?php foreach ($attendance as $a): ?>
            <tr>
              <td style="font-size:0.875rem"><?= date('D, M d Y', strtotime($a['date'])) ?></td>
              <td><span class="badge badge-<?= ['present'=>'success','absent'=>'danger','late'=>'warning','excused'=>'info'][$a['status']] ?>"><?= $a['status'] ?></span></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <?php endif; ?>
      </div>

      <?php else: ?>
      <div class="empty-state card"><div class="empty-icon"><i class="fas fa-mouse-pointer"></i></div><div class="empty-title">Select a class</div><div class="empty-sub">Click on a class in the sidebar to view details</div></div>
      <?php endif; ?>
    </div>
  </div>
</div>
</div></div>
<?php renderFooter(); ?>
</body></html>
