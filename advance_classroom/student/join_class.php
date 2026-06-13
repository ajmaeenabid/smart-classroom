<?php
/**
 * Join Class — Student Phase
 */
$pageTitle = 'Join Class';
require_once __DIR__ . '/../includes/header.php';
requireStudent();

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid request token. Please try again.';
    } else {
        $classCode = strtoupper(trim($_POST['class_code'] ?? ''));

        if (empty($classCode)) {
            $errors[] = 'Please enter a class code.';
        } else {
            // Find class
            $stmt = $pdo->prepare("SELECT id FROM classes WHERE class_code = ? AND status = 'active'");
            $stmt->execute([$classCode]);
            $class = $stmt->fetch();

            if (!$class) {
                $errors[] = 'Class not found. Please check the code and try again.';
            } else {
                // Check if already enrolled
                $stmt = $pdo->prepare("SELECT id FROM class_enrollments WHERE class_id = ? AND student_id = ?");
                $stmt->execute([$class['id'], $_SESSION['user_id']]);
                if ($stmt->fetch()) {
                    setFlash('info', 'You are already enrolled in this class.');
                    redirect(BASE_URL . '/class_details.php?id=' . $class['id']);
                }

                // Enroll
                $stmt = $pdo->prepare("INSERT INTO class_enrollments (class_id, student_id) VALUES (?, ?)");
                $stmt->execute([$class['id'], $_SESSION['user_id']]);

                setFlash('success', 'Successfully joined the class!');
                redirect(BASE_URL . '/class_details.php?id=' . $class['id']);
            }
        }
    }
}

require_once __DIR__ . '/../includes/navbar.php';
?>

<div class="page-header">
    <div>
        <h1>Join a Class</h1>
        <p>Enter the class code provided by your teacher.</p>
    </div>
</div>

<div class="card" style="max-width: 500px;">
    <div class="card-body text-center" style="padding: 40px;">
        <div style="font-size:48px; margin-bottom:20px;">🔗</div>
        <h3 style="margin-bottom:8px;">Ask your teacher for the class code</h3>
        <p class="text-muted mb-3">A class code is made of 7 characters (e.g., A7XY3B9).</p>

        <?php if (!empty($errors)): ?>
            <div class="flash-message flash-error" style="text-align:left;">
                <span class="flash-icon">✕</span>
                <span class="flash-text"><?php echo e($errors[0]); ?></span>
            </div>
        <?php endif; ?>

        <form method="POST" action="" style="text-align:left;">
            <?php echo csrfField(); ?>
            <div class="form-group">
                <input type="text" name="class_code" class="form-control" placeholder="Class Code" style="font-size:20px; text-transform:uppercase; text-align:center; padding:16px;" required autocomplete="off">
            </div>
            <button type="submit" class="btn btn-primary btn-block btn-lg">Join Class</button>
        </form>
    </div>
</div>

</main>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
