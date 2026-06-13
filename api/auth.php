<?php
// =============================================
// Smart Classroom — Auth API
// =============================================
require_once __DIR__ . '/../config/db.php';

$action = $_GET['action'] ?? $_POST['action'] ?? '';

if ($action === 'logout') {
    session_destroy();
    header('Location: ' . BASE_URL . '/index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($action === 'login') {
        $email    = trim($_POST['email'] ?? '');
        $password = trim($_POST['password'] ?? '');
        $stmt     = $pdo->prepare("SELECT * FROM users WHERE email=?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user']    = $user;
            jsonResponse(['success' => true, 'redirect' => BASE_URL . "/dashboard/{$user['role']}.php"]);
        }
        jsonResponse(['error' => 'Invalid credentials'], 401);
    }
    if ($action === 'register') {
        $name     = trim($_POST['name'] ?? '');
        $email    = trim($_POST['email'] ?? '');
        $password = trim($_POST['password'] ?? '');
        $role     = trim($_POST['role'] ?? 'student');
        if (!in_array($role, ['teacher','student','guardian'])) jsonResponse(['error' => 'Invalid role'], 400);
        $check = $pdo->prepare("SELECT id FROM users WHERE email=?");
        $check->execute([$email]);
        if ($check->fetch()) jsonResponse(['error' => 'Email already registered'], 400);
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("INSERT INTO users (name, email, password, role) VALUES (?,?,?,?)");
        $stmt->execute([$name, $email, $hash, $role]);
        $id   = $pdo->lastInsertId();
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id=?");
        $stmt->execute([$id]);
        $user = $stmt->fetch();
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user']    = $user;
        jsonResponse(['success' => true, 'redirect' => BASE_URL . "/dashboard/{$user['role']}.php"]);
    }
}
jsonResponse(['error' => 'Invalid request'], 400);
?>
