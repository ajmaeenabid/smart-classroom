<?php
// =============================================
// Smart Classroom — Calendar
// =============================================
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/layout.php';
requireLogin();

$user = currentUser();
$uid  = $user['id'];

$year  = (int)($_GET['year']  ?? date('Y'));
$month = (int)($_GET['month'] ?? date('m'));
if ($month < 1)  { $month = 12; $year--; }
if ($month > 12) { $month = 1;  $year++; }

// Add event
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title    = trim($_POST['title'] ?? '');
    $desc     = trim($_POST['description'] ?? '');
    $date     = $_POST['event_date'] ?? '';
    $type     = $_POST['type'] ?? 'event';
    if ($title && $date) {
        $ins = $pdo->prepare("INSERT INTO calendar_events (user_id, title, description, event_date, type) VALUES (?,?,?,?,?)");
        $ins->execute([$uid, $title, $desc, $date . ':00', $type]);
    }
    redirect(BASE_URL . "/global/calendar.php?year={$year}&month={$month}");
}

// Load events this month
$eventsRaw = $pdo->prepare("SELECT * FROM calendar_events WHERE user_id=? AND YEAR(event_date)=? AND MONTH(event_date)=? ORDER BY event_date");
$eventsRaw->execute([$uid, $year, $month]);
$eventsList = $eventsRaw->fetchAll();

// Also load assignments due this month
$asgnRaw = $pdo->prepare("SELECT a.*,c.name as class_name,'assignment' as ev_type FROM assignments a JOIN classes c ON c.id=a.class_id JOIN class_members cm ON cm.class_id=c.id WHERE cm.user_id=? AND YEAR(a.due_date)=? AND MONTH(a.due_date)=?");
$asgnRaw->execute([$uid, $year, $month]);
$assignEvents = $asgnRaw->fetchAll();
// Teacher: own class assignments
$asgnTeach = $pdo->prepare("SELECT a.*,c.name as class_name,'assignment' as ev_type FROM assignments a JOIN classes c ON c.id=a.class_id WHERE c.teacher_id=? AND YEAR(a.due_date)=? AND MONTH(a.due_date)=?");
$asgnTeach->execute([$uid, $year, $month]);
$assignEvents = array_merge($assignEvents, $asgnTeach->fetchAll());

// Build event map by day
$eventMap = [];
foreach ($eventsList as $ev) {
    $day = (int)date('j', strtotime($ev['event_date']));
    $eventMap[$day][] = ['title' => $ev['title'], 'type' => $ev['type'], 'source' => 'custom'];
}
foreach ($assignEvents as $ev) {
    $day = (int)date('j', strtotime($ev['due_date']));
    $eventMap[$day][] = ['title' => $ev['title'], 'type' => 'assignment', 'source' => 'assignment', 'class' => $ev['class_name']];
}

$daysInMonth  = cal_days_in_month(CAL_GREGORIAN, $month, $year);
$firstWeekday = (int)date('w', mktime(0,0,0,$month,1,$year)); // 0=Sun
$monthName    = date('F Y', mktime(0,0,0,$month,1,$year));

$typeColors = ['assignment'=>'var(--warning)','quiz'=>'var(--primary)','meet'=>'var(--info)','event'=>'var(--success)','holiday'=>'var(--danger)'];

renderHead('Calendar');
?>
<body>
<div class="app-wrapper">
<?php renderSidebar($user, 'calendar.php'); ?>
<div class="main-content">
<?php renderTopbar('Calendar', $user, [['icon'=>'fa-plus','label'=>'Add Event','onclick'=>"openModal('add-event-modal')"]]); ?>

