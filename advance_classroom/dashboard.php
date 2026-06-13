<?php
/**
 * Dashboard — Advanced Classroom System
 * Role-based dashboard showing class cards for teachers and students.
 */
$pageTitle = 'Dashboard';
require_once __DIR__ . '/includes/header.php';
requireLogin();

$role = getUserRole();
$userId = $_SESSION['user_id'];

// Redirect admin and guardian to their dashboards
if ($role === 'admin') redirect(BASE_URL . '/admin/dashboard.php');
if ($role === 'guardian') redirect(BASE_URL . '/guardian/dashboard.php');

// === TEACHER: Get classes they created ===
if ($role === 'teacher') {
    $stmt = $pdo->prepare("
        SELECT c.*, 
            (SELECT COUNT(*) FROM class_enrollments WHERE class_id = c.id) as student_count,
            (SELECT COUNT(*) FROM assignments WHERE class_id = c.id) as assignment_count,
            (SELECT COUNT(*) FROM announcements WHERE class_id = c.id) as announcement_count
        FROM classes c 
        WHERE c.teacher_id = ? AND c.status = 'active'
        ORDER BY c.created_at DESC
    ");
    $stmt->execute([$userId]);
    $classes = $stmt->fetchAll();
}

// === STUDENT: Get enrolled classes ===
if ($role === 'student') {
    $stmt = $pdo->prepare("
        SELECT c.*, u.full_name as teacher_name,
            (SELECT COUNT(*) FROM class_enrollments WHERE class_id = c.id) as student_count,
            (SELECT COUNT(*) FROM assignments WHERE class_id = c.id) as assignment_count,
            (SELECT COUNT(*) FROM submissions s 
             JOIN assignments a ON s.assignment_id = a.id 
             WHERE a.class_id = c.id AND s.student_id = ?) as submitted_count
        FROM class_enrollments ce
        JOIN classes c ON ce.class_id = c.id
        JOIN users u ON c.teacher_id = u.id
        WHERE ce.student_id = ? AND c.status = 'active'
        ORDER BY ce.joined_at DESC
    ");
    $stmt->execute([$userId, $userId]);
    $classes = $stmt->fetchAll();
    
    // Get upcoming deadlines
    $stmt = $pdo->prepare("
        SELECT a.*, c.class_name, c.color
        FROM assignments a
        JOIN classes c ON a.class_id = c.id
        JOIN class_enrollments ce ON ce.class_id = c.id
        LEFT JOIN submissions s ON s.assignment_id = a.id AND s.student_id = ?
        WHERE ce.student_id = ? AND a.due_date > NOW() AND s.id IS NULL
        ORDER BY a.due_date ASC LIMIT 5
    ");
    $stmt->execute([$userId, $userId]);
    $upcomingDeadlines = $stmt->fetchAll();
}

// Get recent notifications
$stmt = $pdo->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 5");
$stmt->execute([$userId]);
$recentNotifs = $stmt->fetchAll();

require_once __DIR__ . '/includes/navbar.php';
?>

<!-- Flash Messages -->
<?php displayFlash(); ?>

<!-- Page Header -->
<div class="page-header">
    <div>
        <h1><?php echo $role === 'teacher' ? '👨‍🏫 My Classes' : '🎒 My Classroom'; ?></h1>
        <p>Welcome back, <?php echo e($currentUser['full_name']); ?>!</p>
    </div>
    <div>
        <?php if ($role === 'teacher'): ?>
            <a href="<?php echo BASE_URL; ?>/teacher/create_class.php" class="btn btn-primary">➕ Create Class</a>
        <?php else: ?>
            <a href="<?php echo BASE_URL; ?>/student/join_class.php" class="btn btn-primary">🔗 Join Class</a>
        <?php endif; ?>
    </div>
</div>

<!-- Quick Stats -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon purple">📚</div>
        <div class="stat-info">
            <h3><?php echo count($classes); ?></h3>
            <p><?php echo $role === 'teacher' ? 'Active Classes' : 'Enrolled Classes'; ?></p>
        </div>
    </div>
    <?php if ($role === 'teacher'): ?>
        <div class="stat-card">
            <div class="stat-icon blue">👥</div>
            <div class="stat-info">
                <h3><?php echo array_sum(array_column($classes, 'student_count')); ?></h3>
                <p>Total Students</p>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon green">📝</div>
            <div class="stat-info">
                <h3><?php echo array_sum(array_column($classes, 'assignment_count')); ?></h3>
                <p>Assignments Created</p>
            </div>
        </div>
    <?php else: ?>
        <div class="stat-card">
            <div class="stat-icon orange">📝</div>
            <div class="stat-info">
                <h3><?php echo isset($upcomingDeadlines) ? count($upcomingDeadlines) : 0; ?></h3>
                <p>Pending Work</p>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon green">✅</div>
            <div class="stat-info">
                <h3><?php echo array_sum(array_column($classes, 'submitted_count')); ?></h3>
                <p>Submissions</p>
            </div>
        </div>
    <?php endif; ?>
    <div class="stat-card">
        <div class="stat-icon red">🔔</div>
        <div class="stat-info">
            <h3><?php echo $notificationCount; ?></h3>
            <p>Unread Notifications</p>
        </div>
    </div>
</div>

<!-- Upcoming Deadlines (Student only) -->
<?php if ($role === 'student' && !empty($upcomingDeadlines)): ?>
<div class="card mb-3">
    <div class="card-body">
        <h3 style="font-size:16px;font-weight:700;margin-bottom:12px;">⏰ Upcoming Deadlines</h3>
        <?php foreach ($upcomingDeadlines as $deadline): ?>
            <div class="d-flex align-center justify-between" style="padding:10px 0;border-bottom:1px solid var(--border);">
                <div class="d-flex align-center gap-2">
                    <span style="width:10px;height:10px;border-radius:50%;background:<?php echo e($deadline['color']); ?>;display:inline-block;"></span>
                    <div>
                        <strong style="font-size:14px;"><?php echo e($deadline['title']); ?></strong>
                        <p class="text-muted text-sm"><?php echo e($deadline['class_name']); ?></p>
                    </div>
                </div>
                <span class="badge badge-warning"><?php echo formatDate($deadline['due_date'], 'M d, h:i A'); ?></span>
            </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<!-- Class Cards Grid -->
<?php if (!empty($classes)): ?>
<div class="class-grid">
    <?php foreach ($classes as $class): ?>
        <a href="<?php echo BASE_URL; ?>/class_details.php?id=<?php echo $class['id']; ?>" class="class-card card" style="text-decoration:none;color:inherit;">
            <div class="card-header" style="background:linear-gradient(135deg, <?php echo e($class['color']); ?>, <?php echo e($class['color']); ?>dd);">
                <span class="class-code"><?php echo e($class['class_code']); ?></span>
                <h3><?php echo e($class['class_name']); ?></h3>
                <p><?php echo e($class['subject']); ?><?php echo $class['section'] ? ' • ' . e($class['section']) : ''; ?></p>
            </div>
            <div class="card-body">
                <div class="class-stats">
                    <div class="stat">
                        <div class="stat-value"><?php echo $class['student_count']; ?></div>
                        <div class="stat-label">Students</div>
                    </div>
                    <div class="stat">
                        <div class="stat-value"><?php echo $class['assignment_count']; ?></div>
                        <div class="stat-label">Tasks</div>
                    </div>
                </div>
                <?php if ($role === 'student' && isset($class['teacher_name'])): ?>
                    <div class="teacher-info">
                        <span class="avatar-initials" style="width:28px;height:28px;font-size:11px;background:<?php echo e($class['color']); ?>;">
                            <?php echo e(getInitials($class['teacher_name'])); ?>
                        </span>
                        <?php echo e($class['teacher_name']); ?>
                    </div>
                <?php endif; ?>
            </div>
        </a>
    <?php endforeach; ?>
</div>
<?php else: ?>
<div class="empty-state">
    <div class="empty-icon"><?php echo $role === 'teacher' ? '📚' : '🎒'; ?></div>
    <h3>No Classes Yet</h3>
    <p><?php echo $role === 'teacher' 
        ? 'Create your first class to get started with teaching.' 
        : 'Join a class using a class code from your teacher.'; ?></p>
    <?php if ($role === 'teacher'): ?>
        <a href="<?php echo BASE_URL; ?>/teacher/create_class.php" class="btn btn-primary">➕ Create First Class</a>
    <?php else: ?>
        <a href="<?php echo BASE_URL; ?>/student/join_class.php" class="btn btn-primary">🔗 Join a Class</a>
    <?php endif; ?>
</div>
<?php endif; ?>

</main>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
