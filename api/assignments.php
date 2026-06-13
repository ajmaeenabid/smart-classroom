<?php
// =============================================
// Smart Classroom — Assignments API
// =============================================
require_once __DIR__ . '/../config/db.php';
requireLogin();

$action = $_POST['action'] ?? '';
$user   = currentUser();
$uid    = $user['id'];

// ── Create Assignment (Teacher)
if ($action === 'create' && $user['role'] === 'teacher') {
    $classId  = (int)($_POST['class_id'] ?? 0);
    $title    = trim($_POST['title'] ?? '');
    $desc     = trim($_POST['description'] ?? '');
    $dueDate  = $_POST['due_date'] ? date('Y-m-d H:i:s', strtotime($_POST['due_date'])) : null;
    $points   = (int)($_POST['points'] ?? 100);
    $offline  = isset($_POST['allow_offline']) ? 1 : 0;
    $attach   = '';

    if (!$title || !$classId) {
        if (isset($_SERVER['HTTP_ACCEPT']) && str_contains($_SERVER['HTTP_ACCEPT'], 'json')) jsonResponse(['error' => 'Title required'], 400);
        redirect($_SERVER['HTTP_REFERER'] ?? BASE_URL);
    }

    // File upload
    if (!empty($_FILES['attachment']['name'])) {
        $uploadDir = __DIR__ . '/../uploads/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
        $ext    = pathinfo($_FILES['attachment']['name'], PATHINFO_EXTENSION);
        $fname  = 'assign_' . uniqid() . '.' . $ext;
        move_uploaded_file($_FILES['attachment']['tmp_name'], $uploadDir . $fname);
        $attach = $fname;
    }

    $ins = $pdo->prepare("INSERT INTO assignments (class_id, title, description, due_date, points, attachment, allow_offline) VALUES (?,?,?,?,?,?,?)");
    $ins->execute([$classId, $title, $desc, $dueDate, $points, $attach, $offline]);
    $assignId = $pdo->lastInsertId();

    // Notify students
    $members = $pdo->prepare("SELECT user_id FROM class_members WHERE class_id=?");
    $members->execute([$classId]);
    foreach ($members->fetchAll() as $m) {
        $notif = $pdo->prepare("INSERT INTO notifications (user_id, title, message, type, link) VALUES (?,?,?,?,?)");
        $notif->execute([$m['user_id'], 'New Assignment', "New assignment: {$title}", 'warning', "/classroom/index.php?id={$classId}&tab=classwork"]);
    }

    if (isset($_SERVER['HTTP_ACCEPT']) && str_contains($_SERVER['HTTP_ACCEPT'], 'json')) {
        jsonResponse(['success' => true, 'assignment_id' => $assignId]);
    }
    redirect(BASE_URL . "/classroom/index.php?id={$classId}&tab=classwork");
}

// ── Submit Assignment (Student)
if ($action === 'submit' && $user['role'] === 'student') {
    $assignId   = (int)($_POST['assignment_id'] ?? 0);
    $text       = trim($_POST['text_content'] ?? '');
    $filePath   = '';

    // Check deadline
    $asgn = $pdo->prepare("SELECT * FROM assignments WHERE id=?");
    $asgn->execute([$assignId]);
    $a = $asgn->fetch();
    if (!$a) jsonResponse(['error' => 'Assignment not found'], 404);

    $status = 'submitted';
    if ($a['due_date'] && strtotime($a['due_date']) < time()) $status = 'late';

    // Check duplicate
    $dup = $pdo->prepare("SELECT id FROM submissions WHERE assignment_id=? AND student_id=?");
    $dup->execute([$assignId, $uid]);
    if ($dup->fetch()) {
        redirect(BASE_URL . "/classroom/index.php?id={$a['class_id']}&tab=classwork&error=already_submitted");
    }

    // File upload
    if (!empty($_FILES['file']['name'])) {
        $uploadDir = __DIR__ . '/../uploads/submissions/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
        $ext   = pathinfo($_FILES['file']['name'], PATHINFO_EXTENSION);
        $fname = 'sub_' . uniqid() . '.' . $ext;
        move_uploaded_file($_FILES['file']['tmp_name'], $uploadDir . $fname);
        $filePath = $fname;
    }

    $ins = $pdo->prepare("INSERT INTO submissions (assignment_id, student_id, file_path, text_content, status) VALUES (?,?,?,?,?)");
    $ins->execute([$assignId, $uid, $filePath, $text, $status]);

    // Notify teacher
    $cls = $pdo->prepare("SELECT teacher_id FROM classes WHERE id=?");
    $cls->execute([$a['class_id']]);
    $class = $cls->fetch();
    $notif = $pdo->prepare("INSERT INTO notifications (user_id, title, message, type) VALUES (?,?,?,?)");
    $notif->execute([$class['teacher_id'], 'New Submission', "{$user['name']} submitted {$a['title']}", 'info']);

    redirect(BASE_URL . "/classroom/index.php?id={$a['class_id']}&tab=classwork&submitted=1");
}

// ── Grade Submission (Teacher)
if ($action === 'grade' && $user['role'] === 'teacher') {
    $subId    = (int)($_POST['submission_id'] ?? 0);
    $grade    = (int)($_POST['grade'] ?? 0);
    $feedback = trim($_POST['feedback'] ?? '');

    $upd = $pdo->prepare("UPDATE submissions SET grade=?, feedback=?, status='graded', graded_at=NOW() WHERE id=?");
    $upd->execute([$grade, $feedback, $subId]);

    // Notify student
    $sub = $pdo->prepare("SELECT s.*,a.title FROM submissions s JOIN assignments a ON a.id=s.assignment_id WHERE s.id=?");
    $sub->execute([$subId]);
    $subData = $sub->fetch();
    if ($subData) {
        $notif = $pdo->prepare("INSERT INTO notifications (user_id, title, message, type) VALUES (?,?,?,?)");
        $notif->execute([$subData['student_id'], 'Assignment Graded', "Your submission for '{$subData['title']}' has been graded: {$grade}%", 'success']);
    }

    jsonResponse(['success' => true]);
}

jsonResponse(['error' => 'Invalid action'], 400);
?>
