<?php
// =============================================
// Smart Classroom — Archive & Personal Storage
// =============================================
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/layout.php';
requireLogin();

$user  = currentUser();
$role  = $user['role'];

renderHead($role === 'teacher' ? 'Archive' : 'My Archive');
?>
<body>
<div class="app-wrapper">
<?php renderSidebar($user, 'archive.php'); ?>
<div class="main-content">
<?php renderTopbar($role === 'teacher' ? 'Classroom Archive' : 'My Archive', $user); ?>

<div class="page-content animate-up">

<?php if ($role === 'teacher'): ?>
<!-- ── TEACHER: Archived Classes ─────────────── -->
<div style="max-width:860px;margin:0 auto">
  <div class="card" style="margin-bottom:1.5rem;background:linear-gradient(135deg,rgba(245,158,11,0.1),rgba(239,68,68,0.05));border:1px solid rgba(245,158,11,0.2)">
    <div style="display:flex;align-items:center;gap:0.75rem;padding:1rem">
      <i class="fas fa-info-circle" style="color:var(--warning);font-size:1.1rem"></i>
      <div style="font-size:0.875rem;color:var(--text-secondary)">
        Archived classrooms enter a <strong>2-day grace period</strong>. Students are notified to save their materials. After 48 hours, the classroom is permanently deleted.
      </div>
    </div>
  </div>

  <div class="card">
    <div class="card-header">
      <div class="card-title"><i class="fas fa-archive" style="color:var(--warning)"></i> Archived Classrooms</div>
    </div>
    <div id="teacher-archive-list">
      <div style="text-align:center;padding:2rem;color:var(--text-muted)"><i class="fas fa-spinner spin"></i> Loading...</div>
    </div>
  </div>
</div>

<?php else: ?>
<!-- ── STUDENT: Personal Archive ─────────────── -->
<div style="max-width:860px;margin:0 auto">
  <div class="card" style="margin-bottom:1.5rem;background:linear-gradient(135deg,rgba(16,185,129,0.1),rgba(14,165,233,0.05));border:1px solid rgba(16,185,129,0.2)">
    <div style="display:flex;align-items:center;gap:0.75rem;padding:1rem">
      <i class="fas fa-bookmark" style="color:var(--success);font-size:1.1rem"></i>
      <div style="font-size:0.875rem;color:var(--text-secondary)">
        Your personal archive stores copies of materials from all your courses. Even after a classroom is deleted, your saved materials remain accessible here.
      </div>
    </div>
  </div>

  <!-- Archived Classes (read-only) -->
  <div class="card" style="margin-bottom:1.5rem">
    <div class="card-header">
      <div class="card-title"><i class="fas fa-archive" style="color:var(--warning)"></i> Archived Classrooms</div>
      <span class="badge badge-warning" id="archived-classes-count">0</span>
    </div>
    <div id="student-archived-classes">
      <div style="text-align:center;padding:1.5rem;color:var(--text-muted)"><i class="fas fa-spinner spin"></i> Loading...</div>
    </div>
  </div>

  <!-- Personal Storage Folders -->
  <div class="card">
    <div class="card-header">
      <div class="card-title"><i class="fas fa-folder-star" style="color:var(--success)"></i> My Saved Materials</div>
    </div>
    <div id="student-archive-folders">
      <div style="text-align:center;padding:1.5rem;color:var(--text-muted)"><i class="fas fa-spinner spin"></i> Loading...</div>
    </div>
  </div>

  <!-- Folder Contents (hidden by default) -->
  <div class="card" id="archive-folder-contents" style="display:none;margin-top:1.5rem">
    <div class="card-header">
      <div class="card-title" id="folder-contents-title"><i class="fas fa-folder-open" style="color:var(--primary)"></i> Folder</div>
      <button class="btn btn-ghost btn-sm" onclick="closeFolderContents()"><i class="fas fa-times"></i> Close</button>
    </div>
    <div id="folder-items-list"></div>
  </div>
</div>
<?php endif; ?>

</div>
</div>
</div>

<?php renderFooter(); ?>
<script>
const BASE = '<?= BASE_URL ?>';
const ROLE = '<?= $role ?>';

