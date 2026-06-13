<?php
/**
 * Authentication Guards — Advanced Classroom System
 * 
 * Functions to protect pages based on login status and user role.
 * Include this file at the top of any protected page.
 */

/**
 * Require the user to be logged in.
 * Redirects to login page if not authenticated.
 */
function requireLogin() {
    if (!isset($_SESSION['user_id'])) {
        setFlash('error', 'Please log in to continue.');
        redirect(BASE_URL . '/login.php');
    }
}

/**
 * Require a specific role.
 * Redirects to dashboard if role doesn't match.
 */
function requireRole($roles) {
    requireLogin();
    
    if (is_string($roles)) {
        $roles = [$roles];
    }
    
    if (!in_array($_SESSION['user_role'], $roles)) {
        setFlash('error', 'You do not have permission to access that page.');
        redirect(BASE_URL . '/dashboard.php');
    }
}

/**
 * Require admin role
 */
function requireAdmin() {
    requireRole('admin');
}

/**
 * Require teacher role
 */
function requireTeacher() {
    requireRole('teacher');
}

/**
 * Require student role
 */
function requireStudent() {
    requireRole('student');
}

/**
 * Require guardian role
 */
function requireGuardian() {
    requireRole('guardian');
}

/**
 * Require teacher or admin role
 */
function requireTeacherOrAdmin() {
    requireRole(['teacher', 'admin']);
}

/**
 * Verify that a teacher owns a specific class
 */
function requireClassOwner($pdo, $classId) {
    requireTeacher();
    
    $stmt = $pdo->prepare("SELECT id FROM classes WHERE id = ? AND teacher_id = ?");
    $stmt->execute([$classId, $_SESSION['user_id']]);
    
    if (!$stmt->fetch()) {
        setFlash('error', 'You do not have access to this class.');
        redirect(BASE_URL . '/dashboard.php');
    }
}

/**
 * Verify that a student is enrolled in a class
 */
function requireEnrollment($pdo, $classId) {
    requireStudent();
    
    $stmt = $pdo->prepare("SELECT id FROM class_enrollments WHERE class_id = ? AND student_id = ?");
    $stmt->execute([$classId, $_SESSION['user_id']]);
    
    if (!$stmt->fetch()) {
        setFlash('error', 'You are not enrolled in this class.');
        redirect(BASE_URL . '/dashboard.php');
    }
}

/**
 * Check if user has access to a class (teacher who owns it OR enrolled student)
 */
function requireClassAccess($pdo, $classId) {
    requireLogin();
    
    $role = $_SESSION['user_role'];
    $userId = $_SESSION['user_id'];
    
    if ($role === 'admin') {
        return true; // Admin can access everything
    }
    
    if ($role === 'teacher') {
        $stmt = $pdo->prepare("SELECT id FROM classes WHERE id = ? AND teacher_id = ?");
        $stmt->execute([$classId, $userId]);
        if ($stmt->fetch()) return true;
    }
    
    if ($role === 'student') {
        $stmt = $pdo->prepare("SELECT id FROM class_enrollments WHERE class_id = ? AND student_id = ?");
        $stmt->execute([$classId, $userId]);
        if ($stmt->fetch()) return true;
    }
    
    if ($role === 'guardian') {
        // Guardian can access classes of linked students
        $stmt = $pdo->prepare("
            SELECT ce.id FROM class_enrollments ce
            JOIN guardian_links gl ON gl.student_id = ce.student_id
            WHERE ce.class_id = ? AND gl.guardian_id = ?
        ");
        $stmt->execute([$classId, $userId]);
        if ($stmt->fetch()) return true;
    }
    
    setFlash('error', 'You do not have access to this class.');
    redirect(BASE_URL . '/dashboard.php');
}

/**
 * Redirect if already logged in
 */
function redirectIfLoggedIn() {
    if (isset($_SESSION['user_id'])) {
        redirect(BASE_URL . '/dashboard.php');
    }
}
