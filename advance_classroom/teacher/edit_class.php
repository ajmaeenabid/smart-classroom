<?php
/**
 * Edit Class — Teacher Phase
 */
$pageTitle = 'Edit Class';
require_once __DIR__ . '/../includes/header.php';
requireTeacher();

$classId = $_GET['id'] ?? null;

if (!$classId) {
    redirect(BASE_URL . '/dashboard.php');
}

// Verify ownership
$stmt = $pdo->prepare("SELECT * FROM classes WHERE id = ? AND teacher_id = ?");
$stmt->execute([$classId, $_SESSION['user_id']]);
$classInfo = $stmt->fetch();

if (!$classInfo) {
    setFlash('error', 'Class not found or you do not have permission.');
    redirect(BASE_URL . '/dashboard.php');
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid request token. Please try again.';
    } else {
        $className = trim($_POST['class_name'] ?? '');
        $subject = trim($_POST['subject'] ?? '');
        $section = trim($_POST['section'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $color = $_POST['color'] ?? $classInfo['color'];

        if (empty($className)) $errors[] = 'Class name is required.';
        if (empty($subject)) $errors[] = 'Subject is required.';

        if (empty($errors)) {
            $stmt = $pdo->prepare("UPDATE classes SET class_name = ?, subject = ?, section = ?, description = ?, color = ? WHERE id = ?");
            $stmt->execute([$className, $subject, $section, $description, $color, $classId]);

            setFlash('success', 'Class updated successfully!');
            redirect(BASE_URL . '/class_details.php?id=' . $classId);
        }
    }
}

require_once __DIR__ . '/../includes/navbar.php';
?>

<div class="page-header">
    <div>
        <h1>Edit Class</h1>
        <p><?php echo e($classInfo['class_name']); ?></p>
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

        <form method="POST" action="">
            <?php echo csrfField(); ?>

            <div class="form-group">
                <label class="form-label" for="class_name">Class Name *</label>
                <input type="text" id="class_name" name="class_name" class="form-control" required value="<?php echo e($classInfo['class_name']); ?>">
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label class="form-label" for="subject">Subject *</label>
                    <input type="text" id="subject" name="subject" class="form-control" required value="<?php echo e($classInfo['subject']); ?>">
                </div>
                <div class="form-group">
                    <label class="form-label" for="section">Section</label>
                    <input type="text" id="section" name="section" class="form-control" value="<?php echo e($classInfo['section']); ?>">
                </div>
            </div>

            <div class="form-group">
                <label class="form-label" for="description">Description</label>
                <textarea id="description" name="description" class="form-control"><?php echo e($classInfo['description']); ?></textarea>
            </div>

            <div class="form-group">
                <label class="form-label">Theme Color</label>
                <div style="display:flex;gap:10px;flex-wrap:wrap;margin-top:8px;">
                    <?php foreach (getClassColors() as $hex => $name): ?>
                        <label style="cursor:pointer;" title="<?php echo $name; ?>">
                            <input type="radio" name="color" value="<?php echo $hex; ?>" style="display:none;" <?php echo $hex === $classInfo['color'] ? 'checked' : ''; ?>>
                            <span style="display:inline-block;width:32px;height:32px;border-radius:50%;background:<?php echo $hex; ?>;border:3px solid <?php echo $hex === $classInfo['color'] ? '#fff' : 'transparent'; ?>;<?php echo $hex === $classInfo['color'] ? 'outline:2px solid '.$hex : ''; ?>;transition:all 0.2s;" onclick="document.querySelectorAll('input[name=color] + span').forEach(s=>{s.style.borderColor='transparent'; s.style.outline='none'}); this.style.borderColor='#fff'; this.style.outline='2px solid <?php echo $hex; ?>';"></span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <div style="margin-top:24px;">
                <button type="submit" class="btn btn-primary">Update Class</button>
                <a href="<?php echo BASE_URL; ?>/class_details.php?id=<?php echo $classId; ?>" class="btn btn-outline">Cancel</a>
            </div>
        </form>
    </div>
</div>

</main>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