<?php if ($role === 'teacher'): ?>
// Teacher: Load archived classes
async function loadTeacherArchive() {
  const res = await SCS.apiRequest(`${BASE}/api/archive.php?action=list_archived`);
  const container = document.getElementById('teacher-archive-list');
  if (!res.success || !res.archives.length) {
    container.innerHTML = '<div class="empty-state"><div class="empty-icon"><i class="fas fa-archive"></i></div><div class="empty-title">No archived classrooms</div><div class="empty-sub">When you archive a classroom, it will appear here</div></div>';
    return;
  }
  let html = '<div style="display:flex;flex-direction:column;gap:0.75rem;padding:1rem">';
  for (const a of res.archives) {
    const deleteDate = new Date(a.delete_after);
    const remaining = Math.max(0, deleteDate - new Date());
    const hours = Math.floor(remaining / 3600000);
    const mins = Math.floor((remaining % 3600000) / 60000);
    const urgent = remaining < 86400000;
    html += `
    <div class="card" style="padding:1rem;border-left:3px solid ${urgent ? 'var(--danger)' : 'var(--warning)'}">
      <div style="display:flex;align-items:center;justify-content:space-between">
        <div>
          <div style="font-weight:700;font-size:1rem">${a.name}</div>
          <div style="font-size:0.8rem;color:var(--text-muted);margin-top:0.25rem">${a.section || ''} ${a.subject ? '· ' + a.subject : ''}</div>
          <div style="font-size:0.8rem;margin-top:0.5rem;color:${urgent ? 'var(--danger)' : 'var(--warning)'};font-weight:600">
            <i class="fas fa-clock"></i> Permanent deletion in ${hours}h ${mins}m
          </div>
        </div>
        <div style="display:flex;gap:0.5rem">
          <a href="${BASE}/classroom/index.php?id=${a.original_class_id}" class="btn btn-ghost btn-sm"><i class="fas fa-eye"></i> View</a>
          <button class="btn btn-success btn-sm" onclick="restoreArchive(${a.original_class_id})"><i class="fas fa-undo"></i> Restore</button>
        </div>
      </div>
    </div>`;
  }
  html += '</div>';
  container.innerHTML = html;
}

async function restoreArchive(classId) {
  const fd = new FormData();
  fd.append('action', 'restore_class');
  fd.append('class_id', classId);
  const res = await SCS.apiRequest(`${BASE}/api/archive.php`, 'POST', fd);
  if (res.success) {
    SCS.showToast('Classroom restored!', 'success');
    setTimeout(() => location.reload(), 1000);
  } else SCS.showToast(res.error || 'Restore failed', 'error');
}

loadTeacherArchive();

// Also purge expired archives on page load
fetch(`${BASE}/api/archive.php?action=purge_expired`);

<?php else: ?>
// Student: Load archived classes + personal archive
async function loadStudentArchive() {
  // Archived classes
  const archRes = await SCS.apiRequest(`${BASE}/api/archive.php?action=list_archived`);
  const archContainer = document.getElementById('student-archived-classes');
  const countEl = document.getElementById('archived-classes-count');
  if (archRes.success && archRes.archives.length) {
    countEl.textContent = archRes.archives.length;
    let html = '';
    for (const a of archRes.archives) {
      const deleteDate = new Date(a.delete_after);
      const remaining = Math.max(0, deleteDate - new Date());
      const hours = Math.floor(remaining / 3600000);
      html += `
      <div style="display:flex;align-items:center;justify-content:space-between;padding:0.75rem;border-bottom:1px solid var(--border)">
        <div>
          <div style="font-weight:600;font-size:0.9rem">${a.name}</div>
          <div style="font-size:0.75rem;color:var(--text-muted)">${a.section || ''} · Deleted in ~${hours}h</div>
        </div>
        <div style="display:flex;gap:0.5rem">
          <a href="${BASE}/classroom/index.php?id=${a.original_class_id}" class="btn btn-ghost btn-sm"><i class="fas fa-eye"></i></a>
          <button class="btn btn-warning btn-sm" onclick="saveArchivedClass(${a.original_class_id})"><i class="fas fa-bookmark"></i> Save Materials</button>
        </div>
      </div>`;
    }
    archContainer.innerHTML = html;
  } else {
    countEl.textContent = '0';
    archContainer.innerHTML = '<div style="padding:1rem;text-align:center;color:var(--text-muted);font-size:0.875rem">No archived classrooms</div>';
  }

  // Personal archive folders
  const myRes = await SCS.apiRequest(`${BASE}/api/archive.php?action=my_archive`);
  const folderContainer = document.getElementById('student-archive-folders');
  if (myRes.success && myRes.folders.length) {
    let html = '';
    for (const f of myRes.folders) {
      html += `
      <div style="display:flex;align-items:center;justify-content:space-between;padding:0.75rem;border-bottom:1px solid var(--border);cursor:pointer" onclick="openFolder(${f.id}, '${f.folder_name.replace(/'/g, "\\'")}')">
        <div style="display:flex;align-items:center;gap:0.75rem">
          <div style="width:40px;height:40px;border-radius:var(--radius-sm);background:rgba(16,185,129,0.15);display:flex;align-items:center;justify-content:center"><i class="fas fa-folder" style="color:var(--success)"></i></div>
          <div>
            <div style="font-weight:600;font-size:0.9rem">${f.folder_name}</div>
            <div style="font-size:0.75rem;color:var(--text-muted)">${f.source_class_name || ''} · ${f.item_count} items</div>
          </div>
        </div>
        <div style="display:flex;gap:0.5rem;align-items:center">
          <button class="btn btn-ghost btn-sm" style="color:var(--danger)" onclick="event.stopPropagation();deleteFolder(${f.id})"><i class="fas fa-trash"></i></button>
          <i class="fas fa-chevron-right" style="color:var(--text-muted)"></i>
        </div>
      </div>`;
    }
    folderContainer.innerHTML = html;
  } else {
    folderContainer.innerHTML = '<div class="empty-state"><div class="empty-icon"><i class="fas fa-folder-open"></i></div><div class="empty-title">No saved materials yet</div><div class="empty-sub">Save materials from any classroom to your personal archive</div></div>';
  }
}

