<?php
// =============================================
// Smart Classroom — Attendance Tracker
// =============================================
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/layout.php';
requireLogin();

$user      = currentUser();
$classId   = (int)($_GET['class_id'] ?? 0);
$isTeacher = ($user['role'] === 'teacher');
$today     = date('Y-m-d');
$selDate   = $_GET['date'] ?? $today;

if (!$classId) redirect(BASE_URL . '/index.php');

$cls = $pdo->prepare("SELECT c.*,u.name as teacher_name FROM classes c JOIN users u ON u.id=c.teacher_id WHERE c.id=?");
$cls->execute([$classId]);
$class = $cls->fetch();

// Get students
$members = $pdo->prepare("SELECT u.* FROM users u JOIN class_members cm ON cm.user_id=u.id WHERE cm.class_id=? ORDER BY u.name");
$members->execute([$classId]);
$students = $members->fetchAll();

// Mark attendance (teacher POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $isTeacher && isset($_POST['attendance'])) {
    $date = $_POST['date'] ?? $today;
    foreach ($students as $s) {
        $status = $_POST['attendance'][$s['id']] ?? '';
        if ($status === 'clear' || $status === '') {
            // Delete attendance record if clear/empty selected
            $del = $pdo->prepare("DELETE FROM attendance WHERE class_id=? AND student_id=? AND date=?");
            $del->execute([$classId, $s['id'], $date]);
        } else {
            $ins = $pdo->prepare("INSERT INTO attendance (class_id, student_id, date, status) VALUES (?,?,?,?) ON DUPLICATE KEY UPDATE status=VALUES(status)");
            $ins->execute([$classId, $s['id'], $date, $status]);
        }
    }
    // Notify students
    foreach ($students as $s) {
        $status = $_POST['attendance'][$s['id']] ?? '';
        if ($status === 'absent') {
            $notif = $pdo->prepare("INSERT INTO notifications (user_id, title, message, type) VALUES (?,?,?,?)");
            $notif->execute([$s['id'], 'Attendance Marked', "You were marked absent on {$date} in {$class['name']}", 'warning']);
        }
    }
    SCS_redirect(BASE_URL . "/classroom/attendance.php?class_id={$classId}&date={$date}&saved=1");
}

function SCS_redirect($url) { header("Location: $url"); exit; }

// Attendance for selected date
$attForDate = [];
if ($isTeacher) {
    $att = $pdo->prepare("SELECT * FROM attendance WHERE class_id=? AND date=?");
    $att->execute([$classId, $selDate]);
    foreach ($att->fetchAll() as $a) $attForDate[$a['student_id']] = $a['status'];
}

// Student's own attendance
$myAttendance = [];
if (!$isTeacher) {
    $att = $pdo->prepare("SELECT * FROM attendance WHERE class_id=? AND student_id=? ORDER BY date DESC");
    $att->execute([$classId, $user['id']]);
    $myAttendance = $att->fetchAll();
}

// Summary stats
$stats = [];
foreach ($students as $s) {
    $p = $pdo->prepare("SELECT status, COUNT(*) as cnt FROM attendance WHERE class_id=? AND student_id=? GROUP BY status");
    $p->execute([$classId, $s['id']]);
    $statusRows = $p->fetchAll();
    $cnt = ['present'=>0,'absent'=>0,'late'=>0,'excused'=>0];
    foreach ($statusRows as $r) $cnt[$r['status']] = $r['cnt'];
    $total = array_sum($cnt);
    $stats[$s['id']] = $cnt + ['total'=>$total, 'rate'=>$total > 0 ? round($cnt['present']/$total*100,1) : 100];
}

renderHead('Attendance · ' . ($class['name'] ?? ''));
?>
<body>
<div class="app-wrapper">
<?php renderSidebar($user, 'attendance.php'); ?>
<div class="main-content">
<?php renderTopbar('Attendance Tracker', $user); ?>

