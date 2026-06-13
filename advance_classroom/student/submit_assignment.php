<?php
/**
 * Submit Assignment
 */
$pageTitle = 'Submit Assignment';
require_once __DIR__ . '/../includes/header.php';
requireStudent();

$assignmentId = $_GET['id'] ?? null;
if (!$assignmentId) redirect(BASE_URL . '/dashboard.php');

$stmt = $pdo->prepare("SELECT a.*, c.class_name FROM assignments a JOIN classes c ON a.class_id = c.id WHERE a.id = ?");
$stmt->execute([$assignmentId]);
$assignment = $stmt->fetch();

if (!$assignment) redirect(BASE_URL . '/dashboard.php');
requireEnrollment($pdo, $assignment['class_id']);

// Check prior submission
$stmt = $pdo->prepare("SELECT * FROM submissions WHERE assignment_id = ? AND student_id = ?");
$stmt->execute([$assignmentId, $_SESSION['user_id']]);
$submission = $stmt->fetch();

// Can't resubmit if already graded
if ($submission && $submission['status'] === 'graded') {
    setFlash('error', 'This assignment has already been graded and cannot be resubmitted.');
    redirect(BASE_URL . '/student/view_assignment.php?id=' . $assignmentId);
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid request.';
    } else {
        $comment = trim($_POST['comment'] ?? '');
        $statusMarker = (strtotime($assignment['due_date']) < time()) ? 'late' : 'submitted';

        $file = $_FILES['submission_file'] ?? null;
        if (!$submission && (!$file || $file['error'] === UPLOAD_ERR_NO_FILE)) {
            $errors[] = 'You must upload a file to submit.';
        }

        if (empty($errors)) {
            $filePath = $submission['file_path'] ?? null;
            
            // Handle new file upload
            if ($file && $file['error'] !== UPLOAD_ERR_NO_FILE) {
                $uploadResult = handleFileUpload($file, 'submissions');
                if ($uploadResult['success']) {
                    $filePath = $uploadResult['path'];
                } else {
                    $errors[] = $uploadResult['error'];
                }
            }

            if (empty($errors)) {
                if ($submission) {
                    $stmt = $pdo->prepare("UPDATE submissions SET file_path = ?, comment = ?, submitted_at = CURRENT_TIMESTAMP, status = ? WHERE id = ?");
                    $stmt->execute([$filePath, $comment, $statusMarker, $submission['id']]);
                } else {
                    $stmt = $pdo->prepare("INSERT INTO submissions (assignment_id, student_id, file_path, comment, status) VALUES (?, ?, ?, ?, ?)");
                    $stmt->execute([$assignmentId, $_SESSION['user_id'], $filePath, $comment, $statusMarker]);
                }

                // Notify teacher
                createNotification($pdo, $assignment['teacher_id'], "{$currentUser['full_name']} submitted '{$assignment['title']}'", 'submission', BASE_URL . '/teacher/grade_submission.php?id=' . ($submission['id'] ?? $pdo->lastInsertId()));

                setFlash('success', 'Assignment submitted successfully.');
                redirect(BASE_URL . '/student/view_assignment.php?id=' . $assignmentId);
            }
        }
    }
}

require_once __DIR__ . '/../includes/navbar.php';
?>

<div class="page-header">
    <div>
        <h1>Submit Work</h1>
        <p><?php echo e($assignment['title']); ?> • <?php echo e($assignment['class_name']); ?></p>
    </div>
</div>

<div class="card" style="max-width: 600px;">
    <div class="card-body">
        <?php if (!empty($errors)): ?>
            <div class="flash-message flash-error">
                <span class="flash-icon">✕</span>
                <span class="flash-text"><?php echo e($errors[0]); ?></span>
            </div>
        <?php endif; ?>

        <?php if (strtotime($assignment['due_date']) < time()): ?>
            <div class="flash-message flash-warning">
                <span class="flash-icon">⚠</span>
                <span class="flash-text">This assignment is past due. It will be marked as late.</span>
            </div>
        <?php endif; ?>

        <form method="POST" action="" enctype="multipart/form-data">
            <?php echo csrfField(); ?>

            <div class="form-group">
                <label class="form-label">Upload Your Work *</label>
                <div class="file-upload-area" id="fileUploadArea">
                    <div class="upload-icon">📤</div>
                    <p>Click or drag your assignment file here</p>
                    <p class="text-sm text-muted mt-1">Accepts PDF, DOCX, PPTX, JPG, ZIP (Max 10MB)</p>
                    <input type="file" id="submission_file" name="submission_file" accept=".pdf,.doc,.docx,.ppt,.pptx,.txt,.jpg,.jpeg,.png,.zip" <?php echo !$submission ? 'required' : ''; ?>>
                </div>
                <?php if ($submission && $submission['file_path']): ?>
                    <p class="text-sm text-muted mt-2">Current file: <?php echo basename($submission['file_path']); ?> (Upload a new file to replace)</p>
                <?php endif; ?>
            </div>

            <div class="form-group">
                <label class="form-label" for="comment">Private Comment to Teacher</label>
                <textarea id="comment" name="comment" class="form-control" placeholder="Optional notes about your submission..."><?php echo e($submission['comment'] ?? ''); ?></textarea>
            </div>

            <div style="margin-top:24px;">
                <button type="submit" class="btn btn-primary"><?php echo $submission ? 'Resubmit Work' : 'Turn In Assignment'; ?></button>
                <a href="<?php echo BASE_URL; ?>/student/view_assignment.php?id=<?php echo $assignmentId; ?>" class="btn btn-outline">Cancel</a>
            </div>
        </form>
    </div>
</div>

</main>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
