<?php
// =============================================
// Smart Classroom — Live Quiz
// =============================================
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/layout.php';
requireLogin();

$user   = currentUser();
$quizId = (int)($_GET['quiz_id'] ?? 0);
if (!$quizId) redirect(BASE_URL . '/index.php');

$quiz = $pdo->prepare("SELECT q.*, c.name as class_name, c.id as class_id FROM quizzes q JOIN classes c ON c.id=q.class_id WHERE q.id=?");
$quiz->execute([$quizId]);
$quizData = $quiz->fetch();
if (!$quizData) { http_response_code(404); die('Quiz not found'); }

$isHost = ($user['role'] === 'teacher' && $quizData['created_by'] == $user['id']);
$classId = $quizData['class_id'];

// Verify student is in the class (or is the teacher)
if (!$isHost) {
    $mem = $pdo->prepare("SELECT id FROM class_members WHERE class_id=? AND user_id=?");
    $mem->execute([$classId, $user['id']]);
    if (!$mem->fetch()) redirect(BASE_URL . '/index.php');
}

$totalQs = (int)$pdo->query("SELECT COUNT(*) FROM quiz_questions WHERE quiz_id={$quizId}")->fetchColumn();

renderHead('Live Quiz · ' . $quizData['title']);
?>
<body style="background:var(--bg-base);color:var(--on-surface, var(--text-primary))">