async function saveArchivedClass(classId) {
  const fd = new FormData();
  fd.append('action', 'save_to_archive');
  fd.append('class_id', classId);
  const res = await SCS.apiRequest(`${BASE}/api/archive.php`, 'POST', fd);
  if (res.success) SCS.showToast(`${res.copied} material(s) saved!`, 'success');
  else SCS.showToast(res.error || 'Save failed', 'error');
}

async function openFolder(archiveId, folderName) {
  const res = await SCS.apiRequest(`${BASE}/api/archive.php?action=archive_items&archive_id=${archiveId}`);
  const container = document.getElementById('folder-items-list');
  const titleEl = document.getElementById('folder-contents-title');
  const panel = document.getElementById('archive-folder-contents');

  titleEl.innerHTML = `<i class="fas fa-folder-open" style="color:var(--primary)"></i> ${folderName}`;
  panel.style.display = 'block';
  panel.scrollIntoView({ behavior: 'smooth' });

  if (!res.success || !res.items.length) {
    container.innerHTML = '<div style="padding:1rem;text-align:center;color:var(--text-muted)">No items in this folder</div>';
    return;
  }

  const typeIcons = {file:'fa-file-alt',link:'fa-link',video:'fa-video'};
  let html = '';
  for (const item of res.items) {
    html += `
    <div style="display:flex;align-items:center;gap:0.75rem;padding:0.75rem;border-bottom:1px solid var(--border)">
      <i class="fas ${typeIcons[item.type] || 'fa-file'}" style="color:var(--info);width:20px;text-align:center"></i>
      <div style="flex:1">
        <div style="font-weight:600;font-size:0.9rem">${item.title}</div>
        ${item.description ? `<div style="font-size:0.8rem;color:var(--text-secondary);margin-top:0.25rem">${item.description}</div>` : ''}
      </div>
      <div style="display:flex;gap:0.5rem">
        ${item.file_path ? `<a href="${BASE}/uploads/${item.file_path}" target="_blank" class="btn btn-ghost btn-sm"><i class="fas fa-eye"></i></a>
        <a href="${BASE}/uploads/${item.file_path}" download class="btn btn-ghost btn-sm"><i class="fas fa-download"></i></a>` : ''}
        ${item.link_url ? `<a href="${item.link_url}" target="_blank" class="btn btn-ghost btn-sm"><i class="fas fa-external-link-alt"></i></a>` : ''}
      </div>
    </div>`;
  }
  container.innerHTML = html;
}

function closeFolderContents() {
  document.getElementById('archive-folder-contents').style.display = 'none';
}

async function deleteFolder(archiveId) {
  SCS.confirmAction('Delete this archive folder and all its saved materials?', async () => {
    const fd = new FormData();
    fd.append('action', 'delete_archive');
    fd.append('archive_id', archiveId);
    const res = await SCS.apiRequest(`${BASE}/api/archive.php`, 'POST', fd);
    if (res.success) {
      SCS.showToast('Archive folder deleted', 'info');
      loadStudentArchive();
      closeFolderContents();
    } else SCS.showToast(res.error || 'Delete failed', 'error');
  });
}

loadStudentArchive();
<?php endif; ?>
</script>
</body></html>
