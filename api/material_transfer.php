<?php
// =============================================
// Smart Classroom — Material Transfer / Reuse API
// =============================================
require_once __DIR__ . '/../config/db.php';
requireLogin();

header('Content-Type: application/json');

$user   = currentUser();
$uid    = $user['id'];
$role   = $user['role'];
$action = $_POST['action'] ?? $_GET['action'] ?? '';

if ($role !== 'teacher') {
    echo json_encode(['error' => 'Only teachers can transfer materials']);
    exit;
}

// ── Get Teacher's Past Courses with Materials ──
if ($action === 'get_courses') {
    $currentClassId = (int)($_GET['current_class_id'] ?? 0);

    // Get all active classes owned by this teacher (excluding current class)
    $stmt = $pdo->prepare("SELECT c.id, c.name, c.section, c.subject, c.created_at,
        (SELECT COUNT(*) FROM materials WHERE class_id=c.id) as material_count
        FROM classes c
        WHERE c.teacher_id=? AND c.id != ? AND (c.status='active' OR c.status IS NULL)
        ORDER BY c.created_at DESC");
    $stmt->execute([$uid, $currentClassId]);
    $courses = $stmt->fetchAll();

    echo json_encode(['success' => true, 'courses' => $courses]);
    exit;
}

// ── Get Materials for a Specific Course ────────
if ($action === 'get_materials') {
    $sourceClassId = (int)($_GET['source_class_id'] ?? 0);

    // Verify teacher owns the source class and it's active
    $chk = $pdo->prepare("SELECT id, name FROM classes WHERE id=? AND teacher_id=? AND (status='active' OR status IS NULL)");
    $chk->execute([$sourceClassId, $uid]);
    if (!$chk->fetch()) {
        echo json_encode(['error' => 'Unauthorized or class archived']);
        exit;
    }

    $stmt = $pdo->prepare("SELECT id, title, description, file_path, link_url, type, created_at FROM materials WHERE class_id=? ORDER BY created_at DESC");
    $stmt->execute([$sourceClassId]);
    $materials = $stmt->fetchAll();

    echo json_encode(['success' => true, 'materials' => $materials]);
    exit;
}

// ── Copy Selected Materials to Target Class ────
if ($action === 'transfer') {
    $targetClassId = (int)($_POST['target_class_id'] ?? 0);
    $materialIds   = $_POST['material_ids'] ?? [];

    // Verify teacher owns the target class and it's active
    $chk = $pdo->prepare("SELECT id FROM classes WHERE id=? AND teacher_id=? AND (status='active' OR status IS NULL)");
    $chk->execute([$targetClassId, $uid]);
    if (!$chk->fetch()) {
        echo json_encode(['error' => 'Unauthorized: not your class or class archived']);
        exit;
    }

    if (empty($materialIds) || !is_array($materialIds)) {
        echo json_encode(['error' => 'No materials selected']);
        exit;
    }

    // Sanitize material IDs
    $materialIds = array_map('intval', $materialIds);
    $placeholders = implode(',', array_fill(0, count($materialIds), '?'));

    // Fetch source materials (verify they belong to teacher's active classes)
    $stmt = $pdo->prepare("SELECT m.* FROM materials m JOIN classes c ON c.id=m.class_id WHERE c.teacher_id=? AND (c.status='active' OR c.status IS NULL) AND m.id IN ($placeholders)");
    $params = array_merge([$uid], $materialIds);
    $stmt->execute($params);
    $sourceMaterials = $stmt->fetchAll();

    if (empty($sourceMaterials)) {
        echo json_encode(['error' => 'No valid materials found']);
        exit;
    }

    $copied = 0;
    $ins = $pdo->prepare("INSERT INTO materials (class_id, title, description, file_path, link_url, type) VALUES (?,?,?,?,?,?)");

    foreach ($sourceMaterials as $m) {
        // Copy file if it's a file type (not a link)
        $filePath = $m['file_path'];
        if ($filePath && $m['type'] === 'file') {
            $srcPath = __DIR__ . '/../uploads/' . $filePath;
            if (file_exists($srcPath)) {
                $ext = pathinfo($filePath, PATHINFO_EXTENSION);
                $newFile = uniqid('copy_') . '.' . $ext;
                copy($srcPath, __DIR__ . '/../uploads/' . $newFile);
                $filePath = $newFile;
            }
        }

        $ins->execute([
            $targetClassId,
            $m['title'],
            $m['description'],
            $filePath,
            $m['link_url'],
            $m['type']
        ]);
        $copied++;
    }

    echo json_encode(['success' => true, 'copied' => $copied]);
    exit;
}

echo json_encode(['error' => 'Invalid action']);
?>
