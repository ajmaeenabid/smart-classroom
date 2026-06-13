<?php
/**
 * Manage Classes - Admin
 */
$pageTitle = 'Manage Classes';
require_once __DIR__ . '/../includes/header.php';
requireAdmin();

if (isset($_GET['action'], $_GET['id']) && $_GET['action'] === 'archive') {
    $id = (int)$_GET['id'];
    $stmt = $pdo->prepare("UPDATE classes SET status = IF(status='active', 'archived', 'active') WHERE id = ?");
    $stmt->execute([$id]);
    setFlash('success', 'Class status updated.');
    redirect(BASE_URL . '/admin/manage_classes.php');
}

$stmt = $pdo->query("
    SELECT c.*, u.full_name as teacher_name,
    (SELECT COUNT(*) FROM class_enrollments WHERE class_id = c.id) as students
    FROM classes c
    JOIN users u ON c.teacher_id = u.id
    ORDER BY c.created_at DESC
");
$classes = $stmt->fetchAll();

require_once __DIR__ . '/../includes/navbar.php';
?>

<div class="page-header">
    <div>
        <h1>Manage Classes</h1>
        <p>Review all active and archived classes in the system.</p>
    </div>
</div>

<div class="card">
    <div class="table-wrapper">
        <table class="table">
            <thead>
                <tr>
                    <th>Class Title</th>
                    <th>Teacher</th>
                    <th>Code</th>
                    <th>Students</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($classes as $c): ?>
                <tr>
                    <td>
                        <strong><a href="<?php echo BASE_URL; ?>/class_details.php?id=<?php echo $c['id']; ?>"><?php echo e($c['class_name']); ?></a></strong><br>
                        <span class="text-xs text-muted"><?php echo e($c['subject']); ?></span>
                    </td>
                    <td><?php echo e($c['teacher_name']); ?></td>
                    <td><code><?php echo e($c['class_code']); ?></code></td>
                    <td><?php echo $c['students']; ?></td>
                    <td><?php echo statusBadge($c['status']); ?></td>
                    <td>
                        <a href="?action=archive&id=<?php echo $c['id']; ?>" class="btn btn-sm btn-outline" onclick="return confirm('Change archive status for this class?');">
                            <?php echo $c['status']==='active' ? 'Archive' : 'Unarchive'; ?>
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if(empty($classes)): ?>
                <tr><td colspan="6" class="text-center py-4 text-muted">No classes found.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

</main>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
