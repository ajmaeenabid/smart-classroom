<?php
// =============================================
// Smart Classroom — Notifications
// =============================================
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/layout.php';
requireLogin();

$user = currentUser();
$uid  = $user['id'];

// Mark all as read
if (isset($_GET['mark_all'])) {
    $pdo->prepare("UPDATE notifications SET is_read=1 WHERE user_id=?")->execute([$uid]);
    redirect(BASE_URL . '/global/notifications.php');
}

// Mark one
if (isset($_GET['mark'])) {
    $pdo->prepare("UPDATE notifications SET is_read=1 WHERE id=? AND user_id=?")->execute([(int)$_GET['mark'], $uid]);
    if (isset($_GET['redirect'])) redirect(urldecode($_GET['redirect']));
}

// Delete
if (isset($_GET['delete'])) {
    $pdo->prepare("DELETE FROM notifications WHERE id=? AND user_id=?")->execute([(int)$_GET['delete'], $uid]);
    redirect(BASE_URL . '/global/notifications.php');
}

// Filter
$filter = $_GET['filter'] ?? 'all';
$where  = "WHERE user_id=?";
if ($filter === 'unread') $where .= " AND is_read=0";
if ($filter === 'read')   $where .= " AND is_read=1";

$notifs = $pdo->prepare("SELECT * FROM notifications {$where} ORDER BY created_at DESC LIMIT 50");
$notifs->execute([$uid]);
$notifList = $notifs->fetchAll();

$unread = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id=? AND is_read=0");
$unread->execute([$uid]);
$unreadCount = $unread->fetchColumn();

$typeIcons = ['info'=>'fa-info-circle','warning'=>'fa-exclamation-triangle','success'=>'fa-check-circle','error'=>'fa-times-circle'];
$typeColors = ['info'=>'var(--info)','warning'=>'var(--warning)','success'=>'var(--success)','error'=>'var(--danger)'];

renderHead('Notifications');
?>
<body>
<div class="app-wrapper">
<?php renderSidebar($user, 'notifications.php'); ?>
<div class="main-content">
<?php renderTopbar('Notifications', $user); ?>

<div class="page-content animate-up">
  <div style="max-width:760px;margin:0 auto">

    <!-- Header -->
    <div class="page-header">
      <div>
        <div class="page-title">🔔 Notifications</div>
        <?php if ($unreadCount > 0): ?>
        <div style="font-size:0.875rem;color:var(--text-muted);margin-top:0.25rem"><?= $unreadCount ?> unread notification<?= $unreadCount !== 1 ? 's' : '' ?></div>
        <?php endif; ?>
      </div>
      <div style="display:flex;gap:0.75rem;align-items:center">
        <?php if ($unreadCount > 0): ?>
        <a href="?mark_all=1" class="btn btn-secondary btn-sm"><i class="fas fa-check-double"></i> Mark All Read</a>
        <?php endif; ?>
      </div>
    </div>

    <!-- Filters -->
    <div style="display:flex;gap:0.5rem;margin-bottom:1.5rem">
      <?php foreach ([['all','All'],['unread','Unread'],['read','Read']] as [$f,$label]): ?>
      <a href="?filter=<?= $f ?>" class="btn btn-<?= $filter===$f?'primary':'secondary' ?> btn-sm"><?= $label ?></a>
      <?php endforeach; ?>
    </div>

    <!-- Notification List -->
    <div class="card">
      <?php if (empty($notifList)): ?>
      <div class="empty-state">
        <div class="empty-icon"><i class="fas fa-bell-slash"></i></div>
        <div class="empty-title">No notifications</div>
        <div class="empty-sub">You're all caught up! Notifications about assignments, grades, and class activity will appear here.</div>
      </div>
      <?php else: ?>
      <?php foreach ($notifList as $n): ?>
      <div class="notif-item <?= $n['is_read'] ? '' : 'unread' ?>" onclick="window.location='<?= BASE_URL ?>/global/notifications.php?mark=<?= $n['id'] ?><?= $n['link'] ? '&redirect=' . urlencode(BASE_URL . $n['link']) : '' ?>'">
        <!-- Icon -->
        <div style="width:40px;height:40px;border-radius:50%;background:<?= str_replace(')',',0.15)', str_replace('var(','rgba(', $typeColors[$n['type']] ?? 'var(--info-c--')) ?>;display:flex;align-items:center;justify-content:center;flex-shrink:0;color:<?= $typeColors[$n['type']] ?? 'var(--info)' ?>">
          <i class="fas <?= $typeIcons[$n['type']] ?? 'fa-bell' ?>"></i>
        </div>
        <div style="flex:1">
          <div style="font-size:0.875rem;font-weight:<?= $n['is_read'] ? '500' : '700' ?>;display:flex;align-items:center;gap:0.5rem">
            <?= e($n['title']) ?>
            <?php if (!$n['is_read']): ?><span style="width:8px;height:8px;background:var(--primary);border-radius:50%;display:inline-block;flex-shrink:0"></span><?php endif; ?>
          </div>
          <div class="notif-text" style="color:var(--text-secondary)"><?= e($n['message']) ?></div>
          <div class="notif-time"><?= timeAgo($n['created_at']) ?></div>
        </div>
        <a href="?delete=<?= $n['id'] ?>" class="btn btn-ghost btn-icon btn-sm" onclick="event.stopPropagation()" title="Delete"><i class="fas fa-trash" style="font-size:0.75rem;color:var(--danger)"></i></a>
      </div>
      <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </div>
</div>
</div></div>
<?php renderFooter(); ?>
</body></html>