<div class="page-content animate-up">
  <div style="display:flex;align-items:center;gap:0.5rem;margin-bottom:1.25rem;font-size:0.875rem;color:var(--text-muted)">
    <a href="<?= BASE_URL ?>/classroom/index.php?id=<?= $classId ?>">← Back to <?= e($class['name'] ?? '') ?></a>
  </div>

  <?php if (isset($_GET['saved'])): ?>
  <div style="background:rgba(16,185,129,0.1);border:1px solid rgba(16,185,129,0.3);border-radius:var(--radius-sm);padding:0.75rem 1rem;font-size:0.875rem;color:var(--success);display:flex;align-items:center;gap:0.5rem;margin-bottom:1rem auto-dismiss">
    <i class="fas fa-check-circle"></i> Attendance saved successfully!
  </div>
  <?php endif; ?>

  <?php if ($isTeacher): ?>
  <!-- ── TEACHER VIEW ─────────────────────── -->

  <!-- Session Warning -->
  <div style="background:rgba(245,158,11,0.1);border:1px solid rgba(245,158,11,0.3);border-radius:var(--radius-sm);padding:0.75rem;margin-bottom:1rem">
    <div style="display:flex;align-items:center;gap:0.5rem;color:var(--warning);font-size:0.875rem">
      <i class="fas fa-exclamation-triangle"></i>
      <span><strong>Testing Tip:</strong> Students must use a different browser or incognito mode to scan the QR code. Same-browser logins will conflict with your session.</span>
    </div>
  </div>

  <div style="display:grid;grid-template-columns:1fr 300px;gap:1.5rem">
    <div>
      <!-- Date selector & mark form -->
      <div class="card" style="margin-bottom:1.5rem">
        <div class="card-header">
          <div class="card-title"><i class="fas fa-calendar-check" style="color:var(--success)"></i> Mark Attendance</div>
          <form method="GET" style="display:flex;align-items:center;gap:0.5rem">
            <input type="hidden" name="class_id" value="<?= $classId ?>">
            <input type="date" name="date" value="<?= $selDate ?>" class="form-control" style="width:auto" onchange="this.form.submit()">
          </form>
        </div>
        <form method="POST">
          <input type="hidden" name="date" value="<?= $selDate ?>">
          <div class="table-wrap">
            <table>
              <thead><tr><th>#</th><th>Student</th><th>Present</th><th>Late</th><th>Absent</th><th>Excused</th><th>Clear</th><th>Rate</th></tr></thead>
              <tbody>
              <?php foreach ($students as $i => $s): ?>
              <?php $curStatus = $attForDate[$s['id']] ?? ''; ?>
              <tr>
                <td style="color:var(--text-muted)"><?= $i+1 ?></td>
                <td>
                  <div style="display:flex;align-items:center;gap:0.5rem">
                    <div class="avatar" style="width:30px;height:30px;font-size:0.75rem"><?= strtoupper($s['name'][0]) ?></div>
                    <span style="font-weight:500;font-size:0.875rem"><?= e($s['name']) ?></span>
                  </div>
                </td>
                <?php foreach (['present','late','absent','excused'] as $status): ?>
                <td style="text-align:center">
                  <label style="cursor:pointer;display:flex;align-items:center;justify-content:center">
                    <input type="radio" name="attendance[<?= $s['id'] ?>]" value="<?= $status ?>" <?= $curStatus===$status ? 'checked' : '' ?> style="width:18px;height:18px;accent-color:var(--<?= $status==='present'?'success':($status==='absent'?'danger':($status==='late'?'warning':'info')) ?>)">
                  </label>
                </td>
                <?php endforeach; ?>
                <td style="text-align:center">
                  <label style="cursor:pointer;display:flex;align-items:center;justify-content:center" title="Clear/Remove attendance">
                    <input type="radio" name="attendance[<?= $s['id'] ?>]" value="clear" <?= $curStatus==='' ? 'checked' : '' ?> style="width:18px;height:18px;accent-color:var(--text-muted)">
                  </label>
                </td>
                <td>
                  <?php $r = $stats[$s['id']] ?? []; ?>
                  <span style="font-weight:700;font-size:0.85rem;color:var(--<?= ($r['rate']??100) >= 80 ? 'success' : 'warning' ?>)"><?= $r['rate'] ?? 100 ?>%</span>
                </td>
              </tr>
              <?php endforeach; ?>
              </tbody>
            </table>
          </div>
          <div style="display:flex;justify-content:flex-end;gap:0.75rem;margin-top:1rem;padding-top:1rem;border-top:1px solid var(--border)">
            <button type="button" class="btn btn-secondary" onclick="markAll('present')"><i class="fas fa-check"></i> All Present</button>
            <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save Attendance</button>
          </div>
        </form>
      </div>

      <div class="card mt-4" id="qr-session-card">
        <div class="card-header">
           <div class="card-title"><i class="fas fa-qrcode" style="color:var(--primary)"></i> Time-Limited QR Attendance</div>
        </div>
        <div style="padding:1.5rem">
           <p style="color:var(--text-secondary);font-size:0.9rem;margin-bottom:1rem">
              Generate a time-limited QR code for this class session. Students can scan it remotely to mark themselves present. The QR code expires automatically.
           </p>
           <!-- Duration Selector -->
           <div style="display:flex;align-items:center;gap:0.75rem;margin-bottom:1.25rem">
             <label style="font-size:0.875rem;font-weight:600;color:var(--text-primary)"><i class="fas fa-clock"></i> Duration:</label>
             <select id="qr-duration" class="form-control" style="width:auto">
               <option value="2">2 minutes</option>
               <option value="3">3 minutes</option>
               <option value="4">4 minutes</option>
               <option value="5" selected>5 minutes</option>
             </select>
           </div>
           <div style="display:flex;flex-direction:column;align-items:center;text-align:center;gap:1rem">
             <div id="qr-code-display" style="display:none;background:white;padding:1rem;border-radius:8px"></div>
             <div id="qr-expire-text" style="display:none;color:var(--danger);font-size:1rem;font-weight:700;padding:0.5rem 1rem;background:rgba(239,68,68,0.1);border-radius:var(--radius-sm)"></div>
             <div style="display:flex;gap:1rem">
                <button type="button" class="btn btn-primary" id="btn-generate-qr" onclick="event.preventDefault(); console.log('Generate button clicked'); generateQR(); return false;"><i class="fas fa-qrcode"></i> Generate Attendance QR</button>
                <button class="btn btn-secondary hidden" id="btn-stop-qr" onclick="stopQR()"><i class="fas fa-stop-circle"></i> End Session</button>
             </div>
           </div>
           <!-- Live Present List -->
           <div id="qr-live-list" style="display:none;margin-top:1.5rem;border-top:1px solid var(--border);padding-top:1rem">
             <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:0.75rem">
               <div style="font-weight:700;font-size:0.9rem"><i class="fas fa-user-check" style="color:var(--success)"></i> Live — Students Marked Present</div>
               <span id="qr-live-count" class="badge badge-success">0</span>
             </div>
             <div id="qr-live-students" style="display:flex;flex-direction:column;gap:0.375rem;max-height:200px;overflow-y:auto"></div>
           </div>
        </div>
      </div>
    </div>

    <!-- Summary sidebar -->
    <div style="display:flex;flex-direction:column;gap:1rem">
      <div class="card">
        <div class="card-title" style="margin-bottom:1rem"><i class="fas fa-chart-pie" style="color:var(--primary)"></i> Class Summary</div>

        <?php
          // Today's count
          $todayAtt = $pdo->prepare("SELECT status, COUNT(*) c FROM attendance WHERE class_id=? AND date=? GROUP BY status");
          $todayAtt->execute([$classId, $selDate]);
          $todayCounts = ['present'=>0,'absent'=>0,'late'=>0,'excused'=>0];
          foreach ($todayAtt->fetchAll() as $r) $todayCounts[$r['status']]=$r['c'];
        ?>
        <?php foreach ([['present','success','fa-check-circle'],['absent','danger','fa-times-circle'],['late','warning','fa-exclamation-circle'],['excused','info','fa-info-circle']] as [$status,$color,$icon]): ?>
        <div style="display:flex;align-items:center;justify-content:space-between;padding:0.625rem 0;border-bottom:1px solid var(--border)">
          <div style="display:flex;align-items:center;gap:0.5rem;font-size:0.875rem;text-transform:capitalize">
            <i class="fas <?= $icon ?>" style="color:var(--<?= $color ?>)"></i> <?= $status ?>
          </div>
          <span style="font-weight:700;color:var(--<?= $color ?>)"><?= $todayCounts[$status] ?></span>
        </div>
        <?php endforeach; ?>

        <div style="margin-top:1rem">
          <div style="font-size:0.8rem;color:var(--text-muted);margin-bottom:0.5rem">Attendance rate trend</div>
          <div class="chart-wrapper" style="height:140px">
            <canvas id="att-mini-chart"></canvas>
          </div>
        </div>
      </div>

      <!-- Low attendance alerts -->
      <?php $lowAtt = array_filter($stats, fn($s) => ($s['rate'] ?? 100) < 75); ?>
      <?php if (!empty($lowAtt)): ?>
      <div class="card" style="border-left:3px solid var(--danger)">
        <div class="card-title" style="margin-bottom:0.75rem;color:var(--danger)"><i class="fas fa-exclamation-triangle"></i> Low Attendance Alert</div>
        <?php foreach ($students as $s): ?>
        <?php if (isset($stats[$s['id']]) && $stats[$s['id']]['rate'] < 75): ?>
        <div style="display:flex;align-items:center;gap:0.5rem;padding:0.5rem 0;border-bottom:1px solid var(--border);font-size:0.8rem">
          <div class="avatar" style="width:24px;height:24px;font-size:0.65rem"><?= strtoupper($s['name'][0]) ?></div>
          <span style="flex:1"><?= e($s['name']) ?></span>
          <span style="color:var(--danger);font-weight:700"><?= $stats[$s['id']]['rate'] ?>%</span>
        </div>
        <?php endif; ?>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </div>
  </div>

  <?php else: ?>
  <!-- ── STUDENT VIEW ─────────────────────── -->

  <!-- Session Warning -->
  <div style="background:rgba(245,158,11,0.1);border:1px solid rgba(245,158,11,0.3);border-radius:var(--radius-sm);padding:0.75rem;margin-bottom:1rem">
    <div style="display:flex;align-items:center;gap:0.5rem;color:var(--warning);font-size:0.875rem">
      <i class="fas fa-exclamation-triangle"></i>
      <span><strong>Testing Tip:</strong> If Teacher & Student are on same computer, use different browsers (Chrome vs Firefox) or incognito mode to avoid login conflicts.</span>
    </div>
  </div>

  <!-- QR Scan / Enter Token Section -->
  <div class="card" style="margin-bottom:1.5rem;border-left:3px solid var(--primary)" id="student-qr-section">
    <div class="card-header">
      <div class="card-title"><i class="fas fa-qrcode" style="color:var(--primary)"></i> QR Attendance Check-in</div>
      <span id="qr-status-badge" class="badge badge-secondary">Checking...</span>
    </div>
    <div style="padding:1.25rem">
      <div id="qr-active-notice" style="display:none;background:rgba(16,185,129,0.1);border:1px solid rgba(16,185,129,0.3);border-radius:var(--radius-sm);padding:0.75rem;margin-bottom:1rem">
        <div style="display:flex;align-items:center;gap:0.5rem;color:var(--success);font-weight:600">
          <i class="fas fa-broadcast-tower"></i>
          <span>Live QR Session Active!</span>
        </div>
        <div style="font-size:0.8rem;color:var(--text-secondary);margin-top:0.25rem">Your teacher has generated a QR code. Scan it with your camera or enter the token below!</div>
        <div id="qr-student-timer" style="font-size:0.75rem;color:var(--warning);margin-top:0.25rem;font-weight:500"></div>
        <div id="qr-student-display" style="display:flex;justify-content:center;margin-top:1rem">
          <img id="qr-student-image" src="" width="250" height="250" alt="Attendance QR Code" style="border-radius:8px;box-shadow:0 4px 12px rgba(0,0,0,0.1);background:white;padding:8px;">
        </div>
        <div id="qr-token-display" style="text-align:center;margin-top:0.75rem;display:none">
          <div style="font-size:0.7rem;color:var(--text-muted);margin-bottom:0.25rem">Or enter this token:</div>
          <code id="qr-token-code" style="background:#1e293b;padding:0.5rem 1rem;border-radius:4px;font-family:monospace;font-size:0.9rem;color:var(--primary);letter-spacing:1px"></code>
        </div>
      </div>
      <div id="qr-inactive-notice" style="background:rgba(148,163,184,0.1);border:1px solid rgba(148,163,184,0.3);border-radius:var(--radius-sm);padding:0.75rem;margin-bottom:1rem">
        <div style="display:flex;align-items:center;gap:0.5rem;color:var(--text-muted);font-size:0.875rem">
          <i class="fas fa-info-circle"></i>
          <span>Waiting for teacher to generate QR code...</span>
        </div>
      </div>
      <!-- Manual check button -->
      <div style="margin-bottom:1rem">
        <button type="button" class="btn btn-ghost btn-sm" onclick="checkStudentQRStatus()" style="font-size:0.75rem">
          <i class="fas fa-sync"></i> Check for QR Code
        </button>
        <span id="last-check-time" style="font-size:0.7rem;color:var(--text-muted);margin-left:0.5rem"></span>
      </div>
      <p style="color:var(--text-secondary);font-size:0.875rem;margin-bottom:1rem">When your teacher generates a QR code, scan it with your device camera or enter the token below to mark yourself present.</p>
      <div style="display:flex;gap:0.75rem;align-items:flex-end">
        <div class="form-group" style="flex:1;margin-bottom:0">
          <label class="form-label">Attendance Token</label>
          <input type="text" id="qr-token-input" class="form-control" placeholder="Paste token or scan QR URL" style="font-family:monospace;font-size:0.9rem">
        </div>
        <button class="btn btn-primary" onclick="submitQRToken()" id="btn-submit-token"><i class="fas fa-check-circle"></i> Check In</button>
      </div>
      <div id="qr-scan-result" style="display:none;margin-top:1rem;padding:0.75rem;border-radius:var(--radius-sm);font-size:0.875rem"></div>
      <!-- Camera Scan Option -->
      <div style="margin-top:1rem;border-top:1px solid var(--border);padding-top:1rem">
        <button class="btn btn-secondary btn-sm" onclick="startCameraScan()" id="btn-camera-scan"><i class="fas fa-camera"></i> Scan QR with Camera</button>
        <div id="camera-scan-area" style="display:none;margin-top:0.75rem">
          <video id="qr-camera-video" style="width:100%;max-width:320px;border-radius:var(--radius-sm);border:1px solid var(--border)"></video>
          <canvas id="qr-camera-canvas" style="display:none"></canvas>
          <button class="btn btn-ghost btn-sm" onclick="stopCameraScan()" style="margin-top:0.5rem"><i class="fas fa-times"></i> Stop Camera</button>
        </div>
      </div>
    </div>
  </div>

  <?php
    $myPresent  = count(array_filter($myAttendance, fn($a) => $a['status']==='present'));
    $myAbsent   = count(array_filter($myAttendance, fn($a) => $a['status']==='absent'));
    $myLate     = count(array_filter($myAttendance, fn($a) => $a['status']==='late'));
    $myTotal    = count($myAttendance);
    $myRate     = $myTotal > 0 ? round($myPresent / $myTotal * 100, 1) : 100;
  ?>
  <div class="grid grid-4 gap-4 mb-4">
    <div class="stat-card" style="border-left:3px solid var(--success)"><div class="stat-icon" style="background:rgba(16,185,129,0.15);color:var(--success)"><i class="fas fa-check"></i></div><div class="stat-info"><div class="stat-value" style="color:var(--success)"><?= $myRate ?>%</div><div class="stat-label">Attendance Rate</div></div></div>
    <div class="stat-card" style="border-left:3px solid var(--primary)"><div class="stat-icon" style="background:rgba(99,102,241,0.15);color:var(--primary)"><i class="fas fa-check-circle"></i></div><div class="stat-info"><div class="stat-value" style="color:var(--primary)"><?= $myPresent ?></div><div class="stat-label">Days Present</div></div></div>
    <div class="stat-card" style="border-left:3px solid var(--danger)"><div class="stat-icon" style="background:rgba(239,68,68,0.15);color:var(--danger)"><i class="fas fa-times-circle"></i></div><div class="stat-info"><div class="stat-value" style="color:var(--danger)"><?= $myAbsent ?></div><div class="stat-label">Days Absent</div></div></div>
    <div class="stat-card" style="border-left:3px solid var(--warning)"><div class="stat-icon" style="background:rgba(245,158,11,0.15);color:var(--warning)"><i class="fas fa-exclamation-circle"></i></div><div class="stat-info"><div class="stat-value" style="color:var(--warning)"><?= $myLate ?></div><div class="stat-label">Days Late</div></div></div>
  </div>

  <div class="card">
    <div class="card-header">
      <div class="card-title"><i class="fas fa-calendar" style="color:var(--primary)"></i> My Attendance Record</div>
      <span class="badge badge-<?= $myRate >= 75 ? 'success' : 'danger' ?>"><?= $myRate >= 75 ? 'Good Standing' : 'Low Attendance' ?></span>
    </div>
    <?php if (empty($myAttendance)): ?>
    <div class="empty-state"><div class="empty-icon"><i class="fas fa-calendar"></i></div><div class="empty-title">No records yet</div></div>
    <?php else: ?>
    <div class="table-wrap">
      <table>
        <thead><tr><th>Date</th><th>Status</th></tr></thead>
        <tbody>
        <?php foreach ($myAttendance as $a): ?>
        <tr>
          <td><?= date('D, M d Y', strtotime($a['date'])) ?></td>
          <td><span class="badge badge-<?= ['present'=>'success','absent'=>'danger','late'=>'warning','excused'=>'info'][$a['status']] ?>"><?= $a['status'] ?></span></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>
  </div>
  <?php endif; ?>

