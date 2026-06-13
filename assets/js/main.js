/* =============================================
   SMART CLASSROOM SYSTEM — Main JavaScript
   ============================================= */

'use strict';

// ── Utility Functions ─────────────────────────
const $ = (sel, ctx = document) => ctx.querySelector(sel);
const $$ = (sel, ctx = document) => [...ctx.querySelectorAll(sel)];

function timeAgo(dateStr) {
  const now = new Date(), past = new Date(dateStr);
  const diff = Math.floor((now - past) / 1000);
  if (diff < 60)    return 'just now';
  if (diff < 3600)  return Math.floor(diff / 60) + 'm ago';
  if (diff < 86400) return Math.floor(diff / 3600) + 'h ago';
  return Math.floor(diff / 86400) + 'd ago';
}

function formatDate(dateStr) {
  return new Date(dateStr).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
}

function formatDateTime(dateStr) {
  return new Date(dateStr).toLocaleString('en-US', { month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' });
}

// ── Toast Notifications ───────────────────────
const toastContainer = (() => {
  let container = $('.toast-container');
  if (!container) {
    container = document.createElement('div');
    container.className = 'toast-container';
    document.body.appendChild(container);
  }
  return container;
})();

function showToast(message, type = 'info', duration = 3500) {
  const icons = { success: 'fa-check-circle', error: 'fa-times-circle', warning: 'fa-exclamation-triangle', info: 'fa-info-circle' };
  const colors = { success: '#10b981', error: '#ef4444', warning: '#f59e0b', info: '#06b6d4' };

  const toast = document.createElement('div');
  toast.className = `toast ${type}`;
  toast.innerHTML = `
    <i class="fas ${icons[type] || icons.info}" style="color:${colors[type]};font-size:1.1rem;flex-shrink:0"></i>
    <span style="font-size:0.875rem;font-weight:500;flex:1">${message}</span>
    <button onclick="this.parentElement.remove()" style="background:none;border:none;color:var(--text-muted);cursor:pointer;font-size:1rem">
      <i class="fas fa-times"></i>
    </button>`;
  toastContainer.appendChild(toast);
  setTimeout(() => { toast.style.opacity = '0'; toast.style.transform = 'translateX(100%)'; toast.style.transition = '0.3s'; setTimeout(() => toast.remove(), 300); }, duration);
}

// ── Modal Manager ─────────────────────────────
function openModal(id) {
  const overlay = $(`#${id}`);
  if (overlay) { overlay.classList.add('show'); document.body.style.overflow = 'hidden'; }
}
function closeModal(id) {
  const overlay = $(`#${id}`);
  if (overlay) { overlay.classList.remove('show'); document.body.style.overflow = ''; }
}

// Close modal on overlay click
document.addEventListener('click', e => {
  if (e.target.classList.contains('modal-overlay')) closeModal(e.target.id);
  if (e.target.classList.contains('modal-close')) {
    const overlay = e.target.closest('.modal-overlay');
    if (overlay) closeModal(overlay.id);
  }
});

// Close on Escape
document.addEventListener('keydown', e => {
  if (e.key === 'Escape') {
    $$('.modal-overlay.show').forEach(m => closeModal(m.id));
  }
});

// ── Sidebar Toggle ────────────────────────────
const sidebar = $('.sidebar');
const sidebarToggle = $('.sidebar-toggle');
const mainContent = $('.main-content');

// Load collapsed state from localStorage
if (sidebar && mainContent && localStorage.getItem('sidebar-collapsed') === 'true') {
  sidebar.classList.add('collapsed');
  mainContent.classList.add('sidebar-collapsed');
}

if (sidebarToggle && sidebar) {
  sidebarToggle.addEventListener('click', (e) => {
    // If not inline onclick, we will toggle classes here. The inline onclick is doing it already.
    // However, let's persist the state based on whatever the class is now.
    setTimeout(() => {
      const isCollapsed = sidebar.classList.contains('collapsed');
      localStorage.setItem('sidebar-collapsed', isCollapsed);
    }, 50);

    // mobile overlay
    if (window.innerWidth < 768) {
        sidebar.classList.toggle('open');
        let overlay = $('.sidebar-overlay');
        if (!overlay) {
          overlay = document.createElement('div');
          overlay.className = 'sidebar-overlay';
          overlay.style.cssText = 'position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:99;display:none;backdrop-filter:blur(4px)';
          document.body.appendChild(overlay);
          overlay.addEventListener('click', () => {
            sidebar.classList.remove('open');
            overlay.style.display = 'none';
          });
        }
        overlay.style.display = sidebar.classList.contains('open') ? 'block' : 'none';
    }
  });
}

if (sidebarToggle && sidebar) {
  sidebarToggle.addEventListener('click', () => {
    sidebar.classList.toggle('open');
    // overlay for mobile
    let overlay = $('.sidebar-overlay');
    if (!overlay) {
      overlay = document.createElement('div');
      overlay.className = 'sidebar-overlay';
      overlay.style.cssText = 'position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:99;display:none;backdrop-filter:blur(4px)';
      document.body.appendChild(overlay);
      overlay.addEventListener('click', () => {
        sidebar.classList.remove('open');
        overlay.style.display = 'none';
      });
    }
    overlay.style.display = sidebar.classList.contains('open') ? 'block' : 'none';
  });
}

// ── Dropdown ──────────────────────────────────
document.addEventListener('click', e => {
  if (e.target.closest('[data-dropdown]')) {
    const btn = e.target.closest('[data-dropdown]');
    const menuId = btn.dataset.dropdown;
    const menu = $(`#${menuId}`);
    if (menu) {
      $$('.dropdown-menu.show').forEach(m => { if (m !== menu) m.classList.remove('show'); });
      menu.classList.toggle('show');
    }
  } else {
    $$('.dropdown-menu.show').forEach(m => m.classList.remove('show'));
  }
});

// ── Tabs ──────────────────────────────────────
$$('.tab-item[data-tab]').forEach(tab => {
  tab.addEventListener('click', () => {
    const target = tab.dataset.tab;
    const tabGroup = tab.closest('[data-tab-group]')?.dataset.tabGroup || 'default';
    $$(`[data-tab][data-group="${tabGroup}"]`).forEach(t => t.classList.remove('active'));
    $$(`[data-tab-pane][data-group="${tabGroup}"]`).forEach(p => p.classList.add('hidden'));
    tab.classList.add('active');
    $(`[data-tab-pane="${target}"]`)?.classList.remove('hidden');
  });
});

// ── Form Loading State ────────────────────────
function setLoading(btn, loading = true) {
  if (loading) {
    btn.dataset.original = btn.innerHTML;
    btn.innerHTML = `<i class="fas fa-spinner spin"></i> Please wait...`;
    btn.disabled = true;
  } else {
    btn.innerHTML = btn.dataset.original || btn.innerHTML;
    btn.disabled = false;
  }
}

// ── AJAX Helper ───────────────────────────────
async function apiRequest(url, method = 'GET', data = null) {
  const opts = { method, headers: { 'Accept': 'application/json' } };
  if (data) {
    if (data instanceof FormData) {
      opts.body = data;
    } else {
      opts.headers['Content-Type'] = 'application/json';
      opts.body = JSON.stringify(data);
    }
  }
  try {
    const res = await fetch(url, opts);
    return await res.json();
  } catch (err) {
    console.error('API Error:', err);
    return { error: 'Network error' };
  }
}

// ── Confirmation Dialog ───────────────────────
function confirmAction(message, onConfirm) {
  const existing = $('#confirm-modal');
  if (existing) existing.remove();

  const modal = document.createElement('div');
  modal.id = 'confirm-modal';
  modal.className = 'modal-overlay show';
  modal.innerHTML = `
  <div class="modal" style="max-width:380px">
    <div class="modal-header">
      <div class="modal-title"><i class="fas fa-exclamation-triangle" style="color:var(--warning)"></i> Confirm Action</div>
      <button class="modal-close">✕</button>
    </div>
    <div class="modal-body">
      <p style="color:var(--text-secondary);font-size:0.9rem">${message}</p>
    </div>
    <div class="modal-footer">
      <button class="btn btn-secondary btn-sm modal-close">Cancel</button>
      <button class="btn btn-danger btn-sm" id="confirm-yes">Confirm</button>
    </div>
  </div>`;
  document.body.appendChild(modal);
  document.body.style.overflow = 'hidden';

  $('#confirm-yes').addEventListener('click', () => {
    modal.remove();
    document.body.style.overflow = '';
    onConfirm();
  });
  modal.addEventListener('click', e => {
    if (e.target === modal || e.target.classList.contains('modal-close')) {
      modal.remove();
      document.body.style.overflow = '';
    }
  });
}

// ── Progress Animation ────────────────────────
function animateProgress() {
  $$('.progress-fill[data-width]').forEach(bar => {
    const w = bar.dataset.width;
    setTimeout(() => { bar.style.width = w + '%'; }, 100);
  });
}
animateProgress();

// ── Copy to Clipboard ─────────────────────────
function copyText(text, label = 'Copied!') {
  navigator.clipboard.writeText(text).then(() => showToast(label, 'success')).catch(() => showToast('Copy failed', 'error'));
}

// ── Mark notifications as read ────────────────
$$('.notif-item[data-id]').forEach(item => {
  item.addEventListener('click', () => {
    item.classList.remove('unread');
    const dot = item.querySelector('.notif-dot-sm');
    if (dot) dot.remove();
    apiRequest(`/advance_classroom/api/notifications.php?mark=${item.dataset.id}`, 'POST');
  });
});

// ── Keyboard shortcuts ────────────────────────
document.addEventListener('keydown', e => {
  if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
    e.preventDefault();
    const search = $('.search-bar input');
    if (search) search.focus();
  }
});

// ── Smooth page load ──────────────────────────
document.addEventListener('DOMContentLoaded', () => {
  document.body.classList.add('animate-fade');

  // Highlight exactly one nav item matching the current page path.
  // PHP already marks the correct item server-side; JS adds coverage for
  // pages that call renderSidebar() with no $activeNav argument.
  const currentPath = window.location.pathname;
  $$('.nav-item[href]').forEach(item => {
    // Skip items already activated by PHP
    if (item.classList.contains('active')) return;
    const rawHref = item.getAttribute('href') || '';
    try {
      const itemPath = new URL(rawHref, window.location.origin).pathname;
      // Exact pathname match only — no substr, no prefix
      if (itemPath && itemPath !== '/' && itemPath === currentPath) {
        item.classList.add('active');
      }
    } catch (e) { /* ignore non-URL hrefs like # */ }
  });
});

// ── Auto-dismiss alerts ───────────────────────
$$('.auto-dismiss').forEach(el => {
  setTimeout(() => { el.style.opacity = '0'; el.style.transition = '0.5s'; setTimeout(() => el.remove(), 500); }, 4000);
});

// ── File upload preview ────────────────────────
function previewFile(input, previewEl) {
  const file = input.files[0];
  if (!file) return;
  const reader = new FileReader();
  reader.onload = e => {
    if (previewEl.tagName === 'IMG') {
        previewEl.src = e.target.result;
    } else if (previewEl.classList.contains('preview-content')) {
        // Feature 3: Right-panel material preview
        previewEl.innerHTML = '';
        if (file.type.startsWith('image/')) {
            previewEl.style.backgroundImage = `url('${e.target.result}')`;
        } else if (file.type === 'application/pdf') {
            previewEl.style.backgroundImage = 'none';
            previewEl.innerHTML = `<iframe src="${e.target.result}" style="width:100%;height:100%;border:none"></iframe>`;
        } else {
            previewEl.style.backgroundImage = 'none';
            previewEl.innerHTML = `<i class="fas fa-file-alt preview-icon"></i>`;
        }
    } else {
        previewEl.textContent = file.name;
    }
  };
  if (file.type.startsWith('image/') || file.type === 'application/pdf') {
      reader.readAsDataURL(file);
  } else {
      reader.readAsText(file);
  }
}

// ── Character counter ─────────────────────────
$$('textarea[data-maxlength]').forEach(ta => {
  const max = parseInt(ta.dataset.maxlength);
  const counter = document.createElement('div');
  counter.style.cssText = 'font-size:0.75rem;color:var(--text-muted);text-align:right;margin-top:0.25rem';
  counter.textContent = `0 / ${max}`;
  ta.after(counter);
  ta.addEventListener('input', () => {
    const len = ta.value.length;
    counter.textContent = `${len} / ${max}`;
    counter.style.color = len > max * 0.9 ? 'var(--warning)' : 'var(--text-muted)';
    if (len > max) ta.value = ta.value.slice(0, max);
  });
});

// ── Export helper ─────────────────────────────
function downloadCSV(data, filename) {
  const blob = new Blob([data], { type: 'text/csv' });
  const url = URL.createObjectURL(blob);
  const a = document.createElement('a');
  a.href = url; a.download = filename;
  a.click();
  URL.revokeObjectURL(url);
}

// ── Student Join Class ────────────────────────
const joinForm = $('#join-class-form');
if (joinForm) {
  // Clear feedback when user types
  const codeInput = $('#class-code-input');
  if (codeInput) codeInput.addEventListener('input', () => {
    const fb = $('#join-feedback');
    if (fb) { fb.style.display = 'none'; fb.textContent = ''; }
  });

  joinForm.addEventListener('submit', async e => {
    e.preventDefault();
    const btn = joinForm.querySelector('[type=submit]');
    const fb  = $('#join-feedback');
    setLoading(btn);
    if (fb) fb.style.display = 'none';

    const code = $('#class-code-input')?.value.trim().toUpperCase();
    if (!code) { showToast('Please enter a class code', 'error'); setLoading(btn, false); return; }

    const fd = new FormData();
    fd.append('action', 'join');
    fd.append('code', code);
    const res = await apiRequest((window.BASE_URL||'') + '/api/classes.php', 'POST', fd);
    setLoading(btn, false);

    if (res.success) {
      showToast('Joined ' + (res.class_name || 'class') + ' successfully! 🎉', 'success');
      if (fb) {
        fb.style.display = 'block';
        fb.style.background = 'rgba(16,185,129,0.12)';
        fb.style.color = 'var(--success)';
        fb.style.border = '1px solid rgba(16,185,129,0.3)';
        fb.innerHTML = '<i class="fas fa-check-circle"></i> Joined successfully! Refreshing...';
      }
      setTimeout(() => location.reload(), 1200);
    } else {
      const isFull = (res.error || '').toLowerCase().includes('full');
      if (fb) {
        fb.style.display = 'block';
        fb.style.background = isFull ? 'rgba(245,158,11,0.12)' : 'rgba(239,68,68,0.12)';
        fb.style.color = isFull ? 'var(--warning)' : 'var(--danger)';
        fb.style.border = isFull ? '1px solid rgba(245,158,11,0.3)' : '1px solid rgba(239,68,68,0.3)';
        fb.innerHTML = `<i class="fas fa-${isFull ? 'exclamation-triangle' : 'times-circle'}"></i> ${res.error || 'Invalid class code. Please try again.'}`;
      } else {
        showToast(res.error || 'Invalid code', 'error');
      }
    }
  });
}


// ── Create Class ──────────────────────────────
const createClassForm = $('#create-class-form');
if (createClassForm) {
  createClassForm.addEventListener('submit', async e => {
    e.preventDefault();
    const btn = createClassForm.querySelector('[type=submit]');
    setLoading(btn);
    const fd = new FormData(createClassForm);
    const res = await apiRequest((window.BASE_URL||'') + '/api/classes.php', 'POST', fd);
    setLoading(btn, false);
    if (res.success) {
      showToast('Class created!', 'success');
      closeModal('create-class-modal');
      setTimeout(() => location.reload(), 800);
    } else showToast(res.error || 'Failed to create class', 'error');
  });
}

// ── AI Chat ───────────────────────────────────
const aiChatForm = $('#ai-chat-form');
if (aiChatForm) {
  aiChatForm.addEventListener('submit', async e => {
    e.preventDefault();
    const input = $('#ai-chat-input');
    const msg = input.value.trim();
    if (!msg) return;
    
    const body = $('#ai-chat-body');
    body.innerHTML += `<div class="ai-message user">${msg.replace(/</g, "&lt;")}</div>`;
    input.value = '';
    body.scrollTop = body.scrollHeight;

    const typing = document.createElement('div');
    typing.className = 'ai-typing';
    typing.innerHTML = '<span></span><span></span><span></span>';
    body.appendChild(typing);
    body.scrollTop = body.scrollHeight;

    const fd = new FormData();
    fd.append('message', msg);
    
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.has('id')) fd.append('class_id', urlParams.get('id'));

    const base = window.BASE_URL || '';
    const res = await apiRequest(base + '/api/ai_chat.php', 'POST', fd);
    typing.remove();

    if (res.reply) {
        let html = res.reply.replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>');
        html = html.replace(/\n/g, '<br>');
        body.innerHTML += `<div class="ai-message ai">${html}</div>`;
    } else {
        body.innerHTML += `<div class="ai-message ai" style="color:var(--danger)">Connection error. Please try again.</div>`;
    }
    body.scrollTop = body.scrollHeight;
  });
}

