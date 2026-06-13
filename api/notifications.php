<?php
// Notifications API
require_once __DIR__ . '/../config/db.php';
requireLogin();

$user   = currentUser();
$uid    = $user['id'];
$action = $_GET['mark'] ?? $_POST['action'] ?? '';

if ($action && is_numeric($action)) {
    $pdo->prepare("UPDATE notifications SET is_read=1 WHERE id=? AND user_id=?")->execute([(int)$action, $uid]);
    jsonResponse(['success' => true]);
}

if (($_POST['action'] ?? '') === 'mark_all') {
    $pdo->prepare("UPDATE notifications SET is_read=1 WHERE user_id=?")->execute([$uid]);
    jsonResponse(['success' => true]);
}

// Get unread count
$count = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id=? AND is_read=0");
$count->execute([$uid]);
jsonResponse(['unread' => $count->fetchColumn()]);
?>
