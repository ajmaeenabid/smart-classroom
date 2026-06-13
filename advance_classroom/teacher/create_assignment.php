<?php
/**
 * Create Assignment
 */
$pageTitle = 'Create Assignment';
require_once __DIR__ . '/../includes/header.php';
requireTeacher();

$classId = $_GET['class_id'] ?? null;
if (!$classId) {
    $stmt = $pdo->prepare("SELECT id, class_name FROM classes WHERE teacher_id = ? AND status='active'");
    $stmt->execute([$_SESSION['user_id']]);
    $classes = $stmt->fetchAll();
    if (empty($classes)) { 
        setFlash('error', 'Create a class first.');
        redirect(BASE_URL . '/dashboard.php');
    }
} else {
    requireClassOwner($pdo, $classId);
    $classes = [['id' => $classId, 'class_name' => 'Current Class']];
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid request.';
    } else {
        $selectedClassId = $_POST['class_id'] ?? $classId;
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $dueDate = $_POST['due_date'] ?? '';
        $maxMarks = (int)$_POST['max_marks'] ?? 100;
        $file = $_FILES['assignment_file'] ?? null;

        if (empty($title)) $errors[] = 'Title is required.';
        if (empty($dueDate)) $errors[] = 'Due date is required.';

        if (empty($errors)) {
            $filePath = null;
            if ($file && $file['error'] !== UPLOAD_ERR_NO_FILE) {
                $uploadResult = handleFileUpload($file, 'assignments');
                if ($uploadResult['success']) {
                    $filePath = $uploadResult['path'];
                } else {
                    $errors[] = $uploadResult['error'];
                }
            }

            if (empty($errors)) {
                $stmt = $pdo->prepare("INSERT INTO assignments (class_id, teacher_id, title, description, due_date, max_marks, file_path) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$selectedClassId, $_SESSION['user_id'], $title, $description, $dueDate, $maxMarks, $filePath]);

                notifyClassStudents($pdo, $selectedClassId, "New Assignment: {$title}", 'assignment', BASE_URL . '/student/view_assignment.php?id=' . $pdo->lastInsertId());

                setFlash('success', 'Assignment created successfully.');
                redirect(BASE_URL . '/class_details.php?id=' . $selectedClassId);
            }
        }
    }
}

require_once __DIR__ . '/../includes/navbar.php';
?>

<div class="page-header">
    <div>
        <h1>Create Assignment</h1>
        <p>Assign tasks, essays, or projects to your students.</p>
    </div>
</div>

<div class="card" style="max-width: 700px;">
    <div class="card-body">
        <?php if (!empty($errors)): ?>
            <div class="flash-message flash-error">
                <span class="flash-icon">✕</span>
                <span class="flash-text"><?php echo e($errors[0]); ?></span>
            </div>
        <?php endif; ?>

        <form method="POST" action="" enctype="multipart/form-data">
            <?php echo csrfField(); ?>
            
            <?php if (!$classId): ?>
            <div class="form-group">
                <label class="form-label" for="class_id">Select Class *</label>
                <select id="class_id" name="class_id" class="form-control" required>
                    <?php foreach ($classes as $c): ?>
                        <option value="<?php echo $c['id']; ?>"><?php echo e($c['class_name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php else: ?>
                <input type="hidden" name="class_id" value="<?php echo $classId; ?>">
            <?php endif; ?>

            <div class="form-group">
                <label class="form-label" for="title">Assignment Title *</label>
                <input type="text" id="title" name="title" class="form-control" required placeholder="e.g. Midterm Essay">
            </div>

            <div class="form-group">
                <label class="form-label" for="description">Instructions</label>
                <textarea id="description" name="description" class="form-control" placeholder="Provide clear instructions for students..." style="min-height:150px;"></textarea>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label class="form-label" for="due_date">Due Date & Time *</label>
                    <input type="datetime-local" id="due_date" name="due_date" class="form-control" required>
                </div>
                <div class="form-group">
                    <label class="form-label" for="max_marks">Max Marks</label>
                    <input type="number" id="max_marks" name="max_marks" class="form-control" value="100" min="0" max="1000">
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">Attach File (Optional)</label>
                <div class="file-upload-area" id="fileUploadArea">
                    <div class="upload-icon">📎</div>
                    <p>Click or drag file here</p>
                    <p class="text-sm text-muted mt-1">Rubrics, templates, or reading materials</p>
                    <input type="file" id="assignment_file" name="assignment_file">
                </div>
            </div>

            <div style="margin-top:24px;">
                <button type="submit" class="btn btn-primary">Publish Assignment</button>
            </div>
        </form>
    </div>
</div>

</main>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
