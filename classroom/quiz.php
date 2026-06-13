<?php
// =============================================
// Smart Classroom — Quiz & Poll
// =============================================
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/layout.php';
requireLogin();

$user    = currentUser();
$classId = (int)($_GET['class_id'] ?? 0);
if (!$classId) redirect(BASE_URL . '/index.php');

$isTeacher = ($user['role'] === 'teacher');

$cls = $pdo->prepare("SELECT * FROM classes WHERE id=?");
$cls->execute([$classId]);
$class = $cls->fetch();

$itemId = (int)($_GET['item_id'] ?? 0);

// Load quizzes
if ($itemId) {
    $quizzes = $pdo->prepare("SELECT q.*,(SELECT COUNT(*) FROM quiz_questions WHERE quiz_id=q.id) as question_count,(SELECT COUNT(*) FROM quiz_responses WHERE quiz_id=q.id) as response_count FROM quizzes q WHERE q.class_id=? AND q.id=? ORDER BY q.created_at DESC");
    $quizzes->execute([$classId, $itemId]);
} else {
    $quizzes = $pdo->prepare("SELECT q.*,(SELECT COUNT(*) FROM quiz_questions WHERE quiz_id=q.id) as question_count,(SELECT COUNT(*) FROM quiz_responses WHERE quiz_id=q.id) as response_count FROM quizzes q WHERE q.class_id=? ORDER BY q.created_at DESC");
    $quizzes->execute([$classId]);
}
$quizList = $quizzes->fetchAll();

// Load polls (hide completely if isolating a quiz)
$pollList = [];
if (!$itemId) {
    $polls = $pdo->prepare("SELECT p.*,(SELECT COUNT(*) FROM poll_votes WHERE poll_id=p.id) as vote_count FROM polls p WHERE p.class_id=? ORDER BY p.created_at DESC");
    $polls->execute([$classId]);
    $pollList = $polls->fetchAll();
}

renderHead('Quiz & Poll · ' . ($class['name'] ?? ''));
?>
<body>
<div class="app-wrapper">
<?php renderSidebar($user, 'quiz.php'); ?>
<div class="main-content">
<?php renderTopbar('Quiz & Poll', $user, $isTeacher ? [['icon'=>'fa-plus','label'=>'Create Quiz','onclick'=>"openModal('create-quiz-modal')"]] : []); ?>

