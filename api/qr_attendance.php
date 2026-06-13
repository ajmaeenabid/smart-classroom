<?php
// =============================================
// Smart Classroom — QR Attendance API
// =============================================
require_once __DIR__ . '/../config/db.php';
requireLogin();

header('Content-Type: application/json');

$user   = currentUser();
$uid    = $user['id'];
$role   = $user['role'];
$action = $_POST['action'] ?? $_GET['action'] ?? '';

// ── Generate QR (Teacher Only) ─────────────────
if ($action === 'generate' && $role === 'teacher') {
    $classId  = (int)($_POST['class_id'] ?? 0);
    $date     = $_POST['date'] ?? date('Y-m-d');
    $duration = (int)($_POST['duration'] ?? 5);
    // Clamp duration to 2-5 minutes as per requirement
    if ($duration < 2) $duration = 2;
    if ($duration > 5) $duration = 5;

    // Verify teacher owns this class and it's active
    $chk = $pdo->prepare("SELECT id, status FROM classes WHERE id=? AND teacher_id=?");
    $chk->execute([$classId, $uid]);
    $class = $chk->fetch();
    if (!$class) {
        echo json_encode(['error' => 'Unauthorized']); exit;
    }
    if (($class['status'] ?? 'active') === 'archived') {
        echo json_encode(['error' => 'Cannot generate QR for archived classroom']); exit;
    }

    // Expire old sessions for this class+date
    $pdo->prepare("UPDATE qr_attendance_sessions SET is_active=0 WHERE class_id=? AND date=?")->execute([$classId, $date]);

    // Create new session token
    $token   = bin2hex(random_bytes(16));
    // Simple original approach - use server time consistently
    $expires = date('Y-m-d H:i:s', strtotime("+{$duration} minutes"));

    $ins = $pdo->prepare("INSERT INTO qr_attendance_sessions (class_id, teacher_id, date, token, expires_at, is_active) VALUES (?,?,?,?,?,1)");
    $ins->execute([$classId, $uid, $date, $token, $expires]);

    // Build the scan URL
    $scanUrl = BASE_URL . '/classroom/qr_scan.php?token=' . $token;

    // Generate QR code using Google Charts API (works offline once loaded)
    $qrUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=250x250&data=' . urlencode($scanUrl);

    // Convert to ISO 8601 format for JavaScript compatibility
    $expiresIso = date('c', strtotime($expires));
    echo json_encode([
        'success'   => true,
        'token'     => $token,
        'scan_url'  => $scanUrl,
        'qr_url'    => $qrUrl,
        'expires'   => $expiresIso,
        'duration'  => $duration,
        'date'      => $date,
    ]);
    exit;
}

// ── Validate / Scan QR (Student) ──────────────
if ($action === 'scan' && $role === 'student') {
    $token  = trim($_POST['token'] ?? $_GET['token'] ?? '');
    if (!$token) { echo json_encode(['error' => 'No token provided']); exit; }

    // Find session
    $sess = $pdo->prepare("SELECT * FROM qr_attendance_sessions WHERE token=? AND is_active=1");
    $sess->execute([$token]);
    $session = $sess->fetch();

    if (!$session) {
        echo json_encode(['error' => 'Invalid or expired QR code. Please ask your teacher to regenerate.']);
        exit;
    }

    // Check expiry
    if (strtotime($session['expires_at']) < time()) {
        $pdo->prepare("UPDATE qr_attendance_sessions SET is_active=0 WHERE id=?")->execute([$session['id']]);
        echo json_encode(['error' => 'QR code has expired. Ask teacher to generate a new one.']);
        exit;
    }

    // Check if student is a member of the class
    $mem = $pdo->prepare("SELECT id FROM class_members WHERE class_id=? AND user_id=?");
    $mem->execute([$session['class_id'], $uid]);
    if (!$mem->fetch()) {
        echo json_encode(['error' => 'You are not enrolled in this class.']);
        exit;
    }

    // Prevent duplicate marking
    $dup = $pdo->prepare("SELECT id FROM attendance WHERE class_id=? AND student_id=? AND date=?");
    $dup->execute([$session['class_id'], $uid, $session['date']]);
    if ($dup->fetch()) {
        echo json_encode(['error' => 'already_marked', 'message' => 'Your attendance is already recorded for today!']);
        exit;
    }

    // Mark attendance as present
    $ins = $pdo->prepare("INSERT INTO attendance (class_id, student_id, date, status) VALUES (?,?,?,'present') ON DUPLICATE KEY UPDATE status='present'");
    $ins->execute([$session['class_id'], $uid, $session['date']]);

    // Get class name for confirmation
    $cls = $pdo->prepare("SELECT name FROM classes WHERE id=?");
    $cls->execute([$session['class_id']]);
    $className = $cls->fetchColumn();

    // Notify student their attendance was recorded
    $pdo->prepare("INSERT INTO notifications (user_id, title, message, type, link) VALUES (?,?,?,?,?)")
        ->execute([
            $uid,
            'Attendance Recorded',
            'Your attendance was marked Present in ' . $className . ' for ' . date('M d, Y', strtotime($session['date'])),
            'success',
            '/classroom/attendance.php?class_id=' . $session['class_id'],
        ]);

    echo json_encode([
        'success'    => true,
        'message'    => "✅ Attendance marked as Present!",
        'class_name' => $className,
        'date'       => $session['date'],
    ]);
    exit;
}

