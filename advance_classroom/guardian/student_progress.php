<?php
/**
 * Guardian - View Student Progress (Read Only)
 */
$pageTitle = 'Student Progress';
require_once __DIR__ . '/../includes/header.php';
requireGuardian();

$studentId = $_GET['id'] ?? null;
if (!$studentId) redirect(BASE_URL . '/guardian/dashboard.php');

// Verfiy link
$stmt = $pdo->prepare("SELECT u.* FROM guardian_links gl JOIN users u ON gl.student_id = u.id WHERE gl.guardian_id = ? AND gl.student_id = ?");
$stmt->execute([$_SESSION['user_id'], $studentId]);
$student = $stmt->fetch();

if (!$student) redirect(BASE_URL . '/guardian/dashboard.php');

// Get classes
$stmt = $pdo->prepare("SELECT c.*, u.full_name as teacher FROM class_enrollments ce JOIN classes c ON ce.class_id = c.id JOIN users u ON c.teacher_id=u.id WHERE ce.student_id = ?");
$stmt->execute([$studentId]);
$classes = $stmt->fetchAll();

// Get recent grades
$stmt = $pdo->prepare("
    SELECT s.*, a.title, a.max_marks, c.class_name, c.color
    FROM submissions s 
    JOIN assignments a ON s.assignment_id = a.id
    JOIN classes c ON a.class_id = c.id
    WHERE s.student_id = ? AND s.status = 'graded'
    ORDER BY s.graded_at DESC LIMIT 10
");
$stmt->execute([$studentId]);
$recentGrades = $stmt->fetchAll();

require_once __DIR__ . '/../includes/navbar.php';
?>

<div class="page-header">
    <div>
        <div class="text-sm text-muted mb-1"><a href="<?php echo BASE_URL; ?>/guardian/dashboard.php">← Back to Dashboard</a></div>
        <h1>Progress Report: <?php echo e($student['full_name']); ?></h1>
        <p>Read-only overview of academic status.</p>
    </div>
</div>

<div style="display:grid;grid-template-columns:2fr 1fr;gap:24px;">
    <div>
        <!-- Recent Grades -->
        <div class="card mb-4">
            <div class="card-header" style="background:var(--body-bg);color:var(--text);border-bottom:1px solid var(--border);">
                <h3 class="text-base">Recent Graded Work</h3>
            </div>
            <div class="table-wrapper text-left">
                <table class="table">
                    <thead><tr><th>Task</th><th>Class</th><th>Score</th></tr></thead>
                    <tbody>
                        <?php if(empty($recentGrades)): ?>
                            <tr><td colspan="3" class="text-center py-4 text-muted">No graded work available recently.</td></tr>
                        <?php else: ?>
                            <?php foreach($recentGrades as $grade): ?>
                            <tr>
                                <td>
                                    <strong class="text-sm block" style="margin-bottom:2px;"><?php echo e($grade['title']); ?></strong>
                                    <?php if($grade['feedback']): ?>
                                    <div class="text-xs text-muted" style="max-width:250px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
                                        "<?php echo e($grade['feedback']); ?>"
                                    </div>
                                    <?php endif; ?>
                                </td>
                                <td><span class="badge" style="background:<?php echo e($grade['color']); ?>22;color:<?php echo e($grade['color']); ?>;"><?php echo e($grade['class_name']); ?></span></td>
                                <td style="font-weight:700;color:var(--primary);"><?php echo $grade['marks']; ?> <span class="text-xs text-muted">/ <?php echo $grade['max_marks']; ?></span></td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif;?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Enrolled Classes -->
        <h3 class="mb-3" style="font-size:16px;">Enrolled Classes</h3>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
            <?php foreach($classes as $c): ?>
                <div class="card" style="border-left:4px solid <?php echo e($c['color']); ?>;">
                    <div class="card-body p-3">
                        <div class="text-sm font-weight-bold"><?php echo e($c['class_name']); ?></div>
                        <div class="text-xs text-muted mt-1">Teacher: <?php echo e($c['teacher']); ?></div>
                    </div>
                </div>
            <?php endforeach;?>
        </div>
    </div>

    <!-- Attendance Sidebar -->
    <div>
        <div class="card">
            <div class="card-header bg-warning text-white" style="background:var(--warning);">
                <h3 class="text-base">Quick Notices</h3>
            </div>
            <div class="card-body">
                <p class="text-sm mb-3">🎓 <strong>Attendance Check:</strong> Review the student's personal portal for detailed attendance stats.</p>
                <p class="text-sm">🔔 <strong>Missing Work:</strong> <span class="text-danger">0</span> assignments past due currently.</p>
            </div>
        </div>
    </div>
</div>

</main>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
