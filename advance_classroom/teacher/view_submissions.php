<?php
/**
 * View Submissions - Teacher phase
 */
$pageTitle = 'View Submissions';
require_once __DIR__ . '/../includes/header.php';
requireTeacher();

$assignmentId = $_GET['id'] ?? null;
if (!$assignmentId) redirect(BASE_URL . '/dashboard.php');

$stmt = $pdo->prepare("SELECT a.*, c.class_name FROM assignments a JOIN classes c ON a.class_id = c.id WHERE a.id = ? AND a.teacher_id = ?");
$stmt->execute([$assignmentId, $_SESSION['user_id']]);
$assignment = $stmt->fetch();

if (!$assignment) redirect(BASE_URL . '/dashboard.php');

// Get all enrolled students and their submissions
$stmt = $pdo->prepare("
    SELECT u.id as student_id, u.full_name, u.email,
           s.id as submission_id, s.status, s.submitted_at, s.marks
    FROM class_enrollments ce
    JOIN users u ON ce.student_id = u.id
    LEFT JOIN submissions s ON s.student_id = u.id AND s.assignment_id = ?
    WHERE ce.class_id = ?
    ORDER BY u.full_name
");
$stmt->execute([$assignmentId, $assignment['class_id']]);
$students = $stmt->fetchAll();

$gradedCount = 0; $submittedCount = 0; $missingCount = 0;
foreach ($students as $stu) {
    if ($stu['status'] === 'graded') $gradedCount++;
    else if ($stu['status']) $submittedCount++;
    else $missingCount++;
}

require_once __DIR__ . '/../includes/navbar.php';
?>

<div class="page-header">
    <div>
        <div class="text-sm text-muted mb-1"><a href="<?php echo BASE_URL; ?>/class_details.php?id=<?php echo $assignment['class_id']; ?>"><?php echo e($assignment['class_name']); ?></a> / Assignment</div>
        <h1><?php echo e($assignment['title']); ?></h1>
    </div>
</div>

<div class="stats-grid mb-3">
    <div class="stat-card">
        <div class="stat-icon blue">📋</div>
        <div class="stat-info">
            <h3><?php echo $submittedCount; ?></h3>
            <p>To Grade</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon green">✅</div>
        <div class="stat-info">
            <h3><?php echo $gradedCount; ?></h3>
            <p>Graded</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon red">⚠️</div>
        <div class="stat-info">
            <h3><?php echo $missingCount; ?></h3>
            <p>Missing</p>
        </div>
    </div>
</div>

<div class="card">
    <div class="table-wrapper">
        <table class="table">
            <thead>
                <tr>
                    <th>Student</th>
                    <th>Status</th>
                    <th>Submitted At</th>
                    <th>Score</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($students as $stu): 
                    $statHTML = '<span class="badge badge-danger">Missing</span>';
                    $dateText = '—';
                    $scoreText = '—';
                    
                    if ($stu['status']) {
                        if ($stu['status'] === 'graded') {
                            $statHTML = '<span class="badge badge-success">Graded</span>';
                            $scoreText = "<strong>{$stu['marks']}</strong> / {$assignment['max_marks']}";
                        } else if ($stu['status'] === 'late') {
                            $statHTML = '<span class="badge badge-warning">Late</span>';
                        } else {
                            $statHTML = '<span class="badge badge-info">Submitted</span>';
                        }
                        $dateText = formatDate($stu['submitted_at'], 'M d, h:i A');
                    }
                ?>
                <tr>
                    <td>
                        <div class="d-flex align-center gap-2">
                            <span class="avatar-initials" style="background:#64748B;width:32px;height:32px;font-size:12px;"><?php echo getInitials($stu['full_name']); ?></span>
                            <div>
                                <strong style="display:block;font-size:14px;"><?php echo e($stu['full_name']); ?></strong>
                                <span style="font-size:12px;color:var(--text-muted);"><?php echo e($stu['email']); ?></span>
                            </div>
                        </div>
                    </td>
                    <td><?php echo $statHTML; ?></td>
                    <td class="text-sm"><?php echo $dateText; ?></td>
                    <td class="text-sm"><?php echo $scoreText; ?></td>
                    <td>
                        <?php if ($stu['submission_id']): ?>
                            <a href="<?php echo BASE_URL; ?>/teacher/grade_submission.php?id=<?php echo $stu['submission_id']; ?>" class="btn btn-sm btn-primary">Review</a>
                        <?php else: ?>
                            <span class="text-sm text-muted">No Submission</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

</main>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
