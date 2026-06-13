<?php
/**
 * Registration Page — Advanced Classroom System
 */
session_start();
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

redirectIfLoggedIn();

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullName = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    $role = $_POST['role'] ?? 'student';
    
    // Validate
    if (empty($fullName) || strlen($fullName) < 2) $errors[] = 'Full name is required (min 2 characters).';
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Valid email is required.';
    if (strlen($password) < 6) $errors[] = 'Password must be at least 6 characters.';
    if ($password !== $confirmPassword) $errors[] = 'Passwords do not match.';
    if (!in_array($role, ['teacher', 'student', 'guardian'])) $errors[] = 'Invalid role selected.';
    
    // Check duplicate email
    if (empty($errors)) {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $errors[] = 'An account with this email already exists.';
        }
    }
    
    // Create account
    if (empty($errors)) {
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        
        $stmt = $pdo->prepare("INSERT INTO users (full_name, email, password, role) VALUES (?, ?, ?, ?)");
        $stmt->execute([$fullName, $email, $hashedPassword, $role]);
        
        $userId = $pdo->lastInsertId();
        
        // Auto-login
        $_SESSION['user_id'] = $userId;
        $_SESSION['user_role'] = $role;
        $_SESSION['user_name'] = $fullName;
        
        // Welcome notification
        createNotification($pdo, $userId, "Welcome to Advanced Classroom, {$fullName}! 🎉", 'system', BASE_URL . '/dashboard.php');
        
        // Guardian: link to student if email provided
        if ($role === 'guardian') {
            $studentEmail = trim($_POST['student_email'] ?? '');
            if (!empty($studentEmail)) {
                $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND role = 'student'");
                $stmt->execute([$studentEmail]);
                $student = $stmt->fetch();
                if ($student) {
                    $stmt = $pdo->prepare("INSERT IGNORE INTO guardian_links (guardian_id, student_id) VALUES (?, ?)");
                    $stmt->execute([$userId, $student['id']]);
                    // Notify student
                    createNotification($pdo, $student['id'], "{$fullName} has linked as your guardian.", 'system', BASE_URL . '/dashboard.php');
                    setFlash('success', 'Account created and linked to student successfully!');
                } else {
                    setFlash('warning', 'Account created, but no student found with that email. You can link later.');
                }
            } else {
                setFlash('success', 'Account created successfully! Welcome aboard.');
            }
            redirect(BASE_URL . '/guardian/dashboard.php');
        } else {
            setFlash('success', 'Account created successfully! Welcome aboard.');
            redirect(BASE_URL . '/dashboard.php');
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Account — <?php echo APP_NAME; ?></title>
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
                <h1>Create Account</h1>
                <p>Join the Advanced Classroom community</p>
            </div>
            
            <?php if (!empty($errors)): ?>
                <div class="flash-message flash-error">
                    <span class="flash-icon">✕</span>
                    <span class="flash-text"><?php echo e($errors[0]); ?></span>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="" id="registerForm">
                <div class="form-group">
                    <label class="form-label" for="full_name">Full Name</label>
                    <input type="text" id="full_name" name="full_name" class="form-control" 
                           placeholder="John Doe" value="<?php echo e($_POST['full_name'] ?? ''); ?>" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label" for="email">Email Address</label>
                    <input type="email" id="email" name="email" class="form-control" 
                           placeholder="you@example.com" value="<?php echo e($_POST['email'] ?? ''); ?>" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label" for="role">I am a...</label>
                    <select id="role" name="role" class="form-control" required>
                        <option value="student" <?php echo (($_POST['role'] ?? '') === 'student') ? 'selected' : ''; ?>>🎒 Student</option>
                        <option value="teacher" <?php echo (($_POST['role'] ?? '') === 'teacher') ? 'selected' : ''; ?>>👨‍🏫 Teacher</option>
                        <option value="guardian" <?php echo (($_POST['role'] ?? '') === 'guardian') ? 'selected' : ''; ?>>👨‍👩‍👧 Guardian</option>
                    </select>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label" for="password">Password</label>
                        <input type="password" id="password" name="password" class="form-control" 
                               placeholder="Min 6 characters" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="confirm_password">Confirm</label>
                        <input type="password" id="confirm_password" name="confirm_password" class="form-control" 
                               placeholder="Repeat password" required>
                    </div>
                </div>
                
                <!-- Guardian-specific field -->
                <div class="form-group" id="guardianField" style="display:none;">
                    <label class="form-label" for="student_email">Student's Email (to link)</label>
                    <input type="email" id="student_email" name="student_email" class="form-control" 
                           placeholder="student@example.com">
                    <p class="form-text">Enter the student's registered email to link accounts.</p>
                </div>
                
                <button type="submit" class="btn btn-primary btn-block btn-lg" id="registerBtn">
                    🚀 Create Account
                </button>
            </form>
            
            <div class="auth-footer">
                <p>Already have an account? <a href="<?php echo BASE_URL; ?>/login.php">Sign In</a></p>
                <p style="margin-top:12px;"><a href="<?php echo BASE_URL; ?>/" style="color:var(--text-muted);font-size:13px;">← Back to Home</a></p>
            </div>
        </div>
    </div>
    
    <script>
        // Show/hide guardian field
        document.getElementById('role').addEventListener('change', function() {
            document.getElementById('guardianField').style.display = this.value === 'guardian' ? 'block' : 'none';
        });
        // Initialize on load
        if (document.getElementById('role').value === 'guardian') {
            document.getElementById('guardianField').style.display = 'block';
        }
    </script>
</body>
</html>
