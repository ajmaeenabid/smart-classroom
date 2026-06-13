<?php
/**
 * Header Include — Advanced Classroom System
 * 
 * Include at the top of every page. Handles session, DB connection, and HTML head.
 * Set $pageTitle before including this file.
 */

session_start();

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/auth.php';

// Page title default
$pageTitle = isset($pageTitle) ? $pageTitle . ' — ' . APP_NAME : APP_NAME;

// Get current user if logged in
$currentUser = null;
$notificationCount = 0;
if (isLoggedIn()) {
    $currentUser = getCurrentUser($pdo);
    if ($currentUser) {
        $notificationCount = getUnreadNotificationCount($pdo, $currentUser['id']);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Advanced Classroom System — A modern Learning Management System for educators and students.">
    <meta name="theme-color" content="#1E1B4B">
    
    <title><?php echo e($pageTitle); ?></title>
    
    <!-- Google Fonts — Inter -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- Main Stylesheet -->
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/assets/css/style.css">
    
    <!-- Favicon -->
    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>🎓</text></svg>">
</head>
<body class="<?php echo isLoggedIn() ? 'has-sidebar' : ''; ?>">
