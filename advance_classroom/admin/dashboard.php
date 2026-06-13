<?php
/**
 * Admin Dashboard
 */
$pageTitle = 'Admin Dashboard';
require_once __DIR__ . '/../includes/header.php';
requireAdmin();

// Overview stats
$stmt = $pdo->query("SELECT role, COUNT(*) as count FROM users GROUP BY role");
$userStats = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

$totalUsers = array_sum($userStats);
$totalClasses = $pdo->query("SELECT COUNT(*) FROM classes")->fetchColumn();
$totalSubmissions = $pdo->query("SELECT COUNT(*) FROM submissions")->fetchColumn();
$totalMaterials = $pdo->query("SELECT COUNT(*) FROM materials")->fetchColumn();

// Recent signups
$stmt = $pdo->query("SELECT full_name, email, role, created_at FROM users ORDER BY created_at DESC LIMIT 5");
$recentSignups = $stmt->fetchAll();

require_once __DIR__ . '/../includes/navbar.php';
?>

<div class="page-header">
    <div>
        <h1>System Administration</h1>
        <p>Monitor platform health and user activity.</p>
    </div>
</div>

<div class="stats-grid mb-4">
    <div class="stat-card">
        <div class="stat-icon purple">👥</div>
        <div class="stat-info">
            <h3><?php echo $totalUsers; ?></h3>
            <p>Total Users</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon blue">📚</div>
        <div class="stat-info">
            <h3><?php echo $totalClasses; ?></h3>
            <p>Active Classes</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon green">📤</div>
        <div class="stat-info">
            <h3><?php echo $totalSubmissions; ?></h3>
            <p>Total Submissions</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon orange">📁</div>
        <div class="stat-info">
            <h3><?php echo $totalMaterials; ?></h3>
            <p>Resources Shared</p>
        </div>
    </div>
</div>

<div style="display:grid;grid-template-columns:2fr 1fr;gap:24px;">
    <!-- Recent Signups -->
    <div class="card">
        <div class="card-header" style="background:var(--body-bg);color:var(--text);border-bottom:1px solid var(--border);">
            <div class="d-flex justify-between align-center">
                <h3 class="text-base">Recent Registrations</h3>
                <a href="<?php echo BASE_URL; ?>/admin/manage_users.php" class="btn btn-sm btn-outline">View All</a>
            </div>
        </div>
        <div class="table-wrapper">
            <table class="table">
                <thead><tr><th>User</th><th>Role</th><th>Registered</th></tr></thead>
                <tbody>
                    <?php foreach ($recentSignups as $user): ?>
                    <tr>
                        <td>
                            <strong><?php echo e($user['full_name']); ?></strong><br>
                            <span class="text-xs text-muted"><?php echo e($user['email']); ?></span>
                        </td>
                        <td><span class="role-tag role-<?php echo e($user['role']); ?>"><?php echo ucfirst(e($user['role'])); ?></span></td>
                        <td><?php echo timeAgo($user['created_at']); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Quick Stats -->
    <div class="card">
        <div class="card-header" style="background:var(--body-bg);color:var(--text);border-bottom:1px solid var(--border);">
            <h3 class="text-base">User Breakdown</h3>
        </div>
        <div class="card-body">
            <div class="d-flex justify-between mb-3 text-sm border-bottom pb-2">
                <span>Students</span>
                <strong><?php echo $userStats['student'] ?? 0; ?></strong>
            </div>
            <div class="d-flex justify-between mb-3 text-sm border-bottom pb-2">
                <span>Teachers</span>
                <strong><?php echo $userStats['teacher'] ?? 0; ?></strong>
            </div>
            <div class="d-flex justify-between mb-3 text-sm border-bottom pb-2">
                <span>Guardians</span>
                <strong><?php echo $userStats['guardian'] ?? 0; ?></strong>
            </div>
            <div class="d-flex justify-between text-sm">
                <span>Admins</span>
                <strong><?php echo $userStats['admin'] ?? 0; ?></strong>
            </div>
        </div>
    </div>
</div>

</main>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
