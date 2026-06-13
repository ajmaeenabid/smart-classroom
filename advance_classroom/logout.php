<?php
/**
 * Logout — Advanced Classroom System
 * Destroys session and redirects to login.
 */
session_start();
$_SESSION = [];
session_destroy();

// Clear session cookie
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

require_once __DIR__ . '/config/db.php'; // For BASE_URL
header('Location: ' . BASE_URL . '/login.php');
exit();
