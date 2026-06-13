<?php
// =============================================
// Smart Classroom — Quiz & Poll API
// =============================================
require_once __DIR__ . '/../config/db.php';
requireLogin();

$action = $_POST['action'] ?? $_GET['action'] ?? '';
$user   = currentUser();
$uid    = $user['id'];

// ── Create Quiz (Teacher)
if ($action === 'create_quiz' && $user['role'] === 'teacher') {
    $classId   = (int)($_POST['class_id'] ?? 0);
    $title     = trim($_POST['title'] ?? '');
    $desc      = trim($_POST['description'] ?? '');
    $timeLimit = (int)($_POST['time_limit'] ?? 30);
    $status    = in_array($_POST['status'] ?? 'draft', ['draft','live','closed']) ? $_POST['status'] : 'draft';

    if (!$title || !$classId) {
        redirect(BASE_URL . "/classroom/quiz.php?class_id={$classId}&error=1");
    }

    $ins = $pdo->prepare("INSERT INTO quizzes (class_id, title, description, time_limit, status, is_live, created_by) VALUES (?,?,?,?,?,?,?)");
    $ins->execute([$classId, $title, $desc, $timeLimit, $status, $status === 'live' ? 1 : 0, $uid]);
    $quizId = $pdo->lastInsertId();

    // Save questions
    $questions = $_POST['questions'] ?? [];
    foreach ($questions as $q) {
        $question = trim($q['question'] ?? '');
        if (!$question) continue;
        $options = array_filter($q['options'] ?? [], fn($o) => trim($o) !== '');
        $correct = strtoupper(trim($q['correct'] ?? 'A'));
        $points  = (int)($q['points'] ?? 10);
        $ins2 = $pdo->prepare("INSERT INTO quiz_questions (quiz_id, question, type, options, correct_answer, points) VALUES (?,?,'mcq',?,?,?)");
        $ins2->execute([$quizId, $question, json_encode(array_values($options)), $correct, $points]);
    }

    // Notify students if live
    if ($status === 'live') {
        $members = $pdo->prepare("SELECT user_id FROM class_members WHERE class_id=?");
        $members->execute([$classId]);
        foreach ($members->fetchAll() as $m) {
            $notif = $pdo->prepare("INSERT INTO notifications (user_id, title, message, type) VALUES (?,?,?,?)");
            $notif->execute([$m['user_id'], '🔴 Live Quiz!', "Quiz '{$title}' is now live!", 'info']);
        }
    }

    redirect(BASE_URL . "/classroom/quiz.php?class_id={$classId}&created=1");
}

// ── Create Poll (Teacher)
if ($action === 'create_poll' && $user['role'] === 'teacher') {
    $classId  = (int)($_POST['class_id'] ?? 0);
    $question = trim($_POST['question'] ?? '');
    $options  = array_filter($_POST['options'] ?? [], fn($o) => trim($o) !== '');

    if (!$question || count($options) < 2) {
        redirect(BASE_URL . "/classroom/quiz.php?class_id={$classId}&error=poll");
    }

    $ins = $pdo->prepare("INSERT INTO polls (class_id, question, options, created_by) VALUES (?,?,?,?)");
    $ins->execute([$classId, $question, json_encode(array_values($options)), $uid]);
    redirect(BASE_URL . "/classroom/quiz.php?class_id={$classId}&poll_created=1");
}

// ── Vote Poll (Student)
if ($action === 'vote' && $user['role'] === 'student') {
    $pollId     = (int)($_POST['poll_id'] ?? 0);
    $optionIdx  = (int)($_POST['option_index'] ?? 0);

    // Check already voted
    $c = $pdo->prepare("SELECT id FROM poll_votes WHERE poll_id=? AND student_id=?");
    $c->execute([$pollId, $uid]);
    if ($c->fetch()) {
        redirect($_SERVER['HTTP_REFERER'] ?? BASE_URL);
    }

    $ins = $pdo->prepare("INSERT INTO poll_votes (poll_id, student_id, option_index) VALUES (?,?,?)");
    $ins->execute([$pollId, $uid, $optionIdx]);
    redirect($_SERVER['HTTP_REFERER'] ?? BASE_URL);
}

// ── Toggle Quiz Status (Teacher)
if ($action === 'toggle_status' && $user['role'] === 'teacher') {
    $quizId = (int)($_POST['quiz_id'] ?? 0);
    $status = trim($_POST['status'] ?? 'live');
    $upd = $pdo->prepare("UPDATE quizzes SET status=?, is_live=? WHERE id=? AND created_by=?");
    $upd->execute([$status, $status === 'live' ? 1 : 0, $quizId, $uid]);
    jsonResponse(['success' => true]);
}

