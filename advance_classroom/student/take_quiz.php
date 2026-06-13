<?php
/**
 * Take Quiz - Student View
 */
$pageTitle = 'Take Quiz';
require_once __DIR__ . '/../includes/header.php';
requireStudent();

$quizId = $_GET['id'] ?? null;
if (!$quizId) redirect(BASE_URL . '/dashboard.php');

// Load quiz details securely
$stmt = $pdo->prepare("SELECT q.*, c.class_name FROM quizzes q JOIN classes c ON q.class_id = c.id JOIN class_enrollments ce ON c.id = ce.class_id WHERE q.id = ? AND ce.student_id = ?");
$stmt->execute([$quizId, $_SESSION['user_id']]);
$quiz = $stmt->fetch();

if (!$quiz) {
    setFlash('error', 'Quiz not found or inaccessible.');
    redirect(BASE_URL . '/dashboard.php');
}

// Check if already taken
$stmt = $pdo->prepare("SELECT COUNT(*) FROM quiz_answers WHERE quiz_id = ? AND student_id = ?");
$stmt->execute([$quizId, $_SESSION['user_id']]);
$alreadyTaken = $stmt->fetchColumn() > 0;

// Get questions
$stmt = $pdo->prepare("SELECT id, question_text, option_a, option_b, option_c, option_d FROM quiz_questions WHERE quiz_id = ? ORDER BY sort_order");
$stmt->execute([$quizId]);
$questions = $stmt->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$alreadyTaken) {
    $answers = $_POST['answers'] ?? [];
    
    $pdo->beginTransaction();
    try {
        $insertStmt = $pdo->prepare("INSERT INTO quiz_answers (quiz_id, question_id, student_id, selected_option, is_correct) VALUES (?, ?, ?, ?, (SELECT (case when correct_option = ? then 1 else 0 end) FROM quiz_questions WHERE id = ?))");
        
        foreach ($questions as $q) {
            $ans = $answers[$q['id']] ?? null;
            if ($ans && in_array($ans, ['A','B','C','D'])) {
                $insertStmt->execute([$quizId, $q['id'], $_SESSION['user_id'], $ans, $ans, $q['id']]);
            }
        }
        $pdo->commit();
        setFlash('success', 'Quiz submitted successfully! Great job.');
        redirect(BASE_URL . '/student/take_quiz.php?id=' . $quizId);
    } catch (Exception $e) {
        $pdo->rollBack();
        setFlash('error', 'Failed to submit quiz.');
    }
}

// Load previous answers if taken
$prevAnswers = [];
$totalScore = 0;
if ($alreadyTaken) {
    $stmt = $pdo->prepare("SELECT question_id, selected_option, is_correct FROM quiz_answers WHERE quiz_id = ? AND student_id = ?");
    $stmt->execute([$quizId, $_SESSION['user_id']]);
    foreach ($stmt->fetchAll() as $row) {
        $prevAnswers[$row['question_id']] = $row['selected_option'];
        if ($row['is_correct']) $totalScore++;
    }
}

require_once __DIR__ . '/../includes/navbar.php';
?>

<div class="page-header">
    <div>
        <div class="text-sm text-muted mb-1"><?php echo e($quiz['class_name']); ?></div>
        <h1><?php echo e($quiz['title']); ?></h1>
        <?php if($quiz['description']): ?>
            <p><?php echo e($quiz['description']); ?></p>
        <?php endif; ?>
    </div>
    <?php if ($alreadyTaken): ?>
        <div style="background:#F0FDFA;border:1px solid #14B8A6;padding:16px 24px;border-radius:var(--radius-sm);text-align:center;">
            <div class="text-sm text-muted">Your Score</div>
            <div style="font-size:24px;font-weight:800;color:#0D9488;"><?php echo $totalScore; ?> / <?php echo count($questions); ?></div>
        </div>
    <?php endif; ?>
</div>

<div class="card" style="max-width:800px;">
    <div class="card-body">
        <?php if ($alreadyTaken): ?>
            <div class="flash-message flash-info mb-4">
                <span class="flash-icon">ℹ</span>
                <span class="flash-text">You have already completed this quiz. Below are your submitted responses.</span>
            </div>
        <?php endif; ?>

        <form method="POST" action="">
            <?php echo csrfField(); ?>
            
            <?php foreach ($questions as $idx => $q): 
                $qid = $q['id'];
                $prev = $prevAnswers[$qid] ?? null;
            ?>
            <div class="mb-4 p-3" style="background:#F8FAFC;border-radius:var(--radius-sm);border:1px solid var(--border);">
                <div class="font-weight-bold mb-3" style="font-size:16px;">
                    <?php echo ($idx+1) . ". " . e($q['question_text']); ?>
                </div>
                
                <div style="display:flex;flex-direction:column;gap:8px;">
                    <?php foreach (['A' => 'option_a', 'B' => 'option_b', 'C' => 'option_c', 'D' => 'option_d'] as $letter => $col): 
                        $isChecked = $prev === $letter ? 'checked' : '';
                        $disabled = $alreadyTaken ? 'disabled' : '';
                        $isSelClass = $prev === $letter ? 'background:#EEF2FF;border-color:var(--primary);' : '';
                    ?>
                    <label class="p-2 m-0" style="display:flex;align-items:center;gap:12px;cursor:<?php echo $alreadyTaken ? 'default':'pointer';?>;border:1px solid var(--border);border-radius:var(--radius-sm);background:#fff;<?php echo $isSelClass;?>">
                        <input type="radio" name="answers[<?php echo $qid; ?>]" value="<?php echo $letter; ?>" required <?php echo $isChecked; ?> <?php echo $disabled; ?>>
                        <span style="font-weight:500;width:24px;color:var(--text-muted);"><?php echo $letter; ?>.</span>
                        <span style="flex:1;"><?php echo e($q[$col]); ?></span>
                    </label>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endforeach; ?>

            <?php if (!$alreadyTaken): ?>
            <div class="mt-4 pt-3 border-top">
                <button type="submit" class="btn btn-primary btn-lg" onclick="return confirm('Are you sure you want to submit your answers?')">Submit Quiz ✅</button>
            </div>
            <?php endif; ?>
        </form>
    </div>
</div>

</main>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
