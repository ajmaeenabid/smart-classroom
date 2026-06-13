<?php
// =============================================
// Smart Classroom — AI Auto-Grader API
// =============================================
require_once __DIR__ . '/../config/db.php';
requireLogin();

header('Content-Type: application/json');

if (userRole() !== 'teacher') {
    echo json_encode(['error' => 'Teachers only']); exit;
}

$user         = currentUser();
$uid          = $user['id'];
$submissionId = (int)($_POST['submission_id'] ?? 0);
$assignmentId = (int)($_POST['assignment_id'] ?? 0);

if (!$submissionId || !$assignmentId) {
    echo json_encode(['error' => 'Missing parameters']); exit;
}

// Fetch assignment (verify teacher owns it via the class)
$aStmt = $pdo->prepare("
    SELECT a.* FROM assignments a
    JOIN classes c ON c.id=a.class_id
    WHERE a.id=? AND c.teacher_id=?
");
$aStmt->execute([$assignmentId, $uid]);
$assignment = $aStmt->fetch();

if (!$assignment) {
    echo json_encode(['error' => 'Assignment not found or unauthorized']); exit;
}

// Fetch submission
$sStmt = $pdo->prepare("SELECT * FROM submissions WHERE id=? AND assignment_id=?");
$sStmt->execute([$submissionId, $assignmentId]);
$submission = $sStmt->fetch();

if (!$submission) {
    echo json_encode(['error' => 'Submission not found']); exit;
}

$textContent = trim($submission['text_content'] ?? '');
$hasFile     = !empty($submission['file_path']);

if (!$textContent && !$hasFile) {
    echo json_encode(['error' => 'No text content to grade (file-only submissions cannot be AI-graded)']); exit;
}

if (!$textContent) {
    echo json_encode(['error' => 'This submission has a file attachment only — AI grading works on text responses']); exit;
}

$maxPoints   = (int)($assignment['points'] ?? 100);
$title       = $assignment['title'];
$description = trim($assignment['description'] ?? '');

$prompt = <<<PROMPT
You are an academic grader. Grade this student's assignment response fairly and constructively.

Assignment: {$title}
Instructions: {$description}
Maximum Points: {$maxPoints}

Student's Answer:
{$textContent}

Your task: Grade the student response on a scale from 0 to {$maxPoints}.
- Be objective and fair
- Consider accuracy, completeness, and clarity
- Give one short, constructive sentence of feedback

Respond ONLY with valid JSON in this exact format (no other text):
{"grade": <integer>, "feedback": "<one concise sentence>"}
PROMPT;

$apiKey = getenv('OPENROUTER_API_KEY') ?: '';
$apiUrl = "https://openrouter.ai/api/v1/chat/completions";

$payload = [
    "model"    => "nvidia/nemotron-3-super-120b-a12b:free",
    "messages" => [
        ["role" => "system", "content" => "You are a grading assistant. Always respond with valid JSON only. No markdown, no explanation, just the JSON object."],
        ["role" => "user",   "content" => $prompt],
    ],
    "temperature" => 0.3,
];

$options = [
    'http' => [
        'header'        => "Content-type: application/json\r\nAuthorization: Bearer {$apiKey}\r\nHTTP-Referer: " . BASE_URL . "\r\nX-Title: Smart Classroom Grader",
        'method'        => 'POST',
        'content'       => json_encode($payload),
        'ignore_errors' => true,
        'timeout'       => 25,
    ],
];

try {
    $context  = stream_context_create($options);
    $response = @file_get_contents($apiUrl, false, $context);

    if ($response === false) {
        echo json_encode(['error' => 'Could not reach AI service. Check your internet connection.']); exit;
    }

    $json = json_decode($response, true);

    if (isset($json['error'])) {
        echo json_encode(['error' => 'AI service error: ' . ($json['error']['message'] ?? 'Unknown')]); exit;
    }

    $aiText = trim($json['choices'][0]['message']['content'] ?? '');

    // Strip any markdown code fences if present
    $aiText = preg_replace('/^```json\s*/i', '', $aiText);
    $aiText = preg_replace('/```\s*$/', '', $aiText);
    $aiText = trim($aiText);

    $result = json_decode($aiText, true);

    if (!isset($result['grade']) || !isset($result['feedback'])) {
        echo json_encode(['error' => 'AI returned unexpected format. Try again.']); exit;
    }

    // Clamp grade to valid range
    $suggestedGrade    = max(0, min($maxPoints, (int)$result['grade']));
    $suggestedFeedback = substr(trim($result['feedback']), 0, 300);

    echo json_encode([
        'success'   => true,
        'grade'     => $suggestedGrade,
        'feedback'  => $suggestedFeedback,
        'max_points'=> $maxPoints,
    ]);

} catch (Exception $e) {
    echo json_encode(['error' => 'Unexpected error: ' . $e->getMessage()]);
}
?>
