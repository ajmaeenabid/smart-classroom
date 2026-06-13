<?php
// =============================================
// Smart Classroom — Classroom Central Hub
// =============================================
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/layout.php';
requireLogin();

$user      = currentUser();
$classId   = (int)($_GET['id'] ?? 0);
$activeTab = $_GET['tab'] ?? 'stream';

if (!$classId) redirect(BASE_URL . '/index.php');

// Fetch class
$stmt = $pdo->prepare("SELECT c.*,u.name as teacher_name FROM classes c JOIN users u ON u.id=c.teacher_id WHERE c.id=?");
$stmt->execute([$classId]);
$class = $stmt->fetch();
if (!$class) { http_response_code(404); die('Class not found'); }
if (($class['status'] ?? 'active') === 'archived') { /* allow read-only access during grace period */ }

// Check access
$isTeacher = ($user['role'] === 'teacher' && $class['teacher_id'] == $user['id']);
$isMember  = false;
if (!$isTeacher) {
    $m = $pdo->prepare("SELECT id FROM class_members WHERE class_id=? AND user_id=?");
    $m->execute([$classId, $user['id']]);
    $isMember = (bool)$m->fetch();
} else { $isMember = true; }

if (!$isMember && !$isTeacher) redirect(BASE_URL . '/index.php');

// ── REDIRECTS FOR EXTERNAL TABS ───────────────
if ($activeTab === 'quiz') redirect(BASE_URL . "/classroom/quiz.php?class_id={$classId}");
if ($activeTab === 'meet') redirect(BASE_URL . "/classroom/video_meet.php?class_id={$classId}");
if ($activeTab === 'attendance') redirect(BASE_URL . "/classroom/attendance.php?class_id={$classId}");

// ── LOAD TAB DATA ─────────────────────────────
$itemId = (int)($_GET['item_id'] ?? 0);

// STREAM
$announcements = [];
if ($activeTab === 'stream') {
    if ($itemId) {
        $ann = $pdo->prepare("SELECT a.*,u.name as author_name FROM announcements a JOIN users u ON u.id=a.author_id WHERE a.class_id=? AND a.id=? ORDER BY a.created_at DESC");
        $ann->execute([$classId, $itemId]);
    } else {
        $ann = $pdo->prepare("SELECT a.*,u.name as author_name FROM announcements a JOIN users u ON u.id=a.author_id WHERE a.class_id=? ORDER BY a.created_at DESC LIMIT 20");
        $ann->execute([$classId]);
    }
    $announcements = $ann->fetchAll();
}

// CLASSWORK
$assignments = [];
if ($activeTab === 'classwork') {
    if ($itemId) {
        $asgn = $pdo->prepare("SELECT a.*,(SELECT COUNT(*) FROM submissions WHERE assignment_id=a.id) as sub_count FROM assignments a WHERE a.class_id=? AND a.id=? ORDER BY a.created_at DESC");
        $asgn->execute([$classId, $itemId]);
    } else {
        $asgn = $pdo->prepare("SELECT a.*,(SELECT COUNT(*) FROM submissions WHERE assignment_id=a.id) as sub_count FROM assignments a WHERE a.class_id=? ORDER BY a.created_at DESC");
        $asgn->execute([$classId]);
    }
    $assignments = $asgn->fetchAll();
}

// PEOPLE
$people = [];
if ($activeTab === 'people') {
    $ppl = $pdo->prepare("SELECT u.* FROM users u JOIN class_members cm ON cm.user_id=u.id WHERE cm.class_id=?");
    $ppl->execute([$classId]);
    $people = $ppl->fetchAll();
    $teacher = $pdo->prepare("SELECT * FROM users WHERE id=?");
    $teacher->execute([$class['teacher_id']]);
    $teacherInfo = $teacher->fetch();
}

// GRADES
$gradeRows = [];
if ($activeTab === 'grades') {
    if ($isTeacher) {
        $gr = $pdo->prepare("SELECT u.name as student_name,u.id as student_id,AVG(s.grade) as avg,COUNT(s.id) as graded FROM users u JOIN class_members cm ON cm.user_id=u.id LEFT JOIN submissions s ON s.student_id=u.id JOIN assignments a ON a.id=s.assignment_id WHERE cm.class_id=? AND a.class_id=? AND s.grade IS NOT NULL GROUP BY u.id ORDER BY avg DESC");
        $gr->execute([$classId, $classId]);
        $gradeRows = $gr->fetchAll();
    } else {
        $gr = $pdo->prepare("SELECT a.title,a.points,s.grade,s.status,s.feedback,s.submitted_at FROM submissions s JOIN assignments a ON a.id=s.assignment_id WHERE a.class_id=? AND s.student_id=? ORDER BY s.submitted_at DESC");
        $gr->execute([$classId, $user['id']]);
        $gradeRows = $gr->fetchAll();
    }
}

// MATERIALS
$materials = [];
if ($activeTab === 'materials') {
    if ($itemId) {
        $mat = $pdo->prepare("SELECT * FROM materials WHERE class_id=? AND id=? ORDER BY created_at DESC");
        $mat->execute([$classId, $itemId]);
    } else {
        $mat = $pdo->prepare("SELECT * FROM materials WHERE class_id=? ORDER BY created_at DESC");
        $mat->execute([$classId]);
    }
    $materials = $mat->fetchAll();
}

// Load teacher's classes for multi-class announcement selector
$teacherClasses = [];
if ($isTeacher) {
    $tc = $pdo->prepare("SELECT id, name FROM classes WHERE teacher_id=? AND status='active' ORDER BY name");
    $tc->execute([$user['id']]);
    $teacherClasses = $tc->fetchAll();
}

// POST Actions (teacher - create announcement)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $activeTab === 'stream' && $isTeacher) {
    $content      = trim($_POST['content'] ?? '');
    $sendToAll    = isset($_POST['send_to_all']) && $_POST['send_to_all'] === '1';
    $selectedIds  = array_map('intval', $_POST['class_ids'] ?? []);
    $ownedIds     = array_column($teacherClasses, 'id');

    if ($content) {
        $attachment = null;
        if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
            $ext = strtolower(pathinfo($_FILES['attachment']['name'], PATHINFO_EXTENSION));
            $filename = uniqid('post_') . '.' . $ext;
            if (move_uploaded_file($_FILES['attachment']['tmp_name'], __DIR__.'/../uploads/'.$filename)) {
                $attachment = $filename;
            }
        }

        // Determine target class IDs (only classes this teacher owns)
        if ($sendToAll) {
            $targetIds = $ownedIds;
        } elseif (!empty($selectedIds)) {
            $targetIds = array_values(array_filter($selectedIds, fn($id) => in_array($id, $ownedIds)));
        } else {
            $targetIds = [$classId];
        }
        if (empty($targetIds)) $targetIds = [$classId];

        foreach ($targetIds as $targetClassId) {
            $pdo->prepare("INSERT INTO announcements (class_id, author_id, content, attachment) VALUES (?,?,?,?)")
                ->execute([$targetClassId, $user['id'], $content, $attachment]);

            // Notify all students in this class
            $clsNameStmt = $pdo->prepare("SELECT name FROM classes WHERE id=?");
            $clsNameStmt->execute([$targetClassId]);
            $targetClassName = $clsNameStmt->fetchColumn();

            $membersStmt = $pdo->prepare("SELECT user_id FROM class_members WHERE class_id=?");
            $membersStmt->execute([$targetClassId]);
            foreach ($membersStmt->fetchAll(PDO::FETCH_COLUMN) as $memberId) {
                $pdo->prepare("INSERT INTO notifications (user_id, title, message, type, link) VALUES (?,?,?,?,?)")
                    ->execute([
                        $memberId,
                        'New Announcement',
                        $user['name'] . ' posted in ' . $targetClassName,
                        'info',
                        '/classroom/index.php?id=' . $targetClassId . '&tab=stream',
                    ]);
            }
        }

        redirect(BASE_URL . "/classroom/index.php?id={$classId}&tab=stream");
    }
}

