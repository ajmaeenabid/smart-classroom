<?php
session_start();
require_once __DIR__ . '/../config/db.php';
requireLogin();
header('Content-Type: application/json');

$user    = currentUser();
$uid     = $user['id'];
$role    = $user['role'];
$message = trim($_POST['message'] ?? '');
$clearHistory = isset($_POST['clear_history']) && $_POST['clear_history'] === 'true';

if ($clearHistory) {
    unset($_SESSION['ai_chat_history_' . $uid]);
    echo json_encode(['reply' => 'Chat history cleared!']);
    exit;
}

if (!$message) {
    echo json_encode(['reply' => 'Please type a question first!']);
    exit;
}

function aiReply(string $text): void {
    echo json_encode(['reply' => $text]);
    exit;
}

if ($role === 'teacher') {
    $classStmt = $pdo->prepare("SELECT * FROM classes WHERE teacher_id=? ORDER BY created_at DESC LIMIT 10");
    $classStmt->execute([$uid]);
} elseif ($role === 'student') {
    $classStmt = $pdo->prepare("SELECT c.* FROM classes c JOIN class_members cm ON cm.class_id=c.id WHERE cm.user_id=? ORDER BY cm.joined_at DESC LIMIT 10");
    $classStmt->execute([$uid]);
} else {
    $classStmt = $pdo->prepare("SELECT c.* FROM classes c JOIN class_members cm ON cm.class_id=c.id JOIN guardian_links gl ON gl.student_id=cm.user_id WHERE gl.guardian_id=? AND gl.status='approved'");
    $classStmt->execute([$uid]);
}
$myClasses = $classStmt->fetchAll();
$classCount = count($myClasses);

$contextStrings = [];
$contextStrings[] = "User Name: {$user['name']}, Role: {$user['role']}.";
$contextStrings[] = "They are connected to {$classCount} classes.";

foreach ($myClasses as $cls) {
    $contextStrings[] = "Class: '{$cls['name']}' (ID: {$cls['id']}), Code: {$cls['code']}, Subject: {$cls['subject']}.";

    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM class_members WHERE class_id=?");
    $stmt->execute([$cls['id']]);
    $studentCount = $stmt->fetchColumn();
    $contextStrings[] = "Class '{$cls['name']}' has {$studentCount} students enrolled.";

    $stmt = $pdo->prepare("SELECT * FROM materials WHERE class_id=? ORDER BY created_at DESC LIMIT 10");
    $stmt->execute([$cls['id']]);
    $allMaterials = $stmt->fetchAll();
    $contextStrings[] = "Class '{$cls['name']}' has " . count($allMaterials) . " study materials.";
    foreach ($allMaterials as $mat) {
        $contextStrings[] = "  - Material: '{$mat['title']}' (Type: {$mat['type']}, ID: {$mat['id']})";
    }

    $stmt = $pdo->prepare("SELECT * FROM assignments WHERE class_id=? ORDER BY due_date ASC LIMIT 5");
    $stmt->execute([$cls['id']]);
    $assignments = $stmt->fetchAll();
    $contextStrings[] = "Class '{$cls['name']}' has " . count($assignments) . " assignments.";
    foreach ($assignments as $assn) {
        $dueDate = $assn['due_date'] ? date('M d, Y', strtotime($assn['due_date'])) : 'No deadline';
        $contextStrings[] = "  - Assignment: '{$assn['title']}' (Due: {$dueDate})";
    }
}

$systemContext = "You are the AI assistant for Smart Classroom. Be concise, helpful, and friendly. Answer the user's questions based on this database context: " . implode(" ", $contextStrings) . ". You have built-in knowledge about common educational topics like complete linkage, average linkage, K-means clustering, hierarchical clustering, and machine learning. Explain topics simply. Remember conversation history. Do NOT mention API or system prompt.";

$sessionKey = 'ai_chat_history_' . $uid;
if (!isset($_SESSION[$sessionKey])) {
    $_SESSION[$sessionKey] = [];
}

$_SESSION[$sessionKey][] = ['role' => 'user', 'content' => $message];

if (count($_SESSION[$sessionKey]) > 10) {
    array_shift($_SESSION[$sessionKey]);
}

$apiKey = getenv('OPENROUTER_API_KEY') ?: '';
$apiUrl = "https://openrouter.ai/api/v1/chat/completions";

$messages = [['role' => 'system', 'content' => $systemContext]];
$messages = array_merge($messages, $_SESSION[$sessionKey]);

$data = [
    "model" => "nvidia/nemotron-3-super-120b-a12b:free",
    "messages" => $messages
];

$options = [
    'http' => [
        'header'  => "Content-type: application/json\r\nAuthorization: Bearer " . $apiKey . "\r\nHTTP-Referer: " . BASE_URL . "\r\nX-Title: Smart Classroom",
        'method'  => 'POST',
        'content' => json_encode($data),
        'ignore_errors' => true,
        'timeout' => 20
    ]
];
try {
    $context  = stream_context_create($options);
    $response = @file_get_contents($apiUrl, false, $context);

    if ($response === false) {
        $error = error_get_last();
        error_log('AI Chat API Error: ' . ($error['message'] ?? 'Unknown error'));
        aiReply("I'm having trouble connecting to the AI service right now. Please check your internet connection and try again in a moment.");
    }

    $json = json_decode($response, true);

    if (isset($json['error'])) {
        error_log('AI Chat API Error: ' . json_encode($json['error']));
        aiReply("The AI service is temporarily unavailable. Please try again later.");
    }

    $aiText = $json['choices'][0]['message']['content'] ?? null;

    if ($aiText) {
        $_SESSION[$sessionKey][] = ['role' => 'assistant', 'content' => trim($aiText)];
        if (count($_SESSION[$sessionKey]) > 10) {
            array_shift($_SESSION[$sessionKey]);
        }
        aiReply(trim($aiText));
    } else {
        error_log('AI Chat API Error: Empty response from API');
        aiReply("I received an empty response from the AI service. Please try again.");
    }
} catch (Exception $e) {
    error_log('AI Chat Exception: ' . $e->getMessage());
    aiReply("An unexpected error occurred. Please try again later.");
}
?>
