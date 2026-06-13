<?php
// =============================================
// Smart Classroom — Classes API
// =============================================
session_start();
require_once __DIR__ . '/../config/db.php';
requireLogin();

$action = $_POST['action'] ?? $_GET['action'] ?? '';
$user   = currentUser();
$uid    = $user['id'];

// ── Create Class (Teacher only)
if ($action === 'create' && $user['role'] === 'teacher') {
    $name   = trim($_POST['name'] ?? '');
    $sec    = trim($_POST['section'] ?? '');
    $room   = trim($_POST['room'] ?? '');
    $subj   = trim($_POST['subject'] ?? '');
    $desc   = trim($_POST['description'] ?? '');
    $color  = trim($_POST['cover_color'] ?? '#4f46e5');

    if (!$name) {
        if (isset($_SERVER['HTTP_ACCEPT']) && str_contains($_SERVER['HTTP_ACCEPT'], 'json')) jsonResponse(['error' => 'Name required'], 400);
        redirect(BASE_URL . '/dashboard/teacher.php');
    }

    $code = generateCode(7);
    // Ensure uniqueness
    while (true) {
        $c = $pdo->prepare("SELECT id FROM classes WHERE code=?");
        $c->execute([$code]);
        if (!$c->fetch()) break;
        $code = generateCode(7);
    }

    $stmt = $pdo->prepare("INSERT INTO classes (name, section, room, subject, description, code, teacher_id, cover_color, max_students) VALUES (?,?,?,?,?,?,?,?,40)");
    $stmt->execute([$name, $sec, $room, $subj, $desc, $code, $uid, $color]);
    $classId = $pdo->lastInsertId();

    if (isset($_SERVER['HTTP_ACCEPT']) && str_contains($_SERVER['HTTP_ACCEPT'], 'json')) {
        jsonResponse(['success' => true, 'class_id' => $classId, 'code' => $code]);
    }
    redirect(BASE_URL . "/classroom/index.php?id={$classId}");
}

// ── Upload Logo (Teacher only)
if ($action === 'upload_logo' && $user['role'] === 'teacher') {
    $classId = (int)$_POST['class_id'];
    
    // Verify teacher owns this class
    $chk = $pdo->prepare("SELECT id FROM classes WHERE id=? AND teacher_id=?");
    $chk->execute([$classId, $uid]);
    if (!$chk->fetch()) jsonResponse(['error' => 'Unauthorized'], 403);

    if (empty($_FILES['logo']['name'])) jsonResponse(['error' => 'No file uploaded'], 400);

    $uploadDir = __DIR__ . '/../uploads/logos/';
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

    $ext = strtolower(pathinfo($_FILES['logo']['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp'])) {
        jsonResponse(['error' => 'Only JPG, PNG, WEBP allowed'], 400);
    }

    $fn = 'class_' . $classId . '_' . uniqid() . '.' . $ext;
    if (move_uploaded_file($_FILES['logo']['tmp_name'], $uploadDir . $fn)) {
        $pdo->prepare("UPDATE classes SET logo=? WHERE id=?")->execute([$fn, $classId]);
        jsonResponse(['success' => true, 'logo' => $fn]);
    }
    jsonResponse(['error' => 'Upload failed'], 500);
}

// ── Join Class (Student only)
if ($action === 'join' && $user['role'] === 'student') {
    $code = strtoupper(trim($_POST['code'] ?? ''));
    if (!$code) jsonResponse(['error' => 'Code required'], 400);

    $cls = $pdo->prepare("SELECT * FROM classes WHERE code=?");
    $cls->execute([$code]);
    $class = $cls->fetch();
    if (!$class) jsonResponse(['error' => 'Class not found'], 404);

    // Check already member
    $m = $pdo->prepare("SELECT id FROM class_members WHERE class_id=? AND user_id=?");
    $m->execute([$class['id'], $uid]);
    if ($m->fetch()) jsonResponse(['error' => 'Already enrolled in this class'], 400);

    // Check class capacity
    $mCount = $pdo->prepare("SELECT COUNT(*) FROM class_members WHERE class_id=?");
    $mCount->execute([$class['id']]);
    $totalMembers = $mCount->fetchColumn();
    $maxStudents = $class['max_students'] ?? 40;
    
    if ($totalMembers >= $maxStudents) {
        jsonResponse(['error' => "Class is full ({$totalMembers}/{$maxStudents} students)"], 400);
    }

    $ins = $pdo->prepare("INSERT INTO class_members (class_id, user_id) VALUES (?,?)");
    $ins->execute([$class['id'], $uid]);

    // Notify teacher
    $notif = $pdo->prepare("INSERT INTO notifications (user_id, title, message, type) VALUES (?,?,?,?)");
    $notif->execute([$class['teacher_id'], 'New Student Joined', "{$user['name']} joined {$class['name']}", 'info']);

    jsonResponse(['success' => true, 'class_name' => $class['name']]);
}

// ── Delete Announcement
if (isset($_GET['del_ann']) && $user['role'] === 'teacher') {
    $annId   = (int)$_GET['del_ann'];
    $classId = (int)$_GET['class_id'];
    $del = $pdo->prepare("DELETE FROM announcements WHERE id=? AND class_id=?");
    $del->execute([$annId, $classId]);
    redirect(BASE_URL . "/classroom/index.php?id={$classId}&tab=stream");
}

// ── Add Material
if ($action === 'add_material' && $user['role'] === 'teacher') {
    $classId    = (int)($_POST['class_id'] ?? 0);
    $title      = trim($_POST['title'] ?? '');
    $desc       = trim($_POST['description'] ?? '');
    $type       = trim($_POST['type'] ?? 'file');
    $linkUrl    = trim($_POST['link_url'] ?? '');
    $sendToAll  = isset($_POST['send_to_all']) && $_POST['send_to_all'] === '1';
    $filePath   = '';

    if (!empty($_FILES['file']['name'])) {
        $uploadDir = __DIR__ . '/../uploads/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
        $ext  = pathinfo($_FILES['file']['name'], PATHINFO_EXTENSION);
        $fn   = uniqid() . '.' . $ext;
        move_uploaded_file($_FILES['file']['tmp_name'], $uploadDir . $fn);
        $filePath = $fn;
    }

    if ($sendToAll) {
        // Get all classes taught by this teacher
        $classesStmt = $pdo->prepare("SELECT id FROM classes WHERE teacher_id=? AND status='active'");
        $classesStmt->execute([$user['id']]);
        $allClasses = $classesStmt->fetchAll(PDO::FETCH_COLUMN);

        // Add material to all classes
        foreach ($allClasses as $targetClassId) {
            $ins = $pdo->prepare("INSERT INTO materials (class_id, title, description, file_path, link_url, type) VALUES (?,?,?,?,?,?)");
            $ins->execute([$targetClassId, $title, $desc, $filePath, $linkUrl, $type]);
        }
    } else {
        // Add to current class only
        $ins = $pdo->prepare("INSERT INTO materials (class_id, title, description, file_path, link_url, type) VALUES (?,?,?,?,?,?)");
        $ins->execute([$classId, $title, $desc, $filePath, $linkUrl, $type]);
    }

    redirect(BASE_URL . "/classroom/index.php?id={$classId}&tab=materials");
}

jsonResponse(['error' => 'Invalid action'], 400);
?>
