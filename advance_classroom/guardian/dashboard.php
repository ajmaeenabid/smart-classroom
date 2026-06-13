<?php
/**
 * Guardian Dashboard
 */
$pageTitle = 'Guardian Dashboard';
require_once __DIR__ . '/../includes/header.php';
requireGuardian();

$userId = $_SESSION['user_id'];

// Get linked students
$stmt = $pdo->prepare("
    SELECT u.id, u.full_name, u.email, u.avatar 
    FROM guardian_links gl 
    JOIN users u ON gl.student_id = u.id 
    WHERE gl.guardian_id = ?
");
$stmt->execute([$userId]);
$students = $stmt->fetchAll();

require_once __DIR__ . '/../includes/navbar.php';
?>

<div class="page-header">
    <div>
        <h1>Welcome, <?php echo e($currentUser['full_name']); ?></h1>
        <p>Monitor the academic progress of your linked students.</p>
    </div>
</div>

<?php if (empty($students)): ?>
    <div class="empty-state card text-center">
        <div class="empty-icon text-muted">👨‍👩‍👧</div>
        <h3>No connected students</h3>
        <p class="text-muted">It looks like your account is not linked to any student yet. Please contact the administrator.</p>
    </div>
<?php else: ?>
    <!-- Student Cards -->
    <div style="display:grid;grid-template-columns:repeat(auto-fill, minmax(340px, 1fr));gap:24px;">
        <?php foreach ($students as $stu): 
            // Quick stats for student
            $stmt = $pdo->prepare("
                SELECT 
                    (SELECT COUNT(*) FROM class_enrollments WHERE student_id = ?) as cc,
                    (SELECT AVG((marks/max_marks)*100) FROM submissions s JOIN assignments a ON s.assignment_id = a.id WHERE s.student_id = ? AND status='graded') as avg
            ");
            $stmt->execute([$stu['id'], $stu['id']]);
            $stats = $stmt->fetch();
            $avg = round($stats['avg'] ?? 0, 1);
        ?>
        <div class="card" style="border-top:4px solid var(--primary);">
            <div class="card-body">
                <div class="d-flex align-center gap-2 mb-4">
                    <div class="avatar-initials" style="width:56px;height:56px;font-size:20px;background:#818CF8;"><?php echo getInitials($stu['full_name']); ?></div>
                    <div>
                        <h3 style="font-size:18px;margin-bottom:2px;"><?php echo e($stu['full_name']); ?></h3>
                        <p class="text-sm text-muted">Student ID: #<?php echo $stu['id']; ?></p>
                    </div>
                </div>
                
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:24px;border-top:1px solid var(--border);border-bottom:1px solid var(--border);padding:16px 0;">
                    <div class="text-center" style="border-right:1px solid var(--border);">
                        <strong style="display:block;font-size:24px;color:var(--dark);"><?php echo $stats['cc']; ?></strong>
                        <span class="text-xs text-muted text-uppercase">Classes Tracked</span>
                    </div>
                    <div class="text-center">
                        <strong style="display:block;font-size:24px;color:var(--primary);"><?php echo $avg; ?>%</strong>
                        <span class="text-xs text-muted text-uppercase">Average Grade</span>
                    </div>
                </div>
                
                <a href="<?php echo BASE_URL; ?>/guardian/student_progress.php?id=<?php echo $stu['id']; ?>" class="btn btn-primary btn-block">View Full Progress Report</a>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

</main>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
