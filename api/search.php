<?php
// =============================================
// Smart Classroom — Search Suggestions API
// =============================================
require_once __DIR__ . '/../config/db.php';
requireLogin();

header('Content-Type: application/json');

$user = currentUser();
$uid  = $user['id'];
$role = $user['role'];
$q    = trim($_GET['q'] ?? '');

if (strlen($q) < 2) {
    echo json_encode(['results' => []]);
    exit;
}

$results = [];
$like    = '%' . $q . '%';

// ── Assignments ───────────────────────────────
if ($role === 'teacher') {
    $s = $pdo->prepare("SELECT a.id, a.title, a.class_id, a.due_date FROM assignments a JOIN classes c ON c.id=a.class_id WHERE c.teacher_id=? AND a.title LIKE ? LIMIT 4");
    $s->execute([$uid, $like]);
} else {
    $s = $pdo->prepare("SELECT a.id, a.title, a.class_id, a.due_date FROM assignments a JOIN class_members cm ON cm.class_id=a.class_id WHERE cm.user_id=? AND a.title LIKE ? LIMIT 4");
    $s->execute([$uid, $like]);
}
foreach ($s->fetchAll() as $r) {
    $due = $r['due_date'] ? ' · Due ' . date('M d', strtotime($r['due_date'])) : '';
    $results[] = [
        'type'  => 'assignment',
        'icon'  => 'fa-file-alt',
        'label' => $r['title'],
        'sub'   => 'Assignment' . $due,
        'url'   => '/classroom/index.php?id=' . $r['class_id'] . '&tab=classwork&item_id=' . $r['id'],
    ];
}

// ── Materials ─────────────────────────────────
if ($role !== 'guardian') {
    if ($role === 'teacher') {
        $s = $pdo->prepare("SELECT m.id, m.title, m.class_id, m.type FROM materials m JOIN classes c ON c.id=m.class_id WHERE c.teacher_id=? AND m.title LIKE ? LIMIT 3");
        $s->execute([$uid, $like]);
    } else {
        $s = $pdo->prepare("SELECT m.id, m.title, m.class_id, m.type FROM materials m JOIN class_members cm ON cm.class_id=m.class_id WHERE cm.user_id=? AND m.title LIKE ? LIMIT 3");
        $s->execute([$uid, $like]);
    }
    foreach ($s->fetchAll() as $r) {
        $results[] = [
            'type'  => 'material',
            'icon'  => ['file' => 'fa-file-alt', 'link' => 'fa-link', 'video' => 'fa-video'][$r['type']] ?? 'fa-file',
            'label' => $r['title'],
            'sub'   => 'Material · ' . ucfirst($r['type']),
            'url'   => '/classroom/index.php?id=' . $r['class_id'] . '&tab=materials&item_id=' . $r['id'],
        ];
    }
}

// ── Quizzes ─────────────────────────────────
if ($role !== 'guardian') {
    if ($role === 'teacher') {
        $s = $pdo->prepare("SELECT q.id, q.title, q.class_id FROM quizzes q JOIN classes c ON c.id=q.class_id WHERE c.teacher_id=? AND q.title LIKE ? LIMIT 3");
        $s->execute([$uid, $like]);
    } else {
        $s = $pdo->prepare("SELECT q.id, q.title, q.class_id FROM quizzes q JOIN class_members cm ON cm.class_id=q.class_id WHERE cm.user_id=? AND q.title LIKE ? LIMIT 3");
        $s->execute([$uid, $like]);
    }
    foreach ($s->fetchAll() as $r) {
        $results[] = [
            'type'  => 'quiz',
            'icon'  => 'fa-question-circle',
            'label' => $r['title'],
            'sub'   => 'Quiz',
            'url'   => '/classroom/quiz.php?class_id=' . $r['class_id'] . '&item_id=' . $r['id'],
        ];
    }
}

// ── Announcements (Stream) ────────────────────
if ($role !== 'guardian') {
    if ($role === 'teacher') {
        $s = $pdo->prepare("SELECT a.id, a.content, a.class_id, u.name as author_name FROM announcements a JOIN classes c ON c.id=a.class_id JOIN users u ON u.id=a.author_id WHERE c.teacher_id=? AND a.content LIKE ? LIMIT 3");
        $s->execute([$uid, $like]);
    } else {
        $s = $pdo->prepare("SELECT a.id, a.content, a.class_id, u.name as author_name FROM announcements a JOIN class_members cm ON cm.class_id=a.class_id JOIN users u ON u.id=a.author_id WHERE cm.user_id=? AND a.content LIKE ? LIMIT 3");
        $s->execute([$uid, $like]);
    }
    foreach ($s->fetchAll() as $r) {
        $results[] = [
            'type'  => 'announcement',
            'icon'  => 'fa-bullhorn',
            'label' => mb_strimwidth($r['content'], 0, 40, '…'),
            'sub'   => 'Announcement by ' . e($r['author_name'] ?? 'User'),
            'url'   => '/classroom/index.php?id=' . $r['class_id'] . '&tab=stream&item_id=' . $r['id'],
        ];
    }
}

// ── Students (teacher only) ───────────────────
if ($role === 'teacher') {
    $s = $pdo->prepare("SELECT DISTINCT u.id, u.name, u.email FROM users u JOIN class_members cm ON cm.user_id=u.id JOIN classes c ON c.id=cm.class_id WHERE c.teacher_id=? AND (u.name LIKE ? OR u.email LIKE ?) LIMIT 3");
    $s->execute([$uid, $like, $like]);
    foreach ($s->fetchAll() as $r) {
        $results[] = [
            'type'  => 'student',
            'icon'  => 'fa-user-graduate',
            'label' => $r['name'],
            'sub'   => $r['email'],
            'url'   => '/analytics/performance.php?student_id=' . $r['id'],
        ];
    }
}

echo json_encode(['results' => $results]);
?>