<div class="page-content animate-up">

  <!-- Class breadcrumb -->
  <div style="display:flex;align-items:center;gap:0.5rem;margin-bottom:1.5rem;font-size:0.875rem;color:var(--text-muted)">
    <a href="<?= BASE_URL ?>/classroom/index.php?id=<?= $classId ?>">← Back to <?= e($class['name'] ?? '') ?></a>
  </div>

  <?php if ($itemId): ?>
  <div style="background:rgba(99,102,241,0.1);border:1px dashed var(--primary);border-radius:var(--radius-md);padding:1rem;display:flex;align-items:center;justify-content:space-between;margin-bottom:1.5rem">
    <div>
      <div style="font-size:0.95rem;color:var(--primary);font-weight:700"><i class="fas fa-search"></i> Isolated Search Result</div>
      <div style="font-size:0.8rem;color:var(--text-muted);margin-top:0.25rem">You are viewing a single quiz from your search. Other quizzes and polls are currently hidden.</div>
    </div>
    <a href="?class_id=<?= $classId ?>" class="btn btn-primary btn-sm"><i class="fas fa-list"></i> Show All Quizzes</a>
  </div>
  <?php endif; ?>

  <div style="display:grid;grid-template-columns:1fr 320px;gap:1.5rem">
    <div>
      <!-- Quizzes -->
      <div class="card" style="margin-bottom:1.5rem">
        <div class="card-header">
          <div class="card-title"><i class="fas fa-question-circle" style="color:var(--primary)"></i> Quizzes</div>
          <?php if ($isTeacher): ?>
          <button class="btn btn-primary btn-sm" onclick="openModal('create-quiz-modal')"><i class="fas fa-plus"></i> New Quiz</button>
          <?php endif; ?>
        </div>

        <?php if (empty($quizList)): ?>
        <div class="empty-state"><div class="empty-icon"><i class="fas fa-question-circle"></i></div><div class="empty-title">No quizzes created</div></div>
        <?php else: ?>
        <div style="display:flex;flex-direction:column;gap:0.75rem">
          <?php foreach ($quizList as $quiz): ?>
          <div class="card" style="padding:1rem;background:var(--bg-surface)">
            <div style="display:flex;align-items:center;gap:1rem">
              <div class="assignment-icon" style="background:rgba(99,102,241,0.15);color:var(--primary)"><i class="fas fa-clipboard-check"></i></div>
              <div style="flex:1">
                <div style="font-weight:700;font-size:0.95rem"><?= e($quiz['title']) ?></div>
                <div style="font-size:0.8rem;color:var(--text-muted);display:flex;gap:0.75rem;margin-top:0.2rem">
                  <span><i class="fas fa-list"></i> <?= $quiz['question_count'] ?> questions</span>
                  <span><i class="fas fa-users"></i> <?= $quiz['response_count'] ?> responses</span>
                  <span><i class="fas fa-clock"></i> <?= $quiz['time_limit'] ?> min</span>
                </div>
              </div>
              <div style="display:flex;align-items:center;gap:0.5rem">
                <?php if ($quiz['status'] === 'live'): ?><span class="live-badge">LIVE</span><?php endif; ?>
                <span class="badge badge-<?= $quiz['status']==='live'?'danger':($quiz['status']==='closed'?'warning':'info') ?>"><?= $quiz['status'] ?></span>
                <?php if ($isTeacher): ?>
                <a href="<?= BASE_URL ?>/classroom/quiz_live.php?quiz_id=<?= $quiz['id'] ?>" class="btn btn-danger btn-sm" title="Launch live Kahoot-style session"><i class="fas fa-broadcast-tower"></i> Live</a>
                <a href="<?= BASE_URL ?>/classroom/quiz.php?class_id=<?= $classId ?>&quiz_id=<?= $quiz['id'] ?>&action=manage" class="btn btn-primary btn-sm">Manage</a>
                <?php elseif ($quiz['status'] === 'live'): ?>
                <a href="<?= BASE_URL ?>/classroom/quiz_live.php?quiz_id=<?= $quiz['id'] ?>" class="btn btn-danger btn-sm"><i class="fas fa-broadcast-tower"></i> Join Live</a>
                <a href="<?= BASE_URL ?>/classroom/quiz.php?class_id=<?= $classId ?>&quiz_id=<?= $quiz['id'] ?>&action=take" class="btn btn-success btn-sm"><i class="fas fa-play"></i> Take Quiz</a>
                <?php endif; ?>
              </div>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
      </div>

      <!-- Polls -->
      <div class="card">
        <div class="card-header">
          <div class="card-title"><i class="fas fa-poll" style="color:var(--success)"></i> Polls</div>
          <?php if ($isTeacher): ?>
          <button class="btn btn-success btn-sm" onclick="openModal('create-poll-modal')"><i class="fas fa-plus"></i> New Poll</button>
          <?php endif; ?>
        </div>
        <?php if (empty($pollList)): ?>
        <div class="empty-state"><div class="empty-icon"><i class="fas fa-poll"></i></div><div class="empty-title">No polls yet</div></div>
        <?php else: ?>
        <?php foreach ($pollList as $poll): ?>
        <?php $opts = json_decode($poll['options'], true) ?? []; ?>
        <div style="margin-bottom:1.25rem;padding:1rem;background:var(--bg-surface);border-radius:var(--radius-md);border:1px solid var(--border)">
          <div style="font-weight:700;margin-bottom:0.875rem;display:flex;align-items:center;gap:0.5rem">
            <i class="fas fa-poll" style="color:var(--success)"></i> <?= e($poll['question']) ?>
            <span class="badge badge-info" style="margin-left:auto"><?= $poll['vote_count'] ?> votes</span>
          </div>
          <?php foreach ($opts as $i => $opt): ?>
          <?php
            $voteCount = $pdo->prepare("SELECT COUNT(*) FROM poll_votes WHERE poll_id=? AND option_index=?");
            $voteCount->execute([$poll['id'], $i]);
            $count = $voteCount->fetchColumn();
            $pct   = $poll['vote_count'] > 0 ? round($count / $poll['vote_count'] * 100) : 0;
          ?>
          <div style="margin-bottom:0.625rem">
            <div style="display:flex;justify-content:space-between;font-size:0.8rem;margin-bottom:0.25rem">
              <span><?= e($opt) ?></span><span style="font-weight:700"><?= $pct ?>%</span>
            </div>
            <div class="progress-bar" style="height:10px">
              <div class="progress-fill success" style="width:<?= $pct ?>%"></div>
            </div>
          </div>
          <?php endforeach; ?>
          <?php if (!$isTeacher && $poll['is_active']): ?>
          <form method="POST" action="<?= BASE_URL ?>/api/quiz.php" style="margin-top:0.875rem;display:flex;gap:0.625rem;flex-wrap:wrap">
            <input type="hidden" name="action" value="vote">
            <input type="hidden" name="poll_id" value="<?= $poll['id'] ?>">
            <?php foreach ($opts as $i => $opt): ?>
            <button type="submit" name="option_index" value="<?= $i ?>" class="btn btn-secondary btn-sm"><?= e($opt) ?></button>
            <?php endforeach; ?>
          </form>
          <?php endif; ?>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div>

    <!-- Sidebar stats -->
    <div style="display:flex;flex-direction:column;gap:1.5rem">
      <div class="card">
        <div class="card-title" style="margin-bottom:1rem"><i class="fas fa-chart-pie" style="color:var(--warning)"></i> Quiz Stats</div>
        <div style="display:flex;flex-direction:column;gap:0.875rem">
          <div class="stat-card" style="border-left:3px solid var(--primary);padding:1rem">
            <div class="stat-icon" style="background:rgba(99,102,241,0.15);color:var(--primary);width:44px;height:44px"><i class="fas fa-clipboard-list"></i></div>
            <div class="stat-info"><div class="stat-value" style="font-size:1.5rem;color:var(--primary)"><?= count($quizList) ?></div><div class="stat-label">Total Quizzes</div></div>
          </div>
          <div class="stat-card" style="border-left:3px solid var(--danger);padding:1rem">
            <div class="stat-icon" style="background:rgba(239,68,68,0.15);color:var(--danger);width:44px;height:44px"><i class="fas fa-broadcast-tower"></i></div>
            <div class="stat-info"><div class="stat-value" style="font-size:1.5rem;color:var(--danger)"><?= count(array_filter($quizList, fn($q) => $q['status']==='live')) ?></div><div class="stat-label">Live Now</div></div>
          </div>
          <div class="stat-card" style="border-left:3px solid var(--success);padding:1rem">
            <div class="stat-icon" style="background:rgba(16,185,129,0.15);color:var(--success);width:44px;height:44px"><i class="fas fa-poll"></i></div>
            <div class="stat-info"><div class="stat-value" style="font-size:1.5rem;color:var(--success)"><?= count($pollList) ?></div><div class="stat-label">Active Polls</div></div>
          </div>
        </div>
      </div>

      <?php if ($isTeacher): ?>
      <div class="card">
        <div class="card-title" style="margin-bottom:1rem"><i class="fas fa-bolt" style="color:var(--warning)"></i> Quick Actions</div>
        <div style="display:flex;flex-direction:column;gap:0.5rem">
          <button class="btn btn-primary btn-full" onclick="openModal('create-quiz-modal')"><i class="fas fa-plus-circle"></i> Create Quiz</button>
          <button class="btn btn-success btn-full" onclick="openModal('create-poll-modal')"><i class="fas fa-poll"></i> Create Poll</button>
          <a href="<?= BASE_URL ?>/analytics/performance.php?class_id=<?= $classId ?>" class="btn btn-secondary btn-full"><i class="fas fa-chart-line"></i> Quiz Analytics</a>
        </div>
      </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- Create Quiz Modal -->
