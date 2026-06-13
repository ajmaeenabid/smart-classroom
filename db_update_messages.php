<?php
// =============================================
// Database Update - Add attachment column to direct_messages
// =============================================
require_once __DIR__ . '/config/db.php';

try {
    // Check if column exists
    $stmt = $pdo->query("SHOW COLUMNS FROM direct_messages LIKE 'attachment'");
    if ($stmt->rowCount() === 0) {
        $pdo->exec("ALTER TABLE direct_messages ADD COLUMN attachment VARCHAR(255) DEFAULT NULL AFTER content");
        echo "✅ Added 'attachment' column to direct_messages table<br>";
    } else {
        echo "✅ 'attachment' column already exists in direct_messages table<br>";
    }
    echo "<br>Database update completed! You can delete this file.";
} catch (PDOException $e) {
    echo "❌ Error: " . $e->getMessage();
}
