<?php
/**
 * Database Configuration — Advanced Classroom System
 * 
 * Uses PDO for secure database connections with prepared statements.
 * Configure the constants below for your XAMPP/Laragon environment.
 */

// ============================================================
// Database Configuration
// ============================================================
define('DB_HOST', 'localhost');
define('DB_NAME', 'advanced_classroom');
define('DB_USER', 'root');
define('DB_PASS', '');          // Default XAMPP has no password
define('DB_CHARSET', 'utf8mb4');

// ============================================================
// Application Configuration
// ============================================================
define('APP_NAME', 'Advanced Classroom');
define('APP_VERSION', '1.0.0');
define('BASE_URL', '/advance_classroom');   // Folder name as served by XAMPP
define('UPLOAD_DIR', __DIR__ . '/../assets/uploads/');
define('MAX_FILE_SIZE', 10 * 1024 * 1024);  // 10 MB

// Allowed file types for uploads
define('ALLOWED_EXTENSIONS', ['pdf', 'doc', 'docx', 'ppt', 'pptx', 'xls', 'xlsx', 'txt', 'jpg', 'jpeg', 'png', 'gif', 'zip', 'rar']);

// ============================================================
// PDO Database Connection
// ============================================================
try {
    $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
    
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES " . DB_CHARSET
    ];
    
    $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
    
} catch (PDOException $e) {
    // In production, log this error instead of displaying it
    die('<div style="font-family:Inter,sans-serif;padding:40px;text-align:center;">
        <h2 style="color:#EF4444;">⚠ Database Connection Failed</h2>
        <p style="color:#6B7280;">Please check your database configuration in <code>config/db.php</code></p>
        <p style="color:#9CA3AF;font-size:14px;">Error: ' . htmlspecialchars($e->getMessage()) . '</p>
        <p style="color:#9CA3AF;font-size:13px;">Make sure MySQL is running and the database "' . DB_NAME . '" exists.</p>
    </div>');
}
