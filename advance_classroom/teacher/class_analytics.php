<?php
/**
 * Class Analytics - Teacher View
 */
$pageTitle = 'Class Analytics';
require_once __DIR__ . '/../includes/header.php';
requireTeacher();

$classId = $_GET['class_id'] ?? null;
if (!$classId) {
    $stmt = $pdo->prepare("SELECT id, class_name FROM classes WHERE teacher_id = ? AND status='active'");
    $stmt->execute([$_SESSION['user_id']]);
    $classes = $stmt->fetchAll();
    if(empty($classes)) redirect(BASE_URL.'/dashboard.php');
    $classId = $classes[0]['id'];
} else {
    requireClassOwner($pdo, $classId);
    $stmt = $pdo->prepare("SELECT id, class_name FROM classes WHERE id=?");
    $stmt->execute([$classId]);
    $classes = $stmt->fetchAll();
}

$classNameForTitle = $classes[0]['class_name'];
$totalStudents = 0; $avgClassGrade = 0; $assignCount = 0;

if ($classId) {
    // Basic stats
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM class_enrollments WHERE class_id = ?");
    $stmt->execute([$classId]);
    $totalStudents = $stmt->fetchColumn();

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM assignments WHERE class_id = ?");
    $stmt->execute([$classId]);
    $assignCount = $stmt->fetchColumn();

    $stmt = $pdo->prepare("
        SELECT AVG((s.marks / a.max_marks) * 100) as avg
        FROM submissions s 
        JOIN assignments a ON s.assignment_id = a.id
        WHERE a.class_id = ? AND s.status = 'graded'
    ");
    $stmt->execute([$classId]);
    $avgClassGrade = round($stmt->fetchColumn() ?? 0, 1);
    
    // Top Students (High Performers)
    $stmt = $pdo->prepare("
        SELECT u.full_name, AVG((s.marks / a.max_marks) * 100) as perf
        FROM users u
        JOIN class_enrollments ce ON u.id = ce.student_id AND ce.class_id = ?
        JOIN submissions s ON u.id = s.student_id AND s.status = 'graded'
        JOIN assignments a ON s.assignment_id = a.id AND a.class_id = ?
        GROUP BY u.id
        ORDER BY perf DESC
        LIMIT 5
    ");
    $stmt->execute([$classId, $classId]);
    $topPerformers = $stmt->fetchAll();
}

require_once __DIR__ . '/../includes/navbar.php';
?>

<div class="page-header">
    <div>
        <h1>Class Analytics <span class="text-muted text-base ml-2 font-weight-normal"><?php echo e($classNameForTitle); ?></span></h1>
        <p>Insights and performance metrics for your classroom.</p>
    </div>
</div>

<div class="stats-grid mb-4">
    <div class="stat-card">
        <div class="stat-icon blue">👥</div>
        <div class="stat-info">
            <h3><?php echo $totalStudents; ?></h3>
            <p>Enrolled Students</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon orange">📊</div>
        <div class="stat-info">
            <h3><?php echo $avgClassGrade; ?>%</h3>
            <p>Class Average Grade</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon green">📁</div>
        <div class="stat-info">
            <h3><?php echo $assignCount; ?></h3>
            <p>Total Assignments</p>
        </div>
    </div>
</div>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:24px;">
    <div class="card">
        <div class="card-header" style="background:var(--body-bg);color:var(--text);border-bottom:1px solid var(--border);">
            <h3 class="text-base">Top Performing Students</h3>
        </div>
        <div class="card-body p-0">
            <?php if(empty($topPerformers)): ?>
                <p class="text-center p-4 text-muted">Not enough graded assignments to determine ranking.</p>
            <?php else: ?>
                <?php foreach($topPerformers as $idx => $perf): ?>
                <div class="d-flex justify-between align-center p-3" style="border-bottom:1px solid var(--border);">
                    <div class="d-flex align-center gap-2">
                        <span class="badge" style="background:#F1F5F9;color:var(--text);width:24px;text-align:center;">#<?php echo $idx+1; ?></span>
                        <span class="font-weight-bold" style="font-size:14px;"><?php echo e($perf['full_name']); ?></span>
                    </div>
                    <span class="text-success font-weight-bold"><?php echo round($perf['perf'], 1); ?>%</span>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Export Module Box -->
    <div class="card" style="border:1px solid var(--primary-light);">
        <div class="card-body">
            <div class="text-center mb-3">
                <span style="font-size:40px;">📑</span>
                <h3 class="mt-2" style="font-size:18px;">Export Gradebook</h3>
                <p class="text-sm text-muted mt-1">Download complete class records as CSV for external analysis or archiving.</p>
            </div>
            <a href="<?php echo BASE_URL; ?>/export.php?type=grades&class_id=<?php echo $classId; ?>" class="btn btn-primary btn-block mb-2">📥 Download Grades (CSV)</a>
            <a href="<?php echo BASE_URL; ?>/export.php?type=attendance&class_id=<?php echo $classId; ?>" class="btn btn-outline btn-block">📥 Download Attendance (CSV)</a>
        </div>
    </div>
</div>

</main>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