// ── Get active session status ──────────────────
if ($action === 'status') {
    try {
        $classId = (int)($_GET['class_id'] ?? 0);
        $date    = $_GET['date'] ?? date('Y-m-d');

        error_log("QR Status Check: class_id=$classId, date=$date, user_id=$uid, role=$role");

        // Verify user is enrolled in this class (teacher or student)
        $member = $pdo->prepare("SELECT id FROM class_members WHERE class_id=? AND user_id=? UNION SELECT id FROM classes WHERE id=? AND teacher_id=?");
        $member->execute([$classId, $uid, $classId, $uid]);
        $enrollment = $member->fetch();
        if (!$enrollment) {
            error_log("QR Status: User $uid not enrolled in class $classId");
            echo json_encode(['error' => 'Not enrolled in this class', 'debug' => "uid=$uid, class=$classId, date=$date"]);
            exit;
        }
        error_log("QR Status: User $uid is enrolled");

        $sess = $pdo->prepare("SELECT * FROM qr_attendance_sessions WHERE class_id=? AND date=? AND is_active=1 AND expires_at > NOW()");
        $sess->execute([$classId, $date]);
        $active = $sess->fetch();

        if ($active) {
            $expiresIso = date('c', strtotime($active['expires_at']));
            // Return QR URL and token to both teachers and students
            $scanUrl = BASE_URL . '/classroom/qr_scan.php?token=' . $active['token'];
            $qrUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=' . urlencode($scanUrl);
            $response = [
                'active' => true,
                'expires' => $expiresIso,
                'qr_url' => $qrUrl,
                'token' => $active['token']
            ];
            echo json_encode($response);
        } else {
            echo json_encode(['active' => false, 'expires' => null]);
        }
    } catch (Exception $e) {
        error_log("QR Status Error: " . $e->getMessage());
        echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
    }
    exit;
}

// ── Deactivate Session ─────────────────────────
if ($action === 'deactivate' && $role === 'teacher') {
    $classId = (int)($_POST['class_id'] ?? 0);
    $pdo->prepare("UPDATE qr_attendance_sessions SET is_active=0 WHERE class_id=? AND teacher_id=?")->execute([$classId, $uid]);
    echo json_encode(['success' => true]);
    exit;
}

// ── Live Attendance List (for QR session) ────────
if ($action === 'live_list' && $role === 'teacher') {
    $classId = (int)($_GET['class_id'] ?? 0);
    $date    = $_GET['date'] ?? date('Y-m-d');
    $att = $pdo->prepare("SELECT u.name, a.status, a.marked_at FROM attendance a JOIN users u ON u.id=a.student_id WHERE a.class_id=? AND a.date=? ORDER BY a.marked_at DESC");
    $att->execute([$classId, $date]);
    $list = $att->fetchAll();
    echo json_encode(['success' => true, 'list' => $list, 'count' => count($list)]);
    exit;
}

echo json_encode(['error' => 'Invalid action']);
?>