</div>

<?php renderFooter(); ?>
<script>
function markAll(status) {
  document.querySelectorAll(`input[type=radio][value="${status}"]`).forEach(r => r.checked = true);
}

// Student QR Token Submission
async function submitQRToken() {
  let token = document.getElementById('qr-token-input').value.trim();
  if (!token) { SCS.showToast('Please enter or scan a token', 'error'); return; }
  // If user pasted a full URL, extract the token param
  try {
    const url = new URL(token);
    const t = url.searchParams.get('token');
    if (t) token = t;
  } catch(e) { /* not a URL, use as-is */ }

  const btn = document.getElementById('btn-submit-token');
  SCS.setLoading(btn, true);
  const fd = new FormData();
  fd.append('action', 'scan');
  fd.append('token', token);
  const res = await SCS.apiRequest('<?= BASE_URL ?>/api/qr_attendance.php', 'POST', fd);
  SCS.setLoading(btn, false);

  const resultEl = document.getElementById('qr-scan-result');
  resultEl.style.display = 'block';
  if (res.success) {
    resultEl.style.background = 'rgba(16,185,129,0.15)';
    resultEl.style.color = 'var(--success)';
    resultEl.innerHTML = '<i class="fas fa-check-circle"></i> ' + res.message;
    SCS.showToast('Attendance marked!', 'success');
    setTimeout(() => location.reload(), 2000);
  } else {
    const isWarn = res.error === 'already_marked';
    resultEl.style.background = isWarn ? 'rgba(245,158,11,0.15)' : 'rgba(239,68,68,0.15)';
    resultEl.style.color = isWarn ? 'var(--warning)' : 'var(--danger)';
    resultEl.innerHTML = `<i class="fas fa-${isWarn ? 'info-circle' : 'times-circle'}"></i> ${res.message || res.error}`;
  }
}