$tabs = [
    ['id'=>'stream',    'icon'=>'fa-stream',         'label'=>'Stream'],
    ['id'=>'classwork', 'icon'=>'fa-tasks',           'label'=>'Classwork'],
    ['id'=>'people',    'icon'=>'fa-users',           'label'=>'People'],
    ['id'=>'grades',    'icon'=>'fa-star',            'label'=>'Grades'],
    ['id'=>'materials', 'icon'=>'fa-folder-open',     'label'=>'Materials'],
];
if ($isTeacher) {
    array_push($tabs,
        ['id'=>'quiz',      'icon'=>'fa-question-circle','label'=>'Quiz'],
        ['id'=>'meet',      'icon'=>'fa-video',          'label'=>'Video Meet'],
        ['id'=>'attendance','icon'=>'fa-calendar-check', 'label'=>'Attendance']
    );
} else {
    // Students also get Attendance tab to view their attendance
    array_push($tabs,
        ['id'=>'attendance','icon'=>'fa-calendar-check', 'label'=>'Attendance']
    );
}

$coverColor = $class['cover_color'] ?? '#4f46e5';

renderHead('Classroom · ' . $class['name']);
?>
<body>
<div class="app-wrapper">
<?php renderSidebar($user); ?>
<div class="main-content">

<!-- Classroom Topbar with Cover -->
<div style="background:linear-gradient(135deg,<?= $coverColor ?>,<?= $coverColor ?>99);padding:1.5rem 1.5rem 0;position:relative;overflow:hidden">
  <div style="position:absolute;inset:0;background:url('data:image/svg+xml,<svg.../>') center;opacity:0.05"></div>
  <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:1rem;position:relative;z-index:1">
    <div>
      <div style="display:flex;align-items:center;gap:0.75rem;margin-bottom:0.5rem">
        <a href="<?= $user['role']==='teacher' ? BASE_URL.'/dashboard/teacher.php' : BASE_URL.'/dashboard/student.php' ?>" style="color:white;font-size:0.9rem;display:flex;align-items:center;gap:0.35rem;text-decoration:none;background:rgba(255,255,255,0.15);padding:0.35rem 0.75rem;border-radius:8px;font-weight:500">
          <i class="fas fa-arrow-left"></i> Dashboard
        </a>
        <span style="color:white;font-size:1.1rem;font-weight:700">/</span>
        <span style="color:white;font-size:0.95rem;font-weight:600;text-shadow:0 1px 2px rgba(0,0,0,0.2)"><?= e($class['name']) ?></span>
      </div>
      <h1 style="font-size:1.6rem;font-weight:900;color:white"><?= e($class['name']) ?></h1>
      <div style="color:rgba(255,255,255,0.75);font-size:0.875rem;margin-top:0.25rem;display:flex;align-items:center;gap:1rem">
        <span><i class="fas fa-chalkboard-teacher"></i> <?= e($class['teacher_name']) ?></span>
        <?php if ($class['section']): ?><span>· <?= e($class['section']) ?></span><?php endif; ?>
        <?php if ($class['subject']): ?><span>· <?= e($class['subject']) ?></span><?php endif; ?>
        <?php if ($class['room']): ?><span>· <?= e($class['room']) ?></span><?php endif; ?>
      </div>
    </div>
    <div style="display:flex;align-items:center;gap:0.75rem">
      <?php if ($isTeacher): ?>
      <div style="background:rgba(255,255,255,0.15);border:1px solid rgba(255,255,255,0.2);border-radius:var(--radius-sm);padding:0.5rem 1rem;text-align:center">
        <div style="font-size:1.1rem;font-weight:800;letter-spacing:0.15em;cursor:pointer" onclick="SCS.copyText('<?= $class['code'] ?>','Class code copied!')"><?= e($class['code']) ?></div>
        <div style="font-size:0.65rem;opacity:0.7;margin-top:0.1rem">CLICK TO COPY CODE</div>
      </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- Tabs -->
  <div style="display:flex;gap:0.35rem;margin-top:1.25rem;overflow-x:auto;position:relative;z-index:1;padding:0 0 0.15rem 0">
    <?php foreach ($tabs as $tab): ?>
    <a href="?id=<?= $classId ?>&tab=<?= $tab['id'] ?>" class="tab-item <?= $activeTab===$tab['id']?'active':'' ?>" style="<?= $activeTab===$tab['id'] ? 'background:rgba(255,255,255,0.35);color:white;border-bottom-color:white;font-weight:800;box-shadow:0 -2px 10px rgba(255,255,255,0.1)' : '' ?>">
      <i class="fas <?= $tab['icon'] ?>"></i> <?= $tab['label'] ?>
    </a>
    <?php endforeach; ?>
  </div>
</div>

<!-- Tab Content -->
<div class="page-content animate-up">

<?php if (($class['status'] ?? 'active') === 'archived'): ?>
<div style="background:rgba(245,158,11,0.15);border:1px solid rgba(245,158,11,0.3);border-radius:var(--radius-md);padding:1rem;margin-bottom:1.5rem;display:flex;align-items:center;justify-content:space-between;gap:1rem">
  <div style="display:flex;align-items:center;gap:0.75rem">
    <i class="fas fa-archive" style="color:var(--warning);font-size:1.25rem"></i>
    <div>
      <div style="font-weight:700;color:var(--warning)">This classroom is archived</div>
      <div style="font-size:0.8rem;color:var(--text-secondary)">Read-only access. This class will be permanently deleted after the 2-day grace period. Save your materials now!</div>
    </div>
  </div>
  <?php if ($isTeacher): ?>
  <button class="btn btn-success btn-sm" onclick="restoreClass()"><i class="fas fa-undo"></i> Restore Class</button>
  <?php elseif (!$isTeacher): ?>
  <button class="btn btn-warning btn-sm" onclick="saveToMyArchive()"><i class="fas fa-bookmark"></i> Save All Materials</button>
  <?php endif; ?>
</div>
<?php endif; ?>

<?php if ($isTeacher && ($class['status'] ?? 'active') === 'active'): ?>
<div style="display:flex;justify-content:flex-end;gap:0.5rem;margin-bottom:0.5rem">
  <button class="btn btn-ghost btn-sm" style="color:var(--danger)" onclick="archiveClass()"><i class="fas fa-archive"></i> Archive / Delete Classroom</button>
</div>
<?php endif; ?>

<?php if (!$isTeacher && ($class['status'] ?? 'active') === 'active' && $activeTab === 'materials'): ?>
<div style="display:flex;justify-content:flex-end;margin-bottom:0.5rem">
  <button class="btn btn-ghost btn-sm" style="color:var(--warning)" onclick="saveToMyArchive()"><i class="fas fa-bookmark"></i> Save All Materials to My Archive</button>
</div>
<?php endif; ?>