<div style="min-height:100vh;display:flex;flex-direction:column">

  <!-- Top bar -->
  <div style="background:var(--bg-surface,var(--surface-container));border-bottom:1px solid var(--border);padding:0.875rem 1.5rem;display:flex;align-items:center;justify-content:space-between;gap:1rem">
    <div style="display:flex;align-items:center;gap:0.875rem">
      <span style="display:inline-flex;align-items:center;gap:0.4rem;padding:0.25rem 0.75rem;border-radius:999px;font-size:0.75rem;font-weight:700;letter-spacing:0.05em" id="live-badge" style="background:rgba(239,68,68,0.15);color:var(--danger)">
        <span id="live-dot" style="width:8px;height:8px;border-radius:50%;background:var(--danger);display:inline-block;animation:pulse 1.2s infinite"></span>
        LIVE
      </span>
      <span style="font-weight:700;font-size:1rem"><?= e($quizData['title']) ?></span>
      <span style="font-size:0.8rem;color:var(--text-muted)"><?= e($quizData['class_name']) ?></span>
    </div>
    <div style="display:flex;align-items:center;gap:0.75rem">
      <span id="q-counter" style="font-size:0.875rem;font-weight:600;color:var(--text-muted)">Q — / <?= $totalQs ?></span>
      <?php if ($isHost): ?>
      <button id="end-btn" class="btn btn-ghost btn-sm" style="color:var(--danger)" onclick="endQuiz()"><i class="fas fa-stop-circle"></i> End Quiz</button>
      <?php else: ?>
      <a href="<?= BASE_URL ?>/classroom/index.php?id=<?= $classId ?>&tab=stream" class="btn btn-ghost btn-sm"><i class="fas fa-sign-out-alt"></i> Leave</a>
      <?php endif; ?>
    </div>
  </div>

  <!-- Main content -->
  <div style="flex:1;display:flex;padding:1.5rem;gap:1.5rem;max-width:1100px;width:100%;margin:0 auto;box-sizing:border-box">

    <!-- Left: Question area -->
    <div style="flex:1;display:flex;flex-direction:column;gap:1rem" id="question-area">

      <!-- Pre-launch (host only) -->
      <?php if ($isHost && $quizData['status'] !== 'live' && $quizData['status'] !== 'closed'): ?>
      <div id="prelaunch-panel" style="text-align:center;padding:3rem 1rem">
        <div style="font-size:3rem;margin-bottom:1rem">🚀</div>
        <h2 style="font-size:1.5rem;font-weight:800;margin-bottom:0.5rem">Ready to launch?</h2>
        <p style="color:var(--text-muted);margin-bottom:1.5rem">Students will be notified and the quiz will start immediately.</p>
        <p style="color:var(--text-muted);font-size:0.875rem;margin-bottom:1.5rem"><?= $totalQs ?> questions · <?= $quizData['time_limit'] ?> min limit</p>
        <button class="btn btn-danger" style="padding:0.875rem 2rem;font-size:1rem;font-weight:700" onclick="launchQuiz()">
          <i class="fas fa-broadcast-tower"></i> Launch Live Quiz
        </button>
      </div>
      <?php endif; ?>

      <!-- Live question display -->
      <div id="question-panel" style="display:none">
        <div class="card" style="margin-bottom:0">
          <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1rem">
            <span id="q-label" style="font-size:0.8rem;font-weight:700;color:var(--primary);text-transform:uppercase;letter-spacing:0.1em">Question 1</span>
            <span id="q-points" style="font-size:0.8rem;color:var(--text-muted)"></span>
          </div>
          <div id="q-text" style="font-size:1.2rem;font-weight:700;margin-bottom:1.5rem;line-height:1.5"></div>

          <!-- Answer options -->
          <div id="options-grid" style="display:grid;grid-template-columns:1fr 1fr;gap:0.75rem"></div>

          <!-- Student: result feedback -->
          <div id="answer-result" style="display:none;margin-top:1rem;padding:1rem;border-radius:var(--radius-sm);text-align:center">
            <div id="result-icon" style="font-size:2rem;margin-bottom:0.5rem"></div>
            <div id="result-text" style="font-weight:700;font-size:1rem"></div>
            <div id="result-points" style="color:var(--text-muted);font-size:0.875rem;margin-top:0.25rem"></div>
          </div>

          <!-- Student: waiting for next -->
          <div id="waiting-panel" style="display:none;margin-top:1rem;text-align:center;padding:1.25rem;background:rgba(99,102,241,0.08);border-radius:var(--radius-sm)">
            <div style="font-size:0.875rem;color:var(--text-muted)"><i class="fas fa-hourglass-half" style="color:var(--primary);margin-right:0.5rem"></i>Waiting for teacher to advance...</div>
          </div>

          <!-- Teacher: advance button -->
          <?php if ($isHost): ?>
          <div id="host-controls" style="margin-top:1.25rem;padding-top:1rem;border-top:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;gap:1rem">
            <div style="font-size:0.875rem;color:var(--text-muted)"><span id="answered-count" style="font-weight:700;color:var(--primary)">0</span> / <span id="student-count">?</span> answered</div>
            <button id="next-btn" class="btn btn-primary" onclick="advanceQuestion()"><i class="fas fa-arrow-right"></i> Next Question</button>
          </div>
          <?php endif; ?>
        </div>
      </div>

      <!-- Ended state -->
      <div id="ended-panel" style="display:none;text-align:center;padding:2rem">
        <div style="font-size:3rem;margin-bottom:1rem">🏆</div>
        <h2 style="font-size:1.5rem;font-weight:800;margin-bottom:0.5rem">Quiz Finished!</h2>
        <p style="color:var(--text-muted);margin-bottom:1.5rem">Final Results</p>
        <div id="my-score-box" style="display:none;background:rgba(99,102,241,0.12);border:1px solid rgba(99,102,241,0.3);border-radius:var(--radius-md);padding:1.5rem;margin:0 auto 1.5rem;max-width:300px">
          <div style="font-size:0.75rem;text-transform:uppercase;letter-spacing:0.1em;color:var(--text-muted);margin-bottom:0.25rem">Your Score</div>
          <div id="my-final-score" style="font-size:3rem;font-weight:900;color:var(--primary)">0</div>
          <div style="font-size:0.875rem;color:var(--text-muted)">points</div>
        </div>
        <a href="<?= BASE_URL ?>/classroom/index.php?id=<?= $classId ?>&tab=stream" class="btn btn-secondary">Back to Class</a>
      </div>

      <!-- Waiting for quiz to start (student) -->
      <?php if (!$isHost && $quizData['status'] !== 'live' && $quizData['status'] !== 'closed'): ?>
      <div id="student-wait-panel" style="text-align:center;padding:3rem 1rem">
        <div style="font-size:3rem;margin-bottom:1rem">⏳</div>
        <h2 style="font-size:1.25rem;font-weight:800;margin-bottom:0.5rem">Waiting for quiz to start</h2>
        <p style="color:var(--text-muted)">Your teacher will launch the quiz shortly. This page will update automatically.</p>
      </div>
      <?php endif; ?>

    </div>

    <!-- Right: Leaderboard -->
    <div style="width:280px;flex-shrink:0">
      <div class="card" style="position:sticky;top:1.5rem">
        <div class="card-title" style="margin-bottom:1rem"><i class="fas fa-trophy" style="color:var(--warning)"></i> Leaderboard</div>
        <div id="leaderboard-list">
          <div style="text-align:center;padding:1rem;color:var(--text-muted);font-size:0.875rem">Waiting for answers...</div>
        </div>
      </div>
    </div>

  </div>
