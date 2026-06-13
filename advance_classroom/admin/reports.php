<?php
/**
 * Admin Reports
 */
$pageTitle = 'System Reports';
require_once __DIR__ . '/../includes/header.php';
requireAdmin();
require_once __DIR__ . '/../includes/navbar.php';
?>

<div class="page-header">
    <div>
        <h1>System Reports</h1>
        <p>Generate analytical exports of platform activity.</p>
    </div>
</div>

<div style="display:grid;grid-template-columns:repeat(auto-fill, minmax(320px, 1fr));gap:24px;">

    <!-- User Report -->
    <div class="card text-center" style="padding:40px 20px;">
        <span style="font-size:48px;display:block;margin-bottom:16px;">👥</span>
        <h3 style="font-size:18px;margin-bottom:8px;">Users List</h3>
        <p class="text-sm text-muted mb-4">Export all registered users, their roles, and status.</p>
        <a href="<?php echo BASE_URL; ?>/export.php?type=users" class="btn btn-primary btn-block">Download CSV</a>
    </div>

    <!-- Classes Report -->
    <div class="card text-center" style="padding:40px 20px;">
        <span style="font-size:48px;display:block;margin-bottom:16px;">📚</span>
        <h3 style="font-size:18px;margin-bottom:8px;">Classes Summary</h3>
        <p class="text-sm text-muted mb-4">Export all classes with their enrollment statistics.</p>
        <a href="<?php echo BASE_URL; ?>/export.php?type=classes" class="btn btn-primary btn-block">Download CSV</a>
    </div>

    <!-- Complete DB Backup Note -->
    <div class="card text-center" style="padding:40px 20px;border:1px dashed var(--warning);background:#FEF3C7;">
        <span style="font-size:48px;display:block;margin-bottom:16px;">💾</span>
        <h3 style="font-size:18px;margin-bottom:8px;color:#92400E;">Full Database Backup</h3>
        <p class="text-sm mb-4" style="color:#92400E;">To perform a full SQL backup of all 13 tables, please use your MySQL administration tool (e.g. phpMyAdmin).</p>
    </div>

</div>

</main>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
