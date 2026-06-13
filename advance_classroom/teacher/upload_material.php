<?php
/**
 * Upload Material
 */
$pageTitle = 'Upload Material';
require_once __DIR__ . '/../includes/header.php';
requireTeacher();

$classId = $_GET['class_id'] ?? null;
// If no class selected, redirect or show class selector (assuming accessed via class page here)
if (!$classId) {
    // Basic class selector for standalone page access
    $stmt = $pdo->prepare("SELECT id, class_name FROM classes WHERE teacher_id = ? AND status='active'");
    $stmt->execute([$_SESSION['user_id']]);
    $classes = $stmt->fetchAll();
    if (empty($classes)) { 
        setFlash('error', 'Create a class first before uploading materials.');
        redirect(BASE_URL . '/dashboard.php');
    }
} else {
    requireClassOwner($pdo, $classId);
    $classes = [['id' => $classId, 'class_name' => 'Current Class']]; // simplified
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid request.';
    } else {
        $selectedClassId = $_POST['class_id'] ?? $classId;
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $file = $_FILES['material_file'] ?? null;

        if (empty($title)) $errors[] = 'Title is required.';
        if (!$file || $file['error'] === UPLOAD_ERR_NO_FILE) $errors[] = 'Please select a file to upload.';

        if (empty($errors)) {
            $uploadResult = handleFileUpload($file, 'materials');
            
            if ($uploadResult['success']) {
                $stmt = $pdo->prepare("INSERT INTO materials (class_id, teacher_id, title, description, file_path, file_type, file_size) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([
                    $selectedClassId, 
                    $_SESSION['user_id'], 
                    $title, 
                    $description, 
                    $uploadResult['path'], 
                    $uploadResult['type'], 
                    $uploadResult['size']
                ]);

                notifyClassStudents($pdo, $selectedClassId, "New study material added: {$title}", 'system', BASE_URL . '/class_details.php?id=' . $selectedClassId);

                setFlash('success', 'Material uploaded successfully.');
                redirect(BASE_URL . '/class_details.php?id=' . $selectedClassId);
            } else {
                $errors[] = $uploadResult['error'];
            }
        }
    }
}

require_once __DIR__ . '/../includes/navbar.php';
?>

<div class="page-header">
    <div>
        <h1>Upload Material</h1>
        <p>Share documents, notes, and resources with your class.</p>
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
                <label class="form-label" for="title">Document Title *</label>
                <input type="text" id="title" name="title" class="form-control" required placeholder="e.g. Lecture 1 Slides">
            </div>

            <div class="form-group">
                <label class="form-label" for="description">Description</label>
                <textarea id="description" name="description" class="form-control" placeholder="Optional details..."></textarea>
            </div>

            <div class="form-group">
                <label class="form-label">Attached File *</label>
                <div class="file-upload-area" id="fileUploadArea">
                    <div class="upload-icon">📄</div>
                    <p>Click or drag file here to upload</p>
                    <p class="text-sm text-muted mt-1">Accepts PDF, DOCX, PPTX, JPG, ZIP up to 10MB</p>
                    <input type="file" id="material_file" name="material_file" accept=".pdf,.doc,.docx,.ppt,.pptx,.txt,.jpg,.jpeg,.png,.zip" required>
                </div>
            </div>

            <div style="margin-top:24px;">
                <button type="submit" class="btn btn-primary">Upload Material</button>
            </div>
        </form>
    </div>
</div>

</main>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
