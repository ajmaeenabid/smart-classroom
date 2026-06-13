<?php
/**
 * User Profile Settings
 */
$pageTitle = 'Profile Settings';
require_once __DIR__ . '/includes/header.php';
requireLogin();

$user = getCurrentUser($pdo);
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid request.';
    } else {
        $fullName = trim($_POST['full_name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $bio = trim($_POST['bio'] ?? '');
        $password = $_POST['new_password'] ?? '';

        if (empty($fullName)) $errors[] = 'Full name is required.';

        if (empty($errors)) {
            if (!empty($password)) {
                if (strlen($password) < 6) {
                    $errors[] = 'Password must be at least 6 characters.';
                } else {
                    $hashed = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("UPDATE users SET full_name = ?, phone = ?, bio = ?, password = ? WHERE id = ?");
                    $stmt->execute([$fullName, $phone, $bio, $hashed, $user['id']]);
                }
            } else {
                $stmt = $pdo->prepare("UPDATE users SET full_name = ?, phone = ?, bio = ? WHERE id = ?");
                $stmt->execute([$fullName, $phone, $bio, $user['id']]);
            }
            
            if(empty($errors)) {
                $_SESSION['user_name'] = $fullName;
                setFlash('success', 'Profile updated successfully.');
                redirect(BASE_URL . '/profile.php');
            }
        }
    }
}

require_once __DIR__ . '/includes/navbar.php';
?>

<div class="page-header">
    <div>
        <h1>Profile Settings</h1>
        <p>Update your personal information and preferences.</p>
    </div>
</div>

<div style="display:grid;grid-template-columns:300px 1fr;gap:32px;">
    
    <!-- Profile Sidebar -->
    <div class="card" style="align-self:start;">
        <div class="card-body text-center" style="padding:32px 20px;">
            <div class="avatar-initials" style="background:var(--primary);width:96px;height:96px;font-size:36px;margin:0 auto 16px;">
                <?php echo getInitials($user['full_name']); ?>
            </div>
            <h3 style="font-size:20px;margin-bottom:4px;"><?php echo e($user['full_name']); ?></h3>
            <p class="text-muted text-sm mb-3"><?php echo e($user['email']); ?></p>
            <span class="role-tag role-<?php echo $user['role']; ?>"><?php echo ucfirst($user['role']); ?></span>
            
            <div style="margin-top:24px;padding-top:24px;border-top:1px solid var(--border);text-align:left;font-size:14px;">
                <p class="mb-2"><strong class="text-muted">Member Since:</strong> <br><?php echo formatDate($user['created_at']); ?></p>
                <?php if($user['phone']): ?>
                    <p><strong class="text-muted">Phone:</strong> <br><?php echo e($user['phone']); ?></p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Edit Form -->
    <div class="card">
        <div class="card-header" style="background:var(--body-bg);color:var(--text);border-bottom:1px solid var(--border);">
            <h3 style="font-size:18px;">Edit Information</h3>
        </div>
        <div class="card-body">
            <?php if (!empty($errors)): ?>
                <div class="flash-message flash-error">
                    <span class="flash-icon">✕</span>
                    <span class="flash-text"><?php echo e($errors[0]); ?></span>
                </div>
            <?php endif; ?>
            <?php displayFlash(); ?>

            <form method="POST" action="">
                <?php echo csrfField(); ?>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label" for="full_name">Full Name *</label>
                        <input type="text" id="full_name" name="full_name" class="form-control" value="<?php echo e($user['full_name']); ?>" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="phone">Phone Number</label>
                        <input type="text" id="phone" name="phone" class="form-control" value="<?php echo e($user['phone']); ?>">
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Email Address (Cannot change)</label>
                    <input type="text" class="form-control" value="<?php echo e($user['email']); ?>" disabled style="background:#F1F5F9;cursor:not-allowed;">
                </div>

                <div class="form-group">
                    <label class="form-label" for="bio">Bio / About Me</label>
                    <textarea id="bio" name="bio" class="form-control"><?php echo e($user['bio']); ?></textarea>
                </div>

                <div style="margin:32px 0;border-top:1px solid var(--border);"></div>
                <h4 style="font-size:16px;margin-bottom:16px;">Security</h4>

                <div class="form-group">
                    <label class="form-label" for="new_password">Change Password</label>
                    <input type="password" id="new_password" name="new_password" class="form-control" placeholder="Leave blank to keep current password">
                    <p class="form-text">Min 6 characters if you wish to change it.</p>
                </div>

                <div style="margin-top:32px;">
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

</main>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
