<?php
/**
 * Create Quiz
 */
$pageTitle = 'Create Quiz';
require_once __DIR__ . '/../includes/header.php';
requireTeacher();

$classId = $_GET['class_id'] ?? null;
if (!$classId) {
    $stmt = $pdo->prepare("SELECT id, class_name FROM classes WHERE teacher_id = ? AND status='active'");
    $stmt->execute([$_SESSION['user_id']]);
    $classes = $stmt->fetchAll();
    if(empty($classes)) {
        setFlash('error', 'Please create a class first.');
        redirect(BASE_URL . '/dashboard.php');
    }
} else {
    $classes = [['id' => $classId, 'class_name' => 'Current Class']];
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid request.';
    } else {
        $selectedClassId = $_POST['class_id'] ?? $classId;
        $title = trim($_POST['title']);
        $description = trim($_POST['description']);
        $timeLimit = !empty($_POST['time_limit']) ? (int)$_POST['time_limit'] : null;
        $qTexts = $_POST['q_text'] ?? [];
        $optsA = $_POST['opt_a'] ?? [];
        $optsB = $_POST['opt_b'] ?? [];
        $optsC = $_POST['opt_c'] ?? [];
        $optsD = $_POST['opt_d'] ?? [];
        $correctOpts = $_POST['correct'] ?? [];

        if (empty($title)) $errors[] = "Quiz title is required.";
        if (empty($qTexts)) $errors[] = "At least one question is required.";

        if (empty($errors)) {
            $pdo->beginTransaction();
            try {
                $stmt = $pdo->prepare("INSERT INTO quizzes (class_id, teacher_id, title, description, time_limit) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$selectedClassId, $_SESSION['user_id'], $title, $description, $timeLimit]);
                $quizId = $pdo->lastInsertId();

                $stmtQ = $pdo->prepare("INSERT INTO quiz_questions (quiz_id, question_text, option_a, option_b, option_c, option_d, correct_option, sort_order) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                
                for ($i=0; $i < count($qTexts); $i++) {
                    if (trim($qTexts[$i]) !== '') {
                        $stmtQ->execute([
                            $quizId, $qTexts[$i], $optsA[$i], $optsB[$i], $optsC[$i], $optsD[$i], 
                            strtoupper($correctOpts[$i] ?? 'A'), $i
                        ]);
                    }
                }

                $pdo->commit();
                
                notifyClassStudents($pdo, $selectedClassId, "New Quiz Posted: {$title}", 'quiz', BASE_URL . '/student/take_quiz.php?id=' . $quizId);
                
                setFlash('success', 'Quiz created successfully!');
                redirect(BASE_URL . '/class_details.php?id=' . $selectedClassId);

            } catch (Exception $e) {
                $pdo->rollBack();
                $errors[] = "Database error saving quiz. Please try again.";
            }
        }
    }
}

require_once __DIR__ . '/../includes/navbar.php';
?>

<div class="page-header">
    <div>
        <h1>Create Quiz / Poll</h1>
        <p>Assess student understanding with multiple-choice questions.</p>
    </div>
</div>

<div class="card" style="max-width:800px;">
    <div class="card-body">
        <?php if (!empty($errors)): ?>
            <div class="flash-message flash-error"><span class="flash-text"><?php echo e($errors[0]); ?></span></div>
        <?php endif; ?>

        <form method="POST" action="">
            <?php echo csrfField(); ?>

            <?php if (!$classId): ?>
            <div class="form-group mb-3">
                <label class="form-label">Select Class *</label>
                <select name="class_id" class="form-control" required>
                    <?php foreach ($classes as $c): ?>
                        <option value="<?php echo $c['id']; ?>"><?php echo e($c['class_name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php else: ?>
                <input type="hidden" name="class_id" value="<?php echo $classId; ?>">
            <?php endif; ?>

            <div class="form-group mb-3">
                <label class="form-label">Quiz Title *</label>
                <input type="text" name="title" class="form-control" required placeholder="e.g. Chapter 4 Knowledge Check">
            </div>

            <div class="form-row mb-3">
                <div class="form-group mb-0">
                    <label class="form-label">Description / Instructions</label>
                    <textarea name="description" class="form-control" rows="2" placeholder="Optional details..."></textarea>
                </div>
                <div class="form-group mb-0">
                    <label class="form-label">Time Limit (Minutes)</label>
                    <input type="number" name="time_limit" class="form-control" placeholder="Leave empty for untimed" min="1">
                </div>
            </div>

            <div style="margin:30px 0;border-top:1px dashed var(--border);"></div>

            <h3 class="mb-3">Questions</h3>
            <div id="questions-container">
                <!-- Template Question -->
                <div class="q-block card bg-light mb-3" style="background:#F8FAFC;">
                    <div class="card-body">
                        <div class="d-flex justify-between align-center mb-2">
                            <h4 class="text-sm font-weight-bold">Question 1</h4>
                        </div>
                        <div class="form-group">
                            <input type="text" name="q_text[]" class="form-control" required placeholder="Question text...">
                        </div>
                        <div class="form-row mt-2">
                            <div class="form-group mb-2">
                                <div class="d-flex align-center gap-2">
                                    <input type="radio" name="correct[0]" value="A" checked title="Mark as correct">
                                    <input type="text" name="opt_a[]" class="form-control form-control-sm" required placeholder="Option A">
                                </div>
                            </div>
                            <div class="form-group mb-2">
                                <div class="d-flex align-center gap-2">
                                    <input type="radio" name="correct[0]" value="B" title="Mark as correct">
                                    <input type="text" name="opt_b[]" class="form-control form-control-sm" required placeholder="Option B">
                                </div>
                            </div>
                            <div class="form-group mb-2">
                                <div class="d-flex align-center gap-2">
                                    <input type="radio" name="correct[0]" value="C" title="Mark as correct">
                                    <input type="text" name="opt_c[]" class="form-control form-control-sm" required placeholder="Option C">
                                </div>
                            </div>
                            <div class="form-group mb-2">
                                <div class="d-flex align-center gap-2">
                                    <input type="radio" name="correct[0]" value="D" title="Mark as correct">
                                    <input type="text" name="opt_d[]" class="form-control form-control-sm" required placeholder="Option D">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <button type="button" class="btn btn-outline mb-4" id="addQBtn">➕ Add Another Question</button>

            <div class="form-group pt-3 border-top">
                <button type="submit" class="btn btn-primary btn-lg">Publish Quiz</button>
            </div>
        </form>
    </div>
</div>

<script>
document.getElementById('addQBtn').addEventListener('click', function() {
    const container = document.getElementById('questions-container');
    const idx = container.querySelectorAll('.q-block').length;
    
    const html = `
        <div class="q-block card bg-light mb-3" style="background:#F8FAFC;">
            <div class="card-body">
                <div class="d-flex justify-between align-center mb-2">
                    <h4 class="text-sm font-weight-bold">Question ${idx + 1}</h4>
                    <button type="button" class="btn btn-sm btn-ghost text-danger" onclick="this.closest('.q-block').remove()">Remove</button>
                </div>
                <div class="form-group">
                    <input type="text" name="q_text[]" class="form-control" required placeholder="Question text...">
                </div>
                <div class="form-row mt-2">
                    <div class="form-group mb-2"><div class="d-flex align-center gap-2"><input type="radio" name="correct[${idx}]" value="A" checked><input type="text" name="opt_a[]" class="form-control form-control-sm" required placeholder="Option A"></div></div>
                    <div class="form-group mb-2"><div class="d-flex align-center gap-2"><input type="radio" name="correct[${idx}]" value="B"><input type="text" name="opt_b[]" class="form-control form-control-sm" required placeholder="Option B"></div></div>
                    <div class="form-group mb-2"><div class="d-flex align-center gap-2"><input type="radio" name="correct[${idx}]" value="C"><input type="text" name="opt_c[]" class="form-control form-control-sm" required placeholder="Option C"></div></div>
                    <div class="form-group mb-2"><div class="d-flex align-center gap-2"><input type="radio" name="correct[${idx}]" value="D"><input type="text" name="opt_d[]" class="form-control form-control-sm" required placeholder="Option D"></div></div>
                </div>
            </div>
        </div>
    `;
    container.insertAdjacentHTML('beforeend', html);
});
</script>

</main>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