</div>

<style>
@keyframes pulse { 0%,100%{opacity:1} 50%{opacity:0.3} }
.option-btn {
  width:100%;padding:0.875rem 1rem;border-radius:var(--radius-sm);border:2px solid var(--border);
  background:var(--bg-surface,var(--surface-container));color:var(--text-primary);
  font-size:0.9rem;font-weight:600;cursor:pointer;text-align:left;transition:all 0.18s;
  display:flex;align-items:center;gap:0.75rem;
}
.option-btn:hover:not(:disabled) { border-color:var(--primary);background:rgba(99,102,241,0.08); }
.option-btn.selected { border-color:var(--primary);background:rgba(99,102,241,0.12); }
.option-btn.correct  { border-color:var(--success);background:rgba(16,185,129,0.12);color:var(--success); }
.option-btn.wrong    { border-color:var(--danger);background:rgba(239,68,68,0.08);color:var(--danger); }
.option-btn:disabled { cursor:default;opacity:0.85; }
.option-letter { width:28px;height:28px;border-radius:50%;background:var(--bg-overlay,rgba(255,255,255,0.07));display:flex;align-items:center;justify-content:center;font-size:0.8rem;font-weight:800;flex-shrink:0; }
</style>

<script>
const QUIZ_ID     = <?= $quizId ?>;
const IS_HOST     = <?= $isHost ? 'true' : 'false' ?>;
const BASE_URL    = '<?= BASE_URL ?>';
const OPT_LABELS  = ['A','B','C','D','E'];

let lastQIndex    = -1;
let quizEnded     = false;
let myTotalPoints = 0;

// ── Polling ─────────────────────────────────────
async function poll() {
  if (quizEnded) return;
  try {
    const res  = await fetch(`${BASE_URL}/api/quiz.php?action=get_live_state&quiz_id=${QUIZ_ID}`);
    const data = await res.json();
    if (data.error) return;
    handleState(data);
    await updateLeaderboard();
  } catch(e) {}
}

function handleState(data) {
  const { status, live_question, total_questions, question, answered, my_answer,
          distribution, answered_count, student_count } = data;

  document.getElementById('q-counter').textContent = `Q ${status === 'live' ? live_question + 1 : '—'} / ${total_questions}`;

  if (status === 'closed') {
    endUI();
    return;
  }

  if (status !== 'live') return;

  // Hide pre-launch / student-wait panels
  document.getElementById('prelaunch-panel')?.style && (document.getElementById('prelaunch-panel').style.display = 'none');
  document.getElementById('student-wait-panel')?.style && (document.getElementById('student-wait-panel').style.display = 'none');

  if (!question) return;

  // New question arrived
  if (live_question !== lastQIndex) {
    lastQIndex = live_question;
    renderQuestion(question, live_question, total_questions, answered, my_answer);
  }

  // Host: update distribution + answer count
  if (IS_HOST && distribution !== undefined) {
    updateDistribution(distribution, answered_count, student_count, question);
    document.getElementById('answered-count').textContent = answered_count;
    document.getElementById('student-count').textContent  = student_count;
    const isLast = (live_question + 1) >= total_questions;
    const nextBtn = document.getElementById('next-btn');
    if (nextBtn) nextBtn.innerHTML = isLast ? '<i class="fas fa-flag-checkered"></i> End Quiz' : '<i class="fas fa-arrow-right"></i> Next Question';
  }
}