// ── Submit Quiz Answers (Student)
if ($action === 'submit_quiz' && $user['role'] === 'student') {
    $quizId  = (int)($_POST['quiz_id'] ?? 0);
    $answers = $_POST['answers'] ?? [];

    // Get questions
    $qs = $pdo->prepare("SELECT * FROM quiz_questions WHERE quiz_id=?");
    $qs->execute([$quizId]);
    $questions = $qs->fetchAll();

    $score = 0;
    $total = 0;
    foreach ($questions as $q) {
        $total += $q['points'];
        $ans    = strtoupper(trim($answers[$q['id']] ?? ''));
        if ($ans === strtoupper($q['correct_answer'])) $score += $q['points'];
    }

    // Check if already submitted
    $check = $pdo->prepare("SELECT id FROM quiz_responses WHERE quiz_id=? AND student_id=?");
    $check->execute([$quizId, $uid]);
    if (!$check->fetch()) {
        $ins = $pdo->prepare("INSERT INTO quiz_responses (quiz_id, student_id, answers, score, total_points) VALUES (?,?,?,?,?)");
        $ins->execute([$quizId, $uid, json_encode($answers), $score, $total]);
    }

    jsonResponse(['success' => true, 'score' => $score, 'total' => $total, 'pct' => $total > 0 ? round($score/$total*100) : 0]);
}

// ── Launch Live Quiz (Teacher) ─────────────────
if ($action === 'launch_live' && $user['role'] === 'teacher') {
    $quizId = (int)($_POST['quiz_id'] ?? 0);
    $chk = $pdo->prepare("SELECT id, class_id, title FROM quizzes WHERE id=? AND created_by=?");
    $chk->execute([$quizId, $uid]);
    $quizRow = $chk->fetch();
    if (!$quizRow) jsonResponse(['error' => 'Unauthorized'], 403);

    $pdo->prepare("UPDATE quizzes SET status='live', is_live=1, live_question=0 WHERE id=?")->execute([$quizId]);
    $pdo->prepare("DELETE FROM live_quiz_answers WHERE quiz_id=?")->execute([$quizId]);

    $members = $pdo->prepare("SELECT user_id FROM class_members WHERE class_id=?");
    $members->execute([$quizRow['class_id']]);
    foreach ($members->fetchAll(PDO::FETCH_COLUMN) as $memberId) {
        $pdo->prepare("INSERT INTO notifications (user_id, title, message, type, link) VALUES (?,?,?,?,?)")
            ->execute([$memberId, '🔴 Live Quiz Started!', "'{$quizRow['title']}' is live — join now!", 'warning', "/classroom/quiz_live.php?quiz_id={$quizId}"]);
    }
    jsonResponse(['success' => true]);
}

// ── Get Live State (Any enrolled user) ─────────
if ($action === 'get_live_state') {
    $quizId = (int)($_GET['quiz_id'] ?? 0);
    $quiz = $pdo->prepare("SELECT * FROM quizzes WHERE id=?");
    $quiz->execute([$quizId]);
    $quizData = $quiz->fetch();
    if (!$quizData) jsonResponse(['error' => 'Quiz not found'], 404);

    $questions = $pdo->prepare("SELECT * FROM quiz_questions WHERE quiz_id=? ORDER BY order_num ASC, id ASC");
    $questions->execute([$quizId]);
    $allQs = $questions->fetchAll();

    $totalQs    = count($allQs);
    $currentIdx = max(0, (int)($quizData['live_question'] ?? 0));
    $currentQ   = $allQs[$currentIdx] ?? null;

    $answered = false; $myAnswer = null;
    if ($currentQ && $user['role'] === 'student') {
        $ans = $pdo->prepare("SELECT answer FROM live_quiz_answers WHERE quiz_id=? AND question_id=? AND student_id=?");
        $ans->execute([$quizId, $currentQ['id'], $uid]);
        $ansRow = $ans->fetch();
        $answered = (bool)$ansRow;
        $myAnswer = $ansRow['answer'] ?? null;
    }

    $response = [
        'status'          => $quizData['status'],
        'live_question'   => $currentIdx,
        'total_questions' => $totalQs,
        'answered'        => $answered,
        'my_answer'       => $myAnswer,
    ];

    if ($currentQ && $quizData['status'] === 'live') {
        $opts = json_decode($currentQ['options'], true);
        $response['question'] = [
            'id'      => $currentQ['id'],
            'text'    => $currentQ['question'],
            'options' => $opts,
            'points'  => $currentQ['points'],
        ];
        if ($user['role'] === 'teacher') {
            $response['question']['correct'] = $currentQ['correct_answer'];
        }
    }

    if ($user['role'] === 'teacher' && $currentQ) {
        $dist = $pdo->prepare("SELECT answer, COUNT(*) as cnt FROM live_quiz_answers WHERE quiz_id=? AND question_id=? GROUP BY answer");
        $dist->execute([$quizId, $currentQ['id']]);
        $response['distribution'] = $dist->fetchAll();

        $cntStmt = $pdo->prepare("SELECT COUNT(DISTINCT student_id) FROM live_quiz_answers WHERE quiz_id=? AND question_id=?");
        $cntStmt->execute([$quizId, $currentQ['id']]);
        $response['answered_count'] = (int)$cntStmt->fetchColumn();

        $stuStmt = $pdo->prepare("SELECT COUNT(*) FROM class_members WHERE class_id=?");
        $stuStmt->execute([$quizData['class_id']]);
        $response['student_count'] = (int)$stuStmt->fetchColumn();
    }

    jsonResponse($response);
}

