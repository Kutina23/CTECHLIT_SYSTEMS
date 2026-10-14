<?php
/**
 * Sessions — live board of station time with start, extend, move and end (with payment).
 */

require_once __DIR__ . '/../includes/auth.php';
requireStaffAuth();
requirePermission('sessions.view');

$canManage = hasPermission('sessions.manage');
$canEnd = $canManage || hasPermission('sessions.end');
$canControl = hasPermission('stations.control');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Sessions — CTECHLIT & SYSTEMS</title>
  <link rel="icon" type="image/svg+xml" href="../assets/images/logo-icon.svg">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="stylesheet" href="../assets/css/portal.css">
  <link rel="stylesheet" href="../assets/css/sessions.css?v=<?php echo filemtime(__DIR__ . '/../assets/css/sessions.css'); ?>">
</head>
<body>
  <?php include_once __DIR__ . '/../includes/sidebar.php'; ?>
  <main class="portal-main" style="margin-left: var(--sidebar-width);">
    <?php include_once __DIR__ . '/../includes/header.php'; ?>

    <div class="portal-content">
      <div class="ses-top">
        <div>
          <h1>Sessions</h1>
          <p>Live station time · <span id="rateLabel">—</span> per hour · updated <span id="updated">—</span></p>
        </div>
        <nav class="ses-tabs" aria-label="Sessions views">
          <a href="sessions.php" aria-current="page"><i class="fas fa-bolt"></i> Live</a>
          <a href="session-history.php"><i class="fas fa-clock-rotate-left"></i> History</a>
        </nav>
        <?php if ($canManage): ?>
        <button class="btn btn-primary" id="startBtn" type="button"><i class="fas fa-play"></i> Start session</button>
        <?php endif; ?>
      </div>

      <div class="ses-stats">
        <div class="ses-stat"><span>Active now</span><strong id="sActive">—</strong></div>
        <div class="ses-stat warn"><span>Ending ≤ 10 min</span><strong id="sEnding">—</strong></div>
        <div class="ses-stat"><span>Free stations</span><strong id="sFree">—</strong></div>
        <div class="ses-stat ok"><span>Session revenue today</span><strong id="sRevenue">—</strong></div>
        <div class="ses-stat bad"><span>Unpaid session charges</span><strong id="sUnpaid">—</strong></div>
      </div>

      <section aria-labelledby="boardTitle">
        <h2 class="sr-only" id="boardTitle">Active sessions</h2>
        <div class="ses-board" id="board"><div class="ses-empty">Loading sessions…</div></div>
      </section>
    </div>
  </main>

  <div class="modal-overlay" id="sesModal" role="dialog" aria-modal="true" aria-labelledby="sesModalTitle">
    <div class="modal">
      <div class="modal-header">
        <h3 class="modal-title" id="sesModalTitle">Session</h3>
        <button class="modal-close" type="button" data-close aria-label="Close"><i class="fas fa-times"></i></button>
      </div>
      <div class="modal-body" id="sesModalBody"></div>
      <div class="modal-footer" id="sesModalFooter"></div>
    </div>
  </div>

  <?php include_once __DIR__ . '/../includes/modals.php'; ?>
  <script src="../assets/js/modal-controller.js"></script>
  <script src="../assets/js/sessions-ui.js?v=<?php echo filemtime(__DIR__ . '/../assets/js/sessions-ui.js'); ?>"></script>
  <script>
  (function () {
    const CAN_MANAGE = <?php echo $canManage ? 'true' : 'false'; ?>;
    const CAN_END = <?php echo $canEnd ? 'true' : 'false'; ?>;
    const CAN_CONTROL = <?php echo $canControl ? 'true' : 'false'; ?>;
    const S = window.SessionsUI;
    const $ = (id) => document.getElementById(id);
    let sessions = [];
    let rate = 0;
    let fetchedAt = Date.now();

    async function load() {
      try {
        const {data} = await S.request('api/sessions.php?active=1');
        sessions = data.sessions;
        rate = data.hourly_rate;
        fetchedAt = Date.now();
        $('rateLabel').textContent = S.money(rate);
        $('sActive').textContent = data.stats.active;
        $('sEnding').textContent = data.stats.ending_soon;
        $('sFree').textContent = data.stats.stations_free;
        $('sRevenue').textContent = S.money(data.stats.revenue_today);
        $('sUnpaid').textContent = S.money(data.stats.unpaid_total);
        $('updated').textContent = new Date().toLocaleTimeString([], {hour: '2-digit', minute: '2-digit', second: '2-digit'});
        render();
      } catch (error) {
        $('board').innerHTML = '<div class="ses-empty">' + S.esc(error.message) + '</div>';
      }
    }

    // Time maths use the server's minute counts plus time since the last fetch, so the
    // countdown stays correct whatever timezone the browser is in.
    function liveElapsedSeconds(s) {
      return Number(s.elapsed_minutes) * 60 + Math.floor((Date.now() - fetchedAt) / 1000);
    }
    function liveLeftSeconds(s) {
      return s.minutes_left === null ? null : Number(s.minutes_left) * 60 - Math.floor((Date.now() - fetchedAt) / 1000);
    }

    function card(s) {
      const left = liveLeftSeconds(s);
      const elapsed = liveElapsedSeconds(s);
      const booked = s.booked_minutes ? Number(s.booked_minutes) * 60 : null;
      const pct = booked ? Math.max(0, Math.min(100, (elapsed / booked) * 100)) : null;
      const ending = left !== null && left <= 600;
      const charge = s.prepaid ? Number(s.paid_amount) + Number(s.owed_amount) : elapsed / 3600 * rate;
      const tags = [
        s.prepaid ? '<span class="ses-tag ok">' + (Number(s.owed_amount) > 0 ? 'On account' : 'Prepaid') + '</span>' : '<span class="ses-tag">Pay at end</span>',
        s.agent_online ? '' : '<span class="ses-tag muted" title="The station agent is not checking in">Agent offline</span>'
      ].join('');
      const actions = [];
      if (CAN_MANAGE && s.minutes_left !== null) actions.push('<button type="button" data-act="extend"><i class="fas fa-plus"></i> Time</button>');
      if (CAN_MANAGE) actions.push('<button type="button" data-act="move"><i class="fas fa-right-left"></i> Move</button>');
      if (CAN_CONTROL) actions.push('<button type="button" data-act="message" title="Show a message on the PC" aria-label="Message"><i class="fas fa-message"></i></button>');
      if (CAN_MANAGE) actions.push('<button type="button" data-act="cancel" title="Cancel without charge" aria-label="Cancel session"><i class="fas fa-ban"></i></button>');
      if (CAN_END) actions.push('<button type="button" data-act="end" class="end"><i class="fas fa-stop"></i> End</button>');
      return '<article class="ses-card' + (ending ? ' ending' : '') + '" data-id="' + s.session_id + '">'
        + '<header><span class="ses-station"><i class="fas fa-desktop"></i> ' + S.esc(s.station_code) + '</span><span class="ses-id">#' + s.session_id + '</span></header>'
        + '<div class="ses-customer">' + S.esc(s.customer_name) + '</div>'
        + '<div class="ses-meta">' + S.esc(s.station_name) + (s.staff_name ? ' · by ' + S.esc(s.staff_name) : '') + '</div>'
        + '<div class="ses-clock" data-clock>' + (left !== null ? S.clock(Math.max(0, left)) : S.clock(elapsed)) + '</div>'
        + '<div class="ses-clock-label">' + (left !== null ? (left <= 0 ? 'Time is up — ending' : 'left of ' + S.duration(s.booked_minutes)) : 'running · open session') + '</div>'
        + (pct !== null ? '<div class="ses-bar"><i style="width:' + pct.toFixed(1) + '%"></i></div>' : '<div class="ses-bar open"><i></i></div>')
        + '<div class="ses-row"><span>' + tags + '</span><strong data-charge>' + S.money(charge) + '</strong></div>'
        + '<div class="ses-actions">' + actions.join('') + '</div>'
        + '</article>';
    }

    function render() {
      $('board').innerHTML = sessions.length
        ? sessions.map(card).join('')
        : '<div class="ses-empty"><i class="fas fa-mug-hot"></i><p>No active sessions.</p>' + (CAN_MANAGE ? '<button class="btn btn-primary" type="button" data-start>Start a session</button>' : '') + '</div>';
    }

    // Tick countdowns every second without re-rendering the cards.
    setInterval(() => {
      document.querySelectorAll('.ses-card').forEach((node) => {
        const s = sessions.find((x) => Number(x.session_id) === Number(node.dataset.id));
        if (!s) return;
        const left = liveLeftSeconds(s);
        const elapsed = liveElapsedSeconds(s);
        node.querySelector('[data-clock]').textContent = left !== null ? S.clock(Math.max(0, left)) : S.clock(elapsed);
        node.classList.toggle('ending', left !== null && left <= 600);
        if (!s.prepaid) node.querySelector('[data-charge]').textContent = S.money(elapsed / 3600 * rate);
        if (left !== null && left <= -20) load(); // the server ends expired sessions on the next request
      });
    }, 1000);

    $('board').addEventListener('click', async (event) => {
      if (event.target.closest('[data-start]')) { S.openStart(load); return; }
      const button = event.target.closest('[data-act]');
      if (!button) return;
      const s = sessions.find((x) => Number(x.session_id) === Number(button.closest('.ses-card').dataset.id));
      if (!s) return;
      const act = button.dataset.act;
      if (act === 'end') S.openEnd(s, rate, liveElapsedSeconds(s), load);
      else if (act === 'extend') S.openExtend(s, rate, load);
      else if (act === 'move') S.openMove(s, load);
      else if (act === 'message') {
        const {ok, text} = await CtlDialog.deviceCommand('message', s.station_code + ' · ' + s.station_name);
        if (!ok) return;
        try {
          const result = await S.request('api/devices.php', {method: 'POST', body: JSON.stringify({device: 'station:' + s.station_id, command: 'message', text})});
          S.notify(result.message, result.data && result.data.status === 'queued' ? 'warning' : 'success');
        } catch (error) { S.notify(error.message, 'error'); }
      } else if (act === 'cancel') {
        const reason = await CtlDialog.prompt({
          title: 'Cancel session #' + s.session_id + '?',
          message: 'The session stops without a charge and ' + s.station_code + ' is freed.' + (s.prepaid ? ' Prepaid time is not refunded automatically.' : ''),
          label: 'Reason', presets: ['Started by mistake', 'Wrong station', 'Customer left before using it'],
          icon: 'fa-ban', tone: 'danger', confirmText: 'Cancel session', cancelText: 'Keep it'
        });
        if (reason === null) return;
        try {
          const result = await S.request('api/sessions.php', {method: 'POST', body: JSON.stringify({action: 'cancel', session_id: s.session_id, reason})});
          S.notify(result.message);
          load();
        } catch (error) { S.notify(error.message, 'error'); }
      }
    });

    const startBtn = $('startBtn');
    if (startBtn) startBtn.addEventListener('click', () => S.openStart(load));

    load();
    setInterval(() => { if (!document.hidden && !S.modalOpen()) load(); }, 15000);
  })();
  </script>
</body>
</html>
