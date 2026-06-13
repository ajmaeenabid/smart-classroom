/* ==============================================
   Charts.js — Smart Classroom Analytics
   ============================================== */

'use strict';

// Default chart theme
const CHART_THEME = {
  primary:   '#6366f1',
  secondary: '#0ea5e9',
  success:   '#10b981',
  warning:   '#f59e0b',
  danger:    '#ef4444',
  purple:    '#a855f7',
  pink:      '#ec4899',
  grid:      'rgba(255,255,255,0.05)',
  text:      '#94a3b8',
  textLight: '#64748b',
  bg:        'rgba(26,28,46,0.5)',
};

// Chart.js global defaults
if (typeof Chart !== 'undefined') {
  Chart.defaults.color = CHART_THEME.text;
  Chart.defaults.borderColor = CHART_THEME.grid;
  Chart.defaults.font.family = "'Inter', sans-serif";
  Chart.defaults.plugins.legend.labels.usePointStyle = true;
  Chart.defaults.plugins.legend.labels.padding = 16;
  Chart.defaults.plugins.tooltip.backgroundColor = '#1e2035';
  Chart.defaults.plugins.tooltip.borderColor = 'rgba(255,255,255,0.1)';
  Chart.defaults.plugins.tooltip.borderWidth = 1;
  Chart.defaults.plugins.tooltip.padding = 12;
  Chart.defaults.plugins.tooltip.titleColor = '#f1f5f9';
  Chart.defaults.plugins.tooltip.bodyColor = '#94a3b8';
  Chart.defaults.plugins.tooltip.cornerRadius = 8;
}

// ── Gradient Helper ───────────────────────────
function makeGradient(ctx, colors, alpha = [0.4, 0]) {
  const gradient = ctx.createLinearGradient(0, 0, 0, ctx.canvas.offsetHeight || 300);
  gradient.addColorStop(0, colors[0].replace('rgb', 'rgba').replace(')', `,${alpha[0]})`));
  gradient.addColorStop(1, colors[0].replace('rgb', 'rgba').replace(')', `,${alpha[1]})`));
  return gradient;
}

// ── Grade Distribution Chart ──────────────────
function renderGradeChart(canvasId, labels, data) {
  const canvas = document.getElementById(canvasId);
  if (!canvas || typeof Chart === 'undefined') return;
  const ctx = canvas.getContext('2d');
  return new Chart(ctx, {
    type: 'bar',
    data: {
      labels,
      datasets: [{
        label: 'Avg Grade',
        data,
        backgroundColor: [
          'rgba(99,102,241,0.7)', 'rgba(14,165,233,0.7)',
          'rgba(16,185,129,0.7)', 'rgba(245,158,11,0.7)',
          'rgba(168,85,247,0.7)', 'rgba(236,72,153,0.7)',
        ],
        borderColor: [
          '#6366f1','#0ea5e9','#10b981','#f59e0b','#a855f7','#ec4899'
        ],
        borderWidth: 2,
        borderRadius: 8,
        borderSkipped: false,
      }],
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: { legend: { display: false } },
      scales: {
        y: { beginAtZero: true, max: 100, grid: { color: CHART_THEME.grid }, ticks: { font: { size: 11 } } },
        x: { grid: { display: false }, ticks: { font: { size: 11 } } },
      },
    },
  });
}

// ── Attendance Trend Chart ────────────────────
function renderAttendanceChart(canvasId, labels, present, absent) {
  const canvas = document.getElementById(canvasId);
  if (!canvas || typeof Chart === 'undefined') return;
  const ctx = canvas.getContext('2d');
  return new Chart(ctx, {
    type: 'line',
    data: {
      labels,
      datasets: [
        {
          label: 'Present',
          data: present,
          borderColor: CHART_THEME.success,
          backgroundColor: 'rgba(16,185,129,0.1)',
          fill: true,
          tension: 0.4,
          pointBackgroundColor: CHART_THEME.success,
          pointRadius: 4,
          pointHoverRadius: 6,
        },
        {
          label: 'Absent',
          data: absent,
          borderColor: CHART_THEME.danger,
          backgroundColor: 'rgba(239,68,68,0.08)',
          fill: true,
          tension: 0.4,
          pointBackgroundColor: CHART_THEME.danger,
          pointRadius: 4,
          pointHoverRadius: 6,
        },
      ],
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: { legend: { position: 'top' } },
      scales: {
        y: { beginAtZero: true, grid: { color: CHART_THEME.grid } },
        x: { grid: { display: false } },
      },
    },
  });
}

// ── Performance Radar Chart ────────────────────
function renderRadarChart(canvasId, labels, datasets) {
  const canvas = document.getElementById(canvasId);
  if (!canvas || typeof Chart === 'undefined') return;
  const ctx = canvas.getContext('2d');
  return new Chart(ctx, {
    type: 'radar',
    data: {
      labels,
      datasets: datasets.map((d, i) => ({
        label: d.label,
        data: d.data,
        borderColor: [CHART_THEME.primary, CHART_THEME.success, CHART_THEME.warning][i % 3],
        backgroundColor: [
          'rgba(99,102,241,0.15)',
          'rgba(16,185,129,0.15)',
          'rgba(245,158,11,0.15)',
        ][i % 3],
        pointBackgroundColor: [CHART_THEME.primary, CHART_THEME.success, CHART_THEME.warning][i % 3],
        borderWidth: 2,
      })),
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      scales: {
        r: {
          beginAtZero: true,
          max: 100,
          grid: { color: CHART_THEME.grid },
          pointLabels: { color: CHART_THEME.text, font: { size: 11 } },
          ticks: { display: false },
        },
      },
      plugins: { legend: { position: 'top' } },
    },
  });
}

