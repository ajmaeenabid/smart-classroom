<?php
/**
 * Personal Progress Tracker - Student
 */
$pageTitle = 'My Progress Tracker';
require_once __DIR__ . '/../includes/header.php';
requireStudent();

$userId = $_SESSION['user_id'];

// Get overall stats
$stmt = $pdo->prepare("
    SELECT 
        (SELECT COUNT(*) FROM class_enrollments WHERE student_id = ?) as total_classes,
        (SELECT COUNT(*) FROM assignments a JOIN class_enrollments ce ON a.class_id = ce.class_id WHERE ce.student_id = ?) as total_assignments,
        (SELECT COUNT(*) FROM submissions WHERE student_id = ?) as total_submitted,
        (SELECT AVG((marks / max_marks) * 100) FROM submissions s JOIN assignments a ON s.assignment_id = a.id WHERE s.student_id = ? AND s.status = 'graded') as avg_grade
");
$stmt->execute([$userId, $userId, $userId, $userId]);
$overview = $stmt->fetch();

$avgGrade = round($overview['avg_grade'] ?? 0, 1);
$completionRate = 0;
if ($overview['total_assignments'] > 0) {
    $completionRate = round(($overview['total_submitted'] / $overview['total_assignments']) * 100);
}

// Get class-by-class data for bar chart
$stmt = $pdo->prepare("
    SELECT c.class_name, 
           COUNT(a.id) as assigned,
           SUM(CASE WHEN s.id IS NOT NULL THEN 1 ELSE 0 END) as completed
    FROM classes c
    JOIN class_enrollments ce ON c.id = ce.class_id AND ce.student_id = ?
    LEFT JOIN assignments a ON c.id = a.class_id
    LEFT JOIN submissions s ON a.id = s.assignment_id AND s.student_id = ?
    GROUP BY c.id
");
$stmt->execute([$userId, $userId]);
$classData = $stmt->fetchAll();

$chartLabels = [];
$chartAssigned = [];
$chartCompleted = [];
foreach ($classData as $row) {
    // Truncate name for chart
    $name = mb_substr($row['class_name'], 0, 10);
    if(strlen($row['class_name']) > 10) $name .= '..';
    $chartLabels[] = '"' . e($name) . '"';
    $chartAssigned[] = $row['assigned'];
    $chartCompleted[] = $row['completed'];
}

require_once __DIR__ . '/../includes/navbar.php';
?>

<div class="page-header">
    <div>
        <h1>My Progress Tracker</h1>
        <p>A statistical overview of your academic performance.</p>
    </div>
</div>

<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon purple">🎓</div>
        <div class="stat-info">
            <h3><?php echo $avgGrade; ?>%</h3>
            <p>Overall Average Grade</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon green">✅</div>
        <div class="stat-info">
            <h3><?php echo $completionRate; ?>%</h3>
            <p>Task Completion Rate</p>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon orange">📝</div>
        <div class="stat-info">
            <h3><?php echo $overview['total_submitted']; ?> <span class="text-sm text-muted">/ <?php echo $overview['total_assignments']; ?></span></h3>
            <p>Assignments Done</p>
        </div>
    </div>
</div>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:24px;margin-top:32px;">
    <div class="card">
        <div class="card-header" style="background:var(--body-bg);color:var(--text);border-bottom:1px solid var(--border);">
            <h3 class="text-base">Workload per Class</h3>
        </div>
        <div class="card-body">
            <canvas id="workloadChart" style="width:100%;height:300px;"></canvas>
        </div>
    </div>
    
    <div class="card">
        <div class="card-header" style="background:var(--body-bg);color:var(--text);border-bottom:1px solid var(--border);">
            <h3 class="text-base">Activity Breakdown</h3>
        </div>
        <div class="card-body text-center d-flex flex-column justify-center align-center">
            <canvas id="completionDonut" style="width:200px;height:200px;margin-bottom:24px;"></canvas>
            <div class="d-flex justify-center gap-2">
                <span class="badge" style="background:#10B981;color:#fff;">Done: <?php echo $overview['total_submitted']; ?></span>
                <span class="badge" style="background:#E2E8F0;color:#475569;">Missing: <?php echo max(0, $overview['total_assignments'] - $overview['total_submitted']); ?></span>
            </div>
        </div>
    </div>
</div>

<!-- Fallback inline JS chart rendering since Chart.js is not included -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // We are employing our vanilla HTML5 Canvas chart functions built in app.js
    if(typeof drawBarChart === 'function') {
        const labels = [<?php echo implode(',', $chartLabels); ?>];
        const data = [<?php echo implode(',', $chartAssigned); ?>];
        const colors = ['#4F46E5', '#14B8A6', '#F59E0B', '#3B82F6', '#8B5CF6'];
        drawBarChart('workloadChart', labels, data, colors);
    }
    
    if(typeof drawDonutChart === 'function') {
        const completed = <?php echo $overview['total_submitted']; ?>;
        const missing = <?php echo max(0, $overview['total_assignments'] - $overview['total_submitted']); ?>;
        drawDonutChart('completionDonut', [completed, missing], ['#10B981', '#E2E8F0'], ['Completed', 'Missing']);
    }
});
</script>

</main>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