<?php if ($itemId): ?>
<div style="max-width:860px;margin:0 auto 1.5rem auto;background:rgba(99,102,241,0.1);border:1px dashed var(--primary);border-radius:var(--radius-md);padding:1rem;display:flex;align-items:center;justify-content:space-between">
  <div>
    <div style="font-size:0.95rem;color:var(--primary);font-weight:700"><i class="fas fa-search"></i> Isolated Search Result</div>
    <div style="font-size:0.8rem;color:var(--text-muted);margin-top:0.25rem">You are viewing a single item from your search. Other items in this tab are currently hidden.</div>
  </div>
  <a href="?id=<?= $classId ?>&tab=<?= $activeTab ?>" class="btn btn-primary btn-sm"><i class="fas fa-stream"></i> Show All</a>
</div>
<?php endif; ?>

<?php if ($activeTab === 'stream'): ?>
<!-- ── STREAM ────────────────────────────────── -->
<div style="max-width:760px;margin:0 auto">

  <?php if ($isTeacher): ?>
  <!-- Post box -->
  <div class="card" style="margin-bottom:1.5rem">
    <form method="POST" enctype="multipart/form-data">
      <textarea name="content" class="form-control" placeholder="Share something with your class..." rows="3" data-maxlength="1000" style="margin-bottom:0.75rem"></textarea>
      <?php if (count($teacherClasses) > 1): ?>
      <div style="margin-bottom:0.75rem;padding:0.625rem 0.75rem;background:rgba(99,102,241,0.08);border-radius:var(--radius-sm)">
        <div style="display:flex;align-items:center;gap:0.5rem">
          <input type="checkbox" id="toggle_multi" style="cursor:pointer" onchange="toggleMultiClass(this)">
          <label for="toggle_multi" style="font-size:0.85rem;cursor:pointer;color:var(--primary);font-weight:600;display:flex;align-items:center;gap:0.35rem">
            <i class="fas fa-broadcast-tower"></i> Post to Multiple Classes
          </label>
          <span style="font-size:0.75rem;color:var(--text-muted);margin-left:auto">Currently posting to: <strong><?= e($class['name']) ?></strong></span>
        </div>

        <div id="multi-class-panel" style="display:none;margin-top:0.625rem;padding-top:0.5rem;border-top:1px solid rgba(255,255,255,0.07)">
          <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:0.5rem">
            <span style="font-size:0.75rem;color:var(--text-muted)">Select which classes to post to:</span>
            <div style="display:flex;gap:0.4rem">
              <button type="button" onclick="selectAllClasses(true)"  style="font-size:0.7rem;padding:0.15rem 0.5rem;border-radius:4px;border:1px solid var(--border);background:rgba(99,102,241,0.15);color:var(--primary);cursor:pointer">All</button>
              <button type="button" onclick="selectAllClasses(false)" style="font-size:0.7rem;padding:0.15rem 0.5rem;border-radius:4px;border:1px solid var(--border);background:rgba(255,255,255,0.05);color:var(--text-muted);cursor:pointer">None</button>
            </div>
          </div>
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:0.3rem">
            <?php foreach ($teacherClasses as $tc): ?>
            <label style="display:flex;align-items:center;gap:0.4rem;font-size:0.8rem;cursor:pointer;padding:0.3rem 0.5rem;border-radius:4px;background:rgba(255,255,255,0.04);border:1px solid transparent" onmouseover="this.style.borderColor='rgba(99,102,241,0.3)'" onmouseout="this.style.borderColor='transparent'">
              <input type="checkbox" name="class_ids[]" value="<?= $tc['id'] ?>" class="multi-class-cb" <?= $tc['id'] == $classId ? 'checked' : '' ?>>
              <span style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= e($tc['name']) ?></span>
            </label>
            <?php endforeach; ?>
          </div>
        </div>
      </div>
      <?php endif; ?>
      <div style="display:flex;align-items:center;justify-content:flex-end;gap:0.75rem">
        <input type="file" name="attachment" id="stream-attachment" style="display:none" onchange="document.getElementById('attach-label').innerText = this.files[0]?.name || ''">
        <span id="attach-label" style="font-size:0.8rem;color:var(--text-muted);margin-right:auto;background:rgba(255,255,255,0.05);padding:0.2rem 0.6rem;border-radius:var(--radius-sm);display:empty"></span>
        <button type="button" class="btn btn-ghost btn-sm" onclick="document.getElementById('stream-attachment').click()"><i class="fas fa-paperclip"></i> Attach</button>
        <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-paper-plane"></i> Post</button>
      </div>
    </form>
  </div>
  <?php endif; ?>

  <!-- New Assignment card if teacher -->
  <?php if ($isTeacher): ?>
  <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:1rem;margin-bottom:1.5rem">
    <a href="?id=<?= $classId ?>&tab=classwork" class="card" style="text-align:center;cursor:pointer;text-decoration:none;padding:1rem">
      <div style="font-size:1.5rem;color:var(--primary);margin-bottom:0.5rem"><i class="fas fa-file-alt"></i></div>
      <div style="font-size:0.8rem;font-weight:600">Assignment</div>
    </a>
    <a href="?id=<?= $classId ?>&tab=quiz" class="card" style="text-align:center;cursor:pointer;text-decoration:none;padding:1rem">
      <div style="font-size:1.5rem;color:var(--success);margin-bottom:0.5rem"><i class="fas fa-question-circle"></i></div>
      <div style="font-size:0.8rem;font-weight:600">Quiz/Poll</div>
    </a>
    <a href="?id=<?= $classId ?>&tab=materials" class="card" style="text-align:center;cursor:pointer;text-decoration:none;padding:1rem">
      <div style="font-size:1.5rem;color:var(--warning);margin-bottom:0.5rem"><i class="fas fa-folder-plus"></i></div>
      <div style="font-size:0.8rem;font-weight:600">Material</div>
    </a>
  </div>
  <?php endif; ?>

  <!-- Announcements -->
  <?php if (empty($announcements)): ?>
  <div class="empty-state"><div class="empty-icon"><i class="fas fa-stream"></i></div><div class="empty-title">No posts yet</div><div class="empty-sub"><?= $isTeacher ? 'Post an announcement above' : 'Your teacher has not posted anything yet' ?></div></div>
  <?php else: ?>
  <?php foreach ($announcements as $ann): ?>
  <div class="card" style="margin-bottom:1rem">
    <div style="display:flex;align-items:center;gap:0.75rem;margin-bottom:0.875rem">
      <div class="avatar" style="background:linear-gradient(135deg,var(--primary),var(--purple))"><?= strtoupper($ann['author_name'][0]) ?></div>
      <div>
        <div style="font-weight:600;font-size:0.9rem"><?= e($ann['author_name']) ?></div>
        <div style="font-size:0.75rem;color:var(--text-muted)"><?= timeAgo($ann['created_at']) ?></div>
      </div>
      <?php if ($isTeacher): ?>
      <div class="dropdown" style="margin-left:auto">
        <button class="icon-btn" style="width:30px;height:30px" data-dropdown="ann-<?= $ann['id'] ?>"><i class="fas fa-ellipsis-h"></i></button>
        <div class="dropdown-menu" id="ann-<?= $ann['id'] ?>">
          <div class="dropdown-item danger" onclick="SCS.confirmAction('Delete this announcement?',()=>window.location='<?= BASE_URL ?>/api/classes.php?del_ann=<?= $ann['id'] ?>&class_id=<?= $classId ?>')"><i class="fas fa-trash"></i> Delete</div>
        </div>
      </div>
      <?php endif; ?>
    </div>
    <p style="color:var(--text-secondary);font-size:0.9rem;white-space:pre-wrap"><?= e($ann['content']) ?></p>
    <?php if ($ann['attachment']): ?>
    <div style="margin-top:0.875rem;padding:0.625rem 0.875rem;background:var(--bg-overlay);border-radius:var(--radius-sm);display:flex;align-items:center;gap:0.5rem;font-size:0.875rem">
      <i class="fas fa-paperclip" style="color:var(--info)"></i>
      <a href="<?= BASE_URL ?>/uploads/<?= e($ann['attachment']) ?>" target="_blank" style="color:var(--info)"><?= e($ann['attachment']) ?></a>
    </div>
    <?php endif; ?>
    <div style="margin-top:0.875rem;padding-top:0.875rem;border-top:1px solid var(--border);display:flex;align-items:center;gap:0.5rem">
      <i class="fas fa-comment" style="color:var(--text-muted);font-size:0.875rem"></i>
      <span style="font-size:0.8rem;color:var(--text-muted)">Add comment...</span>
    </div>
  </div>
  <?php endforeach; ?>
  <?php endif; ?>
