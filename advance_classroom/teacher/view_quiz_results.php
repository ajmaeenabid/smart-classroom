<?php
/**
 * View Quiz Results - Teacher
 */
$pageTitle = 'Quiz Results';
require_once __DIR__ . '/../includes/header.php';
requireTeacher();

$quizId = $_GET['id'] ?? null;
if (!$quizId) redirect(BASE_URL . '/dashboard.php');

// Verify owner and get quiz
$stmt = $pdo->prepare("SELECT q.*, c.class_name FROM quizzes q JOIN classes c ON q.class_id = c.id WHERE q.id = ? AND q.teacher_id = ?");
$stmt->execute([$quizId, $_SESSION['user_id']]);
$quiz = $stmt->fetch();

if (!$quiz) redirect(BASE_URL . '/dashboard.php');

// Get questions
$stmt = $pdo->prepare("SELECT * FROM quiz_questions WHERE quiz_id = ? ORDER BY sort_order");
$stmt->execute([$quizId]);
$questions = $stmt->fetchAll();

// Get submissions/scores
$stmt = $pdo->prepare("
    SELECT u.id as student_id, u.full_name,
           SUM(qa.is_correct) as score,
           COUNT(qa.id) as answered,
           MAX(qa.submitted_at) as submitted_at
    FROM users u
    JOIN class_enrollments ce ON u.id = ce.student_id AND ce.class_id = ?
    LEFT JOIN quiz_answers qa ON u.id = qa.student_id AND qa.quiz_id = ?
    GROUP BY u.id
    ORDER BY u.full_name
");
$stmt->execute([$quiz['class_id'], $quizId]);
$studentResults = $stmt->fetchAll();

$totalQuestions = count($questions);

require_once __DIR__ . '/../includes/navbar.php';
?>

<div class="page-header">
    <div>
        <div class="text-sm text-muted mb-1"><a href="<?php echo BASE_URL; ?>/class_details.php?id=<?php echo $quiz['class_id']; ?>"><?php echo e($quiz['class_name']); ?></a> / Quizzes</div>
        <h1>Quiz Results: <?php echo e($quiz['title']); ?></h1>
    </div>
</div>

<div class="card mb-4">
    <div class="card-header" style="background:var(--body-bg);color:var(--text);border-bottom:1px solid var(--border);">
        <h3 class="text-base">Student Scores</h3>
    </div>
    <div class="table-wrapper">
        <table class="table">
            <thead>
                <tr>
                    <th>Student Name</th>
                    <th>Status</th>
                    <th>Score</th>
                    <th>Submitted At</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($studentResults as $stu): 
                    $hasSubmitted = $stu['answered'] > 0;
                ?>
                <tr>
                    <td><strong><?php echo e($stu['full_name']); ?></strong></td>
                    <td>
                        <?php echo $hasSubmitted ? '<span class="badge badge-success">Completed</span>' : '<span class="badge badge-danger">Not Attempted</span>'; ?>
                    </td>
                    <td>
                        <?php if ($hasSubmitted): ?>
                            <strong><?php echo $stu['score']; ?></strong> / <?php echo $totalQuestions; ?>
                            <span class="text-sm text-muted ml-1">(<?php echo round(($stu['score']/$totalQuestions)*100); ?>%)</span>
                        <?php else: ?>
                            —
                        <?php endif; ?>
                    </td>
                    <td class="text-sm text-muted">
                        <?php echo $hasSubmitted ? formatDate($stu['submitted_at'], 'M d, h:i A') : '—'; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Question breakdown could be added here for further analytics -->

</main>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
