<?php
// =============================================
// Smart Classroom — Messages (Class Chat + Direct)
// =============================================
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/layout.php';
requireLogin();

$user    = currentUser();
$uid     = $user['id'];
$classId = (int)($_GET['class_id'] ?? 0);
$toUser  = (int)($_GET['to'] ?? 0);

// Get user's classes
$myClasses = $pdo->prepare("SELECT c.* FROM classes c JOIN class_members cm ON cm.class_id=c.id WHERE cm.user_id=? AND (c.status='active' OR c.status IS NULL) UNION SELECT c.* FROM classes c WHERE c.teacher_id=? AND (c.status='active' OR c.status IS NULL) ORDER BY name");
$myClasses->execute([$uid, $uid]);
$classList = $myClasses->fetchAll();

// Send message
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $content = trim($_POST['content'] ?? '');
    $cid     = (int)($_POST['class_id'] ?? 0);
    $toId    = (int)($_POST['to_user'] ?? 0);
    $attachment = null;

    // Handle file upload
    if (!empty($_FILES['attachment']['name'])) {
        $uploadDir = __DIR__ . '/../uploads/messages/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
        
        $file = $_FILES['attachment'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'pdf', 'doc', 'docx', 'ppt', 'pptx', 'xls', 'xlsx', 'txt', 'zip'];
        
        if (in_array($ext, $allowed)) {
            $filename = 'msg_' . time() . '_' . uniqid() . '.' . $ext;
            if (move_uploaded_file($file['tmp_name'], $uploadDir . $filename)) {
                $attachment = $filename;
            }
        }
    }

    if ($content || $attachment) {
        if ($cid) {
            $ins = $pdo->prepare("INSERT INTO messages (class_id, sender_id, content, attachment) VALUES (?,?,?,?)");
            $ins->execute([$cid, $uid, $content, $attachment]);
            redirect(BASE_URL . "/global/messages.php?class_id={$cid}");
        } elseif ($toId) {
            $ins = $pdo->prepare("INSERT INTO direct_messages (sender_id, receiver_id, content, attachment) VALUES (?,?,?,?)");
            $ins->execute([$uid, $toId, $content, $attachment]);
            redirect(BASE_URL . "/global/messages.php?to={$toId}");
        }
    }
}

// Load messages
$messages = [];
if ($classId) {
    $msg = $pdo->prepare("SELECT m.*,u.name as sender_name FROM messages m JOIN users u ON u.id=m.sender_id WHERE m.class_id=? ORDER BY m.sent_at DESC LIMIT 50");
    $msg->execute([$classId]);
    $messages = array_reverse($msg->fetchAll());
}
if ($toUser) {
    $msg = $pdo->prepare("SELECT dm.*,u.name as sender_name FROM direct_messages dm JOIN users u ON u.id=dm.sender_id WHERE (dm.sender_id=? AND dm.receiver_id=?) OR (dm.sender_id=? AND dm.receiver_id=?) ORDER BY dm.sent_at ASC LIMIT 80");
    $msg->execute([$uid, $toUser, $toUser, $uid]);
    $messages = $msg->fetchAll();
    // Mark as read
    $pdo->prepare("UPDATE direct_messages SET is_read=1 WHERE sender_id=? AND receiver_id=?")->execute([$toUser, $uid]);
}

$activeClass = null;
if ($classId) {
    $ac = $pdo->prepare("SELECT * FROM classes WHERE id=? AND (status='active' OR status IS NULL)");
    $ac->execute([$classId]);
    $activeClass = $ac->fetch();
}
$activePeer = null;
if ($toUser) {
    $ap = $pdo->prepare("SELECT * FROM users WHERE id=?");
    $ap->execute([$toUser]);
    $activePeer = $ap->fetch();
}

// Unread counts
$unread = $pdo->prepare("SELECT COUNT(*) FROM direct_messages WHERE receiver_id=? AND is_read=0");
$unread->execute([$uid]);
$unreadCount = $unread->fetchColumn();

renderHead('Messages');
?>
<body>
<div class="app-wrapper">
<?php renderSidebar($user, 'messages.php'); ?>
<div class="main-content">
<?php renderTopbar('Messages', $user); ?>

