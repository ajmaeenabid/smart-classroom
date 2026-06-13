<?php
/**
 * Daily Attendance view and submit for Teacher
 */
$pageTitle = 'Mark Attendance';
require_once __DIR__ . '/../includes/header.php';
requireTeacher();

$classId = $_GET['class_id'] ?? null;
$date = $_GET['date'] ?? date('Y-m-d');

if (!$classId) {
    $stmt = $pdo->prepare("SELECT id, class_name FROM classes WHERE teacher_id = ? AND status='active'");
    $stmt->execute([$_SESSION['user_id']]);
    $classes = $stmt->fetchAll();
} else {
    requireClassOwner($pdo, $classId);
    $classes = [['id' => $classId, 'class_name' => 'Current Class']];
}

$students = [];
$attendanceMap = [];

if ($classId && $date) {
    // Get students
    $stmt = $pdo->prepare("
        SELECT u.id, u.full_name 
        FROM class_enrollments ce
        JOIN users u ON ce.student_id = u.id
        WHERE ce.class_id = ?
        ORDER BY u.full_name
    ");
    $stmt->execute([$classId]);
    $students = $stmt->fetchAll();

    // Get existing attendance
    $stmt = $pdo->prepare("SELECT student_id, status FROM attendance WHERE class_id = ? AND date = ?");
    $stmt->execute([$classId, $date]);
    foreach ($stmt->fetchAll() as $row) {
        $attendanceMap[$row['student_id']] = $row['status'];
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        setFlash('error', 'Invalid request.');
    } else {
        $postClassId = $_POST['class_id'];
        $postDate = $_POST['date'];
        $attendanceData = $_POST['attendance'] ?? [];

        $pdo->beginTransaction();
        try {
            // Delete existing for this date/class
            $stmt = $pdo->prepare("DELETE FROM attendance WHERE class_id = ? AND date = ?");
            $stmt->execute([$postClassId, $postDate]);

            // Insert new
            $insertStmt = $pdo->prepare("INSERT INTO attendance (class_id, student_id, date, status, marked_by) VALUES (?, ?, ?, ?, ?)");
            foreach ($attendanceData as $studentId => $status) {
                $insertStmt->execute([$postClassId, $studentId, $postDate, $status, $_SESSION['user_id']]);
            }

            $pdo->commit();
            setFlash('success', 'Attendance saved successfully.');
            redirect(BASE_URL . "/teacher/attendance.php?class_id=$postClassId&date=$postDate");
        } catch (Exception $e) {
            $pdo->rollBack();
            setFlash('error', 'Failed to save attendance.');
        }
    }
}

require_once __DIR__ . '/../includes/navbar.php';
?>

<div class="page-header">
    <div>
        <h1>Mark Attendance</h1>
        <p>Record daily presence for your students.</p>
    </div>
</div>

<div class="card mb-4" style="max-width: 800px;">
    <div class="card-body">
        <form method="GET" action="" style="display:flex;gap:16px;align-items:flex-end;flex-wrap:wrap;">
            <div class="form-group mb-0" style="flex:1;min-width:200px;">
                <label class="form-label">Select Class</label>
                <select name="class_id" class="form-control" onchange="this.form.submit()">
                    <option value="">-- Choose Class --</option>
                    <?php if (isset($classes)) foreach ($classes as $c): ?>
                        <option value="<?php echo $c['id']; ?>" <?php echo $classId == $c['id'] ? 'selected' : ''; ?>><?php echo e($c['class_name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group mb-0" style="flex:1;min-width:200px;">
                <label class="form-label">Date</label>
                <input type="date" name="date" class="form-control" value="<?php echo e($date); ?>" onchange="this.form.submit()">
            </div>
        </form>
    </div>
</div>

<?php if ($classId): ?>
<div class="card" style="max-width: 800px;">
    <div class="card-header" style="background:var(--body-bg);color:var(--text);border-bottom:1px solid var(--border);">
        <h3 style="font-size:16px;">Attendance List for <?php echo formatDate($date); ?></h3>
    </div>
    
    <?php if (empty($students)): ?>
        <div class="empty-state">
            <div class="empty-icon">👥</div>
            <h3>No students enrolled</h3>
            <p>Students need to join the class before you can mark attendance.</p>
        </div>
    <?php else: ?>
        <form method="POST" action="">
            <?php echo csrfField(); ?>
            <input type="hidden" name="class_id" value="<?php echo $classId; ?>">
            <input type="hidden" name="date" value="<?php echo $date; ?>">

            <div class="table-wrapper text-left">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Student Name</th>
                            <th style="width:200px;">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($students as $stu): 
                            $status = $attendanceMap[$stu['id']] ?? 'present';
                        ?>
                        <tr>
                            <td style="font-weight:500;"><?php echo e($stu['full_name']); ?></td>
                            <td>
                                <select name="attendance[<?php echo $stu['id']; ?>]" class="form-control" style="padding:6px 12px;font-size:13px;">
                                    <option value="present" <?php echo $status==='present'?'selected':'';?>>Present</option>
                                    <option value="absent" <?php echo $status==='absent'?'selected':'';?>>Absent</option>
                                    <option value="late" <?php echo $status==='late'?'selected':'';?>>Late</option>
                                    <option value="excused" <?php echo $status==='excused'?'selected':'';?>>Excused</option>
                                </select>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <div class="card-body bg-light" style="border-top:1px solid var(--border);">
                <button type="submit" class="btn btn-primary">Save Attendance</button>
            </div>
        </form>
    <?php endif; ?>
</div>
<?php endif; ?>

</main>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