<div class="modal-overlay" id="create-quiz-modal">
  <div class="modal" style="max-width:600px">
    <div class="modal-header">
      <div class="modal-title"><i class="fas fa-question-circle" style="color:var(--primary)"></i> Create New Quiz</div>
      <button class="modal-close">✕</button>
    </div>
    <form method="POST" action="<?= BASE_URL ?>/api/quiz.php">
      <input type="hidden" name="action" value="create_quiz">
      <input type="hidden" name="class_id" value="<?= $classId ?>">
      <div class="modal-body" style="display:flex;flex-direction:column;gap:1rem">
        <div class="form-group"><label class="form-label">Quiz Title *</label><input type="text" name="title" class="form-control" placeholder="e.g., Midterm Quiz" required></div>
        <div class="form-group"><label class="form-label">Description</label><textarea name="description" class="form-control" rows="2"></textarea></div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem">
          <div class="form-group"><label class="form-label">Time Limit (minutes)</label><input type="number" name="time_limit" class="form-control" value="30" min="1"></div>
          <div class="form-group"><label class="form-label">Status</label><select name="status" class="form-control"><option value="draft">Draft</option><option value="live">Live Now</option></select></div>
        </div>
        <!-- Questions -->
        <div>
          <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:0.75rem">
            <label class="form-label" style="margin:0">Questions</label>
            <button type="button" class="btn btn-secondary btn-sm" onclick="addQuestion()"><i class="fas fa-plus"></i> Add Question</button>
          </div>
          <div id="questions-container">
            <div class="question-block" style="background:var(--bg-surface);border:1px solid var(--border);border-radius:var(--radius-md);padding:1rem;margin-bottom:0.75rem">
              <div class="form-group" style="margin-bottom:0.75rem"><input type="text" name="questions[0][question]" class="form-control" placeholder="Question 1" required></div>
              <div style="display:grid;grid-template-columns:1fr 1fr;gap:0.5rem;margin-bottom:0.5rem">
                <input type="text" name="questions[0][options][]" class="form-control" placeholder="Option A" required>
                <input type="text" name="questions[0][options][]" class="form-control" placeholder="Option B" required>
                <input type="text" name="questions[0][options][]" class="form-control" placeholder="Option C">
                <input type="text" name="questions[0][options][]" class="form-control" placeholder="Option D">
              </div>
              <div class="form-group" style="display:grid;grid-template-columns:1fr 1fr;gap:0.75rem">
                <div><label class="form-label">Correct Answer</label><select name="questions[0][correct]" class="form-control"><option value="A">A</option><option value="B">B</option><option value="C">C</option><option value="D">D</option></select></div>
                <div><label class="form-label">Points</label><input type="number" name="questions[0][points]" class="form-control" value="10" min="1"></div>
              </div>
            </div>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary modal-close">Cancel</button>
        <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Create Quiz</button>
      </div>
    </form>
  </div>
