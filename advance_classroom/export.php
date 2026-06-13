<?php
/**
 * CSV Export Handler
 */
session_start();
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

requireLogin();

$type = $_GET['type'] ?? '';
$classId = $_GET['class_id'] ?? null;
$role = getUserRole();

function outputCSV($filename, $headers, $data) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . ' ' . date('Y-m-d') . '.csv"');
    
    $output = fopen('php://output', 'w');
    fputcsv($output, $headers);
    foreach ($data as $row) {
        fputcsv($output, $row);
    }
    fclose($output);
    exit();
}

if ($type === 'grades' && $classId && ($role === 'teacher' || $role === 'admin')) {
    // Verified access
    if ($role === 'teacher') {
        $stmt = $pdo->prepare("SELECT id FROM classes WHERE id=? AND teacher_id=?");
        $stmt->execute([$classId, $_SESSION['user_id']]);
        if(!$stmt->fetch()) die("Unauthorized access.");
    }
    
    $stmt = $pdo->prepare("SELECT class_name FROM classes WHERE id=?");
    $stmt->execute([$classId]);
    $className = $stmt->fetchColumn();

    $stmt = $pdo->prepare("
        SELECT u.full_name as Student, a.title as Assignment, s.marks as 'Score', a.max_marks as 'Max Score', s.status as 'Status', s.submitted_at as 'Date Submitted'
        FROM class_enrollments ce
        JOIN users u ON ce.student_id = u.id
        LEFT JOIN assignments a ON ce.class_id = a.class_id
        LEFT JOIN submissions s ON a.id = s.assignment_id AND u.id = s.student_id
        WHERE ce.class_id = ?
        ORDER BY u.full_name, a.due_date
    ");
    $stmt->execute([$classId]);
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if(empty($data)) die("No data to export.");
    outputCSV("Gradebook_Class_".$className, array_keys($data[0]), $data);
}

if ($type === 'attendance' && $classId && ($role === 'teacher' || $role === 'admin')) {
    if ($role === 'teacher') {
        $stmt = $pdo->prepare("SELECT id FROM classes WHERE id=? AND teacher_id=?");
        $stmt->execute([$classId, $_SESSION['user_id']]);
        if(!$stmt->fetch()) die("Unauthorized access.");
    }
    
    $stmt = $pdo->prepare("
        SELECT u.full_name as Student, u.email as Email, at.date as 'Date', at.status as 'Status'
        FROM attendance at
        JOIN users u ON at.student_id = u.id
        WHERE at.class_id = ?
        ORDER BY at.date DESC, u.full_name
    ");
    $stmt->execute([$classId]);
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if(empty($data)) die("No attendance data to export.");
    outputCSV("Attendance_Class_".$classId, array_keys($data[0]), $data);
}

if ($type === 'users' && $role === 'admin') {
    $stmt = $pdo->query("SELECT id, full_name, email, role, status, created_at FROM users ORDER BY role, full_name");
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    outputCSV("Users_Export", array_keys($data[0]), $data);
}

if ($type === 'classes' && $role === 'admin') {
    $stmt = $pdo->query("
        SELECT c.id as ClassID, c.class_name as 'Class Name', c.subject as Subject, u.full_name as Teacher, c.status as Status, c.created_at as 'Created At'
        FROM classes c JOIN users u ON c.teacher_id = u.id ORDER BY c.created_at DESC
    ");
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    outputCSV("Classes_Export", array_keys($data[0]), $data);
}

// Fallback
setFlash('error', 'Invalid export type or unauthorized.');
redirect(BASE_URL . '/dashboard.php');