</div>

<?php elseif ($activeTab === 'classwork'): ?>
<!-- ── CLASSWORK ────────────────────────────── -->
<div style="max-width:760px;margin:0 auto">
  <?php if ($isTeacher): ?>
  <div style="display:flex;justify-content:flex-end;margin-bottom:1rem">
    <button class="btn btn-primary" onclick="openModal('create-assignment-modal')"><i class="fas fa-plus"></i> Create Assignment</button>
  </div>
  <?php endif; ?>

  <?php if (empty($assignments)): ?>
  <div class="empty-state"><div class="empty-icon"><i class="fas fa-tasks"></i></div><div class="empty-title">No assignments yet</div><div class="empty-sub"><?= $isTeacher ? 'Create your first assignment' : 'No assignments have been posted' ?></div></div>
  <?php else: ?>
  <?php foreach ($assignments as $a): ?>
  <?php
    $isSubmitted = false;
    if (!$isTeacher) {
      $s = $pdo->prepare("SELECT * FROM submissions WHERE assignment_id=? AND student_id=?");
      $s->execute([$a['id'], $user['id']]);
      $mySubmission = $s->fetch();
      $isSubmitted = (bool)$mySubmission;
    }
  ?>
  <div class="card" style="margin-bottom:1rem;cursor:pointer" onclick="openModal('assignment-<?= $a['id'] ?>-modal')">
    <div style="display:flex;align-items:center;gap:1rem">
      <div class="assignment-icon" style="background:rgba(99,102,241,0.15);color:var(--primary)"><i class="fas fa-file-alt"></i></div>
      <div style="flex:1">
        <div style="font-size:1rem;font-weight:700"><?= e($a['title']) ?></div>
        <div style="display:flex;align-items:center;gap:0.75rem;margin-top:0.25rem;font-size:0.8rem;color:var(--text-muted)">
          <?php if ($a['due_date']): ?>
          <span><i class="fas fa-calendar"></i> Due <?= date('M d, Y g:ia', strtotime($a['due_date'])) ?></span>
          <?php endif; ?>
          <span><i class="fas fa-star"></i> <?= $a['points'] ?> pts</span>
          <?php if ($isTeacher): ?><span><i class="fas fa-inbox"></i> <?= $a['sub_count'] ?> submitted</span><?php endif; ?>
        </div>
      </div>
      <?php if (!$isTeacher): ?>
      <div>
        <?php if ($isSubmitted): ?>
        <span class="badge badge-success"><i class="fas fa-check"></i> Submitted</span>
        <?php elseif ($a['due_date'] && strtotime($a['due_date']) < time()): ?>
        <span class="badge badge-danger">Missing</span>
        <?php else: ?>
        <span class="badge badge-warning">Pending</span>
        <?php endif; ?>
      </div>
      <?php endif; ?>
      <i class="fas fa-chevron-right text-muted"></i>
    </div>
  </div>

  <!-- Assignment Modal -->
  <div class="modal-overlay" id="assignment-<?= $a['id'] ?>-modal">
    <div class="modal" style="max-width:620px">
      <div class="modal-header">
        <div class="modal-title"><i class="fas fa-file-alt" style="color:var(--primary)"></i> <?= e($a['title']) ?></div>
        <button class="modal-close">✕</button>
      </div>
      <div class="modal-body">
        <div style="display:flex;gap:1rem;margin-bottom:1rem">
          <?php if ($a['due_date']): ?><span class="badge badge-warning"><i class="fas fa-calendar"></i> Due <?= date('M d, Y', strtotime($a['due_date'])) ?></span><?php endif; ?>
          <span class="badge badge-primary"><i class="fas fa-star"></i> <?= $a['points'] ?> points</span>
        </div>
        <?php if ($a['description']): ?>
        <p style="color:var(--text-secondary);font-size:0.9rem;margin-bottom:1rem;white-space:pre-wrap"><?= e($a['description']) ?></p>
        <?php endif; ?>

        <?php if (!$isTeacher && !$isSubmitted): ?>
        <div style="border-top:1px solid var(--border);padding-top:1rem;margin-top:1rem">
          <h4 style="font-size:0.875rem;font-weight:700;margin-bottom:0.75rem"><i class="fas fa-upload" style="color:var(--success)"></i> Your Submission</h4>
          <form method="POST" action="<?= BASE_URL ?>/api/assignments.php" enctype="multipart/form-data">
            <input type="hidden" name="action" value="submit">
            <input type="hidden" name="assignment_id" value="<?= $a['id'] ?>">
            <div class="form-group" style="margin-bottom:0.875rem">
              <label class="form-label">Text Response</label>
              <textarea name="text_content" class="form-control" rows="4" placeholder="Write your answer..."></textarea>
            </div>
            <div class="form-group">
              <label class="form-label">Attach File (optional)</label>
              <input type="file" name="file" class="form-control">
            </div>
            <button type="submit" class="btn btn-success btn-full" style="margin-top:1rem"><i class="fas fa-paper-plane"></i> Submit Assignment</button>
          </form>
        </div>
        <?php elseif (!$isTeacher && $isSubmitted): ?>
        <div style="border-top:1px solid var(--border);padding-top:1rem;margin-top:1rem">
          <div style="background:rgba(16,185,129,0.1);border:1px solid rgba(16,185,129,0.3);border-radius:var(--radius-sm);padding:0.875rem">
            <div style="display:flex;align-items:center;gap:0.5rem;font-weight:600;color:var(--success);margin-bottom:0.5rem"><i class="fas fa-check-circle"></i> Submitted <?= timeAgo($mySubmission['submitted_at']) ?></div>
            <?php if ($mySubmission['file_path']): ?>
            <?php
              $sExt = strtolower(pathinfo($mySubmission['file_path'], PATHINFO_EXTENSION));
              $sIsImg = in_array($sExt, ['jpg','jpeg','png','gif','webp']);
              $sIsVid = in_array($sExt, ['mp4','webm','ogg']);
              $sIsPdf = $sExt === 'pdf';
              $sPreviewType = $sIsImg ? 'image' : ($sIsVid ? 'video' : ($sIsPdf ? 'pdf' : ''));
            ?>
            <div style="display:flex;align-items:center;gap:0.75rem;margin-bottom:0.5rem;padding:0.5rem;background:var(--bg-overlay);border-radius:var(--radius-sm)">
              <i class="fas fa-paperclip" style="color:var(--info)"></i>
              <span style="font-size:0.85rem;flex:1"><?= e(basename($mySubmission['file_path'])) ?></span>
              <?php if ($sPreviewType): ?>
              <button class="btn btn-ghost btn-sm" style="color:var(--info)" onclick="openMaterialPreview(0, 'My Submission', '<?= e($mySubmission['file_path']) ?>', '<?= $sPreviewType ?>')"><i class="fas fa-eye"></i> Preview</button>
              <?php endif; ?>
              <a href="<?= BASE_URL ?>/uploads/<?= e($mySubmission['file_path']) ?>" download class="btn btn-ghost btn-sm" style="color:var(--primary)"><i class="fas fa-download"></i></a>
            </div>
            <?php endif; ?>
            <?php if ($mySubmission['grade'] !== null): ?>
            <div style="font-size:1.2rem;font-weight:800;color:var(--success)">Grade: <?= $mySubmission['grade'] ?>/<?= $a['points'] ?></div>
            <?php if ($mySubmission['feedback']): ?>
            <div style="margin-top:0.5rem;font-size:0.875rem;color:var(--text-secondary)"><strong>Feedback:</strong> <?= e($mySubmission['feedback']) ?></div>
            <?php endif; ?>
            <?php else: ?>
            <div style="color:var(--text-muted);font-size:0.875rem">Awaiting grading...</div>
            <?php endif; ?>
          </div>
        </div>
        <?php elseif ($isTeacher): ?>
        <div style="border-top:1px solid var(--border);padding-top:1rem;margin-top:1rem">
          <a href="<?= BASE_URL ?>/classroom/grades.php?class_id=<?= $classId ?>&assign=<?= $a['id'] ?>" class="btn btn-primary btn-full"><i class="fas fa-list"></i> View All Submissions (<?= $a['sub_count'] ?>)</a>
        </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
  <?php endforeach; ?>
  <?php endif; ?>
