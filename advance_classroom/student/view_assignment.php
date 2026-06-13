<?php
/**
 * View Assignment - Student Phase
 */
$pageTitle = 'View Assignment';
require_once __DIR__ . '/../includes/header.php';
requireLogin(); // Actually, could be teacher reviewing it too, but let's handle student view mostly, role logic included

$assignmentId = $_GET['id'] ?? null;
if (!$assignmentId) redirect(BASE_URL . '/dashboard.php');

$role = getUserRole();
$userId = $_SESSION['user_id'];

// Get assignment
$stmt = $pdo->prepare("
    SELECT a.*, c.class_name, c.color, u.full_name as teacher_name 
    FROM assignments a 
    JOIN classes c ON a.class_id = c.id 
    JOIN users u ON c.teacher_id = u.id 
    WHERE a.id = ?
");
$stmt->execute([$assignmentId]);
$assignment = $stmt->fetch();

if (!$assignment) redirect(BASE_URL . '/dashboard.php');

requireClassAccess($pdo, $assignment['class_id']);

// Get submission if student
$submission = null;
if ($role === 'student') {
    $stmt = $pdo->prepare("SELECT * FROM submissions WHERE assignment_id = ? AND student_id = ?");
    $stmt->execute([$assignmentId, $userId]);
    $submission = $stmt->fetch();
}

require_once __DIR__ . '/../includes/navbar.php';

$dueTime = strtotime($assignment['due_date']);
$isPastDue = $dueTime < time();
?>

<div class="page-header">
    <div>
        <h1 style="display:flex;align-items:center;gap:12px;">
            <span style="background:var(--primary-bg);color:var(--primary);width:48px;height:48px;border-radius:12px;display:flex;align-items:center;justify-content:center;font-size:24px;">📋</span>
            <?php echo e($assignment['title']); ?>
        </h1>
        <p>Class: <?php echo e($assignment['class_name']); ?> • Teacher: <?php echo e($assignment['teacher_name']); ?></p>
    </div>
    <div>
        <?php if ($role === 'teacher'): ?>
            <a href="<?php echo BASE_URL; ?>/teacher/view_submissions.php?id=<?php echo $assignmentId; ?>" class="btn btn-primary">View Submissions</a>
        <?php endif; ?>
    </div>
</div>

<div style="display:grid;grid-template-columns:1fr 340px;gap:24px;">
    <!-- Assignment Details -->
    <div class="card">
        <div class="card-body">
            <div class="d-flex justify-between align-center mb-3" style="border-bottom:1px solid var(--border);padding-bottom:16px;">
                <div>
                    <span class="text-muted text-sm">Due Date</span>
                    <strong style="display:block;color:<?php echo $isPastDue ? 'var(--danger)' : 'var(--text)'; ?>"><?php echo formatDate($assignment['due_date'], 'F d, Y h:i A'); ?></strong>
                </div>
                <div class="text-right">
                    <span class="text-muted text-sm">Points</span>
                    <strong style="display:block;"><?php echo e($assignment['max_marks']); ?></strong>
                </div>
            </div>

            <div style="font-size:15px;line-height:1.7;margin-bottom:24px;">
                <?php echo nl2br(e($assignment['description'] ?? 'No description provided.')); ?>
            </div>

            <?php if (!empty($assignment['file_path'])): ?>
            <div style="border-top:1px solid var(--border);padding-top:20px;">
                <h4 style="font-size:14px;margin-bottom:12px;color:var(--text-muted);">Attachments</h4>
                <div class="card" style="display:inline-flex;width:auto;">
                    <div class="card-body" style="padding:12px 16px;display:flex;align-items:center;gap:12px;">
                        <span style="font-size:24px;"><?php echo getFileIcon($assignment['file_path']); ?></span>
                        <div>
                            <div style="font-size:14px;font-weight:600;max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?php echo basename($assignment['file_path']); ?></div>
                        </div>
                        <a href="<?php echo BASE_URL . '/' . $assignment['file_path']; ?>" target="_blank" download class="btn btn-sm btn-outline">Download</a>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Submission Panel (Student only) -->
    <?php if ($role === 'student'): ?>
    <div>
        <div class="card">
            <div class="card-body">
                <div class="d-flex justify-between align-center mb-3">
                    <h3 style="font-size:18px;font-weight:700;">Your Work</h3>
                    <?php 
                        if ($submission) {
                            echo statusBadge($submission['status']);
                        } else if ($isPastDue) {
                            echo statusBadge('missing');
                        } else {
                            echo '<span class="badge badge-warning">Assigned</span>';
                        }
                    ?>
                </div>

                <?php if ($submission): ?>
                    <!-- Submitted State -->
                    <div style="background:var(--primary-bg);border:1px solid var(--primary-light);border-radius:var(--radius-sm);padding:16px;margin-bottom:16px;">
                        <div class="d-flex align-center gap-2 mb-2">
                            <span>📄</span>
                            <a href="<?php echo BASE_URL . '/' . $submission['file_path']; ?>" target="_blank" style="font-weight:600;font-size:14px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;display:block;max-width:200px;"><?php echo basename($submission['file_path']); ?></a>
                        </div>
                        <div class="text-sm text-muted">Submitted on: <?php echo formatDate($submission['submitted_at'], 'M d, h:i A'); ?></div>
                    </div>

                    <?php if ($submission['status'] === 'graded'): ?>
                        <div style="background:#F0FDFA;border:1px solid #14B8A6;border-radius:var(--radius-sm);padding:16px;margin-bottom:16px;">
                            <div class="text-sm text-muted mb-1">Grade Received:</div>
                            <div style="font-size:24px;color:#0D9488;font-weight:800;margin-bottom:12px;"><?php echo $submission['marks']; ?> / <?php echo $assignment['max_marks']; ?></div>
                            
                            <?php if (!empty($submission['feedback'])): ?>
                            <div class="text-sm text-muted mb-1">Teacher Feedback:</div>
                            <div style="font-size:14px;font-style:italic;color:var(--text);">"<?php echo nl2br(e($submission['feedback'])); ?>"</div>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <p class="text-sm text-muted text-center mb-3">Your submission is waiting to be graded.</p>
                        <!-- Allow resubmission if not graded -->
                        <a href="<?php echo BASE_URL; ?>/student/submit_assignment.php?id=<?php echo $assignmentId; ?>" class="btn btn-outline btn-block">Resubmit Work</a>
                    <?php endif; ?>

                <?php else: ?>
                    <!-- Unsubmitted State -->
                    <a href="<?php echo BASE_URL; ?>/student/submit_assignment.php?id=<?php echo $assignmentId; ?>" class="btn btn-primary btn-block mb-3">Add Submission</a>
                    <?php if ($isPastDue): ?>
                        <div class="text-sm text-center" style="color:var(--danger);padding:8px;background:#FEF2F2;border-radius:var(--radius-sm);">
                            This assignment is past due. Submissions will be marked as late.
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($submission && $submission['comment']): ?>
        <div class="card mt-3">
            <div class="card-body">
                <h4 style="font-size:14px;color:var(--text-muted);margin-bottom:8px;">Private Comment:</h4>
                <p style="font-size:14px;"><?php echo nl2br(e($submission['comment'])); ?></p>
            </div>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>

</main>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
