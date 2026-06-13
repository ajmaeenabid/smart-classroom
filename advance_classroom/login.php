<?php
/**
 * Login Page — Advanced Classroom System
 */
session_start();
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

// Redirect if already logged in
redirectIfLoggedIn();

$errors = [];

// Handle login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    
    // Validate
    if (empty($email)) $errors[] = 'Email is required.';
    if (empty($password)) $errors[] = 'Password is required.';
    
    if (empty($errors)) {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        
        if ($user && password_verify($password, $user['password'])) {
            // Check if user is blocked
            if ($user['status'] === 'blocked') {
                $errors[] = 'Your account has been blocked. Contact the administrator.';
            } else {
                // Set session
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_role'] = $user['role'];
                $_SESSION['user_name'] = $user['full_name'];
                
                setFlash('success', 'Welcome back, ' . $user['full_name'] . '!');
                
                // Role-based redirect
                switch ($user['role']) {
                    case 'admin':
                        redirect(BASE_URL . '/admin/dashboard.php');
                        break;
                    case 'guardian':
                        redirect(BASE_URL . '/guardian/dashboard.php');
                        break;
                    default:
                        redirect(BASE_URL . '/dashboard.php');
                }
            }
        } else {
            $errors[] = 'Invalid email or password.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign In — <?php echo APP_NAME; ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/style.css">
    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>🎓</text></svg>">
</head>
<body>
    <div class="auth-page">
        <div class="auth-container">
            <div class="auth-logo">
                <div class="logo-icon">🎓</div>
                <h1>Welcome Back</h1>
                <p>Sign in to your classroom account</p>
            </div>
            
            <?php if (!empty($errors)): ?>
                <div class="flash-message flash-error">
                    <span class="flash-icon">✕</span>
                    <span class="flash-text"><?php echo e($errors[0]); ?></span>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="" id="loginForm">
                <div class="form-group">
                    <label class="form-label" for="email">Email Address</label>
                    <input type="email" id="email" name="email" class="form-control" 
                           placeholder="you@example.com" value="<?php echo e($_POST['email'] ?? ''); ?>" required autofocus>
                </div>
                
                <div class="form-group">
                    <label class="form-label" for="password">Password</label>
                    <input type="password" id="password" name="password" class="form-control" 
                           placeholder="Enter your password" required>
                </div>
                
                <button type="submit" class="btn btn-primary btn-block btn-lg" id="loginBtn">
                    🔐 Sign In
                </button>
            </form>
            
            <div class="auth-footer">
                <p>Don't have an account? <a href="<?php echo BASE_URL; ?>/register.php">Create one</a></p>
                <p style="margin-top:12px;"><a href="<?php echo BASE_URL; ?>/" style="color:var(--text-muted);font-size:13px;">← Back to Home</a></p>
            </div>
        </div>
    </div>
</body>
</html>
