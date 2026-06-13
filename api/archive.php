<?php
// =============================================
// Smart Classroom — Archive & Personal Storage API
// =============================================
require_once __DIR__ . '/../config/db.php';
requireLogin();

header('Content-Type: application/json');

$user   = currentUser();
$uid    = $user['id'];
$role   = $user['role'];
$action = $_POST['action'] ?? $_GET['action'] ?? '';

// ── Archive (Soft-Delete) a Classroom (Teacher Only) ──
if ($action === 'archive_class' && $role === 'teacher') {
    $classId = (int)($_POST['class_id'] ?? 0);

    // Verify teacher owns this class
    $chk = $pdo->prepare("SELECT * FROM classes WHERE id=? AND teacher_id=? AND status='active'");
    $chk->execute([$classId, $uid]);
    $class = $chk->fetch();
    if (!$class) {
        echo json_encode(['error' => 'Class not found or not yours']);
        exit;
    }

    $deleteAfter = date('Y-m-d H:i:s', strtotime('+48 hours'));

    // Insert into archived_classes
    $ins = $pdo->prepare("INSERT INTO archived_classes (original_class_id, name, section, subject, room, code, teacher_id, cover_color, description, delete_after) VALUES (?,?,?,?,?,?,?,?,?,?,?)");
    $ins->execute([
        $class['id'], $class['name'], $class['section'], $class['subject'],
        $class['room'], $class['code'], $class['teacher_id'], $class['cover_color'],
        $class['description'], $deleteAfter
    ]);

    // Mark class as archived
    $pdo->prepare("UPDATE classes SET status='archived' WHERE id=?")->execute([$classId]);

    // Notify all enrolled students
    $members = $pdo->prepare("SELECT user_id FROM class_members WHERE class_id=?");
    $members->execute([$classId]);
    foreach ($members->fetchAll() as $m) {
        $notif = $pdo->prepare("INSERT INTO notifications (user_id, title, message, type, link) VALUES (?,?,?,?,?)");
        $notif->execute([
            $m['user_id'],
            'Classroom Archived',
            "Classroom \"{$class['name']}\" will be permanently deleted in 2 days. Save your materials before then!",
            'warning',
            BASE_URL . "/classroom/index.php?id={$classId}&tab=materials"
        ]);
    }

    echo json_encode(['success' => true, 'delete_after' => $deleteAfter]);
    exit;
}

// ── Restore an Archived Classroom (Teacher Only) ──
if ($action === 'restore_class' && $role === 'teacher') {
    $classId = (int)($_POST['class_id'] ?? 0);

    // Verify teacher owns this class and it's archived
    $chk = $pdo->prepare("SELECT * FROM classes WHERE id=? AND teacher_id=? AND status='archived'");
    $chk->execute([$classId, $uid]);
    $class = $chk->fetch();
    if (!$class) {
        echo json_encode(['error' => 'Class not found or not archived']);
        exit;
    }

    // Restore class
    $pdo->prepare("UPDATE classes SET status='active' WHERE id=?")->execute([$classId]);

    // Remove from archived_classes
    $pdo->prepare("DELETE FROM archived_classes WHERE original_class_id=?")->execute([$classId]);

    // Notify students
    $members = $pdo->prepare("SELECT user_id FROM class_members WHERE class_id=?");
    $members->execute([$classId]);
    foreach ($members->fetchAll() as $m) {
        $notif = $pdo->prepare("INSERT INTO notifications (user_id, title, message, type) VALUES (?,?,?,?,?)");
        $notif->execute([$m['user_id'], 'Classroom Restored', "Classroom \"{$class['name']}\" has been restored!", 'success']);
    }

    echo json_encode(['success' => true]);
    exit;
}

// ── Permanently Delete Expired Archives (Cron/On-demand) ──
if ($action === 'purge_expired') {
    // Find expired archives
    $expired = $pdo->prepare("SELECT * FROM archived_classes WHERE delete_after < NOW()");
    $expired->execute();
    foreach ($expired->fetchAll() as $arch) {
        $cid = $arch['original_class_id'];
        // Delete the class and all related data (CASCADE handles it)
        $pdo->prepare("DELETE FROM classes WHERE id=?")->execute([$cid]);
        $pdo->prepare("DELETE FROM archived_classes WHERE original_class_id=?")->execute([$cid]);
    }
    echo json_encode(['success' => true, 'purged' => $expired->rowCount()]);
    exit;
}

// ── Get Archived Classes List ──
if ($action === 'list_archived') {
    if ($role === 'teacher') {
        $stmt = $pdo->prepare("SELECT ac.*, (SELECT COUNT(*) FROM class_members WHERE class_id=ac.original_class_id) as member_count FROM archived_classes ac WHERE ac.teacher_id=? ORDER BY ac.archived_at DESC");
        $stmt->execute([$uid]);
    } else {
        // Students see archived classes they were members of
        $stmt = $pdo->prepare("SELECT ac.* FROM archived_classes ac JOIN class_members cm ON cm.class_id=ac.original_class_id WHERE cm.user_id=? ORDER BY ac.archived_at DESC");
        $stmt->execute([$uid]);
    }
    $list = $stmt->fetchAll();
    echo json_encode(['success' => true, 'archives' => $list]);
    exit;
}