<div style="display:flex;height:calc(100vh - var(--topbar-h));overflow:hidden">

  <!-- Sidebar: Conversations -->
  <div style="width:280px;background:var(--bg-surface);border-right:1px solid var(--border);display:flex;flex-direction:column;flex-shrink:0">
    <div style="padding:1rem;border-bottom:1px solid var(--border)">
      <div class="search-bar"><i class="fas fa-search"></i><input type="text" placeholder="Search conversations..."></div>
    </div>
    <div style="flex:1;overflow-y:auto">
      <!-- Class chats -->
      <div style="padding:0.5rem 1rem;font-size:0.65rem;font-weight:700;color:var(--text-muted);text-transform:uppercase;letter-spacing:0.1em;margin-top:0.5rem">Class Chats</div>
      <?php foreach ($classList as $cls): ?>
      <?php $lastMsg = $pdo->prepare("SELECT * FROM messages WHERE class_id=? ORDER BY sent_at DESC LIMIT 1"); $lastMsg->execute([$cls['id']]); $lm = $lastMsg->fetch(); ?>
      <a href="?class_id=<?= $cls['id'] ?>" style="display:flex;align-items:center;gap:0.75rem;padding:0.875rem 1rem;cursor:pointer;text-decoration:none;background:<?= $classId===$cls['id']?'rgba(99,102,241,0.12)':'transparent' ?>;border-left:<?= $classId===$cls['id']?'3px solid var(--primary)':'3px solid transparent' ?>;transition:all 0.2s" onmouseover="this.style.background='var(--bg-hover)'" onmouseout="this.style.background='<?= $classId===$cls['id']?'rgba(99,102,241,0.12)':'transparent' ?>'">
        <div style="width:40px;height:40px;border-radius:var(--radius-sm);background:<?= $cls['cover_color'] ?? 'var(--primary)' ?>;display:flex;align-items:center;justify-content:center;font-size:0.875rem;flex-shrink:0">
          <i class="fas fa-chalkboard" style="color:white"></i>
        </div>
        <div style="flex:1;overflow:hidden">
          <div style="font-size:0.875rem;font-weight:600;white-space:nowrap;overflow:hidden;text-overflow:ellipsis"><?= e($cls['name']) ?></div>
          <div style="font-size:0.75rem;color:var(--text-muted);white-space:nowrap;overflow:hidden;text-overflow:ellipsis">
            <?php if ($lm): ?>
              <?php if ($lm['attachment']): ?><i class="fas fa-paperclip" style="margin-right:0.25rem"></i><?php endif; ?>
              <?= e(mb_strimwidth($lm['content']?:'Attachment',0,28,'…')) ?>
            <?php else: ?>No messages<?php endif; ?>
          </div>
        </div>
      </a>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- Chat Area -->
  <div style="flex:1;display:flex;flex-direction:column;overflow:hidden">
    <?php if ($activeClass || $activePeer): ?>
    <!-- Chat Header -->
    <div style="padding:0.875rem 1.5rem;border-bottom:1px solid var(--border);display:flex;align-items:center;gap:1rem;background:var(--bg-surface)">
      <div class="avatar" style="background:<?= $activeClass ? ($activeClass['cover_color'] ?? 'var(--primary)') : 'var(--success)' ?>">
        <i class="fas <?= $activeClass ? 'fa-chalkboard' : 'fa-user' ?>"></i>
      </div>
      <div>
        <div style="font-weight:700"><?= e($activeClass ? $activeClass['name'] : ($activePeer ? $activePeer['name'] : '')) ?></div>
        <div style="font-size:0.75rem;color:var(--text-muted)"><?= $activeClass ? 'Class Chat' : 'Direct Message' ?></div>
      </div>
    </div>

    <!-- Messages -->
    <div style="flex:1;overflow-y:auto;padding:1.25rem;display:flex;flex-direction:column;gap:0.75rem" id="chat-messages">
      <?php if (empty($messages)): ?>
      <div class="empty-state"><div class="empty-icon"><i class="fas fa-comment-alt"></i></div><div class="empty-title">No messages yet</div><div class="empty-sub">Be the first to say something!</div></div>
      <?php else: ?>
      <?php foreach ($messages as $msg): ?>
      <?php
        $isMine = ($msg['sender_id'] ?? $msg['sender_id']) == $uid;
        $senderName = $msg['sender_name'] ?? ($isMine ? $user['name'] : ($activePeer ? $activePeer['name'] : ''));
      ?>
      <div style="display:flex;justify-content:<?= $isMine ? 'flex-end' : 'flex-start' ?>;gap:0.625rem">
        <?php if (!$isMine): ?><div class="avatar" style="width:32px;height:32px;font-size:0.75rem;flex-shrink:0"><?= strtoupper($senderName[0]) ?></div><?php endif; ?>
        <div style="max-width:65%">
          <?php if (!$isMine && $activeClass): ?><div style="font-size:0.7rem;color:var(--text-muted);margin-bottom:0.2rem"><?= e($senderName) ?></div><?php endif; ?>
          <div style="background:<?= $isMine ? 'var(--primary)' : 'var(--bg-overlay)' ?>;padding:0.625rem 0.875rem;border-radius:<?= $isMine ? 'var(--radius-md) var(--radius-md) 4px var(--radius-md)' : 'var(--radius-md) var(--radius-md) var(--radius-md) 4px' ?>;font-size:0.875rem;color:<?= $isMine ? 'white' : 'var(--text-primary)' ?>">
            <?php if ($msg['content']): ?><?= nl2br(e($msg['content'])) ?><?php endif; ?>
            <?php if ($msg['attachment']): ?>
              <?php $ext = pathinfo($msg['attachment'], PATHINFO_EXTENSION); $isImg = in_array($ext, ['jpg','jpeg','png','gif','webp']); $isPdf = $ext === 'pdf'; $fileUrl = BASE_URL . '/uploads/messages/' . e($msg['attachment']); ?>
              <div style="margin-top:0.5rem;display:flex;align-items:center;gap:0.5rem;padding:0.5rem;background:<?= $isMine ? 'rgba(255,255,255,0.2)' : 'var(--bg-surface)' ?>;border-radius:var(--radius-sm);max-width:280px">
                <?php if ($isImg): ?>
                  <div style="position:relative;cursor:pointer" onclick="previewFile('<?= $fileUrl ?>', 'image')">
                    <img src="<?= $fileUrl ?>" style="max-width:200px;max-height:150px;border-radius:var(--radius-sm);">
                    <div style="position:absolute;inset:0;display:flex;align-items:center;justify-content:center;background:rgba(0,0,0,0.3);border-radius:var(--radius-sm);opacity:0;transition:0.2s" onmouseover="this.style.opacity=1" onmouseout="this.style.opacity=0">
                      <i class="fas fa-eye" style="color:white;font-size:1.5rem"></i>
                    </div>
                  </div>
                <?php elseif ($isPdf): ?>
                  <div style="display:flex;align-items:center;gap:0.75rem;width:100%">
                    <i class="fas fa-file-pdf" style="font-size:2rem;color:#ef4444"></i>
                    <div style="flex:1;overflow:hidden">
                      <div style="font-size:0.75rem;white-space:nowrap;overflow:hidden;text-overflow:ellipsis"><?= e($msg['attachment']) ?></div>
                      <div style="display:flex;gap:0.5rem;margin-top:0.25rem">
                        <button type="button" class="btn btn-xs" style="font-size:0.65rem;padding:0.2rem 0.5rem;background:<?= $isMine ? 'white' : 'var(--primary)' ?>;color:<?= $isMine ? 'var(--primary)' : 'white' ?>;border:none;border-radius:var(--radius-sm);cursor:pointer" onclick="previewFile('<?= $fileUrl ?>', 'pdf')">
                          <i class="fas fa-eye"></i> Preview
                        </button>
                        <a href="<?= $fileUrl ?>" download style="font-size:0.65rem;padding:0.2rem 0.5rem;background:<?= $isMine ? 'rgba(255,255,255,0.3)' : 'var(--bg-overlay)' ?>;color:<?= $isMine ? 'white' : 'var(--primary)' ?>;text-decoration:none;border-radius:var(--radius-sm);display:flex;align-items:center;gap:0.25rem">
                          <i class="fas fa-download"></i> Download
                        </a>
                      </div>
                    </div>
                  </div>
                <?php else: ?>
                  <i class="fas fa-file" style="font-size:1.5rem;color:var(--primary)"></i>
                  <div style="flex:1;overflow:hidden">
                    <div style="font-size:0.75rem;white-space:nowrap;overflow:hidden;text-overflow:ellipsis"><?= e($msg['attachment']) ?></div>
                    <div style="display:flex;gap:0.5rem;margin-top:0.25rem">
                      <?php if (in_array($ext, ['txt','doc','docx','xls','xlsx','ppt','pptx'])): ?>
                      <button type="button" class="btn btn-xs" style="font-size:0.65rem;padding:0.2rem 0.5rem;background:<?= $isMine ? 'white' : 'var(--primary)' ?>;color:<?= $isMine ? 'var(--primary)' : 'white' ?>;border:none;border-radius:var(--radius-sm);cursor:pointer" onclick="previewFile('<?= $fileUrl ?>', '<?= $ext ?>')">
                        <i class="fas fa-eye"></i> Preview
                      </button>
                      <?php endif; ?>
                      <a href="<?= $fileUrl ?>" download style="font-size:0.65rem;padding:0.2rem 0.5rem;background:<?= $isMine ? 'rgba(255,255,255,0.3)' : 'var(--bg-overlay)' ?>;color:<?= $isMine ? 'white' : 'var(--primary)' ?>;text-decoration:none;border-radius:var(--radius-sm);display:flex;align-items:center;gap:0.25rem">
                        <i class="fas fa-download"></i> Download
                      </a>
                    </div>
                  </div>
                <?php endif; ?>
              </div>
            <?php endif; ?>
          </div>
          <div style="font-size:0.65rem;color:var(--text-muted);margin-top:0.2rem;text-align:<?= $isMine ? 'right' : 'left' ?>"><?= timeAgo($msg['sent_at']) ?></div>
        </div>
        <?php if ($isMine): ?><div class="avatar" style="width:32px;height:32px;font-size:0.75rem;flex-shrink:0"><?= strtoupper($user['name'][0]) ?></div><?php endif; ?>
      </div>
      <?php endforeach; ?>
      <?php endif; ?>
    </div>

    <!-- Input -->
    <form method="POST" enctype="multipart/form-data" style="padding:1rem 1.25rem;border-top:1px solid var(--border);display:flex;gap:0.75rem;align-items:flex-end;background:var(--bg-surface)">
      <input type="hidden" name="class_id" value="<?= $classId ?>">
      <input type="hidden" name="to_user" value="<?= $toUser ?>">
      <input type="file" id="msg-attachment" name="attachment" style="display:none" accept=".jpg,.jpeg,.png,.gif,.webp,.pdf,.doc,.docx,.ppt,.pptx,.xls,.xlsx,.txt,.zip" onchange="document.getElementById('file-name').textContent=this.files[0]?this.files[0].name:''">
      <div style="flex:1;display:flex;flex-direction:column;gap:0.25rem">
        <div style="display:flex;gap:0.5rem;align-items:center">
          <textarea name="content" class="form-control" placeholder="Type a message… (Enter to send)" rows="1" style="resize:none;min-height:42px;max-height:120px;flex:1" onkeydown="if(event.key==='Enter'&&!event.shiftKey&&!event.ctrlKey){event.preventDefault();this.form.submit()}"></textarea>
          <button type="button" class="btn btn-secondary btn-icon" style="height:42px;width:42px;flex-shrink:0" onclick="document.getElementById('msg-attachment').click()" title="Attach file"><i class="fas fa-paperclip"></i></button>
          <button class="btn btn-primary btn-icon" style="height:42px;width:42px;flex-shrink:0" title="Send"><i class="fas fa-paper-plane"></i></button>
        </div>
        <div id="file-name" style="font-size:0.75rem;color:var(--primary)"></div>
      </div>
    </form>

    <?php else: ?>
    <div class="empty-state" style="flex:1">
      <div class="empty-icon" style="width:80px;height:80px;font-size:2rem"><i class="fas fa-comment-dots"></i></div>
      <div class="empty-title">Select a Conversation</div>
      <div class="empty-sub">Choose a class chat from the sidebar to start messaging</div>
    </div>
    <?php endif; ?>
  </div>
