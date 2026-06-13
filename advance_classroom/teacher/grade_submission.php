<?php
/**
 * Grade Submission
 */
$pageTitle = 'Grade Submission';
require_once __DIR__ . '/../includes/header.php';
requireTeacher();

$submissionId = $_GET['id'] ?? null;
if (!$submissionId) redirect(BASE_URL . '/dashboard.php');

$stmt = $pdo->prepare("
    SELECT s.*, 
           a.title as assignment_title, a.max_marks, a.id as assignment_id, a.due_date,
           u.full_name as student_name, u.id as student_user_id
    FROM submissions s
    JOIN assignments a ON s.assignment_id = a.id
    JOIN users u ON s.student_id = u.id
    WHERE s.id = ? AND a.teacher_id = ?
");
$stmt->execute([$submissionId, $_SESSION['user_id']]);
$submission = $stmt->fetch();

if (!$submission) redirect(BASE_URL . '/dashboard.php');

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid request.';
    } else {
        $marks = (int)$_POST['marks'];
        $feedback = trim($_POST['feedback'] ?? '');

        if ($marks < 0 || $marks > $submission['max_marks']) {
            $errors[] = "Marks must be between 0 and {$submission['max_marks']}.";
        }

        if (empty($errors)) {
            $stmt = $pdo->prepare("UPDATE submissions SET marks = ?, feedback = ?, status = 'graded', graded_at = CURRENT_TIMESTAMP WHERE id = ?");
            $stmt->execute([$marks, $feedback, $submissionId]);

            // Notify student
            createNotification($pdo, $submission['student_user_id'], "Grade posted for '{$submission['assignment_title']}'", 'grade', BASE_URL . '/student/view_assignment.php?id=' . $submission['assignment_id']);

            setFlash('success', 'Grade saved successfully.');
            redirect(BASE_URL . '/teacher/view_submissions.php?id=' . $submission['assignment_id']);
        }
    }
}

require_once __DIR__ . '/../includes/navbar.php';
?>

<div class="page-header">
    <div>
        <div class="text-sm text-muted mb-1"><a href="<?php echo BASE_URL; ?>/teacher/view_submissions.php?id=<?php echo $submission['assignment_id']; ?>">← Back to Submissions</a></div>
        <h1>Grade: <?php echo e($submission['student_name']); ?></h1>
        <p><?php echo e($submission['assignment_title']); ?></p>
    </div>
</div>

<div style="display:grid;grid-template-columns:1fr 400px;gap:24px;">
    <!-- Submission View -->
    <div class="card">
        <div class="card-header" style="background:var(--body-bg);color:var(--text);border-bottom:1px solid var(--border);">
            <div class="d-flex justify-between align-center">
                <h3 style="font-size:16px;">Student Submission</h3>
                <?php echo statusBadge($submission['status']); ?>
            </div>
        </div>
        <div class="card-body">
            <div class="text-sm text-muted mb-3">Submitted on: <?php echo formatDate($submission['submitted_at'], 'M d, Y h:i A'); ?></div>
            
            <?php if ($submission['file_path']): ?>
                <div style="background:var(--primary-bg);border:1px dashed var(--primary-light);padding:40px;text-align:center;border-radius:var(--radius-sm);margin-bottom:24px;">
                    <span style="font-size:48px;display:block;margin-bottom:12px;"><?php echo getFileIcon($submission['file_path']); ?></span>
                    <strong style="display:block;margin-bottom:16px;"><?php echo basename($submission['file_path']); ?></strong>
                    <a href="<?php echo BASE_URL . '/' . $submission['file_path']; ?>" download target="_blank" class="btn btn-primary">Download File to Review</a>
                </div>
            <?php else: ?>
                <div class="empty-state" style="padding:40px 20px;">
                    <div class="empty-icon">📂</div>
                    <p>No file attached to this submission.</p>
                </div>
            <?php endif; ?>

            <?php if ($submission['comment']): ?>
                <h4 style="font-size:14px;margin-bottom:8px;">Student Comment:</h4>
                <div style="background:#F8FAFC;padding:16px;border-radius:var(--radius-sm);font-size:14px;border-left:4px solid var(--border);">
                    <?php echo nl2br(e($submission['comment'])); ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Grading Panel -->
    <div class="card" style="align-self:start;">
        <div class="card-body">
            <h3 style="font-size:18px;margin-bottom:20px;">Evaluation Panel</h3>
            
            <?php if (!empty($errors)): ?>
                <div class="flash-message flash-error">
                    <span class="flash-icon">✕</span>
                    <span class="flash-text"><?php echo e($errors[0]); ?></span>
                </div>
            <?php endif; ?>

            <form method="POST" action="">
                <?php echo csrfField(); ?>

                <div class="form-group">
                    <label class="form-label" for="marks">Grade / Score <span class="text-muted">(out of <?php echo $submission['max_marks']; ?>)</span></label>
                    <div style="display:flex;align-items:center;gap:12px;">
                        <input type="number" id="marks" name="marks" class="form-control" style="font-size:24px;font-weight:700;padding:16px;width:120px;text-align:center;" value="<?php echo $submission['marks'] ?? ''; ?>" min="0" max="<?php echo $submission['max_marks']; ?>" required>
                        <span style="font-size:20px;color:var(--text-muted);">/ <?php echo $submission['max_marks']; ?></span>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="feedback">Teacher Feedback</label>
                    <textarea id="feedback" name="feedback" class="form-control" placeholder="Provide constructive feedback to the student..." style="min-height:150px;"><?php echo e($submission['feedback'] ?? ''); ?></textarea>
                </div>

                <div style="margin-top:24px;">
                    <button type="submit" class="btn btn-primary btn-block btn-lg">Save & Return</button>
                </div>
            </form>
        </div>
    </div>
</div>

</main>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