// ── Submit Live Answer (Student) ───────────────
if ($action === 'submit_live_answer' && $user['role'] === 'student') {
    $quizId     = (int)($_POST['quiz_id'] ?? 0);
    $questionId = (int)($_POST['question_id'] ?? 0);
    $answer     = strtoupper(trim($_POST['answer'] ?? ''));

    $q = $pdo->prepare("SELECT * FROM quiz_questions WHERE id=? AND quiz_id=?");
    $q->execute([$questionId, $quizId]);
    $question = $q->fetch();
    if (!$question) jsonResponse(['error' => 'Invalid question'], 404);

    $liveCheck = $pdo->prepare("SELECT live_question FROM quizzes WHERE id=? AND status='live'");
    $liveCheck->execute([$quizId]);
    $liveRow = $liveCheck->fetch();
    if (!$liveRow) jsonResponse(['error' => 'Quiz is not live'], 400);

    $allQsStmt = $pdo->prepare("SELECT id FROM quiz_questions WHERE quiz_id=? ORDER BY order_num ASC, id ASC");
    $allQsStmt->execute([$quizId]);
    $qIds = $allQsStmt->fetchAll(PDO::FETCH_COLUMN);
    if (($qIds[(int)$liveRow['live_question']] ?? null) != $questionId) {
        jsonResponse(['error' => 'This question is no longer active'], 400);
    }

    $isCorrect    = (strtoupper($question['correct_answer']) === $answer) ? 1 : 0;
    $pointsEarned = $isCorrect ? (int)$question['points'] : 0;

    $pdo->prepare("INSERT IGNORE INTO live_quiz_answers (quiz_id, question_id, student_id, answer, is_correct, points_earned) VALUES (?,?,?,?,?,?)")
        ->execute([$quizId, $questionId, $uid, $answer, $isCorrect, $pointsEarned]);

    jsonResponse([
        'success'        => true,
        'is_correct'     => (bool)$isCorrect,
        'points_earned'  => $pointsEarned,
        'correct_answer' => $question['correct_answer'],
    ]);
}

// ── Advance to Next Question (Teacher) ─────────
if ($action === 'advance_question' && $user['role'] === 'teacher') {
    $quizId = (int)($_POST['quiz_id'] ?? 0);
    $chk = $pdo->prepare("SELECT * FROM quizzes WHERE id=? AND created_by=?");
    $chk->execute([$quizId, $uid]);
    $quiz = $chk->fetch();
    if (!$quiz) jsonResponse(['error' => 'Unauthorized'], 403);

    $totalStmt = $pdo->prepare("SELECT COUNT(*) FROM quiz_questions WHERE quiz_id=?");
    $totalStmt->execute([$quizId]);
    $total   = (int)$totalStmt->fetchColumn();
    $nextIdx = (int)$quiz['live_question'] + 1;

    if ($nextIdx >= $total) {
        $pdo->prepare("UPDATE quizzes SET status='closed', is_live=0, live_question=? WHERE id=?")->execute([$nextIdx, $quizId]);
        jsonResponse(['success' => true, 'ended' => true]);
    } else {
        $pdo->prepare("UPDATE quizzes SET live_question=? WHERE id=?")->execute([$nextIdx, $quizId]);
        jsonResponse(['success' => true, 'ended' => false, 'new_index' => $nextIdx]);
    }
}

// ── Live Leaderboard ───────────────────────────
if ($action === 'get_leaderboard') {
    $quizId = (int)($_GET['quiz_id'] ?? 0);
    $lb = $pdo->prepare("
        SELECT u.name,
               SUM(lqa.points_earned) AS total_points,
               SUM(lqa.is_correct)    AS correct_count,
               COUNT(lqa.id)          AS answered_count
        FROM live_quiz_answers lqa
        JOIN users u ON u.id=lqa.student_id
        WHERE lqa.quiz_id=?
        GROUP BY lqa.student_id
        ORDER BY total_points DESC
        LIMIT 20
    ");
    $lb->execute([$quizId]);
    jsonResponse(['success' => true, 'leaderboard' => $lb->fetchAll()]);
}

jsonResponse(['error' => 'Invalid action'], 400);
?>
