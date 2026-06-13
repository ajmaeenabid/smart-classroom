/**
 * Advanced Classroom System — Main JavaScript
 * Handles UI interactions, sidebar, dropdowns, AI helper, etc.
 */

document.addEventListener('DOMContentLoaded', function() {
    initSidebar();
    initUserMenu();
    initAiAssistant();
    initFlashMessages();
    initFileUploads();
    initTabs();
});

/* === Sidebar Toggle === */
function initSidebar() {
    const toggle = document.getElementById('sidebarToggle');
    const sidebar = document.getElementById('sidebar');
    if (!toggle || !sidebar) return;

    toggle.addEventListener('click', function() {
        sidebar.classList.toggle('collapsed');
        sidebar.classList.toggle('show');
    });

    // Close on mobile when clicking outside
    document.addEventListener('click', function(e) {
        if (window.innerWidth <= 1024 && sidebar.classList.contains('show') 
            && !sidebar.contains(e.target) && !toggle.contains(e.target)) {
            sidebar.classList.remove('show');
        }
    });
}

/* === User Menu Dropdown === */
function initUserMenu() {
    const toggle = document.getElementById('userMenuToggle');
    const dropdown = document.getElementById('userDropdown');
    if (!toggle || !dropdown) return;

    toggle.addEventListener('click', function(e) {
        e.stopPropagation();
        dropdown.classList.toggle('active');
    });

    document.addEventListener('click', function(e) {
        if (!dropdown.contains(e.target)) dropdown.classList.remove('active');
    });
}

/* === Flash Messages Auto-dismiss === */
function initFlashMessages() {
    const flash = document.getElementById('flashMessage');
    if (flash) {
        setTimeout(function() {
            flash.style.transition = 'opacity .5s, transform .5s';
            flash.style.opacity = '0';
            flash.style.transform = 'translateY(-10px)';
            setTimeout(function() { flash.remove(); }, 500);
        }, 5000);
    }
}

/* === AI Assistant === */
function initAiAssistant() {
    const toggle = document.getElementById('aiToggle');
    const panel = document.getElementById('aiPanel');
    if (!toggle || !panel) return;

    toggle.addEventListener('click', function() {
        panel.classList.toggle('active');
    });
}

function aiTip(type) {
    const body = document.getElementById('aiBody');
    const tips = {
        deadlines: [
            "📅 Check your dashboard for upcoming due dates.",
            "💡 Tip: Submit assignments at least a day before the deadline!",
            "⏰ Assignments with red labels are past due."
        ],
        missing: [
            "⚠️ Review your classwork tab for any unsubmitted assignments.",
            "📋 Missing submissions affect your overall grade.",
            "✅ Check each class individually for pending work."
        ],
        tips: [
            "📖 Break study sessions into 25-minute focused intervals.",
            "🎯 Prioritize assignments by due date — earliest first.",
            "📝 Review teacher feedback to improve future submissions.",
            "🧠 Teach concepts to others — it's the best way to learn!"
        ],
        progress: [
            "📊 Visit your Progress page for a detailed breakdown.",
            "📈 Track your grades across all enrolled classes.",
            "🎯 Set weekly goals to stay on track with coursework."
        ]
    };

    const messages = tips[type] || ["I'm here to help! Ask me anything about your classes."];
    const randomMsg = messages[Math.floor(Math.random() * messages.length)];

    const msgDiv = document.createElement('div');
    msgDiv.className = 'ai-message ai-bot';
    msgDiv.innerHTML = '<p>' + randomMsg + '</p>';
    body.appendChild(msgDiv);
    body.scrollTop = body.scrollHeight;
}

/* === File Upload Drag & Drop === */
function initFileUploads() {
    document.querySelectorAll('.file-upload-area').forEach(function(area) {
        const input = area.querySelector('input[type="file"]');
        if (!input) return;

        area.addEventListener('click', function() { input.click(); });

        area.addEventListener('dragover', function(e) {
            e.preventDefault();
            area.classList.add('dragover');
        });

        area.addEventListener('dragleave', function() {
            area.classList.remove('dragover');
        });

        area.addEventListener('drop', function(e) {
            e.preventDefault();
            area.classList.remove('dragover');
            if (e.dataTransfer.files.length) {
                input.files = e.dataTransfer.files;
                updateFileLabel(area, e.dataTransfer.files[0].name);
            }
        });

        input.addEventListener('change', function() {
            if (input.files.length) {
                updateFileLabel(area, input.files[0].name);
            }
        });
    });
}

function updateFileLabel(area, filename) {
    let label = area.querySelector('.file-name');
    if (!label) {
        label = document.createElement('p');
        label.className = 'file-name mt-1';
        label.style.fontWeight = '600';
        label.style.color = '#4F46E5';
        area.appendChild(label);
    }
    label.textContent = '📎 ' + filename;
}

