<?php
/**
 * Helper Functions — Advanced Classroom System
 * 
 * Utility functions used across the application.
 */

/**
 * Sanitize output to prevent XSS
 */
function e($string) {
    return htmlspecialchars($string ?? '', ENT_QUOTES, 'UTF-8');
}

/**
 * Redirect to a URL
 */
function redirect($url) {
    header("Location: " . $url);
    exit();
}

/**
 * Set a flash message in session
 */
function setFlash($type, $message) {
    $_SESSION['flash'] = [
        'type' => $type,
        'message' => $message
    ];
}

/**
 * Get and clear flash message
 */
function getFlash() {
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

/**
 * Display flash message HTML
 */
function displayFlash() {
    $flash = getFlash();
    if ($flash) {
        $type = $flash['type'];
        $message = e($flash['message']);
        $icons = [
            'success' => '✓',
            'error' => '✕',
            'warning' => '⚠',
            'info' => 'ℹ'
        ];
        $icon = $icons[$type] ?? 'ℹ';
        echo "<div class='flash-message flash-{$type}' id='flashMessage'>
                <span class='flash-icon'>{$icon}</span>
                <span class='flash-text'>{$message}</span>
                <button class='flash-close' onclick='this.parentElement.remove()'>×</button>
              </div>";
    }
}

/**
 * Generate a unique class code
 */
function generateClassCode($length = 7) {
    $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789'; // Removed confusing chars (I,O,0,1)
    $code = '';
    for ($i = 0; $i < $length; $i++) {
        $code .= $chars[random_int(0, strlen($chars) - 1)];
    }
    return $code;
}

/**
 * Generate a unique class code that doesn't exist in DB
 */
function generateUniqueClassCode($pdo, $length = 7) {
    do {
        $code = generateClassCode($length);
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM classes WHERE class_code = ?");
        $stmt->execute([$code]);
        $exists = $stmt->fetchColumn() > 0;
    } while ($exists);
    return $code;
}

/**
 * Get the current logged-in user
 */
function getCurrentUser($pdo) {
    if (!isset($_SESSION['user_id'])) {
        return null;
    }
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? AND status = 'active'");
    $stmt->execute([$_SESSION['user_id']]);
    return $stmt->fetch();
}

/**
 * Check if user is logged in
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

/**
 * Get user role
 */
function getUserRole() {
    return $_SESSION['user_role'] ?? null;
}

/**
 * Format date for display
 */
function formatDate($date, $format = 'M d, Y') {
    if (!$date) return 'N/A';
    return date($format, strtotime($date));
}

/**
 * Format datetime for display
 */
function formatDateTime($datetime, $format = 'M d, Y h:i A') {
    if (!$datetime) return 'N/A';
    return date($format, strtotime($datetime));
}

/**
 * Time ago format
 */
function timeAgo($datetime) {
    $now = new DateTime();
    $ago = new DateTime($datetime);
    $diff = $now->diff($ago);
    
    if ($diff->y > 0) return $diff->y . ' year' . ($diff->y > 1 ? 's' : '') . ' ago';
    if ($diff->m > 0) return $diff->m . ' month' . ($diff->m > 1 ? 's' : '') . ' ago';
    if ($diff->d > 0) return $diff->d . ' day' . ($diff->d > 1 ? 's' : '') . ' ago';
    if ($diff->h > 0) return $diff->h . ' hour' . ($diff->h > 1 ? 's' : '') . ' ago';
    if ($diff->i > 0) return $diff->i . ' minute' . ($diff->i > 1 ? 's' : '') . ' ago';
    return 'Just now';
}

/**
 * Handle file upload
 * Returns the file path on success, false on failure
 */
function handleFileUpload($file, $subfolder = '') {
    // Validate file
    if (!isset($file) || $file['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'error' => 'Upload failed. Please try again.'];
    }
    
    // Check file size
    if ($file['size'] > MAX_FILE_SIZE) {
        return ['success' => false, 'error' => 'File too large. Maximum size is ' . (MAX_FILE_SIZE / 1024 / 1024) . 'MB.'];
    }
    
    // Check extension
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ALLOWED_EXTENSIONS)) {
        return ['success' => false, 'error' => 'File type not allowed. Accepted: ' . implode(', ', ALLOWED_EXTENSIONS)];
    }
    
    // Create upload directory if needed
    $uploadDir = UPLOAD_DIR . $subfolder;
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    
    // Generate unique filename
    $filename = uniqid() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '', $file['name']);
    $filepath = $uploadDir . '/' . $filename;
    
    // Move file
    if (move_uploaded_file($file['tmp_name'], $filepath)) {
        return [
            'success' => true, 
            'path' => 'assets/uploads/' . $subfolder . '/' . $filename,
            'filename' => $filename,
            'original_name' => $file['name'],
            'size' => $file['size'],
            'type' => $ext
        ];
    }
    
    return ['success' => false, 'error' => 'Failed to save file.'];
}