</div>

<?php elseif ($activeTab === 'people'): ?>
<!-- ── PEOPLE ──────────────────────────────── -->
<div style="max-width:760px;margin:0 auto;display:flex;flex-direction:column;gap:1.5rem">
  <!-- Teacher -->
  <div class="card">
    <div class="card-title" style="margin-bottom:1rem"><i class="fas fa-chalkboard-teacher" style="color:var(--primary)"></i> Teacher</div>
    <div style="display:flex;align-items:center;gap:1rem">
      <div class="avatar avatar-lg" style="background:linear-gradient(135deg,var(--primary),var(--purple))"><?= strtoupper($teacherInfo['name'][0]) ?></div>
      <div>
        <div style="font-weight:700;font-size:1rem"><?= e($teacherInfo['name']) ?></div>
        <div style="font-size:0.8rem;color:var(--text-muted)"><?= e($teacherInfo['email']) ?></div>
      </div>
    </div>
  </div>
  <!-- Students -->
  <div class="card">
    <div class="card-header">
      <div class="card-title"><i class="fas fa-users" style="color:var(--success)"></i> Students</div>
      <span class="badge badge-success"><?= count($people) ?></span>
    </div>
    <div style="display:flex;flex-direction:column;gap:0.5rem">
      <?php foreach ($people as $p): ?>
      <div style="display:flex;align-items:center;gap:0.875rem;padding:0.625rem;border-radius:var(--radius-sm);transition:background 0.2s" onmouseover="this.style.background='var(--bg-hover)'" onmouseout="this.style.background=''">
        <div class="avatar"><?= strtoupper($p['name'][0]) ?></div>
        <div style="flex:1">
          <div style="font-weight:600;font-size:0.875rem"><?= e($p['name']) ?></div>
          <div style="font-size:0.75rem;color:var(--text-muted)"><?= e($p['email']) ?></div>
        </div>
        <?php if ($isTeacher): ?>
        <a href="<?= BASE_URL ?>/global/messages.php?to=<?= $p['id'] ?>" class="btn btn-ghost btn-sm"><i class="fas fa-comment"></i></a>
        <?php endif; ?>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<?php elseif ($activeTab === 'grades'): ?>
<!-- ── GRADES ──────────────────────────────── -->
<div style="max-width:860px;margin:0 auto">
  <div class="card">
    <div class="card-header">
      <div class="card-title"><i class="fas fa-star" style="color:var(--warning)"></i> <?= $isTeacher ? 'All Student Grades' : 'My Grades' ?></div>
      <?php if ($isTeacher): ?>
      <a href="<?= BASE_URL ?>/global/export.php?class_id=<?= $classId ?>" class="btn btn-ghost btn-sm"><i class="fas fa-download"></i> Export CSV</a>
      <?php endif; ?>
    </div>
    <?php if (empty($gradeRows)): ?>
    <div class="empty-state"><div class="empty-icon"><i class="fas fa-star"></i></div><div class="empty-title">No grades available</div></div>
    <?php else: ?>
    <div class="table-wrap">
      <table>
        <?php if ($isTeacher): ?>
        <thead><tr><th>#</th><th>Student</th><th>Avg Grade</th><th>Graded</th><th>Performance</th><th>Action</th></tr></thead>
        <tbody>
        <?php foreach ($gradeRows as $i => $row): ?>
        <tr>
          <td style="font-weight:700;color:var(--text-muted)"><?= $i+1 ?></td>
          <td>
            <div style="display:flex;align-items:center;gap:0.5rem">
              <div class="avatar" style="width:30px;height:30px;font-size:0.75rem"><?= strtoupper($row['student_name'][0]) ?></div>
              <span style="font-weight:500"><?= e($row['student_name']) ?></span>
            </div>
          </td>
          <td style="font-weight:800;font-size:1.1rem;color:var(--<?= $row['avg'] >= 70 ? 'success' : ($row['avg'] >= 50 ? 'warning' : 'danger') ?>)"><?= round($row['avg'],1) ?>%</td>
          <td><?= $row['graded'] ?> assignments</td>
          <td style="width:150px">
            <div class="progress-bar" style="height:6px"><div class="progress-fill <?= $row['avg'] >= 70 ? 'success' : ($row['avg'] >= 50 ? 'warning' : 'danger') ?>" style="width:<?= round($row['avg']) ?>%"></div></div>
          </td>
          <td><a href="<?= BASE_URL ?>/analytics/performance.php?student_id=<?= $row['student_id'] ?>&class_id=<?= $classId ?>" class="btn btn-ghost btn-sm"><i class="fas fa-chart-line"></i></a></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
        <?php else: ?>
        <thead><tr><th>Assignment</th><th>Max Points</th><th>Grade</th><th>Status</th><th>Feedback</th><th>Submitted</th></tr></thead>
        <tbody>
        <?php foreach ($gradeRows as $row): ?>
        <tr>
          <td style="font-weight:600"><?= e($row['title']) ?></td>
          <td><?= $row['points'] ?></td>
          <td style="font-weight:800;color:var(--<?= ($row['grade'] ?? 0) >= 70 ? 'success' : (($row['grade'] ?? 0) >= 50 ? 'warning' : 'danger') ?>)"><?= $row['grade'] !== null ? $row['grade'].'%' : '—' ?></td>
          <td><span class="badge badge-<?= ['submitted'=>'info','graded'=>'success','late'=>'warning','missing'=>'danger'][$row['status']] ?>"><?= $row['status'] ?></span></td>
          <td style="font-size:0.8rem;color:var(--text-muted)"><?= $row['feedback'] ? e(mb_strimwidth($row['feedback'],0,40,'…')) : '—' ?></td>
          <td style="font-size:0.8rem;color:var(--text-muted)"><?= $row['submitted_at'] ? timeAgo($row['submitted_at']) : '—' ?></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
        <?php endif; ?>
      </table>
    </div>
    <?php endif; ?>
  </div>