// ── Doughnut Chart (Pie) ──────────────────────
function renderDoughnutChart(canvasId, labels, data, colors) {
  const canvas = document.getElementById(canvasId);
  if (!canvas || typeof Chart === 'undefined') return;
  const ctx = canvas.getContext('2d');
  const defaultColors = ['#6366f1','#10b981','#f59e0b','#ef4444','#a855f7','#0ea5e9'];
  return new Chart(ctx, {
    type: 'doughnut',
    data: {
      labels,
      datasets: [{
        data,
        backgroundColor: (colors || defaultColors).map(c => c + 'cc'),
        borderColor: colors || defaultColors,
        borderWidth: 2,
        hoverOffset: 8,
      }],
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: {
        legend: { position: 'right', labels: { padding: 12 } },
      },
      cutout: '65%',
    },
  });
}

// ── Progress Timeline Chart ────────────────────
function renderTimelineChart(canvasId, labels, data) {
  const canvas = document.getElementById(canvasId);
  if (!canvas || typeof Chart === 'undefined') return;
  const ctx = canvas.getContext('2d');
  const gradient = ctx.createLinearGradient(0, 0, 0, canvas.offsetHeight || 200);
  gradient.addColorStop(0, 'rgba(99,102,241,0.4)');
  gradient.addColorStop(1, 'rgba(99,102,241,0)');
  return new Chart(ctx, {
    type: 'line',
    data: {
      labels,
      datasets: [{
        label: 'Performance',
        data,
        borderColor: CHART_THEME.primary,
        backgroundColor: gradient,
        fill: true,
        tension: 0.4,
        pointBackgroundColor: CHART_THEME.primary,
        pointBorderColor: '#fff',
        pointBorderWidth: 2,
        pointRadius: 5,
        pointHoverRadius: 7,
      }],
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: { legend: { display: false } },
      scales: {
        y: { beginAtZero: true, max: 100, grid: { color: CHART_THEME.grid } },
        x: { grid: { display: false } },
      },
    },
  });
}

// ── Quiz Results Chart ────────────────────────
function renderQuizResultChart(canvasId, labels, scores) {
  const canvas = document.getElementById(canvasId);
  if (!canvas || typeof Chart === 'undefined') return;
  const ctx = canvas.getContext('2d');
  return new Chart(ctx, {
    type: 'bar',
    data: {
      labels,
      datasets: [{
        label: 'Score',
        data: scores,
        backgroundColor: scores.map(s =>
          s >= 80 ? 'rgba(16,185,129,0.7)' :
          s >= 50 ? 'rgba(245,158,11,0.7)' :
                    'rgba(239,68,68,0.7)'
        ),
        borderColor: scores.map(s =>
          s >= 80 ? '#10b981' : s >= 50 ? '#f59e0b' : '#ef4444'
        ),
        borderWidth: 2,
        borderRadius: 6,
        borderSkipped: false,
      }],
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: { legend: { display: false } },
      scales: {
        y: { beginAtZero: true, max: 100, grid: { color: CHART_THEME.grid } },
        x: { grid: { display: false } },
      },
    },
  });
}

// Auto-render charts from data attributes
document.addEventListener('DOMContentLoaded', () => {
  // Grade chart
  const gradeCanvas = document.getElementById('grade-chart');
  if (gradeCanvas) {
    const labels = JSON.parse(gradeCanvas.dataset.labels || '[]');
    const data   = JSON.parse(gradeCanvas.dataset.values || '[]');
    renderGradeChart('grade-chart', labels, data);
  }

  // Attendance chart
  const attCanvas = document.getElementById('attendance-chart');
  if (attCanvas) {
    const labels  = JSON.parse(attCanvas.dataset.labels  || '[]');
    const present = JSON.parse(attCanvas.dataset.present || '[]');
    const absent  = JSON.parse(attCanvas.dataset.absent  || '[]');
    renderAttendanceChart('attendance-chart', labels, present, absent);
  }

  // Doughnut charts
  document.querySelectorAll('.doughnut-chart').forEach(canvas => {
    const labels = JSON.parse(canvas.dataset.labels || '[]');
    const data   = JSON.parse(canvas.dataset.values || '[]');
    renderDoughnutChart(canvas.id, labels, data);
  });

  // Timeline
  const tlCanvas = document.getElementById('timeline-chart');
  if (tlCanvas) {
    renderTimelineChart('timeline-chart',
      JSON.parse(tlCanvas.dataset.labels || '[]'),
      JSON.parse(tlCanvas.dataset.values || '[]')
    );
  }
});

// Export
window.SCS_Charts = { renderGradeChart, renderAttendanceChart, renderRadarChart, renderDoughnutChart, renderTimelineChart, renderQuizResultChart };