<div class="page-content animate-up">
  <div style="display:grid;grid-template-columns:1fr 280px;gap:1.5rem">

    <!-- Calendar Grid -->
    <div class="card">
      <!-- Navigation -->
      <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:1.5rem">
        <a href="?year=<?= $year ?>&month=<?= $month-1 ?>" class="btn btn-secondary btn-sm"><i class="fas fa-chevron-left"></i></a>
        <div style="font-size:1.2rem;font-weight:800"><?= $monthName ?></div>
        <a href="?year=<?= $year ?>&month=<?= $month+1 ?>" class="btn btn-secondary btn-sm"><i class="fas fa-chevron-right"></i></a>
      </div>

      <!-- Day Headers -->
      <div style="display:grid;grid-template-columns:repeat(7,1fr);gap:0.25rem;margin-bottom:0.25rem">
        <?php foreach (['Sun','Mon','Tue','Wed','Thu','Fri','Sat'] as $d): ?>
        <div style="text-align:center;font-size:0.7rem;font-weight:700;color:var(--text-muted);padding:0.375rem"><?= $d ?></div>
        <?php endforeach; ?>
      </div>

      <!-- Date Grid -->
      <div style="display:grid;grid-template-columns:repeat(7,1fr);gap:0.25rem">
        <?php
        // Empty cells before the 1st
        for ($i = 0; $i < $firstWeekday; $i++): ?>
        <div style="min-height:80px"></div>
        <?php endfor; ?>

        <?php for ($d = 1; $d <= $daysInMonth; $d++):
          $isToday  = ($d === (int)date('j') && $month === (int)date('m') && $year === (int)date('Y'));
          $hasEvents = !empty($eventMap[$d]);
        ?>
        <div style="min-height:80px;border-radius:var(--radius-sm);background:<?= $isToday ? 'rgba(99,102,241,0.15)' : 'var(--bg-surface)' ?>;border:1px solid <?= $isToday ? 'var(--primary)' : 'var(--border)' ?>;padding:0.375rem;transition:all 0.2s;cursor:pointer" onclick="selectDay(<?= $d ?>)" onmouseover="this.style.borderColor='var(--primary)'" onmouseout="this.style.borderColor='<?= $isToday ? 'var(--primary)' : 'var(--border)' ?>'">
          <div style="font-size:0.82rem;font-weight:<?= $isToday ? '900' : '600' ?>;color:<?= $isToday ? 'var(--primary)' : 'var(--text-primary)' ?>;margin-bottom:0.25rem"><?= $d ?></div>
          <?php if ($hasEvents): ?>
          <?php foreach (array_slice($eventMap[$d], 0, 3) as $ev): ?>
          <div style="font-size:0.65rem;background:<?= $typeColors[$ev['type']] ?? 'var(--primary)' ?>22;color:<?= $typeColors[$ev['type']] ?? 'var(--primary)' ?>;border-radius:4px;padding:1px 4px;margin-bottom:2px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;font-weight:600">
            <?= e(mb_strimwidth($ev['title'],0,18,'…')) ?>
          </div>
          <?php endforeach; ?>
          <?php if (count($eventMap[$d]) > 3): ?>
          <div style="font-size:0.6rem;color:var(--text-muted)">+<?= count($eventMap[$d]) - 3 ?> more</div>
          <?php endif; ?>
          <?php endif; ?>
        </div>
        <?php endfor; ?>
      </div>
    </div>

    <!-- Sidebar: Upcoming Events -->
    <div style="display:flex;flex-direction:column;gap:1.5rem">
      <div class="card">
        <div class="card-header">
          <div class="card-title"><i class="fas fa-calendar-alt" style="color:var(--primary)"></i> This Month</div>
          <button class="btn btn-primary btn-sm" onclick="openModal('add-event-modal')"><i class="fas fa-plus"></i></button>
        </div>
        <?php if (empty($eventsList) && empty($assignEvents)): ?>
        <div class="empty-state" style="padding:1.5rem"><div class="empty-icon" style="width:48px;height:48px;font-size:1.25rem"><i class="fas fa-calendar"></i></div><div class="empty-title" style="font-size:0.875rem">No events this month</div></div>
        <?php else: ?>
        <div style="display:flex;flex-direction:column;gap:0.625rem;max-height:400px;overflow-y:auto">
          <?php
          $allEventsForList = [];
          foreach ($eventsList as $e) $allEventsForList[] = ['date'=>strtotime($e['event_date']),'title'=>$e['title'],'type'=>$e['type'],'sub'=>'Personal event'];
          foreach ($assignEvents as $e) $allEventsForList[] = ['date'=>strtotime($e['due_date']),'title'=>$e['title'],'type'=>'assignment','sub'=>$e['class_name'] ?? ''];
          usort($allEventsForList, fn($a,$b) => $a['date'] - $b['date']);
          ?>
          <?php foreach ($allEventsForList as $e): ?>
          <div style="display:flex;gap:0.75rem;align-items:flex-start;padding:0.5rem;border-radius:var(--radius-sm);background:var(--bg-surface)">
            <div style="width:36px;height:36px;border-radius:var(--radius-sm);background:<?= ($typeColors[$e['type']] ?? 'var(--primary)') ?>22;color:<?= $typeColors[$e['type']] ?? 'var(--primary)' ?>;display:flex;align-items:center;justify-content:center;flex-shrink:0;font-size:0.875rem">
              <i class="fas <?= ['assignment'=>'fa-file-alt','quiz'=>'fa-question-circle','meet'=>'fa-video','event'=>'fa-star','holiday'=>'fa-umbrella-beach'][$e['type']] ?? 'fa-calendar' ?>"></i>
            </div>
            <div>
              <div style="font-size:0.8rem;font-weight:600"><?= e($e['title']) ?></div>
              <div style="font-size:0.7rem;color:var(--text-muted)"><?= date('M d', $e['date']) ?> · <?= e($e['sub']) ?></div>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
      </div>

      <!-- Legend -->
      <div class="card">
        <div class="card-title" style="margin-bottom:0.75rem"><i class="fas fa-tag" style="color:var(--text-muted)"></i> Legend</div>
        <?php foreach ([['Assignment','warning'],['Quiz','primary'],['Video Meet','info'],['Event','success'],['Holiday','danger']] as [$label,$c]): ?>
        <div style="display:flex;align-items:center;gap:0.5rem;font-size:0.8rem;margin-bottom:0.375rem">
          <div style="width:12px;height:12px;border-radius:50%;background:var(--<?= $c ?>);flex-shrink:0"></div>
          <?= $label ?>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</div>

