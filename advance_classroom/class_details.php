<?php
/**
 * Class Details (Hub) — Phase 2
 */
$pageTitle = 'Classroom';
require_once __DIR__ . '/includes/header.php';
requireLogin();

$classId = $_GET['id'] ?? null;
if (!$classId) redirect(BASE_URL . '/dashboard.php');

// Make sure user has access
requireClassAccess($pdo, $classId);
$role = getUserRole();

// Get class info
$stmt = $pdo->prepare("SELECT c.*, u.full_name as teacher_name FROM classes c JOIN users u ON c.teacher_id = u.id WHERE c.id = ?");
$stmt->execute([$classId]);
$class = $stmt->fetch();
if (!$class) redirect(BASE_URL . '/dashboard.php');

$pageTitle = $class['class_name'] . ' - Classroom';

// Get Announcements (Stream)
$stmt = $pdo->prepare("SELECT a.*, u.full_name as author_name FROM announcements a JOIN users u ON a.teacher_id = u.id WHERE a.class_id = ? ORDER BY a.created_at DESC LIMIT 20");
$stmt->execute([$classId]);
$announcements = $stmt->fetchAll();

// Get Assignments (Classwork)
$stmt = $pdo->prepare("
    SELECT a.*, 
    (SELECT COUNT(*) FROM submissions WHERE assignment_id = a.id) as sub_count
    FROM assignments a WHERE a.class_id = ? ORDER BY a.due_date DESC
");
$stmt->execute([$classId]);
$assignments = $stmt->fetchAll();

// If student, get their submission status for the assignments
$submissions = [];
if ($role === 'student') {
    $stmt = $pdo->prepare("SELECT * FROM submissions WHERE student_id = ? AND assignment_id IN (SELECT id FROM assignments WHERE class_id = ?)");
    $stmt->execute([$_SESSION['user_id'], $classId]);
    foreach ($stmt->fetchAll() as $s) $submissions[$s['assignment_id']] = $s;
}

// Get Materials
$stmt = $pdo->prepare("SELECT * FROM materials WHERE class_id = ? ORDER BY uploaded_at DESC");
$stmt->execute([$classId]);
$materials = $stmt->fetchAll();

require_once __DIR__ . '/includes/navbar.php';
?>

<!-- Flash Messages -->
<?php displayFlash(); ?>

<!-- Class Banner -->
<div style="background:linear-gradient(135deg, <?php echo e($class['color']); ?>, <?php echo e($class['color']); ?>dd); border-radius:var(--radius-lg); padding:40px; color:#fff; margin-bottom:24px; position:relative; overflow:hidden;">
    <div style="position:relative; z-index:2;">
        <h1 style="font-size:32px;font-weight:800;margin-bottom:8px;"><?php echo e($class['class_name']); ?></h1>
        <p style="font-size:18px;opacity:.9;"><?php echo e($class['subject']); ?><?php echo $class['section'] ? ' • ' . e($class['section']) : ''; ?></p>
        <p style="margin-top:16px;font-size:14px;opacity:.8;">Teacher: <?php echo e($class['teacher_name']); ?></p>
        <?php if ($role === 'teacher'): ?>
            <div style="margin-top:20px;display:inline-block;background:rgba(0,0,0,.2);padding:8px 16px;border-radius:var(--radius);font-weight:600;backdrop-filter:blur(5px);">
                Class Code: <span style="letter-spacing:2px;font-size:18px;margin-left:8px;"><?php echo e($class['class_code']); ?></span>
            </div>
            <a href="<?php echo BASE_URL; ?>/teacher/edit_class.php?id=<?php echo $classId; ?>" class="btn btn-outline" style="position:absolute; right:0; bottom:0; color:#fff; border-color:rgba(255,255,255,0.3);">⚙️ Settings</a>
        <?php endif; ?>
    </div>
    <!-- Decor -->
    <div style="position:absolute; right:-50px; top:-50px; width:200px; height:200px; background:rgba(255,255,255,.1); border-radius:50%;"></div>
    <div style="position:absolute; right:80px; bottom:-30px; width:100px; height:100px; background:rgba(255,255,255,.1); border-radius:50%;"></div>
</div>

<!-- Tabs Navigation -->
<div class="tabs-container">
    <div class="tabs">
        <button class="tab-btn active" data-tab="tab-stream">Stream</button>
        <button class="tab-btn" data-tab="tab-classwork">Classwork</button>
        <button class="tab-btn" data-tab="tab-materials">Materials</button>
        <button class="tab-btn" data-tab="tab-people">People</button>
        <?php if ($role === 'student' || $role === 'guardian'): ?>
            <button class="tab-btn" data-tab="tab-grades">My Grades</button>
        <?php endif; ?>
    </div>

    <!-- STREAM TAB -->
    <div class="tab-content active" id="tab-stream">
        <div style="display:grid; grid-template-columns: 240px 1fr; gap:24px;">
            <!-- Left col (Upcoming) -->
            <div class="hidden-mobile" style="display: <?php echo ($role === 'student' || $role === 'teacher') ? 'block' : 'none'; ?>">
                <div class="card mb-3">
                    <div class="card-body">
                        <h4 style="margin-bottom:12px;font-size:15px;">Upcoming</h4>
                        <p class="text-muted text-sm">No work due soon</p>
                        <a href="#" onclick="document.querySelector('[data-tab=\'tab-classwork\']').click()" class="text-sm" style="display:block;margin-top:12px;">View all</a>
                    </div>
                </div>
            </div>
            
            <!-- Right col (Announcements) -->
            <div>
                <?php if ($role === 'teacher'): ?>
                <div class="card mb-3 stream-item" style="cursor:pointer;" onclick="window.location='<?php echo BASE_URL; ?>/teacher/post_announcement.php?class_id=<?php echo $classId; ?>'">
                    <div class="d-flex align-center gap-2">
                        <span class="avatar-initials" style="background:#4F46E5;width:40px;height:40px;font-size:14px;"><?php echo getInitials($currentUser['full_name']); ?></span>
                        <div class="form-control" style="border:none;background:#F1F5F9;cursor:pointer;color:var(--text-muted);">Announce something to your class...</div>
                    </div>
                </div>
                <?php endif; ?>

                <?php if (empty($announcements)): ?>
                    <div class="empty-state card">
                        <div class="empty-icon">📢</div>
                        <h3>No announcements yet</h3>
                        <p>This is where the teacher posts updates and notices.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($announcements as $ann): ?>
                        <div class="stream-item">
                            <div class="stream-header">
                                <div class="stream-avatar" style="background:var(--primary);"><?php echo getInitials($ann['author_name']); ?></div>
                                <div class="stream-meta">
                                    <h4><?php echo e($ann['author_name']); ?></h4>
                                    <span><?php echo formatDate($ann['created_at']); ?></span>
                                </div>
                            </div>
                            <div class="stream-body" style="margin-bottom:16px;">
                                <strong style="display:block;margin-bottom:8px;font-size:16px;color:var(--text);"><?php echo e($ann['title']); ?></strong>
                                <?php echo nl2br(e($ann['content'])); ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- CLASSWORK TAB -->
    <div class="tab-content" id="tab-classwork">
        <?php if ($role === 'teacher'): ?>
            <div style="margin-bottom:20px;">
                <a href="<?php echo BASE_URL; ?>/teacher/create_assignment.php?class_id=<?php echo $classId; ?>" class="btn btn-primary">➕ Create Assignment</a>
            </div>
        <?php endif; ?>

        <?php if (empty($assignments)): ?>
            <div class="empty-state">
                <div class="empty-icon">📝</div>
                <h3>No classwork yet</h3>
                <p>Assignments will appear here once created.</p>
            </div>
        <?php else: ?>
            <div style="display:flex;flex-direction:column;gap:16px;">
            <?php foreach ($assignments as $task): ?>
                <?php 
                    $dueTime = strtotime($task['due_date']);
                    $isPast = $dueTime < time();
                    $taskStatus = 'warning';
                    $taskLabel = 'Pending';
                    if ($role === 'student') {
                        if (isset($submissions[$task['id']])) {
                            $taskStatus = 'success';
                            $taskLabel = 'Submitted';
                        } else if ($isPast) {
                            $taskStatus = 'danger';
                            $taskLabel = 'Missing';
                        }
                    }
                ?>
                <div class="card" style="border-left: 4px solid var(--primary);">
                    <div class="card-body d-flex justify-between align-center text-left" style="flex-wrap:wrap;gap:16px;">
                        <div class="d-flex align-center gap-2">
                            <div style="width:40px;height:40px;border-radius:50%;background:var(--primary-bg);color:var(--primary);display:flex;align-items:center;justify-content:center;font-size:20px;">📋</div>
                            <div>
                                <h4 style="margin-bottom:4px;"><a href="<?php echo BASE_URL; ?>/<?php echo $role==='teacher' ? 'teacher/view_submissions.php' : 'student/view_assignment.php'; ?>?id=<?php echo $task['id']; ?>"><?php echo e($task['title']); ?></a></h4>
                                <p class="text-sm text-muted">Due: <?php echo formatDate($task['due_date'], 'M d, h:i A'); ?></p>
                            </div>
                        </div>
                        <?php if ($role === 'student'): ?>
                            <span class="badge badge-<?php echo $taskStatus; ?>"><?php echo $taskLabel; ?></span>
                        <?php else: ?>
                            <span class="text-sm text-muted"><?php echo $task['sub_count']; ?> Submissions</span>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- MATERIALS TAB -->
    <div class="tab-content" id="tab-materials">
        <?php if ($role === 'teacher'): ?>
            <div style="margin-bottom:20px;">
                <a href="<?php echo BASE_URL; ?>/teacher/upload_material.php?class_id=<?php echo $classId; ?>" class="btn btn-primary">➕ Upload Material</a>
            </div>
        <?php endif; ?>

        <?php if (empty($materials)): ?>
            <div class="empty-state">
                <div class="empty-icon">📁</div>
                <h3>No materials uploaded</h3>
            </div>
        <?php else: ?>
            <div style="display:grid;grid-template-columns:repeat(auto-fill, minmax(300px, 1fr));gap:20px;">
                <?php foreach ($materials as $mat): ?>
                    <div class="card">
                        <div class="card-body">
                            <div class="d-flex align-center gap-2 mb-2">
                                <span style="font-size:24px;"><?php echo getFileIcon($mat['file_path']); ?></span>
                                <h4 style="font-size:15px;word-break:break-all;"><?php echo e($mat['title']); ?></h4>
                            </div>
                            <p class="text-sm text-muted mb-3" style="min-height:40px;"><?php echo e($mat['description']); ?></p>
                            <div class="d-flex justify-between align-center pt-2" style="border-top:1px solid var(--border);">
                                <span class="text-sm text-muted"><?php echo formatDate($mat['uploaded_at'], 'M d'); ?></span>
                                <a href="<?php echo BASE_URL; ?>/<?php echo $mat['file_path']; ?>" download target="_blank" class="btn btn-sm btn-outline">Download</a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- PEOPLE TAB -->
    <div class="tab-content" id="tab-people">
        <div style="max-width:800px;margin:0 auto;">
            <h2 style="color:var(--primary);border-bottom:1px solid var(--primary);padding-bottom:12px;margin-bottom:16px;">Teachers</h2>
            <div class="d-flex align-center gap-2 mb-3 px-3 py-2">
                <span class="avatar-initials" style="background:#0891B2;width:40px;height:40px;"><?php echo getInitials($class['teacher_name']); ?></span>
                <span style="font-size:16px;font-weight:500;"><?php echo e($class['teacher_name']); ?></span>
            </div>

            <br>
            <?php
                $stmt = $pdo->prepare("SELECT u.full_name, u.email FROM class_enrollments ce JOIN users u ON ce.student_id = u.id WHERE ce.class_id = ? ORDER BY u.full_name");
                $stmt->execute([$classId]);
                $students = $stmt->fetchAll();
            ?>
            <h2 style="color:var(--primary);border-bottom:1px solid var(--primary);padding-bottom:12px;margin-bottom:16px;display:flex;justify-content:space-between;align-items:center;">
                Classmates
                <span style="font-size:14px;color:var(--text-muted);font-weight:normal;"><?php echo count($students); ?> students</span>
            </h2>
            <?php if (empty($students)): ?>
                <p class="text-muted text-center py-3">No students have joined yet.</p>
            <?php else: ?>
                <?php foreach ($students as $stu): ?>
                    <div class="d-flex align-center gap-2 mb-2 px-3 py-2" style="border-bottom:1px solid var(--border);">
                        <span class="avatar-initials" style="background:var(--text-muted);width:36px;height:36px;font-size:13px;"><?php echo getInitials($stu['full_name']); ?></span>
                        <div style="display:flex;flex-direction:column;">
                            <span style="font-size:15px;font-weight:500;"><?php echo e($stu['full_name']); ?></span>
                            <?php if ($role === 'teacher'): ?>
                                <span style="font-size:12px;color:var(--text-muted);"><?php echo e($stu['email']); ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- GRADES TAB (STUDENT ONLY FOR NOW) -->
    <?php if ($role === 'student' || $role === 'guardian'): ?>
    <div class="tab-content" id="tab-grades">
        <div class="table-wrapper">
            <table class="table">
                <thead><tr><th>Assignment</th><th>Due Date</th><th>Status</th><th>Score</th></tr></thead>
                <tbody>
                <?php if(empty($assignments)): ?>
                    <tr><td colspan="4" class="text-center py-4 text-muted">No assignments found</td></tr>
                <?php else: ?>
                    <?php foreach ($assignments as $task): 
                        $sub = $submissions[$task['id']] ?? null;
                        $bg = '';
                        $statusHTML = '<span class="badge badge-warning">Pending</span>';
                        $score = '—';
                        if ($sub) {
                            if ($sub['status'] === 'graded') {
                                $statusHTML = '<span class="badge badge-success">Graded</span>';
                                $score = "<strong>{$sub['marks']}</strong> / {$task['max_marks']}";
                            } else {
                                $statusHTML = '<span class="badge badge-info">Submitted</span>';
                                $score = 'Waiting';
                            }
                        } else if (strtotime($task['due_date']) < time()) {
                            $statusHTML = '<span class="badge badge-danger">Missing</span>';
                            $score = '0 / ' . $task['max_marks'];
                            $bg = 'background:#FEF2F2;';
                        }
                    ?>
                    <tr style="<?php echo $bg; ?>">
                        <td><strong><?php echo e($task['title']); ?></strong></td>
                        <td><?php echo formatDate($task['due_date']); ?></td>
                        <td><?php echo $statusHTML; ?></td>
                        <td><?php echo $score; ?></td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

</div>

</main>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