// ── Save All Materials to Personal Archive (Student) ──
if ($action === 'save_to_archive' && $role === 'student') {
    $classId = (int)($_POST['class_id'] ?? 0);

    // Verify student is a member
    $mem = $pdo->prepare("SELECT cm.class_id, c.name as class_name FROM class_members cm JOIN classes c ON c.id=cm.class_id WHERE cm.class_id=? AND cm.user_id=?");
    $mem->execute([$classId, $uid]);
    $classInfo = $mem->fetch();
    if (!$classInfo) {
        echo json_encode(['error' => 'You are not enrolled in this class']);
        exit;
    }

    // Check if folder already exists
    $existing = $pdo->prepare("SELECT id FROM student_archive WHERE student_id=? AND source_class_id=?");
    $existing->execute([$uid, $classId]);
    $existingArchive = $existing->fetch();

    if ($existingArchive) {
        $archiveId = $existingArchive['id'];
    } else {
        // Create folder named like CSC303_Fall2024
        $folderName = preg_replace('/[^A-Za-z0-9]/', '', $classInfo['class_name']);
        $ins = $pdo->prepare("INSERT INTO student_archive (student_id, folder_name, source_class_id, source_class_name) VALUES (?,?,?,?)");
        $ins->execute([$uid, $folderName, $classId, $classInfo['class_name']]);
        $archiveId = $pdo->lastInsertId();
    }

    // Get all materials from this class
    $materials = $pdo->prepare("SELECT * FROM materials WHERE class_id=?");
    $materials->execute([$classId]);
    $copied = 0;

    $insItem = $pdo->prepare("INSERT INTO student_archive_items (archive_id, title, description, file_path, link_url, type, original_material_id) VALUES (?,?,?,?,?,?,?)");
    foreach ($materials->fetchAll() as $m) {
        // Check if already saved
        $dup = $pdo->prepare("SELECT id FROM student_archive_items WHERE archive_id=? AND original_material_id=?");
        $dup->execute([$archiveId, $m['id']]);
        if ($dup->fetch()) continue;

        // Copy file if it's a file type
        $filePath = $m['file_path'];
        if ($filePath && $m['type'] === 'file') {
            $srcPath = __DIR__ . '/../uploads/' . $filePath;
            if (file_exists($srcPath)) {
                $ext = pathinfo($filePath, PATHINFO_EXTENSION);
                $newFile = uniqid('arch_') . '.' . $ext;
                copy($srcPath, __DIR__ . '/../uploads/' . $newFile);
                $filePath = $newFile;
            }
        }

        $insItem->execute([$archiveId, $m['title'], $m['description'], $filePath, $m['link_url'], $m['type'], $m['id']]);
        $copied++;
    }

    echo json_encode(['success' => true, 'copied' => $copied, 'archive_id' => $archiveId]);
    exit;
}

// ── Get Student's Personal Archive ──
if ($action === 'my_archive' && $role === 'student') {
    $stmt = $pdo->prepare("SELECT sa.*, (SELECT COUNT(*) FROM student_archive_items WHERE archive_id=sa.id) as item_count FROM student_archive sa WHERE sa.student_id=? ORDER BY sa.created_at DESC");
    $stmt->execute([$uid]);
    $folders = $stmt->fetchAll();
    echo json_encode(['success' => true, 'folders' => $folders]);
    exit;
}

// ── Get Items in a Personal Archive Folder ──
if ($action === 'archive_items') {
    $archiveId = (int)($_GET['archive_id'] ?? 0);

    // Verify ownership
    $chk = $pdo->prepare("SELECT * FROM student_archive WHERE id=? AND student_id=?");
    $chk->execute([$archiveId, $uid]);
    if (!$chk->fetch() && $role === 'student') {
        echo json_encode(['error' => 'Unauthorized']);
        exit;
    }

    $items = $pdo->prepare("SELECT * FROM student_archive_items WHERE archive_id=? ORDER BY created_at DESC");
    $items->execute([$archiveId]);
    echo json_encode(['success' => true, 'items' => $items->fetchAll()]);
    exit;
}

// ── Delete a Personal Archive Folder ──
if ($action === 'delete_archive' && $role === 'student') {
    $archiveId = (int)($_POST['archive_id'] ?? 0);

    $chk = $pdo->prepare("SELECT * FROM student_archive WHERE id=? AND student_id=?");
    $chk->execute([$archiveId, $uid]);
    if (!$chk->fetch()) {
        echo json_encode(['error' => 'Unauthorized']);
        exit;
    }

    $pdo->prepare("DELETE FROM student_archive WHERE id=?")->execute([$archiveId]);
    echo json_encode(['success' => true]);
    exit;
}

echo json_encode(['error' => 'Invalid action']);
?>