</div>

<?php elseif ($activeTab === 'materials'): ?>
<!-- ── MATERIALS ──────────────────────────── -->
<div style="max-width:760px;margin:0 auto">
  <?php if ($isTeacher): ?>
  <div style="display:flex;justify-content:flex-end;gap:0.75rem;margin-bottom:1rem">
    <button class="btn btn-secondary" onclick="openModal('import-material-modal');loadImportCourses()"><i class="fas fa-file-import"></i> Import from Past Course</button>
    <button class="btn btn-primary" onclick="openModal('add-material-modal')"><i class="fas fa-plus"></i> Add Material</button>
  </div>
  <?php endif; ?>
  <?php if (empty($materials)): ?>
  <div class="empty-state"><div class="empty-icon"><i class="fas fa-folder-open"></i></div><div class="empty-title">No materials yet</div></div>
  <?php else: ?>
  <div style="display:flex;flex-direction:column;gap:0.75rem">
  <?php foreach ($materials as $m): ?>
  <?php $typeIcons = ['file'=>'fa-file-alt','link'=>'fa-link','video'=>'fa-video']; $typeColors = ['file'=>'var(--primary)','link'=>'var(--info)','video'=>'var(--danger)']; ?>
  <div class="card" style="padding:1.25rem">
    <div style="display:flex;align-items:flex-start;gap:1rem">
      <div class="assignment-icon" style="background:rgba(99,102,241,0.1);color:<?= $typeColors[$m['type']] ?? 'var(--primary)' ?>;flex-shrink:0;margin-top:0.1rem;width:40px;height:40px;display:flex;align-items:center;justify-content:center;border-radius:var(--radius-sm)">
        <i class="fas <?= $typeIcons[$m['type']] ?? 'fa-file' ?>" style="font-size:1.1rem"></i>
      </div>
      <div style="flex:1">
        <div style="font-weight:700;font-size:1rem;color:var(--text-primary)"><?= e($m['title']) ?></div>
        <?php if ($m['description']): ?><div style="font-size:0.875rem;color:var(--text-secondary);margin-top:0.4rem;white-space:pre-wrap"><?= e($m['description']) ?></div><?php endif; ?>
        
        <?php 
          $ext = $m['file_path'] ? strtolower(pathinfo($m['file_path'], PATHINFO_EXTENSION)) : '';
          $isImg = in_array($ext, ['jpg','jpeg','png','gif','webp']);
          $isVid = in_array($ext, ['mp4','webm','ogg']);
          $isPreviewable = $isImg || $isVid || $ext === 'pdf';
        ?>
        <?php if ($m['file_path'] && $isPreviewable): ?>
          <div style="margin-top:0.75rem">
            <button class="btn btn-ghost btn-sm" style="color:var(--info);gap:0.35rem" onclick="openMaterialPreview(<?= $m['id'] ?>, '<?= e($m['title']) ?>', '<?= e($m['file_path']) ?>', '<?= $isImg ? 'image' : ($isVid ? 'video' : 'pdf') ?>')">
              <i class="fas fa-eye"></i> Preview
            </button>
          </div>
        <?php endif; ?>

        <div style="font-size:0.75rem;color:var(--text-muted);margin-top:1rem;display:flex;align-items:center;gap:1.25rem">
          <span style="display:flex;align-items:center;gap:0.4rem"><i class="far fa-clock"></i> <?= timeAgo($m['created_at']) ?></span>
          <?php if ($m['file_path']): ?>
            <span style="display:flex;align-items:center;gap:0.4rem"><i class="fas fa-paperclip"></i> <?= strtoupper($ext) ?> File</span>
          <?php endif; ?>
        </div>
      </div>
      
      <div style="display:flex;flex-direction:column;gap:0.5rem;flex-shrink:0">
        <?php if ($m['link_url']): ?>
        <a href="<?= e($m['link_url']) ?>" target="_blank" class="btn btn-info btn-sm"><i class="fas fa-external-link-alt"></i> Open Link</a>
        <?php elseif ($m['file_path']): ?>
          <?php if (!$isPreviewable): ?>
          <a href="<?= BASE_URL ?>/uploads/<?= e($m['file_path']) ?>" target="_blank" class="btn btn-secondary btn-sm"><i class="fas fa-eye"></i> View File</a>
          <?php endif; ?>
        <a href="<?= BASE_URL ?>/uploads/<?= e($m['file_path']) ?>" download class="btn btn-primary btn-sm"><i class="fas fa-download"></i> Download</a>
        <?php endif; ?>
      </div>
    </div>
  </div>
  <?php endforeach; ?>
  </div>
  <?php endif; ?>
</div>

<?php endif; ?>

</div><!-- page-content -->
</div><!-- main-content -->
</div><!-- app-wrapper -->

<!-- Create Assignment Modal -->
<div class="modal-overlay" id="create-assignment-modal">
  <div class="modal" style="max-width:580px">
    <div class="modal-header">
      <div class="modal-title"><i class="fas fa-file-alt" style="color:var(--primary)"></i> Create Assignment</div>
      <button class="modal-close">✕</button>
    </div>
    <form method="POST" action="<?= BASE_URL ?>/api/assignments.php" enctype="multipart/form-data">
      <input type="hidden" name="action" value="create">
      <input type="hidden" name="class_id" value="<?= $classId ?>">
      <div class="modal-body" style="display:flex;flex-direction:column;gap:1rem">
        <div class="form-group"><label class="form-label">Title *</label><input type="text" name="title" class="form-control" placeholder="Assignment title" required></div>
        <div class="form-group"><label class="form-label">Instructions</label><textarea name="description" class="form-control" rows="4" placeholder="Describe the assignment..." data-maxlength="1500"></textarea></div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem">
          <div class="form-group"><label class="form-label">Due Date & Time</label><input type="datetime-local" name="due_date" class="form-control"></div>
          <div class="form-group"><label class="form-label">Total Points</label><input type="number" name="points" class="form-control" value="100" min="1" max="1000"></div>
        </div>
        <div class="form-group"><label class="form-label">Attach File (optional)</label><input type="file" name="attachment" class="form-control"></div>
        <div style="display:flex;align-items:center;gap:0.5rem"><input type="checkbox" name="allow_offline" id="allow_offline" value="1" checked><label for="allow_offline" style="font-size:0.875rem;cursor:pointer">Allow offline submission (QR code token)</label></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary modal-close">Cancel</button>
        <button type="submit" class="btn btn-primary"><i class="fas fa-paper-plane"></i> Post Assignment</button>
      </div>
    </form>
  </div>
</div>