</div>

<!-- Create Poll Modal -->
<div class="modal-overlay" id="create-poll-modal">
  <div class="modal" style="max-width:500px">
    <div class="modal-header">
      <div class="modal-title"><i class="fas fa-poll" style="color:var(--success)"></i> Create Poll</div>
      <button class="modal-close">✕</button>
    </div>
    <form method="POST" action="<?= BASE_URL ?>/api/quiz.php">
      <input type="hidden" name="action" value="create_poll">
      <input type="hidden" name="class_id" value="<?= $classId ?>">
      <div class="modal-body" style="display:flex;flex-direction:column;gap:1rem">
        <div class="form-group"><label class="form-label">Question *</label><input type="text" name="question" class="form-control" placeholder="What is...?" required></div>
        <div id="poll-options">
          <label class="form-label">Options</label>
          <div style="display:flex;flex-direction:column;gap:0.5rem">
            <input type="text" name="options[]" class="form-control" placeholder="Option 1" required>
            <input type="text" name="options[]" class="form-control" placeholder="Option 2" required>
            <input type="text" name="options[]" class="form-control" placeholder="Option 3">
            <input type="text" name="options[]" class="form-control" placeholder="Option 4">
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary modal-close">Cancel</button>
        <button type="submit" class="btn btn-success"><i class="fas fa-paper-plane"></i> Launch Poll</button>
      </div>
    </form>
  </div>
</div>

<?php renderFooter('<script>
let qIdx = 1;
function addQuestion() {
  const cont = document.getElementById("questions-container");
  const block = `<div class="question-block" style="background:var(--bg-surface);border:1px solid var(--border);border-radius:var(--radius-md);padding:1rem;margin-bottom:0.75rem">
    <div class="form-group" style="margin-bottom:0.75rem"><input type="text" name="questions[${qIdx}][question]" class="form-control" placeholder="Question ${qIdx+1}" required></div>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:0.5rem;margin-bottom:0.5rem">
      <input type="text" name="questions[${qIdx}][options][]" class="form-control" placeholder="Option A" required>
      <input type="text" name="questions[${qIdx}][options][]" class="form-control" placeholder="Option B" required>
      <input type="text" name="questions[${qIdx}][options][]" class="form-control" placeholder="Option C">
      <input type="text" name="questions[${qIdx}][options][]" class="form-control" placeholder="Option D">
    </div>
    <div class="form-group" style="display:grid;grid-template-columns:1fr 1fr;gap:0.75rem">
      <div><label class="form-label">Correct Answer</label><select name="questions[${qIdx}][correct]" class="form-control"><option value="A">A</option><option value="B">B</option><option value="C">C</option><option value="D">D</option></select></div>
      <div><label class="form-label">Points</label><input type="number" name="questions[${qIdx}][points]" class="form-control" value="10" min="1"></div>
    </div>
  </div>`;
  cont.insertAdjacentHTML("beforeend", block);
  qIdx++;
}
</script>'); ?>
</body></html>