</div>

<?php renderFooter('<script>
  // Auto-scroll to bottom
  const chatDiv = document.getElementById("chat-messages");
  if (chatDiv) chatDiv.scrollTop = chatDiv.scrollHeight;
  
  // Preview modal
  function previewFile(url, type) {
    const modal = document.createElement("div");
    modal.style.cssText = "position:fixed;inset:0;background:rgba(0,0,0,0.85);z-index:9999;display:flex;align-items:center;justify-content:center;padding:2rem;backdrop-filter:blur(4px)";
    modal.onclick = (e) => { if (e.target === modal) modal.remove(); };
    
    let content = "";
    if (type === "image") {
      content = `<img src="${url}" style="max-width:90vw;max-height:85vh;border-radius:8px;box-shadow:0 20px 60px rgba(0,0,0,0.5)">`;
    } else if (type === "pdf") {
      content = `<iframe src="${url}" style="width:90vw;height:85vh;border:none;border-radius:8px;background:white"></iframe>`;
    } else {
      content = `<div style="background:white;padding:2rem;border-radius:8px;max-width:600px"><i class="fas fa-file" style="font-size:3rem;color:var(--primary)"></i><p style="margin:1rem 0">Preview not available for this file type</p><a href="${url}" download class="btn btn-primary">Download File</a></div>`;
    }
    
    modal.innerHTML = `
      <div style="position:relative">
        ${content}
        <button onclick="this.closest(\'.modal\')?.parentElement?.remove()" style="position:absolute;top:-40px;right:0;background:rgba(255,255,255,0.2);border:none;color:white;width:32px;height:32px;border-radius:50%;cursor:pointer;font-size:1rem">
          <i class="fas fa-times"></i>
        </button>
        <a href="${url}" download style="position:absolute;top:-40px;right:40px;background:rgba(255,255,255,0.2);border:none;color:white;width:32px;height:32px;border-radius:50%;cursor:pointer;font-size:0.875rem;display:flex;align-items:center;justify-content:center;text-decoration:none">
          <i class="fas fa-download"></i>
        </a>
      </div>
    `;
    document.body.appendChild(modal);
  }
</script>'); ?>
</body></html>