/**
 * Get unread notification count
 */
function getUnreadNotificationCount($pdo, $userId) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
    $stmt->execute([$userId]);
    return $stmt->fetchColumn();
}

/**
 * Create a notification
 */
function createNotification($pdo, $userId, $message, $type = 'system', $link = null) {
    $stmt = $pdo->prepare("INSERT INTO notifications (user_id, message, type, link) VALUES (?, ?, ?, ?)");
    $stmt->execute([$userId, $message, $type, $link]);
}

/**
 * Create notifications for all students in a class
 */
function notifyClassStudents($pdo, $classId, $message, $type = 'system', $link = null) {
    $stmt = $pdo->prepare("SELECT student_id FROM class_enrollments WHERE class_id = ?");
    $stmt->execute([$classId]);
    $students = $stmt->fetchAll();
    
    foreach ($students as $student) {
        createNotification($pdo, $student['student_id'], $message, $type, $link);
    }
}

/**
 * Get class color palette options
 */
function getClassColors() {
    return [
        '#4F46E5' => 'Indigo',
        '#7C3AED' => 'Purple',
        '#2563EB' => 'Blue',
        '#0891B2' => 'Cyan',
        '#059669' => 'Emerald',
        '#D97706' => 'Amber',
        '#DC2626' => 'Red',
        '#DB2777' => 'Pink',
        '#4B5563' => 'Gray',
        '#1E1B4B' => 'Navy'
    ];
}

/**
 * Get initials from a name
 */
function getInitials($name) {
    $words = explode(' ', trim($name));
    $initials = '';
    foreach (array_slice($words, 0, 2) as $word) {
        $initials .= mb_strtoupper(mb_substr($word, 0, 1));
    }
    return $initials;
}

/**
 * Generate CSRF token
 */
function generateCSRFToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Verify CSRF token
 */
function verifyCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * CSRF hidden input field
 */
function csrfField() {
    $token = generateCSRFToken();
    return '<input type="hidden" name="csrf_token" value="' . $token . '">';
}

/**
 * Get file icon based on extension
 */
function getFileIcon($filename) {
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    $icons = [
        'pdf' => '📄', 'doc' => '📝', 'docx' => '📝',
        'ppt' => '📊', 'pptx' => '📊',
        'xls' => '📗', 'xlsx' => '📗',
        'jpg' => '🖼️', 'jpeg' => '🖼️', 'png' => '🖼️', 'gif' => '🖼️',
        'zip' => '📦', 'rar' => '📦',
        'txt' => '📃'
    ];
    return $icons[$ext] ?? '📎';
}

/**
 * Format file size 
 */
function formatFileSize($bytes) {
    if ($bytes >= 1048576) return number_format($bytes / 1048576, 1) . ' MB';
    if ($bytes >= 1024) return number_format($bytes / 1024, 1) . ' KB';
    return $bytes . ' bytes';
}

/**
 * Check if a due date has passed
 */
function isPastDue($dueDate) {
    return strtotime($dueDate) < time();
}

/**
 * Get status badge HTML
 */
function statusBadge($status) {
    $badges = [
        'submitted'  => '<span class="badge badge-info">Submitted</span>',
        'late'       => '<span class="badge badge-warning">Late</span>',
        'graded'     => '<span class="badge badge-success">Graded</span>',
        'draft'      => '<span class="badge badge-secondary">Draft</span>',
        'missing'    => '<span class="badge badge-danger">Missing</span>',
        'present'    => '<span class="badge badge-success">Present</span>',
        'absent'     => '<span class="badge badge-danger">Absent</span>',
        'excused'    => '<span class="badge badge-warning">Excused</span>',
        'active'     => '<span class="badge badge-success">Active</span>',
        'blocked'    => '<span class="badge badge-danger">Blocked</span>',
        'archived'   => '<span class="badge badge-secondary">Archived</span>',
    ];
    return $badges[$status] ?? '<span class="badge">' . e($status) . '</span>';
}
