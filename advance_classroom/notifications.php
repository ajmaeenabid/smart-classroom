<?php
/**
 * Notifications
 */
$pageTitle = 'Notifications';
require_once __DIR__ . '/includes/header.php';
requireLogin();

$userId = $_SESSION['user_id'];

// Mark all as read when visited
$stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0");
$stmt->execute([$userId]);

// Get notifications
$stmt = $pdo->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 50");
$stmt->execute([$userId]);
$notifications = $stmt->fetchAll();

require_once __DIR__ . '/includes/navbar.php';
?>

<div class="page-header">
    <div>
        <h1>Notifications</h1>
        <p>Stay updated on the latest activities and alerts.</p>
    </div>
</div>

<div class="card" style="max-width:800px;">
    <?php if (empty($notifications)): ?>
        <div class="empty-state">
            <div class="empty-icon">🔕</div>
            <h3>All caught up!</h3>
            <p>You have no notifications at the moment.</p>
        </div>
    <?php else: ?>
        <div style="display:flex;flex-direction:column;">
            <?php foreach ($notifications as $notif): 
                $icons = [
                    'assignment' => '📝', 'submission' => '📤', 'grade' => '✅', 
                    'announcement' => '📢', 'enrollment' => '🔗', 'quiz' => '❓', 'system' => '⚙️'
                ];
                $icon = $icons[$notif['type']] ?? '🔔';
                $bgClass = $notif['is_read'] ? '' : 'background: #EEF2FF;';
            ?>
            <a href="<?php echo $notif['link'] ? e($notif['link']) : '#'; ?>" 
               style="display:flex;align-items:flex-start;gap:16px;padding:20px 24px;border-bottom:1px solid var(--border);text-decoration:none;color:inherit;transition:background 0.2s; <?php echo $bgClass; ?>">
                <div style="font-size:24px;background:var(--card-bg);width:48px;height:48px;border-radius:50%;display:flex;align-items:center;justify-content:center;box-shadow:var(--shadow-sm);flex-shrink:0;">
                    <?php echo $icon; ?>
                </div>
                <div style="flex:1;">
                    <p style="font-size:15px;margin-bottom:4px;color:var(--text);"><?php echo htmlspecialchars_decode(e($notif['message'])); ?></p>
                    <span style="font-size:12px;color:var(--text-muted);"><?php echo timeAgo($notif['created_at']); ?></span>
                </div>
                <?php if (!$notif['is_read']): ?>
                    <div style="width:8px;height:8px;border-radius:50%;background:var(--primary);margin-top:8px;"></div>
                <?php endif; ?>
            </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

</main>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