// Camera QR Scan (uses native BarcodeDetector if available, else manual)
let cameraStream = null;
async function startCameraScan() {
  const video = document.getElementById('qr-camera-video');
  const area = document.getElementById('camera-scan-area');
  try {
    cameraStream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' } });
    video.srcObject = cameraStream;
    video.play();
    area.style.display = 'block';
    document.getElementById('btn-camera-scan').style.display = 'none';
    // Try BarcodeDetector API (Chrome/Edge)
    if ('BarcodeDetector' in window) {
      const detector = new BarcodeDetector({ formats: ['qr_code'] });
      const scanLoop = async () => {
        if (!cameraStream) return;
        try {
          const barcodes = await detector.detect(video);
          if (barcodes.length > 0) {
            const val = barcodes[0].rawValue;
            document.getElementById('qr-token-input').value = val;
            stopCameraScan();
            submitQRToken();
            return;
          }
        } catch(e) {}
        requestAnimationFrame(scanLoop);
      };
      scanLoop();
    } else {
      // Fallback: instruct user to use phone's QR scanner app
      SCS.showToast('Use your device camera app to scan the QR, then paste the token above', 'info', 5000);
    }
  } catch(err) {
    SCS.showToast('Camera access denied. Please enter the token manually.', 'error');
  }
}
function stopCameraScan() {
  if (cameraStream) {
    cameraStream.getTracks().forEach(t => t.stop());
    cameraStream = null;
  }
  document.getElementById('camera-scan-area').style.display = 'none';
  document.getElementById('btn-camera-scan').style.display = 'inline-flex';
}