function renderQuestion(q, idx, total, answered, myAnswer) {
  document.getElementById('question-panel').style.display = 'block';
  document.getElementById('ended-panel').style.display    = 'none';
  document.getElementById('answer-result').style.display  = 'none';
  document.getElementById('waiting-panel').style.display  = 'none';

  document.getElementById('q-label').textContent  = `Question ${idx + 1} of ${total}`;
  document.getElementById('q-points').textContent = `${q.points} pts`;
  document.getElementById('q-text').textContent   = q.text;

  const grid = document.getElementById('options-grid');
  grid.innerHTML = '';

  (q.options || []).forEach((opt, i) => {
    const letter = OPT_LABELS[i] || String.fromCharCode(65 + i);
    const btn = document.createElement('button');
    btn.className   = 'option-btn';
    btn.dataset.val = letter;
    btn.innerHTML   = `<span class="option-letter">${letter}</span>${escHtml(opt)}`;

    if (!IS_HOST) {
      if (answered) {
        btn.disabled = true;
        if (myAnswer === letter) btn.classList.add('selected');
      } else {
        btn.onclick = () => submitAnswer(q.id, letter);
      }
    }
    grid.appendChild(btn);
  });

  // Show teacher's correct answer indicator on question render
  if (IS_HOST && q.correct) {
    highlightCorrect(q.correct);
  }

  if (!IS_HOST && answered) {
    document.getElementById('waiting-panel').style.display = 'block';
  }
  if (IS_HOST) {
    document.getElementById('host-controls').style.display = 'flex';
  }
}

function updateDistribution(distribution, answeredCount, studentCount, q) {
  const grid = document.getElementById('options-grid');
  const btns = grid.querySelectorAll('.option-btn');
  btns.forEach(btn => {
    const val   = btn.dataset.val;
    const entry = distribution.find(d => d.answer === val);
    const cnt   = entry ? parseInt(entry.cnt) : 0;
    const pct   = answeredCount > 0 ? Math.round(cnt / answeredCount * 100) : 0;
    // Remove existing bar if any
    btn.querySelectorAll('.dist-bar').forEach(b => b.remove());
    const bar = document.createElement('div');
    bar.className = 'dist-bar';
    bar.style.cssText = `position:absolute;bottom:0;left:0;height:3px;width:${pct}%;background:var(--primary);border-radius:0 0 4px 4px;transition:width 0.4s`;
    btn.style.position = 'relative';
    btn.appendChild(bar);
    // Show count
    let countEl = btn.querySelector('.opt-count');
    if (!countEl) {
      countEl = document.createElement('span');
      countEl.className = 'opt-count';
      countEl.style.cssText = 'margin-left:auto;font-size:0.75rem;font-weight:700;color:var(--text-muted)';
      btn.appendChild(countEl);
    }
    countEl.textContent = cnt > 0 ? cnt : '';
    // Highlight correct
    if (q.correct && btn.dataset.val === q.correct.toUpperCase()) {
      btn.classList.add('correct');
    }
  });
}

function highlightCorrect(correctLetter) {
  document.querySelectorAll('.option-btn').forEach(btn => {
    if (btn.dataset.val === correctLetter.toUpperCase()) btn.classList.add('correct');
  });
}

async function submitAnswer(questionId, letter) {
  // Disable all buttons immediately
  document.querySelectorAll('.option-btn').forEach(b => {
    b.disabled = true;
    if (b.dataset.val === letter) b.classList.add('selected');
  });

  const fd = new FormData();
  fd.append('action', 'submit_live_answer');
  fd.append('quiz_id', QUIZ_ID);
  fd.append('question_id', questionId);
  fd.append('answer', letter);

  try {
    const res  = await fetch(`${BASE_URL}/api/quiz.php`, { method: 'POST', body: fd });
    const data = await res.json();

    if (data.success) {
      const correct = data.correct_answer.toUpperCase();
      document.querySelectorAll('.option-btn').forEach(b => {
        if (b.dataset.val === correct) b.classList.add('correct');
        else if (b.dataset.val === letter && letter !== correct) b.classList.add('wrong');
      });

      const resultBox  = document.getElementById('answer-result');
      const resultIcon = document.getElementById('result-icon');
      const resultText = document.getElementById('result-text');
      const resultPts  = document.getElementById('result-points');

      resultBox.style.display = 'block';
      if (data.is_correct) {
        resultBox.style.background = 'rgba(16,185,129,0.12)';
        resultIcon.textContent = '✅';
        resultText.textContent = 'Correct!';
        resultPts.textContent  = `+${data.points_earned} points`;
        myTotalPoints += data.points_earned;
      } else {
        resultBox.style.background = 'rgba(239,68,68,0.1)';
        resultIcon.textContent = '❌';
        resultText.textContent = `Wrong — correct answer was ${correct}`;
        resultPts.textContent  = '+0 points';
      }

      document.getElementById('waiting-panel').style.display = 'block';
    }
  } catch(e) {}
}

