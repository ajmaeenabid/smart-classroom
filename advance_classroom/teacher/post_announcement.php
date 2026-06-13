<?php
/**
 * Post Announcement
 */
$pageTitle = 'Post Announcement';
require_once __DIR__ . '/../includes/header.php';
requireTeacher();

$classId = $_GET['class_id'] ?? null;
if (!$classId) redirect(BASE_URL . '/dashboard.php');
requireClassOwner($pdo, $classId);

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid request.';
    } else {
        $title = trim($_POST['title'] ?? '');
        $content = trim($_POST['content'] ?? '');

        if (empty($title)) $errors[] = 'Title is required.';
        if (empty($content)) $errors[] = 'Content is required.';

        if (empty($errors)) {
            $stmt = $pdo->prepare("INSERT INTO announcements (class_id, teacher_id, title, content) VALUES (?, ?, ?, ?)");
            $stmt->execute([$classId, $_SESSION['user_id'], $title, $content]);

            notifyClassStudents($pdo, $classId, "New announcement: {$title}", 'announcement', BASE_URL . '/class_details.php?id=' . $classId);

            setFlash('success', 'Announcement posted successfully.');
            redirect(BASE_URL . '/class_details.php?id=' . $classId);
        }
    }
}

require_once __DIR__ . '/../includes/navbar.php';
?>

<div class="page-header">
    <div>
        <h1>Post Announcement</h1>
        <p>Share updates, notices, or news with your class.</p>
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
                <label class="form-label" for="title">Title *</label>
                <input type="text" id="title" name="title" class="form-control" required placeholder="Announcement Subject">
            </div>
            <div class="form-group">
                <label class="form-label" for="content">Message *</label>
                <textarea id="content" name="content" class="form-control" required placeholder="Type your message here..." style="min-height: 150px;"></textarea>
            </div>
            <div>
                <button type="submit" class="btn btn-primary">Post Announcement</button>
                <a href="<?php echo BASE_URL; ?>/class_details.php?id=<?php echo $classId; ?>" class="btn btn-outline">Cancel</a>
            </div>
        </form>
    </div>
</div>

</main>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
