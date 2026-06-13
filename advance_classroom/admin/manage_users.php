<?php
/**
 * Manage Users - Admin
 */
$pageTitle = 'Manage Users';
require_once __DIR__ . '/../includes/header.php';
requireAdmin();

if (isset($_GET['action'], $_GET['id']) && $_GET['action'] === 'toggle_status') {
    $id = (int)$_GET['id'];
    $stmt = $pdo->prepare("UPDATE users SET status = IF(status='active', 'blocked', 'active') WHERE id = ? AND id != ?");
    $stmt->execute([$id, $_SESSION['user_id']]);
    setFlash('success', 'User status updated.');
    redirect(BASE_URL . '/admin/manage_users.php');
}

$search = $_GET['search'] ?? '';
$roleFilter = $_GET['role'] ?? '';

$sql = "SELECT * FROM users WHERE id != ?";
$params = [$_SESSION['user_id']];

if ($search) {
    $sql .= " AND (full_name LIKE ? OR email LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}
if ($roleFilter) {
    $sql .= " AND role = ?";
    $params[] = $roleFilter;
}

$sql .= " ORDER BY created_at DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$users = $stmt->fetchAll();

require_once __DIR__ . '/../includes/navbar.php';
?>

<div class="page-header">
    <div>
        <h1>Manage Users</h1>
        <p>View, block, or search system users.</p>
    </div>
</div>

<div class="card mb-4 mt-2">
    <div class="card-body py-3">
        <form method="GET" action="" class="d-flex align-center gap-2 flex-wrap">
            <input type="text" name="search" class="form-control" placeholder="Search name or email..." value="<?php echo e($search); ?>" style="max-width:300px;">
            <select name="role" class="form-control" style="width:auto;">
                <option value="">All Roles</option>
                <option value="student" <?php if($roleFilter==='student') echo 'selected';?>>Student</option>
                <option value="teacher" <?php if($roleFilter==='teacher') echo 'selected';?>>Teacher</option>
                <option value="guardian" <?php if($roleFilter==='guardian') echo 'selected';?>>Guardian</option>
                <option value="admin" <?php if($roleFilter==='admin') echo 'selected';?>>Admin</option>
            </select>
            <button type="submit" class="btn btn-primary">Filter</button>
            <?php if($search || $roleFilter): ?>
                <a href="<?php echo BASE_URL; ?>/admin/manage_users.php" class="btn btn-outline" style="padding:10px 16px;">Clear</a>
            <?php endif; ?>
        </form>
    </div>
</div>

<div class="card">
    <div class="table-wrapper">
        <table class="table">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Role</th>
                    <th>Status</th>
                    <th>Joined</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $u): ?>
                <tr>
                    <td><strong><?php echo e($u['full_name']); ?></strong></td>
                    <td class="text-muted"><?php echo e($u['email']); ?></td>
                    <td><span class="role-tag role-<?php echo e($u['role']); ?>"><?php echo ucfirst(e($u['role'])); ?></span></td>
                    <td><?php echo statusBadge($u['status']); ?></td>
                    <td class="text-sm"><?php echo formatDate($u['created_at']); ?></td>
                    <td>
                        <a href="?action=toggle_status&id=<?php echo $u['id']; ?>" class="btn btn-sm <?php echo $u['status']==='active' ? 'btn-danger' : 'btn-success'; ?>" onclick="return confirm('Change status for this user?');">
                            <?php echo $u['status']==='active' ? 'Block' : 'Unblock'; ?>
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if(empty($users)): ?>
                <tr><td colspan="6" class="text-center py-4 text-muted">No users found matching criteria.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

</main>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