// ── Search Suggestions ────────────────────────
const searchInput = $('#global-search');
const searchBox = $('#search-suggestions');
if (searchInput && searchBox) {
  let searchTimeout;
  
  // Hide on click outside
  document.addEventListener('click', e => {
    if (e.target !== searchInput && (!searchBox.contains(e.target))) {
      searchBox.classList.add('hidden');
      searchBox.classList.remove('show');
    }
  });

  searchInput.addEventListener('input', () => {
    clearTimeout(searchTimeout);
    const q = searchInput.value.trim();
    
    if (q.length < 2) {
      searchBox.classList.add('hidden');
      searchBox.classList.remove('show');
      return;
    }
    
    searchTimeout = setTimeout(async () => {
      const _base = window.BASE_URL || '';
      const res = await apiRequest(`${_base}/api/search.php?q=${encodeURIComponent(q)}`);
      
      if (res.results && res.results.length > 0) {
        let html = '';
        let currentType = '';
        
        res.results.forEach(item => {
           if (currentType !== item.type) {
               html += `<div class="search-group-label">${item.type}s</div>`;
               currentType = item.type;
           }
           html += `
             <a href="${_base}${item.url}" class="search-suggestion-item">
               <div class="search-suggestion-icon"><i class="fas ${item.icon}"></i></div>
               <div class="search-suggestion-text">
                 <div class="search-suggestion-title">${item.label}</div>
                 <div class="search-suggestion-sub">${item.sub}</div>
               </div>
             </a>`;
        });
        
        searchBox.innerHTML = html;
        searchBox.classList.remove('hidden');
        searchBox.classList.add('show');
      } else {
        searchBox.innerHTML = '<div style="padding:1rem;color:var(--text-muted);font-size:0.875rem;text-align:center">No results found</div>';
        searchBox.classList.remove('hidden');
        searchBox.classList.add('show');
      }
    }, 300); // 300ms debounce
  });
  
  searchInput.addEventListener('focus', () => {
      if (searchInput.value.trim().length >= 2 && searchBox.innerHTML !== '') {
          searchBox.classList.remove('hidden');
          searchBox.classList.add('show');
      }
  });
}

