<?php
// Smart Classroom DB Patcher
require_once __DIR__ . '/config/db.php';

echo "<h1>Database Patch</h1>";

try {
    // Check if columns exist
    $checkLogo = $pdo->query("SHOW COLUMNS FROM classes LIKE 'logo'");
    if ($checkLogo->rowCount() == 0) {
        $pdo->exec("ALTER TABLE classes ADD COLUMN logo VARCHAR(255) DEFAULT NULL");
        echo "<p>✅ Added 'logo' column to classes table.</p>";
    } else {
        echo "<p>ℹ️ 'logo' column already exists.</p>";
    }

    $checkMaxSt = $pdo->query("SHOW COLUMNS FROM classes LIKE 'max_students'");
    if ($checkMaxSt->rowCount() == 0) {
        $pdo->exec("ALTER TABLE classes ADD COLUMN max_students INT DEFAULT 40");
        echo "<p>✅ Added 'max_students' column to classes table.</p>";
    } else {
        echo "<p>ℹ️ 'max_students' column already exists.</p>";
    }
    
    // Ensure qr_attendance_sessions exists just in case
    $pdo->exec("CREATE TABLE IF NOT EXISTS qr_attendance_sessions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        class_id INT NOT NULL,
        teacher_id INT NOT NULL,
        date DATE NOT NULL,
        token VARCHAR(100) UNIQUE NOT NULL,
        is_active TINYINT(1) DEFAULT 1,
        expires_at DATETIME NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (class_id) REFERENCES classes(id) ON DELETE CASCADE,
        FOREIGN KEY (teacher_id) REFERENCES users(id) ON DELETE CASCADE
    )");
    echo "<p>✅ Verified 'qr_attendance_sessions' table exists.</p>";

    echo "<h3>Patch Complete! You can now close this tab, delete db_update.php, and use the application.</h3>";

} catch (PDOException $e) {
    echo "<p style='color:red;'>Database Error: " . $e->getMessage() . "</p>";
}
