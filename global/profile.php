<?php
// =============================================
// Smart Classroom — Profile & Settings
// =============================================
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/layout.php';
requireLogin();

$user = currentUser();
$uid  = $user['id'];

$success = $error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'update_profile') {
        $name  = trim($_POST['name'] ?? '');
        $bio   = trim($_POST['bio'] ?? '');
        $phone = trim($_POST['phone'] ?? '');

        // Avatar upload
        $avatar = $user['avatar'] ?? '';
        if (!empty($_FILES['avatar']['name'])) {
            $uploadDir = __DIR__ . '/../uploads/avatars/';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
            $ext    = pathinfo($_FILES['avatar']['name'], PATHINFO_EXTENSION);
            $fname  = 'avatar_' . $uid . '.' . $ext;
            if (move_uploaded_file($_FILES['avatar']['tmp_name'], $uploadDir . $fname)) {
                $avatar = 'avatars/' . $fname;
            }
        }

        $upd = $pdo->prepare("UPDATE users SET name=?, bio=?, phone=?, avatar=? WHERE id=?");
        $upd->execute([$name, $bio, $phone, $avatar, $uid]);

        // Refresh session
        $s = $pdo->prepare("SELECT * FROM users WHERE id=?");
        $s->execute([$uid]);
        $_SESSION['user'] = $s->fetch();
        $user = $_SESSION['user'];
        $success = 'Profile updated successfully!';
    }

    if ($action === 'change_password') {
        $current = $_POST['current_password'] ?? '';
        $new     = $_POST['new_password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';

        if (!password_verify($current, $user['password'])) {
            $error = 'Current password is incorrect.';
        } elseif (strlen($new) < 6) {
            $error = 'New password must be at least 6 characters.';
        } elseif ($new !== $confirm) {
            $error = 'Passwords do not match.';
        } else {
            $hash = password_hash($new, PASSWORD_DEFAULT);
            $pdo->prepare("UPDATE users SET password=? WHERE id=?")->execute([$hash, $uid]);
            $success = 'Password changed successfully!';
        }
    }
}

$activeTab = $_GET['tab'] ?? 'profile';

renderHead('Profile & Settings');
?>
<body>
<div class="app-wrapper">
<?php renderSidebar($user, 'profile.php'); ?>
<div class="main-content">
<?php renderTopbar('Profile & Settings', $user); ?>