<!-- Add Material Modal -->
<div class="modal-overlay" id="add-material-modal">
  <div class="modal" style="max-width:500px">
    <div class="modal-header">
      <div class="modal-title"><i class="fas fa-folder-plus" style="color:var(--warning)"></i> Add Material</div>
      <button class="modal-close">✕</button>
    </div>
    <form method="POST" action="<?= BASE_URL ?>/api/classes.php" enctype="multipart/form-data">
      <input type="hidden" name="action" value="add_material">
      <input type="hidden" name="class_id" value="<?= $classId ?>">
      <div class="modal-body material-preview-layout" id="material-layout">
        <div style="display:flex;flex-direction:column;gap:1rem">
          <div class="form-group"><label class="form-label">Title *</label><input type="text" name="title" class="form-control" required></div>
          <div class="form-group"><label class="form-label">Description</label><textarea name="description" class="form-control" rows="3"></textarea></div>
          <div class="form-group">
            <label class="form-label">Type</label>
            <select name="type" class="form-control" onchange="
              const isFile = this.value==='file';
              document.getElementById('mat-file-area').classList.toggle('hidden',!isFile);
              document.getElementById('mat-link-area').classList.toggle('hidden',isFile);
              document.getElementById('material-layout').classList.toggle('has-preview', isFile);
            ">
              <option value="file">File Upload</option>
              <option value="link">External Link</option>
              <option value="video">Video Link</option>
            </select>
          </div>
          <div id="mat-file-area" class="form-group">
            <label class="form-label">File to Upload</label>
            <input type="file" name="file" class="form-control" onchange="previewFile(this, document.getElementById('mat-preview-box'))">
          </div>
          <div id="mat-link-area" class="form-group hidden"><label class="form-label">URL</label><input type="url" name="link_url" class="form-control" placeholder="https://..."></div>
          <!-- Send to All Classrooms Option -->
          <div class="form-group" style="padding:0.75rem;background:rgba(99,102,241,0.08);border-radius:var(--radius-sm);margin-top:0.5rem">
            <label style="display:flex;align-items:center;gap:0.5rem;cursor:pointer;font-size:0.85rem;color:var(--primary);font-weight:500">
              <input type="checkbox" name="send_to_all" value="1" style="cursor:pointer">
              <i class="fas fa-broadcast-tower"></i> Send to All My Classrooms
            </label>
            <div style="font-size:0.75rem;color:var(--text-muted);margin-top:0.25rem;margin-left:1.5rem">Add this material to all classes you teach</div>
          </div>
        </div>
        
        <!-- Live Preview Panel on the Right -->
        <div class="material-preview-panel">
            <div id="mat-preview-box" class="preview-content">
                <i class="fas fa-cloud-upload-alt preview-icon"></i>
            </div>
            <div class="preview-info">
                <div style="font-weight:600;font-size:0.9rem">Live Preview</div>
                <div style="font-size:0.75rem;color:var(--text-muted);margin-top:0.25rem">Preview of your file before upload</div>
            </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary modal-close">Cancel</button>
        <button type="submit" class="btn btn-primary"><i class="fas fa-plus"></i> Add Material</button>
      </div>
    </form>
  </div>
</div>

<!-- Import Materials Modal (Feature 3: Bulk Material Transfer) -->
<div class="modal-overlay" id="import-material-modal">
  <div class="modal" style="max-width:700px">
    <div class="modal-header">
      <div class="modal-title"><i class="fas fa-file-import" style="color:var(--warning)"></i> Import Materials from Past Course</div>
      <button class="modal-close">✕</button>
    </div>
    <div class="modal-body" style="max-height:60vh;overflow-y:auto">
      <p style="color:var(--text-secondary);font-size:0.875rem;margin-bottom:1rem">Select materials from your previous courses to copy into this classroom. Files are duplicated (not moved).</p>
      <!-- Search/Filter -->
      <div style="margin-bottom:1rem">
        <input type="text" id="import-search" class="form-control" placeholder="Search by title or file type..." oninput="filterImportMaterials()">
      </div>
      <!-- Course List -->
      <div id="import-courses-list" style="display:flex;flex-direction:column;gap:0.75rem">
        <div style="text-align:center;padding:2rem;color:var(--text-muted)"><i class="fas fa-spinner spin"></i> Loading your courses...</div>
      </div>
    </div>
    <div class="modal-footer" style="justify-content:space-between">
      <div style="font-size:0.8rem;color:var(--text-muted)"><span id="import-selected-count">0</span> materials selected</div>
      <div style="display:flex;gap:0.75rem">
        <button class="btn btn-secondary modal-close">Cancel</button>
        <button class="btn btn-primary" id="btn-transfer-materials" onclick="transferMaterials()"><i class="fas fa-file-import"></i> Send to Current Classroom</button>
      </div>
    </div>
  </div>
</div>

<!-- Material Preview Modal (Feature 1: Preview-On-Demand) -->
<div class="modal-overlay" id="material-preview-modal">
  <div class="modal" style="max-width:860px">
    <div class="modal-header">
      <div class="modal-title" id="preview-modal-title"><i class="fas fa-eye" style="color:var(--info)"></i> Preview</div>
      <button class="modal-close">✕</button>
    </div>
    <div class="modal-body" style="padding:0;overflow:hidden;min-height:300px;display:flex;align-items:center;justify-content:center;background:var(--bg-overlay)" id="preview-modal-content">
    </div>
    <div class="modal-footer" style="justify-content:flex-end;gap:0.75rem">
      <a id="preview-download-btn" href="#" download class="btn btn-primary btn-sm"><i class="fas fa-download"></i> Download</a>
      <button class="btn btn-secondary btn-sm modal-close">Close</button>
    </div>
  </div>
</div>

<?php renderFooter(); ?>
<script>
function toggleMultiClass(cb) {
  const panel = document.getElementById('multi-class-panel');
  panel.style.display = cb.checked ? 'block' : 'none';
  if (!cb.checked) {
    // When collapsed, only the current class should be posted to (handled server-side by default)
    document.querySelectorAll('.multi-class-cb').forEach(c => c.checked = (c.value === '<?= $classId ?>'));
  }
}
function selectAllClasses(val) {
  document.querySelectorAll('.multi-class-cb').forEach(c => c.checked = val);
}

function openMaterialPreview(matId, title, filePath, fileType) {
  const contentEl = document.getElementById('preview-modal-content');
  const titleEl = document.getElementById('preview-modal-title');
  const dlBtn = document.getElementById('preview-download-btn');
  const baseUrl = '<?= BASE_URL ?>/uploads/';

  titleEl.innerHTML = '<i class="fas fa-eye" style="color:var(--info)"></i> ' + title;
  dlBtn.href = baseUrl + filePath;

  if (fileType === 'image') {
    contentEl.innerHTML = '<img src="' + baseUrl + filePath + '" alt="' + title + '" style="max-width:100%;max-height:70vh;display:block;object-fit:contain">';
  } else if (fileType === 'video') {
    contentEl.innerHTML = '<video src="' + baseUrl + filePath + '" controls style="max-width:100%;max-height:70vh;display:block"></video>';
  } else if (fileType === 'pdf') {
    contentEl.innerHTML = '<iframe src="' + baseUrl + filePath + '" style="width:100%;height:70vh;border:none"></iframe>';
  } else {
    contentEl.innerHTML = '<div style="padding:2rem;text-align:center;color:var(--text-muted)"><i class="fas fa-file-alt" style="font-size:3rem;margin-bottom:1rem;display:block"></i>Preview not available for this file type.</div>';
  }
  openModal('material-preview-modal');
}

