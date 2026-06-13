<?php
// =============================================
// Smart Classroom — Teacher Dashboard
// =============================================
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/layout.php';
requireLogin();
if (userRole() !== 'teacher') redirect(BASE_URL . '/index.php');

$user    = currentUser();
$teacher = $user['id'];

// Stats
$totalClasses   = $pdo->prepare("SELECT COUNT(*) FROM classes WHERE teacher_id = ?");
$totalClasses->execute([$teacher]);
$classCount = $totalClasses->fetchColumn();

$totalStudents  = $pdo->prepare("SELECT COUNT(DISTINCT cm.user_id) FROM class_members cm JOIN classes c ON c.id=cm.class_id WHERE c.teacher_id=?");
$totalStudents->execute([$teacher]);
$studentCount = $totalStudents->fetchColumn();

$totalAssign    = $pdo->prepare("SELECT COUNT(*) FROM assignments a JOIN classes c ON c.id=a.class_id WHERE c.teacher_id=?");
$totalAssign->execute([$teacher]);
$assignCount = $totalAssign->fetchColumn();

$pendingGrade   = $pdo->prepare("SELECT COUNT(*) FROM submissions s JOIN assignments a ON a.id=s.assignment_id JOIN classes c ON c.id=a.class_id WHERE c.teacher_id=? AND s.status='submitted'");
$pendingGrade->execute([$teacher]);
$pendingCount = $pendingGrade->fetchColumn();

// Classes list
$classes = $pdo->prepare("SELECT c.*, (SELECT COUNT(*) FROM class_members WHERE class_id=c.id) as member_count FROM classes c WHERE c.teacher_id=? AND (c.status='active' OR c.status IS NULL) ORDER BY c.created_at DESC");
$classes->execute([$teacher]);
$classList = $classes->fetchAll();