<div class="page-content animate-up">
  <div style="max-width:800px;margin:0 auto">

    <!-- Profile Header -->
    <div class="card" style="margin-bottom:1.5rem;background:linear-gradient(135deg,rgba(99,102,241,0.1),rgba(168,85,247,0.08))">
      <div style="display:flex;align-items:center;gap:1.5rem;flex-wrap:wrap">
        <div style="position:relative">
          <div class="avatar avatar-xl" style="background:linear-gradient(135deg,var(--primary),var(--purple))">
            <?php if (!empty($user['avatar'])): ?>
            <img src="<?= BASE_URL ?>/uploads/<?= e($user['avatar']) ?>" alt="Avatar">
            <?php else: ?>
            <?= strtoupper($user['name'][0]) ?>
            <?php endif; ?>
          </div>
          <label for="quick-avatar" style="position:absolute;bottom:-4px;right:-4px;width:28px;height:28px;background:var(--primary);border-radius:50%;display:flex;align-items:center;justify-content:center;cursor:pointer;border:2px solid var(--bg-card)">
            <i class="fas fa-camera" style="font-size:0.65rem;color:white"></i>
          </label>
        </div>
        <div>
          <div style="font-size:1.4rem;font-weight:800"><?= e($user['name']) ?></div>
          <div style="font-size:0.875rem;color:var(--text-muted)"><?= e($user['email']) ?></div>
          <div style="margin-top:0.375rem">
            <span class="badge badge-primary" style="text-transform:capitalize"><?= $user['role'] ?> Account</span>
            <span class="badge badge-success" style="margin-left:0.5rem">Active</span>
          </div>
        </div>
      </div>
    </div>

    <?php if ($success): ?><div class="auto-dismiss" style="background:rgba(16,185,129,0.1);border:1px solid rgba(16,185,129,0.3);border-radius:var(--radius-sm);padding:0.875rem 1rem;color:var(--success);display:flex;align-items:center;gap:0.5rem;margin-bottom:1rem"><i class="fas fa-check-circle"></i> <?= e($success) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="auto-dismiss" style="background:rgba(239,68,68,0.1);border:1px solid rgba(239,68,68,0.3);border-radius:var(--radius-sm);padding:0.875rem 1rem;color:var(--danger);display:flex;align-items:center;gap:0.5rem;margin-bottom:1rem"><i class="fas fa-times-circle"></i> <?= e($error) ?></div><?php endif; ?>

    <!-- Tabs -->
    <div style="display:flex;gap:0.25rem;background:var(--bg-surface);border:1px solid var(--border);border-radius:var(--radius-md);padding:4px;margin-bottom:1.5rem">
      <?php foreach ([['profile','fa-user','Profile'],['security','fa-shield-alt','Security'],['notifications','fa-bell','Preferences']] as [$t,$ic,$lb]): ?>
      <a href="?tab=<?= $t ?>" class="tab-item <?= $activeTab===$t?'active':'' ?>" style="flex:1;justify-content:center;border-bottom:none;border-radius:var(--radius-sm);padding:0.625rem"><i class="fas <?= $ic ?>"></i> <?= $lb ?></a>
      <?php endforeach; ?>
    </div>

    <?php if ($activeTab === 'profile'): ?>
    <!-- Profile Form -->
    <div class="card">
      <div class="card-header"><div class="card-title"><i class="fas fa-user" style="color:var(--primary)"></i> Personal Information</div></div>
      <form method="POST" enctype="multipart/form-data">
        <input type="hidden" name="action" value="update_profile">
        <input type="file" id="quick-avatar" name="avatar" accept="image/*" style="display:none" onchange="this.form.submit()">
        <div style="display:flex;flex-direction:column;gap:1rem">
          <div class="form-group">
            <label class="form-label">Full Name</label>
            <input type="text" name="name" class="form-control" value="<?= e($user['name']) ?>" required>
          </div>
          <div class="form-group">
            <label class="form-label">Email Address</label>
            <input type="email" class="form-control" value="<?= e($user['email']) ?>" readonly style="opacity:0.6;cursor:not-allowed">
          </div>
          <div class="form-group">
            <label class="form-label">Phone Number</label>
            <div class="input-group"><i class="fas fa-phone input-icon"></i><input type="tel" name="phone" class="form-control" value="<?= e($user['phone'] ?? '') ?>" placeholder="+880 1X-XXXX-XXXX"></div>
          </div>
          <div class="form-group">
            <label class="form-label">Bio</label>
            <textarea name="bio" class="form-control" rows="3" data-maxlength="300" placeholder="Tell something about yourself..."><?= e($user['bio'] ?? '') ?></textarea>
          </div>
          <div class="form-group">
            <label class="form-label">Profile Picture</label>
            <input type="file" name="avatar" accept="image/*" class="form-control">
          </div>
        </div>
        <div style="display:flex;justify-content:flex-end;margin-top:1.5rem">
          <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save Changes</button>
        </div>
      </form>
    </div>

    <?php elseif ($activeTab === 'security'): ?>
    <!-- Password Form -->
    <div class="card">
      <div class="card-header"><div class="card-title"><i class="fas fa-lock" style="color:var(--warning)"></i> Change Password</div></div>
      <form method="POST">
        <input type="hidden" name="action" value="change_password">
        <div style="display:flex;flex-direction:column;gap:1rem">
          <div class="form-group"><label class="form-label">Current Password</label><input type="password" name="current_password" class="form-control" required></div>
          <div class="form-group"><label class="form-label">New Password</label><input type="password" name="new_password" class="form-control" required minlength="6"></div>
          <div class="form-group"><label class="form-label">Confirm New Password</label><input type="password" name="confirm_password" class="form-control" required></div>
        </div>
        <div style="display:flex;justify-content:flex-end;margin-top:1.5rem">
          <button type="submit" class="btn btn-warning"><i class="fas fa-key"></i> Change Password</button>
        </div>
      </form>
    </div>

    <!-- Danger Zone -->
    <div class="card" style="margin-top:1.5rem;border-color:rgba(239,68,68,0.3)">
      <div class="card-header"><div class="card-title" style="color:var(--danger)"><i class="fas fa-exclamation-triangle"></i> Danger Zone</div></div>
      <p style="font-size:0.875rem;color:var(--text-secondary);margin-bottom:1rem">These actions are irreversible. Please be careful.</p>
      <div style="display:flex;gap:0.75rem">
        <a href="<?= BASE_URL ?>/api/auth.php?action=logout" class="btn btn-danger btn-sm"><i class="fas fa-sign-out-alt"></i> Logout</a>
      </div>
    </div>

    <?php elseif ($activeTab === 'notifications'): ?>
    <!-- Notification Preferences -->
    <div class="card">
      <div class="card-header"><div class="card-title"><i class="fas fa-bell" style="color:var(--warning)"></i> Notification Preferences</div></div>
      <div style="display:flex;flex-direction:column;gap:0.875rem">
        <?php foreach ([['New Assignments','Notify when teacher posts a new assignment'],['Grades Released','Notify when your submission is graded'],['Class Announcements','Notify when teacher posts an announcement'],['Live Quiz','Notify when a live quiz starts'],['Attendance Alerts','Notify when marked absent'],['Video Meet','Notify when a meet session is scheduled']] as [$label,$desc]): ?>
        <div style="display:flex;align-items:center;justify-content:space-between;padding:0.875rem;background:var(--bg-surface);border:1px solid var(--border);border-radius:var(--radius-md)">
          <div>
            <div style="font-weight:600;font-size:0.9rem"><?= $label ?></div>
            <div style="font-size:0.75rem;color:var(--text-muted)"><?= $desc ?></div>
          </div>
          <label style="position:relative;display:inline-block;width:44px;height:24px;cursor:pointer;flex-shrink:0">
            <input type="checkbox" checked style="display:none">
            <span style="position:absolute;inset:0;background:var(--primary);border-radius:12px;transition:0.3s"></span>
            <span style="position:absolute;width:18px;height:18px;background:white;border-radius:50%;top:3px;left:3px;transition:0.3s;transform:translateX(20px)"></span>
          </label>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>
  </div>
</div>
</div></div>
<?php renderFooter(); ?>
</body></html>