/* === Tabs === */
function initTabs() {
    document.querySelectorAll('.tab-btn').forEach(function(btn) {
        btn.addEventListener('click', function() {
            const tabGroup = btn.closest('.tabs-container') || btn.closest('.tabs').parentElement;
            
            tabGroup.querySelectorAll('.tab-btn').forEach(function(b) { b.classList.remove('active'); });
            tabGroup.querySelectorAll('.tab-content').forEach(function(c) { c.classList.remove('active'); });
            
            btn.classList.add('active');
            const target = document.getElementById(btn.dataset.tab);
            if (target) target.classList.add('active');
        });
    });
}

/* === Confirm Delete === */
function confirmDelete(message) {
    return confirm(message || 'Are you sure you want to delete this? This action cannot be undone.');
}

/* === Simple Chart Drawing (Canvas) === */
function drawBarChart(canvasId, labels, data, colors) {
    const canvas = document.getElementById(canvasId);
    if (!canvas) return;
    const ctx = canvas.getContext('2d');
    const w = canvas.width = canvas.parentElement.offsetWidth;
    const h = canvas.height = 250;
    const padding = 40;
    const barWidth = Math.min(60, (w - padding * 2) / data.length - 10);
    const maxVal = Math.max(...data, 1);
    const chartH = h - padding * 2;

    ctx.clearRect(0, 0, w, h);

    // Grid lines
    ctx.strokeStyle = '#E2E8F0';
    ctx.lineWidth = 1;
    for (let i = 0; i <= 4; i++) {
        const y = padding + (chartH / 4) * i;
        ctx.beginPath(); ctx.moveTo(padding, y); ctx.lineTo(w - padding, y); ctx.stroke();
        ctx.fillStyle = '#94A3B8'; ctx.font = '11px Inter';
        ctx.fillText(Math.round(maxVal - (maxVal / 4) * i), 5, y + 4);
    }

    // Bars
    data.forEach(function(val, i) {
        const x = padding + i * ((w - padding * 2) / data.length) + 5;
        const barH = (val / maxVal) * chartH;
        const y = padding + chartH - barH;

        const gradient = ctx.createLinearGradient(x, y, x, y + barH);
        gradient.addColorStop(0, colors[i % colors.length]);
        gradient.addColorStop(1, colors[i % colors.length] + '88');
        ctx.fillStyle = gradient;

        // Rounded top
        const r = Math.min(6, barWidth / 2);
        ctx.beginPath();
        ctx.moveTo(x, y + barH);
        ctx.lineTo(x, y + r);
        ctx.quadraticCurveTo(x, y, x + r, y);
        ctx.lineTo(x + barWidth - r, y);
        ctx.quadraticCurveTo(x + barWidth, y, x + barWidth, y + r);
        ctx.lineTo(x + barWidth, y + barH);
        ctx.fill();

        // Value on top
        ctx.fillStyle = '#1E293B'; ctx.font = 'bold 12px Inter'; ctx.textAlign = 'center';
        ctx.fillText(val, x + barWidth / 2, y - 8);

        // Label
        ctx.fillStyle = '#64748B'; ctx.font = '11px Inter';
        ctx.fillText(labels[i], x + barWidth / 2, h - 10);
    });
}

/* === Donut Chart === */
function drawDonutChart(canvasId, data, colors, labels) {
    const canvas = document.getElementById(canvasId);
    if (!canvas) return;
    const ctx = canvas.getContext('2d');
    const size = Math.min(canvas.parentElement.offsetWidth, 200);
    canvas.width = size; canvas.height = size;
    const cx = size / 2, cy = size / 2, r = size / 2 - 10, inner = r * 0.6;
    const total = data.reduce(function(a, b) { return a + b; }, 0) || 1;
    let startAngle = -Math.PI / 2;

    data.forEach(function(val, i) {
        const sliceAngle = (val / total) * Math.PI * 2;
        ctx.beginPath();
        ctx.arc(cx, cy, r, startAngle, startAngle + sliceAngle);
        ctx.arc(cx, cy, inner, startAngle + sliceAngle, startAngle, true);
        ctx.closePath();
        ctx.fillStyle = colors[i % colors.length];
        ctx.fill();
        startAngle += sliceAngle;
    });

    // Center text
    ctx.fillStyle = '#1E293B'; ctx.font = 'bold 24px Inter'; ctx.textAlign = 'center';
    ctx.fillText(total, cx, cy + 4);
    ctx.fillStyle = '#94A3B8'; ctx.font = '11px Inter';
    ctx.fillText('Total', cx, cy + 20);
}
