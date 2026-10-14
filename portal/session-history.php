<?php
/**
 * Session History — finished and cancelled sessions with filters, totals and payment collection.
 */

require_once __DIR__ . '/../includes/auth.php';
requireStaffAuth();
requirePermission('sessions.view');

$canManage = hasPermission('sessions.manage');
$stations = Database::fetchAll('SELECT station_id, station_code, name FROM stations ORDER BY station_code');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Session History — CTECHLIT & SYSTEMS</title>
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
          <h1>Session History</h1>
          <p>Finished and cancelled station sessions</p>
        </div>
        <nav class="ses-tabs" aria-label="Sessions views">
          <a href="sessions.php"><i class="fas fa-bolt"></i> Live</a>
          <a href="session-history.php" aria-current="page"><i class="fas fa-clock-rotate-left"></i> History</a>
        </nav>
        <button class="btn btn-secondary" id="exportBtn" type="button"><i class="fas fa-file-csv"></i> Export CSV</button>
      </div>

      <div class="ses-stats">
        <div class="ses-stat"><span>Sessions</span><strong id="tCount">—</strong></div>
        <div class="ses-stat"><span>Total time</span><strong id="tTime">—</strong></div>
        <div class="ses-stat ok"><span>Collected</span><strong id="tPaid">—</strong></div>
        <div class="ses-stat bad"><span>Still unpaid</span><strong id="tOwed">—</strong></div>
      </div>

      <div class="card glass-card">
        <form class="hist-filters" id="filters">
          <div style="flex:1 1 200px"><label for="fSearch">Search</label><input class="form-input" id="fSearch" type="search" placeholder="Customer, phone, station or session #"></div>
          <div><label for="fStation">Station</label><select class="form-input" id="fStation"><option value="">All stations</option>
            <?php foreach ($stations as $s): ?><option value="<?php echo (int) $s['station_id']; ?>"><?php echo sanitize($s['station_code'] . ' — ' . $s['name']); ?></option><?php endforeach; ?>
          </select></div>
          <div><label for="fStatus">Status</label><select class="form-input" id="fStatus"><option value="">All</option><option value="completed">Completed</option><option value="cancelled">Cancelled</option></select></div>
          <div><label for="fFrom">From</label><input class="form-input" id="fFrom" type="date"></div>
          <div><label for="fTo">To</label><input class="form-input" id="fTo" type="date"></div>
          <div><button class="btn btn-secondary small" type="button" id="fReset">Reset</button></div>
        </form>
        <div class="table-container" style="border:none;background:transparent">
          <table class="data-table">
            <thead><tr><th>Session</th><th>Customer</th><th>Station</th><th>Started</th><th>Duration</th><th>Paid</th><th>Unpaid</th><th>Status</th><th><span class="sr-only">Actions</span></th></tr></thead>
            <tbody id="histTable"><tr><td colspan="9" style="text-align:center;color:var(--muted);padding:28px">Loading…</td></tr></tbody>
          </table>
        </div>
        <div class="hist-pager">
          <span id="pageInfo">—</span>
          <div style="display:flex;gap:6px">
            <button class="btn btn-secondary small" type="button" id="prevPage"><i class="fas fa-chevron-left"></i> Previous</button>
            <button class="btn btn-secondary small" type="button" id="nextPage">Next <i class="fas fa-chevron-right"></i></button>
          </div>
        </div>
      </div>
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
    const S = window.SessionsUI;
    const $ = (id) => document.getElementById(id);
    let page = 1;
    let pages = 1;
    let rows = [];
    const fmt = (v) => v ? new Date(String(v).replace(' ', 'T')).toLocaleString('en-GB', {day: '2-digit', month: 'short', hour: '2-digit', minute: '2-digit'}) : '—';

    function query(extra) {
      const params = new URLSearchParams({history: '1', page: String(page)});
      const map = {search: 'fSearch', station_id: 'fStation', status: 'fStatus', from: 'fFrom', to: 'fTo'};
      Object.entries(map).forEach(([key, id]) => { const value = $(id).value.trim(); if (value) params.set(key, value); });
      Object.entries(extra || {}).forEach(([k, v]) => params.set(k, v));
      return 'api/sessions.php?' + params.toString();
    }

    async function load() {
      try {
        const {data} = await S.request(query());
        rows = data.sessions;
        pages = data.pages;
        $('tCount').textContent = data.total.toLocaleString();
        $('tTime').textContent = S.duration(data.totals.minutes);
        $('tPaid').textContent = S.money(data.totals.paid);
        $('tOwed').textContent = S.money(data.totals.owed);
        $('pageInfo').textContent = data.total ? 'Page ' + data.page + ' of ' + data.pages + ' · ' + data.total + ' sessions' : 'No sessions match these filters';
        $('prevPage').disabled = page <= 1;
        $('nextPage').disabled = page >= pages;
        $('histTable').innerHTML = rows.length ? rows.map((s) => {
          const owed = Number(s.owed_amount);
          return '<tr data-id="' + s.session_id + '">'
            + '<td><strong style="color:var(--violet)">#' + s.session_id + '</strong></td>'
            + '<td>' + S.esc(s.customer_name) + (s.customer_phone ? '<div style="font-size:11.5px;color:var(--muted)">' + S.esc(s.customer_phone) + '</div>' : '') + '</td>'
            + '<td>' + S.esc(s.station_code) + '</td>'
            + '<td>' + fmt(s.started_at) + '</td>'
            + '<td>' + S.duration(s.elapsed_minutes) + '</td>'
            + '<td class="amount">' + S.money(s.paid_amount) + '</td>'
            + '<td class="amount' + (owed > 0 ? ' owed' : '') + '">' + (owed > 0 ? S.money(owed) : '—') + '</td>'
            + '<td><span class="status-badge ' + (s.status === 'completed' ? 'success' : 'error') + '"><span class="status-dot"></span>' + S.esc(s.status) + '</span></td>'
            + '<td style="white-space:nowrap">'
            + (CAN_MANAGE && owed > 0 && s.pending_transaction_id ? '<button class="btn btn-primary small" type="button" data-act="settle"><i class="fas fa-hand-holding-dollar"></i> Collect</button> ' : '')
            + (owed > 0 && !s.pending_transaction_id ? '<a class="btn btn-secondary small" href="billing.php?user_id=' + s.user_id + '" title="On an invoice">Invoice</a> ' : '')
            + '<button class="header-btn" type="button" data-act="view" title="Details" aria-label="Session details" style="width:32px;height:32px"><i class="fas fa-eye"></i></button>'
            + '</td></tr>';
        }).join('') : '<tr><td colspan="9" style="text-align:center;color:var(--muted);padding:28px">No sessions match these filters.</td></tr>';
      } catch (error) {
        $('histTable').innerHTML = '<tr><td colspan="9" style="text-align:center;color:var(--red);padding:28px">' + S.esc(error.message) + '</td></tr>';
      }
    }

    $('histTable').addEventListener('click', (event) => {
      const button = event.target.closest('[data-act]');
      if (!button) return;
      const s = rows.find((x) => Number(x.session_id) === Number(button.closest('tr').dataset.id));
      if (button.dataset.act === 'settle') S.openSettle(s, load);
      if (button.dataset.act === 'view') {
        CtlDialog.alert({
          title: 'Session #' + s.session_id,
          message: s.customer_name + ' on ' + s.station_code + ' (' + s.station_name + ')',
          detail: 'Started ' + fmt(s.started_at) + ' · ended ' + fmt(s.ended_at) + ' · ' + S.duration(s.elapsed_minutes)
            + '. Paid ' + S.money(s.paid_amount) + (Number(s.owed_amount) > 0 ? ', unpaid ' + S.money(s.owed_amount) : '')
            + (s.staff_name ? '. Started by ' + s.staff_name : '') + (s.notes ? '. Notes: ' + s.notes : '') + '.',
          icon: 'fa-clock-rotate-left', tone: 'info', confirmText: 'Close'
        });
      }
    });

    let timer = null;
    $('filters').addEventListener('input', () => { clearTimeout(timer); timer = setTimeout(() => { page = 1; load(); }, 250); });
    $('filters').addEventListener('submit', (e) => e.preventDefault());
    $('fReset').addEventListener('click', () => { $('filters').reset(); page = 1; load(); });
    $('prevPage').addEventListener('click', () => { if (page > 1) { page--; load(); } });
    $('nextPage').addEventListener('click', () => { if (page < pages) { page++; load(); } });

    $('exportBtn').addEventListener('click', async () => {
      const button = $('exportBtn');
      button.disabled = true;
      try {
        const all = [];
        for (let p = 1; p <= 40; p++) {
          const {data} = await S.request(query({page: String(p)}));
          all.push(...data.sessions);
          if (p >= data.pages) break;
        }
        const cell = (v) => '"' + String(v == null ? '' : v).replace(/"/g, '""') + '"';
        const csv = [['Session', 'Customer', 'Phone', 'Station', 'Started', 'Ended', 'Minutes', 'Paid', 'Unpaid', 'Status', 'Staff']]
          .concat(all.map((s) => [s.session_id, s.customer_name, s.customer_phone, s.station_code, s.started_at, s.ended_at, s.elapsed_minutes, s.paid_amount, s.owed_amount, s.status, s.staff_name]))
          .map((r) => r.map(cell).join(',')).join('\r\n');
        const link = document.createElement('a');
        link.href = URL.createObjectURL(new Blob(['﻿' + csv], {type: 'text/csv'}));
        link.download = 'session-history-' + new Date().toISOString().slice(0, 10) + '.csv';
        link.click();
        setTimeout(() => URL.revokeObjectURL(link.href), 2000);
      } catch (error) {
        S.notify(error.message, 'error');
      } finally {
        button.disabled = false;
      }
    });

    load();
  })();
  </script>
</body>
</html>