// QR Attendance Logic
let activeInterval, liveListInterval;
async function checkActiveQR() {
    const res = await SCS.apiRequest(`<?= BASE_URL ?>/api/qr_attendance.php?action=status&class_id=<?= $classId ?>&date=<?= $selDate ?>`);
    if(res.active) {
        document.getElementById('qr-code-display').innerHTML = `<img src="${res.qr_url}" width="200" height="200">`;
        document.getElementById('qr-code-display').style.display = 'block';
        document.getElementById('btn-generate-qr').style.display = 'none';
        document.getElementById('btn-stop-qr').classList.remove('hidden');
        document.getElementById('qr-expire-text').style.display = 'block';
        document.getElementById('qr-live-list').style.display = 'block';
        startCountdown(new Date(res.expires).getTime());
        startLiveList();
    }
}
async function generateQR() {
    const duration = document.getElementById('qr-duration').value;
    const fd = new FormData();
    fd.append('action', 'generate');
    fd.append('class_id', '<?= $classId ?>');
    fd.append('date', '<?= $selDate ?>');
    fd.append('duration', duration);
    console.log('Generating QR with duration:', duration);
    const btn = document.getElementById('btn-generate-qr');
    SCS.setLoading(btn, true);
    try {
        const res = await SCS.apiRequest('<?= BASE_URL ?>/api/qr_attendance.php', 'POST', fd);
        console.log('QR response:', res);
        SCS.setLoading(btn, false);
        if(res.success) {
            console.log('Setting QR display with URL:', res.qr_url);
            const qrDisplay = document.getElementById('qr-code-display');
            qrDisplay.innerHTML = `<img src="${res.qr_url}" width="200" height="200" alt="QR Code">`;
            qrDisplay.style.display = 'block';
            document.getElementById('btn-generate-qr').style.display = 'none';
            document.getElementById('btn-stop-qr').classList.remove('hidden');
            document.getElementById('qr-expire-text').style.display = 'block';
            document.getElementById('qr-live-list').style.display = 'block';
            startCountdown(new Date(res.expires).getTime());
            startLiveList();
            SCS.showToast(`QR session started (${res.duration} min)`, 'success');
        } else {
            console.error('QR generation failed:', res.error);
            SCS.showToast(res.error || 'Failed to generate QR', 'error');
        }
    } catch(e) {
        console.error('Error generating QR:', e);
        SCS.setLoading(btn, false);
        SCS.showToast('Error generating QR code', 'error');
    }
}
async function stopQR() {
    const fd = new FormData();
    fd.append('action', 'deactivate');
    fd.append('class_id', '<?= $classId ?>');
    await SCS.apiRequest('<?= BASE_URL ?>/api/qr_attendance.php', 'POST', fd);
    clearInterval(activeInterval);
    clearInterval(liveListInterval);
    document.getElementById('qr-code-display').style.display = 'none';
    document.getElementById('qr-expire-text').style.display = 'none';
    document.getElementById('qr-live-list').style.display = 'none';
    document.getElementById('btn-generate-qr').style.display = 'block';
    document.getElementById('btn-stop-qr').classList.add('hidden');
    SCS.showToast('QR session ended.', 'info');
    // Do NOT reload page - just hide the QR elements
}
function startCountdown(expireTime) {
    clearInterval(activeInterval);
    // Ensure expireTime is valid
    if (!expireTime || isNaN(expireTime)) {
        console.error('Invalid expireTime:', expireTime);
        return;
    }
    // Minimum 5 seconds buffer to prevent immediate expiration
    const minExpireTime = new Date().getTime() + 5000;
    if (expireTime < minExpireTime) {
        console.warn('Expire time too close, adjusting');
        expireTime = minExpireTime;
    }
    activeInterval = setInterval(() => {
        const now = new Date().getTime();
        const d = expireTime - now;
        if(d <= 0) {
            clearInterval(activeInterval);
            document.getElementById('qr-expire-text').textContent = '⏱ Expired';
            stopQR();
            return;
        }
        const m = Math.floor((d % (1000 * 60 * 60)) / (1000 * 60));
        const s = Math.floor((d % (1000 * 60)) / 1000);
        document.getElementById('qr-expire-text').textContent = `⏱ Expires in ${m}m ${s}s`;
    }, 1000);
}
async function fetchLiveList() {
    const res = await SCS.apiRequest(`<?= BASE_URL ?>/api/qr_attendance.php?action=live_list&class_id=<?= $classId ?>&date=<?= $selDate ?>`);
    if(res.success) {
        const container = document.getElementById('qr-live-students');
        const countEl = document.getElementById('qr-live-count');
        countEl.textContent = res.count;
        container.innerHTML = res.list.map(s => `
          <div style="display:flex;align-items:center;gap:0.5rem;padding:0.375rem 0.625rem;background:rgba(16,185,129,0.08);border-radius:var(--radius-sm);font-size:0.8rem">
            <i class="fas fa-check-circle" style="color:var(--success)"></i>
            <span style="flex:1;font-weight:500">${s.name}</span>
            <span style="color:var(--text-muted);font-size:0.7rem">${new Date(s.marked_at).toLocaleTimeString([], {hour:'2-digit',minute:'2-digit'})}</span>
          </div>
        `).join('');
    }
}
function startLiveList() {
    clearInterval(liveListInterval);
    fetchLiveList();
    liveListInterval = setInterval(fetchLiveList, 3000);
}

