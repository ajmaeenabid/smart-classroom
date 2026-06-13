<?php
/**
 * Create Class — Teacher Phase
 */
$pageTitle = 'Create Class';
require_once __DIR__ . '/../includes/header.php';
requireTeacher();

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid request token. Please try again.';
    } else {
        $className = trim($_POST['class_name'] ?? '');
        $subject = trim($_POST['subject'] ?? '');
        $section = trim($_POST['section'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $color = $_POST['color'] ?? '#4F46E5';

        if (empty($className)) $errors[] = 'Class name is required.';
        if (empty($subject)) $errors[] = 'Subject is required.';

        if (empty($errors)) {
            $classCode = generateUniqueClassCode($pdo);

            $stmt = $pdo->prepare("INSERT INTO classes (teacher_id, class_name, subject, section, description, class_code, color) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$_SESSION['user_id'], $className, $subject, $section, $description, $classCode, $color]);

            $classId = $pdo->lastInsertId();
            setFlash('success', 'Class created successfully! Class Code: ' . $classCode);
            redirect(BASE_URL . '/class_details.php?id=' . $classId);
        }
    }
}

require_once __DIR__ . '/../includes/navbar.php';
?>

<div class="page-header">
    <div>
        <h1>Create New Class</h1>
        <p>Set up a new classroom environment for your students.</p>
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
                <input type="text" id="class_name" name="class_name" class="form-control" required placeholder="e.g. Intro to Computer Science">
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label class="form-label" for="subject">Subject *</label>
                    <input type="text" id="subject" name="subject" class="form-control" required placeholder="e.g. Computer Science">
                </div>
                <div class="form-group">
                    <label class="form-label" for="section">Section</label>
                    <input type="text" id="section" name="section" class="form-control" placeholder="e.g. Fall 2026">
                </div>
            </div>

            <div class="form-group">
                <label class="form-label" for="description">Description</label>
                <textarea id="description" name="description" class="form-control" placeholder="Brief description of the course..."></textarea>
            </div>

            <div class="form-group">
                <label class="form-label">Theme Color</label>
                <div style="display:flex;gap:10px;flex-wrap:wrap;margin-top:8px;">
                    <?php foreach (getClassColors() as $hex => $name): ?>
                        <label style="cursor:pointer;" title="<?php echo $name; ?>">
                            <input type="radio" name="color" value="<?php echo $hex; ?>" style="display:none;" <?php echo $hex === '#4F46E5' ? 'checked' : ''; ?>>
                            <span style="display:inline-block;width:32px;height:32px;border-radius:50%;background:<?php echo $hex; ?>;border:3px solid transparent;transition:all 0.2s;" onclick="document.querySelectorAll('input[name=color] + span').forEach(s=>s.style.borderColor='transparent'); this.style.borderColor='#fff'; this.style.outline='2px solid <?php echo $hex; ?>';"></span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <div style="margin-top:24px;">
                <button type="submit" class="btn btn-primary">Create Class</button>
                <a href="<?php echo BASE_URL; ?>/dashboard.php" class="btn btn-outline">Cancel</a>
            </div>
        </form>
    </div>
</div>

</main>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