<!-- Add Event Modal -->
<div class="modal-overlay" id="add-event-modal">
  <div class="modal" style="max-width:440px">
    <div class="modal-header">
      <div class="modal-title"><i class="fas fa-calendar-plus" style="color:var(--primary)"></i> Add Event</div>
      <button class="modal-close">✕</button>
    </div>
    <form method="POST">
      <div class="modal-body" style="display:flex;flex-direction:column;gap:1rem">
        <div class="form-group"><label class="form-label">Event Title *</label><input type="text" name="title" class="form-control" placeholder="Event name" required></div>
        <div class="form-group"><label class="form-label">Description</label><textarea name="description" class="form-control" rows="2"></textarea></div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem">
          <div class="form-group"><label class="form-label">Date & Time *</label><input type="datetime-local" name="event_date" class="form-control" required></div>
          <div class="form-group"><label class="form-label">Type</label><select name="type" class="form-control"><option value="event">General Event</option><option value="quiz">Quiz</option><option value="meet">Video Meet</option><option value="holiday">Holiday</option></select></div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary modal-close">Cancel</button>
        <button type="submit" class="btn btn-primary"><i class="fas fa-plus"></i> Add</button>
      </div>
    </form>
  </div>
</div>

<?php renderFooter('<script>
function selectDay(day) {
  const today = new Date();
  const d = new Date(<?= $year ?>, <?= $month - 1 ?>, day);
  const formatted = d.toISOString().slice(0,10) + "T09:00";
  document.querySelector("input[name=event_date]").value = formatted;
  SCS.openModal("add-event-modal");
}
</script>'); ?>
</body></html>