// Feature 3: Material Transfer / Import
let importMaterialsData = {};
async function loadImportCourses() {
  const container = document.getElementById('import-courses-list');
  container.innerHTML = '<div style="text-align:center;padding:2rem;color:var(--text-muted)"><i class="fas fa-spinner spin"></i> Loading your courses...</div>';
  const res = await SCS.apiRequest('<?= BASE_URL ?>/api/material_transfer.php?action=get_courses&current_class_id=<?= $classId ?>');
  if (!res.success || !res.courses.length) {
    container.innerHTML = '<div style="text-align:center;padding:2rem;color:var(--text-muted)"><i class="fas fa-folder-open" style="font-size:2rem;display:block;margin-bottom:0.5rem"></i>No past courses with materials found.</div>';
    return;
  }
  importMaterialsData = {};
  let html = '';
  for (const course of res.courses) {
    html += `
    <div class="card" style="padding:1rem;border:1px solid var(--border)">
      <div style="display:flex;align-items:center;justify-content:space-between;cursor:pointer" onclick="toggleCourseMaterials(${course.id}, this)">
        <div>
          <div style="font-weight:700;font-size:0.95rem">${course.name}</div>
          <div style="font-size:0.8rem;color:var(--text-muted)">${course.section || ''} ${course.subject ? '· ' + course.subject : ''} · ${course.material_count} materials</div>
        </div>
        <i class="fas fa-chevron-down" style="color:var(--text-muted);transition:transform 0.2s"></i>
      </div>
      <div id="course-mats-${course.id}" style="display:none;margin-top:0.75rem;padding-top:0.75rem;border-top:1px solid var(--border)">
        <div style="text-align:center;padding:1rem;color:var(--text-muted);font-size:0.8rem"><i class="fas fa-spinner spin"></i> Loading materials...</div>
      </div>
    </div>`;
  }
  container.innerHTML = html;
}

async function toggleCourseMaterials(courseId, headerEl) {
  const matContainer = document.getElementById(`course-mats-${courseId}`);
  const arrow = headerEl.querySelector('.fa-chevron-down, .fa-chevron-up');
  if (matContainer.style.display === 'none') {
    matContainer.style.display = 'block';
    if (arrow) arrow.style.transform = 'rotate(180deg)';
    if (!importMaterialsData[courseId]) {
      const res = await SCS.apiRequest(`<?= BASE_URL ?>/api/material_transfer.php?action=get_materials&source_class_id=${courseId}`);
      if (res.success) {
        importMaterialsData[courseId] = res.materials;
        renderCourseMaterials(courseId, res.materials);
      }
    }
  } else {
    matContainer.style.display = 'none';
    if (arrow) arrow.style.transform = 'rotate(0deg)';
  }
}

function renderCourseMaterials(courseId, materials) {
  const container = document.getElementById(`course-mats-${courseId}`);
  if (!materials.length) {
    container.innerHTML = '<div style="font-size:0.8rem;color:var(--text-muted);padding:0.5rem">No materials in this course.</div>';
    return;
  }
  const typeIcons = {file:'fa-file-alt',link:'fa-link',video:'fa-video'};
  let html = `<div style="margin-bottom:0.5rem"><label style="display:flex;align-items:center;gap:0.5rem;font-size:0.8rem;font-weight:600;cursor:pointer"><input type="checkbox" onchange="toggleAllCourseMats(${courseId}, this.checked)"> Select All</label></div>`;
  for (const m of materials) {
    html += `
    <label class="import-mat-item" data-title="${m.title.toLowerCase()}" data-type="${m.type}" style="display:flex;align-items:center;gap:0.75rem;padding:0.5rem;border-radius:var(--radius-sm);cursor:pointer;transition:background 0.15s" onmouseover="this.style.background='var(--bg-hover)'" onmouseout="this.style.background=''">
      <input type="checkbox" class="import-mat-check" data-course="${courseId}" data-mat-id="${m.id}" onchange="updateImportCount()">
      <i class="fas ${typeIcons[m.type] || 'fa-file'}" style="color:var(--info);width:16px;text-align:center"></i>
      <div style="flex:1">
        <div style="font-size:0.85rem;font-weight:500">${m.title}</div>
        <div style="font-size:0.7rem;color:var(--text-muted)">${m.type}${m.file_path ? ' · ' + m.file_path.split('.').pop().toUpperCase() : ''}</div>
      </div>
    </label>`;
  }
  container.innerHTML = html;
}

function toggleAllCourseMats(courseId, checked) {
  document.querySelectorAll(`.import-mat-check[data-course="${courseId}"]`).forEach(cb => cb.checked = checked);
  updateImportCount();
}

function updateImportCount() {
  const count = document.querySelectorAll('.import-mat-check:checked').length;
  document.getElementById('import-selected-count').textContent = count;
}

function filterImportMaterials() {
  const query = document.getElementById('import-search').value.toLowerCase();
  document.querySelectorAll('.import-mat-item').forEach(item => {
    const title = item.dataset.title || '';
    const type = item.dataset.type || '';
    const match = title.includes(query) || type.includes(query);
    item.style.display = match ? '' : 'none';
  });
}

async function transferMaterials() {
  const checked = document.querySelectorAll('.import-mat-check:checked');
  if (!checked.length) { SCS.showToast('Select at least one material', 'error'); return; }

  const btn = document.getElementById('btn-transfer-materials');
  SCS.setLoading(btn, true);
  const materialIds = [...checked].map(cb => cb.dataset.matId);
  const fd = new FormData();
  fd.append('action', 'transfer');
  fd.append('target_class_id', '<?= $classId ?>');
  for (const id of materialIds) fd.append('material_ids[]', id);

  const res = await SCS.apiRequest('<?= BASE_URL ?>/api/material_transfer.php', 'POST', fd);
  SCS.setLoading(btn, false);

  if (res.success) {
    SCS.showToast(`${res.copied} material(s) imported successfully!`, 'success');
    closeModal('import-material-modal');
    setTimeout(() => location.reload(), 1000);
  } else {
    SCS.showToast(res.error || 'Transfer failed', 'error');
  }
}

// Feature 4: Archive & Personal Storage
async function archiveClass() {
  SCS.confirmAction('Archive this classroom? It will enter a 2-day grace period before permanent deletion. Students will be notified to save their materials.', async () => {
    const fd = new FormData();
    fd.append('action', 'archive_class');
    fd.append('class_id', '<?= $classId ?>');
    const res = await SCS.apiRequest('<?= BASE_URL ?>/api/archive.php', 'POST', fd);
    if (res.success) {
      SCS.showToast('Classroom archived. Students have been notified.', 'warning');
      setTimeout(() => location.reload(), 1000);
    } else {
      SCS.showToast(res.error || 'Archive failed', 'error');
    }
  });
}

async function restoreClass() {
  const fd = new FormData();
  fd.append('action', 'restore_class');
  fd.append('class_id', '<?= $classId ?>');
  const res = await SCS.apiRequest('<?= BASE_URL ?>/api/archive.php', 'POST', fd);
  if (res.success) {
    SCS.showToast('Classroom restored!', 'success');
    setTimeout(() => location.reload(), 1000);
  } else {
    SCS.showToast(res.error || 'Restore failed', 'error');
  }
}

async function saveToMyArchive() {
  SCS.confirmAction('Save all materials from this classroom to your personal archive? Files will be copied (not moved).', async () => {
    const fd = new FormData();
    fd.append('action', 'save_to_archive');
    fd.append('class_id', '<?= $classId ?>');
    const res = await SCS.apiRequest('<?= BASE_URL ?>/api/archive.php', 'POST', fd);
    if (res.success) {
      SCS.showToast(`${res.copied} material(s) saved to your personal archive!`, 'success');
    } else {
      SCS.showToast(res.error || 'Save failed', 'error');
    }
  });
}
</script>
</body></html>
