<?php
/**
 * View Attendance - Student
 */
$pageTitle = 'My Attendance';
require_once __DIR__ . '/../includes/header.php';
requireStudent();

$userId = $_SESSION['user_id'];

// Get student classes to filter
$stmt = $pdo->prepare("SELECT c.id, c.class_name FROM class_enrollments ce JOIN classes c ON ce.class_id = c.id WHERE ce.student_id = ?");
$stmt->execute([$userId]);
$classes = $stmt->fetchAll();

$classIdFilter = $_GET['class_id'] ?? ($classes[0]['id'] ?? null);

$attendanceStats = [];
$records = [];

if ($classIdFilter) {
    // Get stats
    $stmt = $pdo->prepare("SELECT status, COUNT(*) as count FROM attendance WHERE student_id = ? AND class_id = ? GROUP BY status");
    $stmt->execute([$userId, $classIdFilter]);
    $stats = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    
    $total = array_sum($stats) ?: 1; // avoid div by zero
    $presentCount = ($stats['present'] ?? 0) + ($stats['late'] ?? 0); // treating late as present for rate
    $presentRate = round(($presentCount / $total) * 100);
    
    // Get logs
    $stmt = $pdo->prepare("SELECT date, status FROM attendance WHERE student_id = ? AND class_id = ? ORDER BY date DESC");
    $stmt->execute([$userId, $classIdFilter]);
    $records = $stmt->fetchAll();
}

require_once __DIR__ . '/../includes/navbar.php';
?>

<div class="page-header">
    <div>
        <h1>My Attendance</h1>
        <p>Review your presence and absence records.</p>
    </div>
</div>

<?php if (empty($classes)): ?>
    <div class="empty-state card">
        <div class="empty-icon">🎒</div>
        <h3>Not Enrolled</h3>
        <p>You need to join a class to view attendance records.</p>
    </div>
<?php else: ?>

<div style="display:grid;grid-template-columns:300px 1fr;gap:24px;">
    <!-- Stats Column -->
    <div>
        <div class="card mb-3">
            <div class="card-body">
                <form method="GET" action="">
                    <label class="form-label">Filter by Class</label>
                    <select name="class_id" class="form-control" onchange="this.form.submit()">
                        <?php foreach($classes as $c): ?>
                            <option value="<?php echo $c['id']; ?>" <?php echo $c['id'] == $classIdFilter ? 'selected' : ''; ?>><?php echo e($c['class_name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </form>
            </div>
        </div>

        <?php if (!empty($records)): ?>
        <div class="card">
            <div class="card-body text-center">
                <h3 class="mb-3 text-muted text-sm">Attendance Rate</h3>
                <div style="position:relative;width:150px;height:150px;margin:0 auto 16px;">
                    <canvas id="attnChart"></canvas>
                </div>
                <div style="font-size:32px;font-weight:800;color:<?php echo $presentRate >= 75 ? 'var(--success)' : 'var(--danger)'; ?>;"><?php echo $presentRate; ?>%</div>
                
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-top:20px;text-align:left;">
                    <div style="background:#F0FDFA;padding:10px;border-radius:8px;">
                        <span class="text-xs text-muted block">Present</span>
                        <strong style="color:var(--success);"><?php echo $stats['present'] ?? 0; ?></strong>
                    </div>
                    <div style="background:#FEF2F2;padding:10px;border-radius:8px;">
                        <span class="text-xs text-muted block">Absent</span>
                        <strong style="color:var(--danger);"><?php echo $stats['absent'] ?? 0; ?></strong>
                    </div>
                    <div style="background:#FEF3C7;padding:10px;border-radius:8px;">
                        <span class="text-xs text-muted block">Late</span>
                        <strong style="color:var(--warning);"><?php echo $stats['late'] ?? 0; ?></strong>
                    </div>
                    <div style="background:#EEF2FF;padding:10px;border-radius:8px;">
                        <span class="text-xs text-muted block">Excused</span>
                        <strong style="color:var(--primary);"><?php echo $stats['excused'] ?? 0; ?></strong>
                    </div>
                </div>
            </div>
        </div>
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            if(typeof drawDonutChart === 'function') {
                drawDonutChart('attnChart', [<?php echo $presentCount; ?>, <?php echo $stats['absent'] ?? 0; ?>, <?php echo $stats['excused'] ?? 0; ?>], ['#10B981', '#EF4444', '#3B82F6'], ['Present', 'Absent', 'Excused']);
            }
        });
        </script>
        <?php endif; ?>
    </div>

    <!-- Logs Column -->
    <div class="card">
        <div class="card-header" style="background:var(--body-bg);color:var(--text);border-bottom:1px solid var(--border);">
            <h3 style="font-size:16px;">Attendance Output</h3>
        </div>
        <div class="table-wrapper">
            <table class="table">
                <thead><tr><th>Date</th><th>Status</th></tr></thead>
                <tbody>
                    <?php if(empty($records)): ?>
                        <tr><td colspan="2" class="text-center py-4 text-muted">No attendance records found.</td></tr>
                    <?php else: ?>
                        <?php foreach($records as $rec): ?>
                            <tr>
                                <td><?php echo formatDate($rec['date'], 'l, M d, Y'); ?></td>
                                <td><?php echo statusBadge($rec['status']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php endif; ?>
</main>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
