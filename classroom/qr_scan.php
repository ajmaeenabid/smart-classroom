<?php
// =============================================
// Smart Classroom — QR Scan Landing
// =============================================
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/layout.php';
requireLogin();

$user = currentUser();
$token = $_GET['token'] ?? '';

renderHead('Scan QR Attendance');
?>
<body style="display:flex;align-items:center;justify-content:center;min-height:100vh;background:var(--bg-base)">
  <div class="card" style="max-width:400px;width:100%;text-align:center">
    <div style="font-size:3rem;color:var(--primary);margin-bottom:1rem">
      <i class="fas fa-qrcode"></i>
    </div>
    <h2 style="font-size:1.5rem;font-weight:800;margin-bottom:0.5rem">Attendance Check-in</h2>
    <p style="color:var(--text-secondary);margin-bottom:1.5rem" id="scan-status-msg">Validating your session...</p>
    
    <div id="scan-result" style="display:none;padding:1rem;border-radius:var(--radius-sm);margin-bottom:1.5rem">
      <div id="scan-result-icon" style="font-size:2rem;margin-bottom:0.5rem"></div>
      <div id="scan-result-title" style="font-weight:700"></div>
    </div>

    <a href="<?= BASE_URL ?>/dashboard/student.php" class="btn btn-secondary btn-full"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>
  </div>

<script>
document.addEventListener('DOMContentLoaded', async () => {
    const token = '<?= htmlspecialchars($token) ?>';
    const statusMsg = document.getElementById('scan-status-msg');
    const resultBox = document.getElementById('scan-result');
    const resultIcon = document.getElementById('scan-result-icon');
    const resultTitle = document.getElementById('scan-result-title');
    
    if (!token) {
        statusMsg.textContent = "No token provided.";
        return;
    }
    
    const fd = new FormData();
    fd.append('action', 'scan');
    fd.append('token', token);
    
    try {
        const res = await fetch('<?= BASE_URL ?>/api/qr_attendance.php', { method: 'POST', body: fd });
        const data = await res.json();
        
        statusMsg.style.display = 'none';
        resultBox.style.display = 'block';
        
        if (data.success) {
            resultBox.style.background = 'rgba(16, 185, 129, 0.15)';
            resultBox.style.color = 'var(--success)';
            resultIcon.innerHTML = '<i class="fas fa-check-circle"></i>';
            resultTitle.textContent = data.message;
        } else {
            const isWarn = data.error === 'already_marked';
            resultBox.style.background = isWarn ? 'rgba(245, 158, 11, 0.15)' : 'rgba(239, 68, 68, 0.15)';
            resultBox.style.color = isWarn ? 'var(--warning)' : 'var(--danger)';
            resultIcon.innerHTML = isWarn ? '<i class="fas fa-info-circle"></i>' : '<i class="fas fa-times-circle"></i>';
            resultTitle.textContent = data.message || data.error;
        }
    } catch(err) {
        statusMsg.textContent = "Error communicating with server.";
    }
});
</script>
</body>
</html>