// ── Expose globals ────────────────────────────
window.SCS = { openModal, closeModal, showToast, confirmAction, copyText, apiRequest, setLoading, formatDate, formatDateTime, timeAgo, downloadCSV };

// ── Profile Picture Upload (sidebar pencil) ───
async function sbUploadAvatar(input) {
  const file = input.files[0];
  if (!file) return;

  const allowed = ['image/jpeg','image/png','image/gif','image/webp'];
  if (!allowed.includes(file.type)) {
    showToast('Invalid file type. Use JPG, PNG, GIF or WEBP.', 'error'); return;
  }
  if (file.size > 5 * 1024 * 1024) {
    showToast('File too large (max 5 MB).', 'error'); return;
  }

  // Preview immediately
  const reader = new FileReader();
  reader.onload = e => {
    const imgEl = document.getElementById('sb-avatar-img');
    const initEl = document.getElementById('sb-avatar-initial');
    if (imgEl) { imgEl.src = e.target.result; imgEl.style.display = 'block'; }
    if (initEl) initEl.style.display = 'none';
  };
  reader.readAsDataURL(file);

  // Upload
  const fd = new FormData();
  fd.append('action', 'upload_avatar');
  fd.append('avatar', file);
  try {
    showToast('Uploading photo…', 'info', 2000);
    const res = await fetch(window.SCS_BASE_URL + '/api/profile.php', { method: 'POST', body: fd });
    const data = await res.json();
    if (data.success) {
      showToast('Profile photo updated!', 'success');
    } else {
      showToast(data.error || 'Upload failed.', 'error');
    }
  } catch(e) {
    showToast('Network error — please try again.', 'error');
  }
  input.value = ''; // reset input
}
window.sbUploadAvatar = sbUploadAvatar;