// Recent submissions
$recentSubs = $pdo->prepare("
  SELECT s.*,u.name as student_name,a.title as assignment_title,c.name as class_name
  FROM submissions s
  JOIN users u ON u.id=s.student_id
  JOIN assignments a ON a.id=s.assignment_id
  JOIN classes c ON c.id=a.class_id
  WHERE c.teacher_id=? ORDER BY s.submitted_at DESC LIMIT 8
");
$recentSubs->execute([$teacher]);
$recentSubsList = $recentSubs->fetchAll();

// Upcoming assignments
$upcomingAssign = $pdo->prepare("
  SELECT a.*,c.name as class_name,(SELECT COUNT(*) FROM submissions WHERE assignment_id=a.id) as sub_count
  FROM assignments a JOIN classes c ON c.id=a.class_id
  WHERE c.teacher_id=? AND a.due_date >= NOW() ORDER BY a.due_date ASC LIMIT 5
");
$upcomingAssign->execute([$teacher]);
$upcomingList = $upcomingAssign->fetchAll();

// Analytics data for charts
$gradeData  = $pdo->prepare("SELECT c.name, COALESCE(AVG(s.grade),0) as avg_grade FROM classes c LEFT JOIN assignments a ON a.class_id=c.id LEFT JOIN submissions s ON s.assignment_id=a.id WHERE c.teacher_id=? GROUP BY c.id LIMIT 6");
$gradeData->execute([$teacher]);
$gradeRows = $gradeData->fetchAll();

// At-risk counts
$riskRed = $pdo->prepare("SELECT COUNT(DISTINCT s.student_id) FROM submissions s JOIN assignments a ON a.id=s.assignment_id JOIN classes c ON c.id=a.class_id WHERE c.teacher_id=? AND s.grade IS NOT NULL AND s.grade < 50");
$riskRed->execute([$teacher]);
$atRiskRedCount = (int)$riskRed->fetchColumn();

$riskYellow = $pdo->prepare("SELECT COUNT(DISTINCT s.student_id) FROM submissions s JOIN assignments a ON a.id=s.assignment_id JOIN classes c ON c.id=a.class_id WHERE c.teacher_id=? AND s.grade IS NOT NULL AND s.grade >= 50 AND s.grade < 65");
$riskYellow->execute([$teacher]);
$atRiskYellowCount = (int)$riskYellow->fetchColumn();
$chartLabels = json_encode(array_column($gradeRows, 'name'));
$chartValues = json_encode(array_map(fn($r) => round($r['avg_grade'], 1), $gradeRows));

renderHead('Teacher Dashboard');
?>
<body>
<div class="app-wrapper">
<?php renderSidebar($user, 'teacher.php'); ?>
<div class="main-content">
<?php renderTopbar('Teacher Dashboard', $user, [['icon'=>'fa-plus','label'=>'New Class','onclick'=>"openModal('create-class-modal')"]]); ?>

<div class="page-content animate-up">

  <!-- Welcome Banner -->
  <div style="background:linear-gradient(135deg,rgba(99,102,241,0.15),rgba(168,85,247,0.1));border:1px solid rgba(99,102,241,0.2);border-radius:var(--radius-lg);padding:1.5rem 2rem;margin-bottom:1.5rem;display:flex;align-items:center;justify-content:space-between;gap:1rem">
    <div>
      <h2 style="font-size:1.4rem;font-weight:800">Good <?= (date('H') < 12 ? 'Morning' : (date('H') < 17 ? 'Afternoon' : 'Evening')) ?>, <?= e(explode(' ',$user['name'])[0]) ?>! 👋</h2>
      <p style="color:var(--text-secondary);margin-top:0.25rem">You have <strong style="color:var(--warning)"><?= $pendingCount ?></strong> submissions waiting for grading.</p>
    </div>
    <div style="text-align:right;font-size:0.8rem;color:var(--text-muted)">
      <div style="font-size:1.5rem;font-weight:800;color:var(--text-primary)"><?= date('d M Y') ?></div>
      <div><?= date('l') ?></div>
    </div>
  </div>

  <!-- Stats Row -->
  <div class="grid grid-4 gap-4 mb-4">
    <div class="stat-card" style="border-left:3px solid var(--primary)">
      <div class="stat-icon" style="background:rgba(99,102,241,0.15);color:var(--primary)"><i class="fas fa-chalkboard"></i></div>
      <div class="stat-info">
        <div class="stat-value" style="color:var(--primary)"><?= $classCount ?></div>
        <div class="stat-label">Active Classes</div>
      </div>
    </div>
    <div class="stat-card" style="border-left:3px solid var(--success)">
      <div class="stat-icon" style="background:rgba(16,185,129,0.15);color:var(--success)"><i class="fas fa-user-graduate"></i></div>
      <div class="stat-info">
        <div class="stat-value" style="color:var(--success)"><?= $studentCount ?></div>
        <div class="stat-label">Total Students</div>
      </div>
    </div>
    <div class="stat-card" style="border-left:3px solid var(--warning)">
      <div class="stat-icon" style="background:rgba(245,158,11,0.15);color:var(--warning)"><i class="fas fa-tasks"></i></div>
      <div class="stat-info">
        <div class="stat-value" style="color:var(--warning)"><?= $assignCount ?></div>
        <div class="stat-label">Assignments Created</div>
      </div>
    </div>
    <div class="stat-card" style="border-left:3px solid var(--danger)">
      <div class="stat-icon" style="background:rgba(239,68,68,0.15);color:var(--danger)"><i class="fas fa-clock"></i></div>
      <div class="stat-info">
        <div class="stat-value" style="color:var(--danger)"><?= $pendingCount ?></div>
        <div class="stat-label">Pending Grading</div>
        <?php if ($pendingCount > 0): ?><div class="stat-change text-danger">⚠ Needs attention</div><?php endif; ?>
      </div>
    </div>
  </div>

  <!-- Main Content Grid -->
  <div style="display:grid;grid-template-columns:1fr 360px;gap:1.5rem" id="classes">

    <!-- Left Column -->
    <div style="display:flex;flex-direction:column;gap:1.5rem">

      <!-- My Classes -->
      <div class="card">
        <div class="card-header">
          <div class="card-title"><i class="fas fa-chalkboard" style="color:var(--primary)"></i> My Classes</div>
          <button class="btn btn-primary btn-sm" onclick="openModal('create-class-modal')"><i class="fas fa-plus"></i> New Class</button>
        </div>
        <?php if (empty($classList)): ?>
          <div class="empty-state">
            <div class="empty-icon"><i class="fas fa-chalkboard"></i></div>
            <div class="empty-title">No classes yet</div>
            <div class="empty-sub">Create your first class to get started</div>
            <button class="btn btn-primary" onclick="openModal('create-class-modal')"><i class="fas fa-plus"></i> Create Class</button>
          </div>
        <?php else: ?>
        <div class="grid grid-auto gap-4">
          <?php foreach ($classList as $cls):
            $colors = ['#4f46e5','#0ea5e9','#10b981','#f59e0b','#a855f7','#ef4444','#ec4899'];
            $col = $cls['cover_color'] ?? $colors[array_search($cls['id'], array_column($classList,'id')) % count($colors)];
            $maxSt = $cls['max_students'] ?? 40;
            $isFull = $cls['member_count'] >= $maxSt;
          ?>
          <div class="class-card" onclick="window.location='<?= BASE_URL ?>/classroom/index.php?id=<?= $cls['id'] ?>'">
            <div class="class-cover" style="background:<?= $cls['logo'] ? 'url('.BASE_URL.'/uploads/logos/'.$cls['logo'].') center/cover' : 'linear-gradient(135deg,'.$col.','.$col.'99)' ?>">
              <i class="fas fa-graduation-cap class-cover-icon"></i>
              <div class="class-cover-name"><?= e($cls['name']) ?></div>
              <div class="class-cover-section"><?= e($cls['section'] ?? '') ?> · <?= e($cls['subject'] ?? '') ?></div>
            </div>
            <div class="class-body">
              <div class="class-meta">
                <i class="fas fa-users"></i> <?= $cls['member_count'] ?>/<?= $maxSt ?> students
                <?php if ($isFull): ?><span class="class-full-badge" style="margin-left:0.5rem">Full</span><?php endif; ?>
                <span style="flex:1"></span>
                <i class="fas fa-key"></i>
                <span onclick="copyText('<?= e($cls['code']) ?>','Code copied!');event.stopPropagation()" style="cursor:copy;color:var(--primary-light);font-weight:600"><?= e($cls['code']) ?></span>
              </div>
            </div>
            <div class="class-actions">
              <a href="<?= BASE_URL ?>/classroom/index.php?id=<?= $cls['id'] ?>" class="btn btn-primary btn-sm" onclick="event.stopPropagation()">Open</a>
              <a href="<?= BASE_URL ?>/classroom/index.php?id=<?= $cls['id'] ?>&tab=classwork" class="btn btn-secondary btn-sm" onclick="event.stopPropagation()">Assignments</a>
              <button class="btn btn-secondary btn-sm" onclick="uploadLogo(<?= $cls['id'] ?>);event.stopPropagation()"><i class="fas fa-image"></i> Logo</button>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
      </div>

      <!-- Grade Chart -->
      <div class="card">
        <div class="card-header">
          <div class="card-title"><i class="fas fa-chart-bar" style="color:var(--warning)"></i> Class Average Grades</div>
          <a href="<?= BASE_URL ?>/analytics/performance.php" class="btn btn-ghost btn-sm">View Full Analytics</a>
        </div>
        <div class="chart-wrapper" style="height:220px">
          <canvas id="grade-chart" data-labels='<?= $chartLabels ?>' data-values='<?= $chartValues ?>'></canvas>
        </div>
      </div>

      <!-- Recent Submissions -->
      <div class="card">
        <div class="card-header">
          <div class="card-title"><i class="fas fa-inbox" style="color:var(--info)"></i> Recent Submissions</div>
          <span class="badge badge-warning"><?= $pendingCount ?> pending</span>
        </div>
        <?php if (empty($recentSubsList)): ?>
          <div class="empty-state"><div class="empty-icon"><i class="fas fa-inbox"></i></div><div class="empty-title">No submissions yet</div></div>
        <?php else: ?>
        <div class="table-wrap">
          <table>
            <thead><tr><th>Student</th><th>Assignment</th><th>Class</th><th>Status</th><th>Grade</th><th>Action</th></tr></thead>
            <tbody>
            <?php foreach ($recentSubsList as $sub): ?>
            <tr>
              <td>
                <div style="display:flex;align-items:center;gap:0.5rem">
                  <div class="avatar" style="width:30px;height:30px;font-size:0.75rem"><?= strtoupper($sub['student_name'][0]) ?></div>
                  <span style="font-size:0.875rem;font-weight:500"><?= e($sub['student_name']) ?></span>
                </div>
              </td>
              <td style="font-size:0.85rem"><?= e(mb_strimwidth($sub['assignment_title'],0,25,'…')) ?></td>
              <td style="font-size:0.8rem;color:var(--text-muted)"><?= e($sub['class_name']) ?></td>
              <td>
                <?php $sc = ['submitted'=>'info','graded'=>'success','late'=>'warning','missing'=>'danger'][$sub['status']] ?? 'info'; ?>
                <span class="badge badge-<?= $sc ?>"><?= $sub['status'] ?></span>
              </td>
              <td style="font-weight:700;color:var(--<?= $sub['grade'] >= 70 ? 'success' : ($sub['grade'] >= 50 ? 'warning' : 'danger') ?>)">
                <?= $sub['grade'] !== null ? $sub['grade'].'%' : '—' ?>
              </td>
              <td>
                <a href="<?= BASE_URL ?>/classroom/grades.php?sub=<?= $sub['id'] ?>" class="btn btn-primary btn-sm">
                  <?= $sub['status'] === 'submitted' ? 'Grade' : 'View' ?>
                </a>
              </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <?php endif; ?>
      </div>
    </div>

    <!-- Right Sidebar -->
    <div style="display:flex;flex-direction:column;gap:1.5rem">

      <!-- Quick Actions -->
      <div class="card">
        <div class="card-title" style="margin-bottom:1rem"><i class="fas fa-bolt" style="color:var(--warning)"></i> Quick Actions</div>
        <div style="display:flex;flex-direction:column;gap:0.625rem">
          <button class="btn btn-secondary btn-full" onclick="openModal('create-class-modal')"><i class="fas fa-chalkboard"></i> Create New Class</button>
          <a href="<?= BASE_URL ?>/advance_classroom/teacher/create_assignment.php" class="btn btn-secondary btn-full"><i class="fas fa-plus-circle"></i> New Assignment</a>
          <a href="<?= BASE_URL ?>/classroom/quiz.php" class="btn btn-secondary btn-full"><i class="fas fa-question-circle"></i> Create Quiz/Poll</a>
          <a href="<?= BASE_URL ?>/classroom/attendance.php" class="btn btn-secondary btn-full"><i class="fas fa-calendar-check"></i> Mark Attendance</a>
          <a href="<?= BASE_URL ?>/classroom/video_meet.php" class="btn btn-secondary btn-full"><i class="fas fa-video"></i> Start Video Meet</a>
          <a href="<?= BASE_URL ?>/analytics/performance.php" class="btn btn-secondary btn-full"><i class="fas fa-chart-line"></i> View Analytics</a>
          <a href="<?= BASE_URL ?>/global/export.php" class="btn btn-secondary btn-full"><i class="fas fa-file-export"></i> Export Reports</a>
        </div>
      </div>

      <!-- At-Risk Students -->
      <div class="card" style="border-top:3px solid var(--danger)">
        <div class="card-header">
          <div class="card-title"><i class="fas fa-user-shield" style="color:var(--danger)"></i> At-Risk Students</div>
          <a href="<?= BASE_URL ?>/dashboard/at_risk.php" class="btn btn-ghost btn-sm">Full Report</a>
        </div>
        <?php if ($atRiskRedCount > 0 || $atRiskYellowCount > 0): ?>
        <div style="display:flex;gap:1rem;margin-bottom:0.875rem">
          <?php if ($atRiskRedCount > 0): ?>
          <div style="flex:1;text-align:center;padding:0.75rem;background:rgba(239,68,68,0.1);border-radius:var(--radius-sm);border:1px solid rgba(239,68,68,0.2)">
            <div style="font-size:1.75rem;font-weight:800;color:var(--danger)"><?= $atRiskRedCount ?></div>
            <div style="font-size:0.7rem;color:var(--text-muted)">High Risk</div>
          </div>
          <?php endif; ?>
          <?php if ($atRiskYellowCount > 0): ?>
          <div style="flex:1;text-align:center;padding:0.75rem;background:rgba(245,158,11,0.1);border-radius:var(--radius-sm);border:1px solid rgba(245,158,11,0.2)">
            <div style="font-size:1.75rem;font-weight:800;color:var(--warning)"><?= $atRiskYellowCount ?></div>
            <div style="font-size:0.7rem;color:var(--text-muted)">Need Attention</div>
          </div>
          <?php endif; ?>
        </div>
        <a href="<?= BASE_URL ?>/dashboard/at_risk.php" class="btn btn-danger btn-full btn-sm"><i class="fas fa-exclamation-circle"></i> View At-Risk Report</a>
        <?php else: ?>
        <div style="text-align:center;padding:0.75rem;color:var(--success);font-size:0.875rem">
          <i class="fas fa-check-circle"></i> All students on track!
        </div>
        <?php endif; ?>
      </div>

      <!-- Upcoming Assignments -->
      <div class="card">
        <div class="card-header">
          <div class="card-title"><i class="fas fa-clock" style="color:var(--primary)"></i> Upcoming Due</div>
        </div>
        <?php if (empty($upcomingList)): ?>
          <div class="empty-state"><div class="empty-icon"><i class="fas fa-calendar"></i></div><div class="empty-title">No upcoming</div></div>
        <?php else: ?>
        <div style="display:flex;flex-direction:column;gap:0.75rem">
          <?php foreach ($upcomingList as $a): ?>
          <div class="assignment-card" onclick="window.location='<?= BASE_URL ?>/classroom/index.php?id=<?= $a['class_id'] ?>&tab=classwork'">
            <div class="assignment-icon" style="background:rgba(99,102,241,0.15);color:var(--primary)"><i class="fas fa-file-alt"></i></div>
            <div class="assignment-info">
              <div class="assignment-title"><?= e(mb_strimwidth($a['title'],0,28,'…')) ?></div>
              <div class="assignment-meta">
                <i class="fas fa-calendar"></i> Due: <?= date('M d', strtotime($a['due_date'])) ?>
                <span class="badge badge-info" style="font-size:0.65rem"><?= $a['sub_count'] ?> submitted</span>
              </div>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
      </div>

      <!-- Notifications Preview -->
      <div class="card">
        <div class="card-header">
          <div class="card-title"><i class="fas fa-bell" style="color:var(--warning)"></i> Notifications</div>
          <a href="<?= BASE_URL ?>/global/notifications.php" class="btn btn-ghost btn-sm">All</a>
        </div>
        <?php
        $notifs = $pdo->prepare("SELECT * FROM notifications WHERE user_id=? ORDER BY created_at DESC LIMIT 5");
        $notifs->execute([$teacher]);
        $notifList = $notifs->fetchAll();
        ?>
        <?php if (empty($notifList)): ?>
          <div class="notif-item">
            <div class="notif-dot-sm" style="background:var(--success)"></div>
            <div><div class="notif-text">Welcome to Smart Classroom!</div><div class="notif-time">Just now</div></div>
          </div>
        <?php else: ?>
          <?php foreach ($notifList as $n): ?>
          <div class="notif-item <?= $n['is_read'] ? '' : 'unread' ?>" data-id="<?= $n['id'] ?>">
            <?php if (!$n['is_read']): ?><div class="notif-dot-sm"></div><?php endif; ?>
            <div><div class="notif-text"><?= e($n['message']) ?></div><div class="notif-time"><?= timeAgo($n['created_at']) ?></div></div>
          </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<!-- Create Class Modal -->
<div class="modal-overlay" id="create-class-modal">
  <div class="modal">
    <div class="modal-header">
      <div class="modal-title"><i class="fas fa-chalkboard" style="color:var(--primary)"></i> Create New Class</div>
      <button class="modal-close">✕</button>
    </div>
    <form id="create-class-form" method="POST" action="<?= BASE_URL ?>/api/classes.php">
      <input type="hidden" name="action" value="create">
      <div class="modal-body" style="display:flex;flex-direction:column;gap:1rem">
        <div class="form-group">
          <label class="form-label">Class Name *</label>
          <input type="text" name="name" class="form-control" placeholder="e.g., Advanced Web Development" required>
        </div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem">
          <div class="form-group">
            <label class="form-label">Section</label>
            <input type="text" name="section" class="form-control" placeholder="Section A">
          </div>
          <div class="form-group">
            <label class="form-label">Room</label>
            <input type="text" name="room" class="form-control" placeholder="Room 301">
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">Subject</label>
          <input type="text" name="subject" class="form-control" placeholder="e.g., CSE 479">
        </div>
        <div class="form-group">
          <label class="form-label">Description</label>
          <textarea name="description" class="form-control" placeholder="Brief class description..." rows="3" data-maxlength="300"></textarea>
        </div>
        <div class="form-group">
          <label class="form-label">Class Color Theme</label>
          <div style="display:flex;gap:0.5rem;flex-wrap:wrap">
            <?php foreach (['#4f46e5','#0ea5e9','#10b981','#f59e0b','#a855f7','#ef4444','#ec4899','#06b6d4'] as $c): ?>
            <label style="cursor:pointer">
              <input type="radio" name="cover_color" value="<?= $c ?>" style="display:none" <?= $c === '#4f46e5' ? 'checked' : '' ?>>
              <div style="width:32px;height:32px;border-radius:50%;background:<?= $c ?>" onclick="this.previousElementSibling.checked=true;document.querySelectorAll('[name=cover_color]+div').forEach(d=>d.style.outline='');this.style.outline='3px solid white'"></div>
            </label>
            <?php endforeach; ?>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary modal-close">Cancel</button>
        <button type="submit" class="btn btn-primary"><i class="fas fa-plus"></i> Create Class</button>
      </div>
    </form>
  </div>
</div>

<!-- Logo Upload Form (Hidden) -->
<form id="logoUpForm" style="display:none" method="POST" enctype="multipart/form-data">
    <input type="file" id="logoUploadFile" name="logo" accept="image/png, image/jpeg, image/webp" onchange="submitLogoForm()">
    <input type="hidden" id="logoClassId" name="class_id">
</form>

</div><!-- end main-content -->
</div><!-- end app-wrapper -->

<?php renderFooter('<script>
function uploadLogo(classId) {
    document.getElementById("logoClassId").value = classId;
    document.getElementById("logoUploadFile").click();
}
async function submitLogoForm() {
    const fd = new FormData(document.getElementById("logoUpForm"));
    fd.append("action", "upload_logo");
    SCS.showToast("Uploading logo...", "info", 1000);
    const res = await SCS.apiRequest("'.BASE_URL.'/api/classes.php", "POST", fd);
    if(res.success) {
        SCS.showToast("Logo updated successfully!", "success");
        setTimeout(() => location.reload(), 1000);
    } else {
        SCS.showToast(res.error || "Upload failed", "error");
    }
}
</script>'); ?>
</body>
</html>