// Student QR Status Check - polls to see if teacher generated a QR code
let studentQrInterval;
async function checkStudentQRStatus() {
    try {
        const res = await SCS.apiRequest(`<?= BASE_URL ?>/api/qr_attendance.php?action=status&class_id=<?= $classId ?>&date=<?= $selDate ?>`);

        const badge = document.getElementById('qr-status-badge');
        const activeNotice = document.getElementById('qr-active-notice');
        const inactiveNotice = document.getElementById('qr-inactive-notice');
        const timerEl = document.getElementById('qr-student-timer');

        if (!badge || !activeNotice || !inactiveNotice) return;

        if (res.error) {
            badge.className = 'badge badge-danger';
            badge.textContent = 'Error';
            return;
        }

        // Update last check time
        const timeEl = document.getElementById('last-check-time');
        if (timeEl) timeEl.textContent = 'Last check: ' + new Date().toLocaleTimeString();

        if (res.active) {
            badge.className = 'badge badge-success';
            badge.innerHTML = '<i class="fas fa-broadcast-tower"></i> LIVE';
            activeNotice.style.display = 'block';
            inactiveNotice.style.display = 'none';

            // Display QR code
            if (res.qr_url) {
                const qrImg = document.getElementById('qr-student-image');
                if (qrImg) {
                    qrImg.src = res.qr_url;
                    qrImg.style.display = 'block';
                }
            }

            // Display token code
            if (res.token) {
                const tokenCode = document.getElementById('qr-token-code');
                const tokenDisplay = document.getElementById('qr-token-display');
                if (tokenCode) {
                    tokenCode.textContent = res.token;
                }
                if (tokenDisplay) {
                    tokenDisplay.style.display = 'block';
                }
                // Auto-fill token input
                const tokenInput = document.getElementById('qr-token-input');
                if (tokenInput) {
                    tokenInput.value = res.token;
                }
            }

            // Update countdown
            const expires = new Date(res.expires).getTime();
            const now = new Date().getTime();
            const diff = expires - now;
            if (diff > 0) {
                const m = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));
                const s = Math.floor((diff % (1000 * 60)) / 1000);
                timerEl.textContent = `⏱ Expires in ${m}m ${s}s - Scan now!`;
            }
        } else {
            badge.className = 'badge badge-secondary';
            badge.textContent = 'No Session';
            activeNotice.style.display = 'none';
            inactiveNotice.style.display = 'block';
            timerEl.textContent = '';
            // Hide token display
            const tokenDisplay = document.getElementById('qr-token-display');
            if (tokenDisplay) {
                tokenDisplay.style.display = 'none';
            }
        }
    } catch(e) {
        console.error('Student: Error checking QR status:', e);
    }
}

// Start student QR status polling
function startStudentQrPolling() {
    checkStudentQRStatus();
    studentQrInterval = setInterval(checkStudentQRStatus, 3000); // Check every 3 seconds
}

<?php if ($isTeacher): ?>
checkActiveQR();
<?php else: ?>
startStudentQrPolling();
<?php endif; ?>
</script>
</body></html>
