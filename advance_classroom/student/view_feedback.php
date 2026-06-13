<?php
/**
 * View Feedback - Student
 */
$pageTitle = 'My Grades & Feedback';
require_once __DIR__ . '/../includes/header.php';
requireStudent();

$userId = $_SESSION['user_id'];

// Get all graded submissions for the student
$stmt = $pdo->prepare("
    SELECT s.*, 
           a.title as assignment_title, a.max_marks,
           c.class_name, c.color
    FROM submissions s
    JOIN assignments a ON s.assignment_id = a.id
    JOIN classes c ON a.class_id = c.id
    WHERE s.student_id = ? AND s.status = 'graded'
    ORDER BY s.graded_at DESC
");
$stmt->execute([$userId]);
$gradedWork = $stmt->fetchAll();

require_once __DIR__ . '/../includes/navbar.php';
?>

<div class="page-header">
    <div>
        <h1>My Grades</h1>
        <p>Review your scores and teacher feedback across all classes.</p>
    </div>
</div>

<?php if (empty($gradedWork)): ?>
    <div class="empty-state card">
        <div class="empty-icon">📊</div>
        <h3>No grades available yet</h3>
        <p>When teachers grade your submissions, they will appear here.</p>
    </div>
<?php else: ?>
    <div style="display:flex;flex-direction:column;gap:16px;">
        <?php foreach ($gradedWork as $work): 
            $percentage = ($work['marks'] / $work['max_marks']) * 100;
            $gradeColor = '#10B981'; // Green A
            if ($percentage < 60) $gradeColor = '#EF4444'; // Red F
            elseif ($percentage < 75) $gradeColor = '#F59E0B'; // Orange C
            elseif ($percentage < 85) $gradeColor = '#3B82F6'; // Blue B
        ?>
        <div class="card">
            <div class="card-body">
                <div class="d-flex justify-between align-center" style="margin-bottom:16px;flex-wrap:wrap;gap:16px;">
                    <div>
                        <div class="d-flex align-center gap-2 mb-1">
                            <span style="width:12px;height:12px;border-radius:50%;background:<?php echo e($work['color']); ?>;"></span>
                            <span class="text-sm text-muted"><?php echo e($work['class_name']); ?></span>
                        </div>
                        <h3 style="font-size:18px;"><a href="<?php echo BASE_URL; ?>/student/view_assignment.php?id=<?php echo $work['assignment_id']; ?>"><?php echo e($work['assignment_title']); ?></a></h3>
                        <p class="text-sm text-muted mt-1">Graded on: <?php echo formatDate($work['graded_at'], 'M d, Y'); ?></p>
                    </div>
                    <div style="text-align:right;">
                        <div style="font-size:28px;font-weight:800;color:<?php echo $gradeColor; ?>;">
                            <?php echo $work['marks']; ?> <span style="font-size:16px;color:var(--text-muted);font-weight:600;">/ <?php echo $work['max_marks']; ?></span>
                        </div>
                        <div class="text-sm" style="color:<?php echo $gradeColor; ?>;font-weight:600;"><?php echo round($percentage, 1); ?>%</div>
                    </div>
                </div>

                <?php if (!empty($work['feedback'])): ?>
                <div style="background:#F8FAFC;border-left:4px solid var(--accent);padding:16px;border-radius:0 var(--radius-sm) var(--radius-sm) 0;">
                    <div class="text-sm" style="font-weight:600;margin-bottom:4px;color:var(--dark);">Teacher Feedback:</div>
                    <div style="font-size:14px;color:var(--text-light);font-style:italic;">"<?php echo nl2br(e($work['feedback'])); ?>"</div>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

</main>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