async function advanceQuestion() {
  const btn = document.getElementById('next-btn');
  btn.disabled = true;
  const fd = new FormData();
  fd.append('action', 'advance_question');
  fd.append('quiz_id', QUIZ_ID);
  try {
    const res  = await fetch(`${BASE_URL}/api/quiz.php`, { method: 'POST', body: fd });
    const data = await res.json();
    if (data.ended) { endUI(); quizEnded = true; }
  } catch(e) {}
  btn.disabled = false;
}

async function launchQuiz() {
  const fd = new FormData();
  fd.append('action', 'launch_live');
  fd.append('quiz_id', QUIZ_ID);
  await fetch(`${BASE_URL}/api/quiz.php`, { method: 'POST', body: fd });
  document.getElementById('prelaunch-panel').style.display = 'none';
}

async function endQuiz() {
  if (!confirm('End the quiz now and show final results to all students?')) return;
  const fd = new FormData();
  fd.append('action', 'advance_question');
  fd.append('quiz_id', QUIZ_ID);
  // Call advance until ended
  let tries = 0;
  while (!quizEnded && tries++ < 50) {
    const res  = await fetch(`${BASE_URL}/api/quiz.php`, { method: 'POST', body: fd });
    const data = await res.json();
    if (data.ended) { endUI(); quizEnded = true; break; }
  }
}

function endUI() {
  quizEnded = true;
  document.getElementById('question-panel').style.display  = 'none';
  document.getElementById('ended-panel').style.display     = 'block';
  document.getElementById('prelaunch-panel')?.style && (document.getElementById('prelaunch-panel').style.display = 'none');
  document.getElementById('student-wait-panel')?.style && (document.getElementById('student-wait-panel').style.display = 'none');
  document.getElementById('live-dot').style.background     = 'var(--success)';

  if (!IS_HOST && myTotalPoints > 0) {
    const box = document.getElementById('my-score-box');
    box.style.display = 'block';
    document.getElementById('my-final-score').textContent = myTotalPoints;
  }
  updateLeaderboard();
}

async function updateLeaderboard() {
  try {
    const res  = await fetch(`${BASE_URL}/api/quiz.php?action=get_leaderboard&quiz_id=${QUIZ_ID}`);
    const data = await res.json();
    if (!data.leaderboard) return;

    const medals = ['🥇','🥈','🥉'];
    const html = data.leaderboard.length === 0
      ? '<div style="text-align:center;padding:1rem;color:var(--text-muted);font-size:0.875rem">No answers yet</div>'
      : data.leaderboard.map((row, i) => `
          <div style="display:flex;align-items:center;gap:0.625rem;padding:0.5rem 0;border-bottom:1px solid var(--border)">
            <span style="font-size:${i < 3 ? '1.1rem' : '0.875rem'};min-width:1.75rem;text-align:center">${i < 3 ? medals[i] : (i + 1)}</span>
            <div style="flex:1;min-width:0">
              <div style="font-size:0.875rem;font-weight:600;overflow:hidden;text-overflow:ellipsis;white-space:nowrap">${escHtml(row.name)}</div>
              <div style="font-size:0.7rem;color:var(--text-muted)">${row.correct_count}/${row.answered_count} correct</div>
            </div>
            <span style="font-weight:800;color:var(--primary);font-size:0.9rem">${row.total_points}</span>
          </div>
        `).join('');
    document.getElementById('leaderboard-list').innerHTML = html;
  } catch(e) {}
}

function escHtml(str) {
  return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

// Start polling every 2 seconds
poll();
setInterval(poll, 2000);
</script>
</body>
</html>
