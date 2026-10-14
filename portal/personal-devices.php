<?php
/**
 * CTECHLIT & SYSTEMS — Personal Network Devices
 * BYOD / hotspot devices with paid time, wallet auto-renew and access control.
 */

require_once __DIR__ . '/../includes/auth.php';
requireStaffAuth();
requirePermission('personal_devices.view');

$canManage = hasPermission('personal_devices.manage');
$canTopUp = hasPermission('billing.manage') || hasPermission('pos.manage');
$customers = Database::fetchAll('SELECT user_id, full_name, phone FROM users WHERE is_active = 1 ORDER BY full_name');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Personal Devices — CTECHLIT & SYSTEMS</title>
  <link rel="icon" type="image/svg+xml" href="../assets/images/logo-icon.svg">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="stylesheet" href="../assets/css/portal.css">
  <style>
    .pd-toolbar { display: flex; gap: 8px; flex-wrap: wrap; align-items: center; justify-content: flex-end; }
    .pd-toolbar .btn { padding: 9px 14px; font-size: 13px; }
    .pd-filter { display: flex; gap: 4px; padding: 4px; border-radius: 10px; background: rgba(148,163,184,0.14); }
    .pd-filter button { padding: 6px 12px; border: 0; border-radius: 8px; background: transparent; font: 600 12.5px Inter, sans-serif; color: var(--ink-secondary); cursor: pointer; }
    .pd-filter button[aria-pressed="true"] { background: #fff; color: var(--violet); box-shadow: 0 2px 6px rgba(15,23,42,0.08); }
    .time-bar { width: 110px; height: 6px; border-radius: 4px; background: rgba(148,163,184,0.25); overflow: hidden; margin-top: 5px; }
    .time-bar i { display: block; height: 100%; border-radius: 4px; background: linear-gradient(90deg, #10B981, #06B6D4); }
    .time-bar.low i { background: linear-gradient(90deg, #F59E0B, #EF4444); }
    .muted { font-size: 11.5px; color: var(--muted); }
    .switch { position: relative; display: inline-block; width: 36px; height: 20px; }
    .switch input { opacity: 0; width: 0; height: 0; }
    .switch span { position: absolute; inset: 0; border-radius: 20px; background: #CBD5E1; transition: 0.2s; cursor: pointer; }
    .switch span::before { content: ''; position: absolute; left: 2px; top: 2px; width: 16px; height: 16px; border-radius: 50%; background: #fff; transition: 0.2s; }
    .switch input:checked + span { background: var(--violet); }
    .switch input:checked + span::before { transform: translateX(16px); }
    .switch input:focus-visible + span { outline: 2px solid var(--violet); outline-offset: 2px; }
    .pd-form { display: grid; gap: 14px; }
    .pd-form .row2 { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
    .pd-methods { display: grid; grid-template-columns: repeat(3, 1fr); gap: 6px; }
    .pd-methods label { display: flex; align-items: center; justify-content: center; gap: 6px; padding: 8px 4px; border: 1px solid rgba(148,163,184,0.35); border-radius: 10px; font-size: 12.5px; font-weight: 600; cursor: pointer; }
    .pd-methods input { position: absolute; opacity: 0; }
    .pd-methods label:has(input:checked) { border-color: var(--violet); color: var(--violet); background: rgba(124,58,237,0.07); }
    .pd-methods label:has(input:disabled) { opacity: 0.4; cursor: not-allowed; }
    .pd-summary { padding: 10px 12px; border-radius: 10px; background: rgba(124,58,237,0.07); font-size: 13px; }
    .pd-history { max-height: 340px; overflow-y: auto; }
    .pd-history div { padding: 8px 0; border-bottom: 1px dashed rgba(148,163,184,0.3); font-size: 13px; }
  </style>
</head>
<body>
  <?php include_once __DIR__ . '/../includes/sidebar.php'; ?>
  <main class="portal-main" style="margin-left: var(--sidebar-width);">
    <?php include_once __DIR__ . '/../includes/header.php'; ?>

    <div class="portal-content">
      <div class="kpi-grid" style="margin-bottom: 24px;">
        <div class="kpi-card" style="--kpi-color: var(--violet); --kpi-bg: rgba(124,58,237,0.1);"><div class="kpi-header"><div class="kpi-icon"><i class="fas fa-mobile-alt"></i></div></div><div class="kpi-value" id="kTotal">—</div><div class="kpi-label">Registered devices</div></div>
        <div class="kpi-card" style="--kpi-color: var(--green); --kpi-bg: rgba(16,185,129,0.1);"><div class="kpi-header"><div class="kpi-icon"><i class="fas fa-wifi"></i></div></div><div class="kpi-value" id="kOnline">—</div><div class="kpi-label">Online now</div></div>
        <div class="kpi-card" style="--kpi-color: var(--amber); --kpi-bg: rgba(245,158,11,0.1);"><div class="kpi-header"><div class="kpi-icon"><i class="fas fa-hourglass-end"></i></div></div><div class="kpi-value" id="kLow">—</div><div class="kpi-label">Online, ≤ 10 min left</div></div>
        <div class="kpi-card" style="--kpi-color: var(--red); --kpi-bg: rgba(239,68,68,0.1);"><div class="kpi-header"><div class="kpi-icon"><i class="fas fa-ban"></i></div></div><div class="kpi-value" id="kBlocked">—</div><div class="kpi-label">Blocked</div></div>
      </div>

      <div class="card glass-card">
        <div class="card-header">
          <div>
            <div class="card-title">Personal Network Devices</div>
            <div class="card-subtitle">Time is metered live and paid through customer billing. Updated <span id="updated">—</span></div>
          </div>
          <div class="pd-toolbar">
            <div class="pd-filter" role="group" aria-label="Filter devices">
              <button data-filter="all" aria-pressed="true">All</button>
              <button data-filter="online" aria-pressed="false">Online</button>
              <button data-filter="offline" aria-pressed="false">Offline</button>
              <button data-filter="blocked" aria-pressed="false">Blocked</button>
            </div>
            <label class="sr-only" for="pdSearch">Search devices</label>
            <input class="form-input" id="pdSearch" type="search" placeholder="Search customer, device, MAC" style="width: 220px; padding: 8px 12px;">
            <?php if ($canManage): ?>
            <button class="btn btn-primary" id="registerBtn" type="button"><i class="fas fa-plus"></i> Register device</button>
            <?php endif; ?>
          </div>
        </div>
        <div class="table-container" style="border: none; background: transparent;">
          <table class="data-table">
            <thead>
              <tr><th>Customer</th><th>Device</th><th>Network</th><th>Time left</th><th>Data</th><th>Status</th><th>Auto-renew</th><th><span class="sr-only">Actions</span></th></tr>
            </thead>
            <tbody id="pdTable"><tr><td colspan="8" style="text-align:center;padding:30px;color:var(--muted)">Loading devices…</td></tr></tbody>
          </table>
        </div>
      </div>
    </div>
  </main>

  <div class="modal-overlay" id="pdModal" role="dialog" aria-modal="true" aria-labelledby="pdModalTitle">
    <div class="modal">
      <div class="modal-header">
        <h3 class="modal-title" id="pdModalTitle">Device</h3>
        <button class="modal-close" type="button" data-close aria-label="Close"><i class="fas fa-times"></i></button>
      </div>
      <div class="modal-body" id="pdModalBody"></div>
      <div class="modal-footer" id="pdModalFooter"></div>
    </div>
  </div>

  <?php include_once __DIR__ . '/../includes/modals.php'; ?>
  <script src="../assets/js/modal-controller.js"></script>
  <script>
  (function () {
    const API = 'api/personal-devices.php';
    const CAN_MANAGE = <?php echo $canManage ? 'true' : 'false'; ?>;
    const CAN_TOPUP = <?php echo $canTopUp ? 'true' : 'false'; ?>;
    const CUSTOMERS = <?php echo json_encode(array_map(fn($c) => ['id' => (int) $c['user_id'], 'name' => $c['full_name'], 'phone' => $c['phone']], $customers)); ?>;
    const CURRENCY = window.CTL_CURRENCY || 'GHS';
    const state = {devices: [], plans: [], filter: 'all', search: ''};

    const money = (v) => CURRENCY + ' ' + Number(v || 0).toLocaleString('en-GH', {minimumFractionDigits: 2, maximumFractionDigits: 2});
    const esc = (v) => { const d = document.createElement('div'); d.textContent = v == null ? '' : String(v); return d.innerHTML; };
    const $ = (id) => document.getElementById(id);
    const duration = (m) => { m = Number(m || 0); return m >= 60 ? Math.floor(m / 60) + ' h ' + (m % 60 ? (m % 60) + ' min' : '') : m + ' min'; };
    const notify = (message, type) => ModalController.showNotification(message, type || 'success');

    async function request(url, options) {
      const response = await fetch(url, Object.assign({credentials: 'same-origin', headers: {'Accept': 'application/json', 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest'}}, options || {}));
      let result = {};
      try { result = await response.json(); } catch (e) { /* ignore */ }
      if (!response.ok || !result.success) throw new Error(result.message || result.error || 'Request failed (HTTP ' + response.status + ')');
      return result;
    }
    const post = (payload) => request(API, {method: 'POST', body: JSON.stringify(payload)});

    async function load() {
      try {
        const {data} = await request(API + '?list=1');
        state.devices = data.devices;
        state.plans = data.plans;
        $('kTotal').textContent = data.stats.total;
        $('kOnline').textContent = data.stats.online;
        $('kLow').textContent = data.stats.low_time;
        $('kBlocked').textContent = data.stats.blocked;
        $('updated').textContent = new Date().toLocaleTimeString([], {hour: '2-digit', minute: '2-digit', second: '2-digit'});
        render();
      } catch (error) {
        $('pdTable').innerHTML = '<tr><td colspan="8" style="text-align:center;padding:30px;color:var(--red)">' + esc(error.message) + '</td></tr>';
      }
    }

    function render() {
      const query = state.search.toLowerCase();
      const rows = state.devices.filter((d) => (state.filter === 'all' || d.status === state.filter)
        && (!query || [d.user_name, d.device_name, d.mac_address, d.ip_address, d.user_phone].some((v) => String(v || '').toLowerCase().includes(query))));
      if (!rows.length) {
        $('pdTable').innerHTML = '<tr><td colspan="8" style="text-align:center;padding:30px;color:var(--muted)">' + (state.devices.length ? 'No devices match.' : 'No personal devices registered yet.') + '</td></tr>';
        return;
      }
      $('pdTable').innerHTML = rows.map((d) => {
        const left = Number(d.minutes_remaining || 0);
        const bought = Math.max(left, Number(d.minutes_purchased || 0), 1);
        const low = left <= 10;
        const badge = {online: 'success', offline: 'info', blocked: 'error', suspended: 'warning'}[d.status] || 'info';
        const actions = [];
        if (CAN_MANAGE && d.status !== 'blocked') actions.push('<button class="header-btn" data-act="buy" title="Sell time" aria-label="Sell time" style="color:var(--green)"><i class="fas fa-cart-plus"></i></button>');
        if (CAN_MANAGE && d.status === 'offline') actions.push('<button class="header-btn" data-act="connect" title="Connect" aria-label="Connect" style="color:var(--cyan)"><i class="fas fa-wifi"></i></button>');
        if (CAN_MANAGE && d.status === 'online') actions.push('<button class="header-btn" data-act="disconnect" title="Disconnect" aria-label="Disconnect" style="color:var(--amber)"><i class="fas fa-power-off"></i></button>');
        if (CAN_MANAGE && d.status === 'online') actions.push('<button class="header-btn" data-act="speed" title="Speed limit" aria-label="Speed limit"><i class="fas fa-gauge-high"></i></button>');
        if (CAN_TOPUP) actions.push('<button class="header-btn" data-act="topup" title="Top up wallet" aria-label="Top up wallet" style="color:var(--violet)"><i class="fas fa-wallet"></i></button>');
        if (CAN_MANAGE) actions.push(d.status === 'blocked'
          ? '<button class="header-btn" data-act="unblock" title="Unblock" aria-label="Unblock" style="color:var(--green)"><i class="fas fa-check"></i></button>'
          : '<button class="header-btn" data-act="block" title="Block" aria-label="Block" style="color:var(--red)"><i class="fas fa-ban"></i></button>');
        actions.push('<button class="header-btn" data-act="history" title="History" aria-label="History"><i class="fas fa-clock-rotate-left"></i></button>');
        return '<tr data-id="' + d.device_id + '">'
          + '<td><strong>' + esc(d.user_name) + '</strong><div class="muted">Wallet ' + money(d.wallet_balance) + (d.user_phone ? ' · ' + esc(d.user_phone) : '') + '</div></td>'
          + '<td><strong>' + esc(d.device_name) + '</strong><div class="muted" style="text-transform:capitalize">' + esc(d.device_type) + (d.plan_name ? ' · ' + esc(d.plan_name) : '') + '</div></td>'
          + '<td><code style="font-size:11px">' + esc(d.mac_address) + '</code><div class="muted">' + esc(d.ip_address || 'No IP') + '</div></td>'
          + '<td><strong style="color:' + (left <= 0 ? 'var(--red)' : low ? 'var(--amber)' : 'var(--ink)') + '">' + (left > 0 ? duration(left) : 'No time') + '</strong>'
          + '<div class="time-bar' + (low ? ' low' : '') + '"><i style="width:' + Math.min(100, Math.round(left / bought * 100)) + '%"></i></div></td>'
          + '<td>' + Number(d.data_used_mb || 0).toLocaleString() + ' MB' + (d.speed_limit_kbps ? '<div class="muted">≤ ' + Number(d.speed_limit_kbps) + ' kbps</div>' : '') + '</td>'
          + '<td><span class="status-badge ' + badge + '"><span class="status-dot"></span>' + esc(d.status) + '</span></td>'
          + '<td><label class="switch" title="Buy the same plan from the wallet when time runs out"><input type="checkbox" data-act="autorenew" aria-label="Auto-renew ' + esc(d.device_name) + '"' + (Number(d.auto_renew) ? ' checked' : '') + (CAN_MANAGE ? '' : ' disabled') + '><span></span></label></td>'
          + '<td><div style="display:flex;gap:6px;flex-wrap:wrap">' + actions.join('') + '</div></td></tr>';
      }).join('');
    }

    // ---------- Modal helpers ----------
    const modal = $('pdModal');
    function openModal(title, body, footer) {
      $('pdModalTitle').textContent = title;
      $('pdModalBody').innerHTML = body;
      $('pdModalFooter').innerHTML = footer;
      modal.classList.add('active');
      document.body.style.overflow = 'hidden';
      const first = modal.querySelector('input:not([type=radio]), select');
      if (first) first.focus();
    }
    function closeModal() { modal.classList.remove('active'); document.body.style.overflow = ''; }
    modal.addEventListener('click', (e) => { if (e.target === modal || e.target.closest('[data-close]')) closeModal(); });
    document.addEventListener('keydown', (e) => { if (e.key === 'Escape' && modal.classList.contains('active')) closeModal(); });

    function planOptions(selected) {
      return state.plans.map((p) => '<option value="' + p.plan_id + '"' + (Number(selected) === Number(p.plan_id) ? ' selected' : '') + ' data-price="' + p.price + '" data-minutes="' + p.duration_minutes + '">' + esc(p.name) + ' — ' + duration(p.duration_minutes) + ' / ' + money(p.price) + '</option>').join('');
    }
    function paymentFields(walletBalance) {
      const methods = [['cash', 'fa-money-bill-wave', 'Cash'], ['mobile_money', 'fa-mobile-screen', 'MoMo'], ['card', 'fa-credit-card', 'Card'], ['bank_transfer', 'fa-building-columns', 'Bank'], ['wallet', 'fa-wallet', 'Wallet'], ['account', 'fa-file-invoice', 'On account']];
      return '<div><span class="form-label">Payment</span><div class="pd-methods">' + methods.map(([value, icon, label], i) => '<label><input type="radio" name="pdMethod" value="' + value + '"' + (i === 0 ? ' checked' : '') + '><i class="fas ' + icon + '"></i>' + label + '</label>').join('') + '</div>'
        + '<div class="muted" id="pdWalletNote" style="margin-top:6px">' + (walletBalance != null ? 'Wallet balance: ' + money(walletBalance) : '') + '</div></div>'
        + '<div><label class="form-label" for="pdRef">Reference (MoMo/bank ID, optional)</label><input class="form-input" id="pdRef" maxlength="100"></div>';
    }
    function selectedMethod() { const checked = modal.querySelector('input[name=pdMethod]:checked'); return checked ? checked.value : 'cash'; }
    function bindTotal(qtyId) {
      const update = () => {
        const option = $('pdPlan').selectedOptions[0];
        const qty = qtyId ? Math.max(1, Number($(qtyId).value) || 1) : 1;
        $('pdTotal').textContent = option ? duration(option.dataset.minutes * qty) + ' for ' + money(option.dataset.price * qty) : 'Choose a plan';
      };
      $('pdPlan').addEventListener('change', update);
      if (qtyId) $(qtyId).addEventListener('input', update);
      update();
    }

    // ---------- Register ----------
    const registerBtn = $('registerBtn');
    if (registerBtn) registerBtn.addEventListener('click', () => {
      if (!state.plans.length) { notify('Create an active pricing plan with minutes first (Services → pricing plans).', 'error'); return; }
      openModal('Register personal device',
        '<div class="pd-form">'
        + '<div><label class="form-label" for="pdCustomer">Customer</label><select class="form-input" id="pdCustomer"><option value="">Choose customer…</option>' + CUSTOMERS.map((c) => '<option value="' + c.id + '">' + esc(c.name + (c.phone ? ' (' + c.phone + ')' : '')) + '</option>').join('') + '</select></div>'
        + '<div class="row2"><div><label class="form-label" for="pdName">Device name</label><input class="form-input" id="pdName" maxlength="100" placeholder="e.g. Ama’s iPhone"></div>'
        + '<div><label class="form-label" for="pdType">Type</label><select class="form-input" id="pdType"><option value="phone">Phone</option><option value="laptop">Laptop</option><option value="tablet">Tablet</option><option value="other">Other</option></select></div></div>'
        + '<div><label class="form-label" for="pdMac">MAC address</label><input class="form-input" id="pdMac" placeholder="AA:BB:CC:DD:EE:FF" autocomplete="off"></div>'
        + '<div><label class="form-label" for="pdPlan">Plan</label><select class="form-input" id="pdPlan">' + planOptions() + '</select></div>'
        + '<div class="pd-summary" id="pdTotal"></div>'
        + paymentFields(null)
        + '<label style="display:flex;gap:8px;align-items:center;font-size:13px"><input type="checkbox" id="pdAuto"> Auto-renew this plan from the wallet when time runs out</label>'
        + '</div>',
        '<button class="btn btn-secondary" type="button" data-close>Cancel</button><button class="btn btn-primary" type="button" id="pdSubmit">Register &amp; charge</button>');
      bindTotal(null);
      $('pdSubmit').addEventListener('click', async (event) => {
        event.target.disabled = true;
        try {
          const result = await post({action: 'register', user_id: Number($('pdCustomer').value), device_name: $('pdName').value.trim(), device_type: $('pdType').value, mac_address: $('pdMac').value.trim(), plan_id: Number($('pdPlan').value), payment_method: selectedMethod(), reference: $('pdRef').value, auto_renew: $('pdAuto').checked});
          closeModal();
          notify(result.message);
          load();
        } catch (error) { notify(error.message, 'error'); } finally { event.target.disabled = false; }
      });
    });

    // ---------- Row actions ----------
    $('pdTable').addEventListener('change', async (event) => {
      if (event.target.dataset.act !== 'autorenew') return;
      const id = Number(event.target.closest('tr').dataset.id);
      try {
        const result = await post({action: 'set_auto_renew', device_id: id, auto_renew: event.target.checked});
        notify(result.message);
      } catch (error) { event.target.checked = !event.target.checked; notify(error.message, 'error'); }
    });

    $('pdTable').addEventListener('click', async (event) => {
      const button = event.target.closest('button[data-act]');
      if (!button) return;
      const device = state.devices.find((d) => Number(d.device_id) === Number(button.closest('tr').dataset.id));
      const act = button.dataset.act;
      const simple = async (payload, confirmCommand) => {
        if (confirmCommand && !(await CtlDialog.deviceCommand(confirmCommand, device.device_name)).ok) return;
        button.disabled = true;
        try { const result = await post(payload); notify(result.message); load(); }
        catch (error) { notify(error.message, error.message.includes('No time left') ? 'warning' : 'error'); }
        finally { button.disabled = false; }
      };

      if (act === 'buy') {
        openModal('Sell time — ' + device.device_name,
          '<div class="pd-form"><div class="pd-summary">' + esc(device.user_name) + ' · ' + (Number(device.minutes_remaining) > 0 ? duration(device.minutes_remaining) + ' left' : 'no time left') + '</div>'
          + '<div class="row2"><div><label class="form-label" for="pdPlan">Plan</label><select class="form-input" id="pdPlan">' + planOptions(device.plan_id) + '</select></div>'
          + '<div><label class="form-label" for="pdQty">Quantity</label><input class="form-input" id="pdQty" type="number" min="1" max="24" value="1"></div></div>'
          + '<div class="pd-summary" id="pdTotal"></div>' + paymentFields(device.wallet_balance) + '</div>',
          '<button class="btn btn-secondary" type="button" data-close>Cancel</button><button class="btn btn-primary" type="button" id="pdSubmit">Charge &amp; add time</button>');
        bindTotal('pdQty');
        $('pdSubmit').addEventListener('click', async (e) => {
          e.target.disabled = true;
          try {
            const result = await post({action: 'buy_time', device_id: device.device_id, plan_id: Number($('pdPlan').value), quantity: Number($('pdQty').value), payment_method: selectedMethod(), reference: $('pdRef').value});
            closeModal();
            notify(result.message);
            load();
          } catch (error) { notify(error.message, 'error'); } finally { e.target.disabled = false; }
        });
      } else if (act === 'connect') {
        const ip = await CtlDialog.prompt({
          title: 'Connect ' + device.device_name,
          message: duration(device.minutes_remaining) + ' of paid time will start counting down.',
          label: 'Device IP address (optional)',
          value: device.ip_address || '',
          placeholder: '192.168.1.150',
          inputMode: 'decimal',
          required: false,
          icon: 'fa-wifi',
          tone: 'success',
          confirmText: 'Connect',
          validate: (v) => v === '' || /^(\d{1,3}\.){3}\d{1,3}$/.test(v) ? '' : 'Enter an IPv4 address like 192.168.1.150, or leave it empty.'
        });
        if (ip === null) return;
        simple({action: 'connect', device_id: device.device_id, ip_address: ip});
      } else if (act === 'disconnect') {
        simple({action: 'disconnect', device_id: device.device_id}, 'disconnect');
      } else if (act === 'block') {
        simple({action: 'block', device_id: device.device_id}, 'block');
      } else if (act === 'unblock') {
        simple({action: 'unblock', device_id: device.device_id});
      } else if (act === 'speed') {
        const value = await CtlDialog.prompt({
          title: 'Speed limit — ' + device.device_name,
          message: 'Limit this device’s bandwidth. Use 0 for no limit.',
          label: 'Speed limit (kbps)',
          type: 'number',
          min: 0,
          value: device.speed_limit_kbps || '0',
          presets: [{label: 'No limit', value: '0'}, {label: '512 kbps', value: '512'}, {label: '1 Mbps', value: '1024'}, {label: '2 Mbps', value: '2048'}, {label: '5 Mbps', value: '5120'}],
          icon: 'fa-gauge-high',
          confirmText: 'Apply limit',
          validate: (v) => /^\d+$/.test(v) ? '' : 'Enter a whole number of kbps (0 for no limit).'
        });
        if (value === null) return;
        simple({action: 'set_speed', device_id: device.device_id, speed_limit_kbps: Number(value)});
      } else if (act === 'topup') {
        CtlPayments.openTopUp({userId: device.user_id, name: device.user_name, onDone: load});
      } else if (act === 'history') {
        try {
          const {data} = await request(API + '?history=' + device.device_id);
          openModal('History — ' + device.device_name, '<div class="pd-history">' + (data.length ? data.map((e) => '<div><strong style="text-transform:capitalize">' + esc(String(e.event_type).replace('_', ' ')) + '</strong> · ' + esc(e.notes || '') + '<div class="muted">' + esc(e.created_at) + ' · ' + esc(e.trigger_source) + (Number(e.minutes_consumed) ? ' · ' + e.minutes_consumed + ' min' : '') + '</div></div>').join('') : '<p class="muted">No activity yet.</p>') + '</div>', '<button class="btn btn-secondary" type="button" data-close>Close</button>');
        } catch (error) { notify(error.message, 'error'); }
      }
    });

    document.querySelectorAll('[data-filter]').forEach((button) => button.addEventListener('click', () => {
      state.filter = button.dataset.filter;
      document.querySelectorAll('[data-filter]').forEach((b) => b.setAttribute('aria-pressed', String(b === button)));
      render();
    }));
    $('pdSearch').addEventListener('input', (e) => { state.search = e.target.value.trim(); render(); });

    load();
    setInterval(() => { if (!document.hidden && !modal.classList.contains('active')) load(); }, 20000);
  })();
  </script>
</body>
</html>
