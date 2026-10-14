<?php
/**
 * Device Control Center — live status and remote control of every networked device.
 */

require_once __DIR__ . '/../includes/auth.php';
requireStaffAuth();
requirePermission('stations.view');

require_once __DIR__ . '/../includes/device-control.php';

$canControl = hasPermission('stations.control');
$canManageStations = hasPermission('stations.manage');
$controlMode = deviceControlMode();
$https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'
    || strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';
$detectedServerUrl = deviceEnv('AGENT_SERVER_URL') ?: ($https ? 'https' : 'http') . '://'
    . preg_replace('/[^A-Za-z0-9.:\-\[\]]/', '', (string) ($_SERVER['HTTP_HOST'] ?? '')) . rtrim(base_path('', false), '/');
$serverIsLocal = (bool) preg_match('#^https?://(localhost|127\.|\[::1\])#i', $detectedServerUrl);
$setupStations = Database::fetchAll('SELECT station_id, station_code, name, mac_address, last_heartbeat FROM stations ORDER BY station_code');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Device Control — CTECHLIT & SYSTEMS</title>
  <link rel="icon" type="image/svg+xml" href="../assets/images/logo-icon.svg">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="stylesheet" href="../assets/css/portal.css">
  <style>
    .dev-toolbar { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; }
    .dev-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 16px; }
    .dev-card { display: flex; flex-direction: column; gap: 12px; padding: 16px; border-radius: 16px; background: rgba(255,255,255,0.75); border: 1px solid rgba(148,163,184,0.22); }
    .dev-head { display: flex; gap: 12px; align-items: center; }
    .dev-icon { display: grid; place-items: center; width: 42px; height: 42px; border-radius: 12px; color: #fff; font-size: 17px; }
    .dev-name { font-weight: 700; color: var(--ink); }
    .dev-meta { font-size: 12px; color: var(--muted); }
    .dev-state { margin-left: auto; display: inline-flex; align-items: center; gap: 6px; font-size: 12px; font-weight: 600; }
    .dev-state i { width: 8px; height: 8px; border-radius: 50%; background: #94A3B8; }
    .dev-state.on { color: #059669; } .dev-state.on i { background: #10B981; box-shadow: 0 0 8px #10B981; }
    .dev-state.off { color: #64748B; }
    .dev-session { font-size: 12.5px; padding: 8px 10px; border-radius: 10px; background: rgba(245,158,11,0.1); color: #92400E; }
    .dev-actions { display: flex; flex-wrap: wrap; gap: 6px; }
    .dev-actions button { display: inline-flex; align-items: center; gap: 6px; padding: 7px 10px; border: 1px solid rgba(148,163,184,0.35); border-radius: 9px; background: #fff; font: 600 12px Inter, sans-serif; color: var(--ink-secondary); cursor: pointer; }
    .dev-actions button:hover:not(:disabled) { border-color: var(--violet); color: var(--violet); }
    .dev-actions button.danger:hover:not(:disabled) { border-color: #EF4444; color: #EF4444; }
    .dev-actions button:disabled { opacity: 0.5; cursor: progress; }
    .dev-result { font-size: 12px; min-height: 16px; }
    .dev-result.ok { color: #059669; } .dev-result.fail { color: #DC2626; } .dev-result.wait { color: #B45309; }
    .setup-box { font-size: 13px; color: var(--ink-secondary); line-height: 1.6; }
    .setup-box code, .setup-box pre { background: rgba(15,23,42,0.06); border-radius: 6px; padding: 2px 6px; font-size: 12px; }
    .setup-box pre { padding: 10px 12px; overflow-x: auto; white-space: pre-wrap; word-break: break-all; }
    .setup-box ol { padding-left: 18px; }
    .setup-form { display: grid; gap: 12px; margin-bottom: 14px; padding: 14px; border-radius: 14px; background: rgba(124,58,237,0.05); border: 1px solid rgba(124,58,237,0.15); }
    .setup-form .btn { justify-self: start; }
    .setup-check { display: flex; gap: 8px; align-items: flex-start; font-size: 12.5px; }
    .setup-check input { margin-top: 3px; }
    .setup-warn { color: #B45309; font-size: 12.5px; }
    .mode-badge { display: inline-flex; align-items: center; gap: 6px; padding: 6px 10px; border-radius: 999px; font-size: 12px; font-weight: 600; }
    .mode-badge.cloud { background: rgba(6,182,212,0.12); color: #0E7490; }
    .mode-badge.lan { background: rgba(16,185,129,0.12); color: #047857; }
  </style>
</head>
<body>
  <?php include_once __DIR__ . '/../includes/sidebar.php'; ?>
  <main class="portal-main" style="margin-left: var(--sidebar-width);">
    <?php include_once __DIR__ . '/../includes/header.php'; ?>
    <div class="portal-content">
      <div class="kpi-grid" style="margin-bottom: 24px;">
        <div class="kpi-card" style="--kpi-color: var(--violet); --kpi-bg: rgba(124,58,237,0.1);"><div class="kpi-header"><div class="kpi-icon"><i class="fas fa-network-wired"></i></div></div><div class="kpi-value" id="kTotal">—</div><div class="kpi-label">Devices</div></div>
        <div class="kpi-card" style="--kpi-color: var(--green); --kpi-bg: rgba(16,185,129,0.1);"><div class="kpi-header"><div class="kpi-icon"><i class="fas fa-signal"></i></div></div><div class="kpi-value" id="kOnline">—</div><div class="kpi-label">Online</div></div>
        <div class="kpi-card" style="--kpi-color: var(--cyan); --kpi-bg: rgba(6,182,212,0.1);"><div class="kpi-header"><div class="kpi-icon"><i class="fas fa-desktop"></i></div></div><div class="kpi-value" id="kAgents">—</div><div class="kpi-label">Station agents connected</div></div>
      </div>

      <div class="card glass-card" style="margin-bottom: 24px;">
        <div class="card-header">
          <div><div class="card-title">Devices on this network</div><div class="card-subtitle" id="updatedAt">Live status refreshes every 10 seconds</div></div>
          <div class="dev-toolbar">
            <?php if ($canControl): ?>
            <button class="btn btn-secondary small" data-bulk="wake"><i class="fas fa-power-off"></i> Wake all</button>
            <button class="btn btn-secondary small" data-bulk="message"><i class="fas fa-message"></i> Message all</button>
            <button class="btn btn-secondary small" data-bulk="lock"><i class="fas fa-lock"></i> Lock all</button>
            <button class="btn btn-danger small" data-bulk="shutdown"><i class="fas fa-plug-circle-xmark"></i> Shut down all</button>
            <?php endif; ?>
            <button class="btn btn-secondary small" id="refreshBtn" aria-label="Refresh"><i class="fas fa-rotate"></i></button>
          </div>
        </div>
        <div class="dev-grid" id="devGrid"><div class="dev-meta">Loading devices…</div></div>
      </div>

      <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(340px,1fr));gap:24px">
        <div class="card glass-card">
          <div class="card-header"><div><div class="card-title">Command history</div><div class="card-subtitle">Commands sent to station agents</div></div></div>
          <div class="table-container" style="border:none;background:transparent">
            <table class="data-table"><thead><tr><th>When</th><th>Station</th><th>Command</th><th>Result</th><th>By</th></tr></thead><tbody id="historyTable"></tbody></table>
          </div>
        </div>
        <div class="card glass-card">
          <div class="card-header">
            <div><div class="card-title">Connect a workstation</div><div class="card-subtitle">One-time setup on each PC</div></div>
            <span class="mode-badge <?php echo $controlMode; ?>" title="Set DEVICE_CONTROL_MODE in config/.env to override">
              <i class="fas <?php echo $controlMode === 'cloud' ? 'fa-cloud' : 'fa-house-signal'; ?>"></i>
              <?php echo $controlMode === 'cloud' ? 'Online server' : 'Café network server'; ?>
            </span>
          </div>
          <div class="setup-box">
            <p id="keyWarning" hidden class="setup-warn"><i class="fas fa-triangle-exclamation"></i> <strong>STATION_AGENT_API_KEY is not set in config/.env.</strong> Agents cannot connect until it is.</p>
            <?php if ($canManageStations): ?>
            <form id="agentForm" class="setup-form">
              <div>
                <label class="form-label" for="agentStation">Station</label>
                <select class="form-input" id="agentStation">
                  <?php foreach ($setupStations as $s): ?>
                    <option value="<?php echo (int) $s['station_id']; ?>" data-mac="<?php echo $s['mac_address'] ? '1' : ''; ?>">
                      <?php echo sanitize($s['station_code'] . ' — ' . $s['name'] . ($s['mac_address'] ? '' : ' (no MAC yet)') . ($s['last_heartbeat'] ? '' : ' · not connected')); ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div>
                <label class="form-label" for="agentServer">Server address the PC will use</label>
                <input class="form-input" id="agentServer" value="<?php echo sanitize($detectedServerUrl); ?>" spellcheck="false">
                <?php if ($serverIsLocal): ?>
                <div class="setup-warn" style="margin-top:6px"><i class="fas fa-circle-info"></i> You're browsing on localhost. Enter the public address, e.g. <code>https://yourdomain.com/systems</code>.</div>
                <?php endif; ?>
              </div>
              <label class="setup-check"><input type="checkbox" id="agentRelay"> Relay-only PC (front desk / always on): relays Wake-on-LAN and device checks, never blocks its own internet</label>
              <button class="btn btn-primary" type="submit"><i class="fas fa-download"></i> Download installer</button>
            </form>
            <?php endif; ?>
            <ol>
              <li>Register the PC under <a href="stations.php">Stations</a> with its MAC address (run <code>getmac /v</code> on the PC).</li>
              <li>Download its installer above, unzip it on that PC and double-click <strong>install.cmd</strong> (accept the Administrator prompt).</li>
              <li>The station shows <em>Online</em> here within a few seconds. Delete the downloaded folder afterwards — it contains the agent key.</li>
            </ol>
            <?php if ($controlMode === 'cloud'): ?>
            <p><strong>Online server:</strong> PCs only make outgoing HTTPS requests, so no router port forwarding is needed. Commands arrive within about 5 seconds. Wake-on-LAN and printer/console checks are relayed by a PC that is already on — keep one always-on PC (e.g. the front desk) installed as <em>relay-only</em>.</p>
            <?php else: ?>
            <p><strong>Café network server:</strong> commands are pushed instantly over TCP 8080 and Wake-on-LAN is broadcast by the server. Enable Wake-on-LAN in each PC's BIOS and network adapter.</p>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>
  </main>
  <?php include_once __DIR__ . '/../includes/modals.php'; ?>
  <script src="../assets/js/modal-controller.js"></script>
  <script>
  (function () {
    const CAN_CONTROL = <?php echo $canControl ? 'true' : 'false'; ?>;
    const TYPE = {station: ['fa-desktop', '#7C3AED'], printer: ['fa-print', '#0891B2'], game_console: ['fa-gamepad', '#DB2777'], router: ['fa-wifi', '#059669'], scanner: ['fa-file-image', '#0E7490'], other: ['fa-microchip', '#64748B']};
    const LABELS = {wake: ['fa-power-off', 'Wake'], ping: ['fa-satellite-dish', 'Ping'], lock: ['fa-lock', 'Lock'], message: ['fa-message', 'Message'], logoff: ['fa-right-from-bracket', 'Sign out'], restart: ['fa-rotate-right', 'Restart'], sleep: ['fa-moon', 'Sleep'], shutdown: ['fa-plug-circle-xmark', 'Shut down'], status: ['fa-chart-simple', 'Status']};
    const DANGER = ['shutdown', 'restart', 'logoff', 'sleep'];
    const esc = (v) => { const d = document.createElement('div'); d.textContent = v == null ? '' : String(v); return d.innerHTML; };
    const ago = (v) => { if (!v) return 'never'; const s = Math.round((Date.now() - new Date(v.replace(' ', 'T'))) / 1000); return s < 60 ? s + 's ago' : s < 3600 ? Math.round(s / 60) + ' min ago' : new Date(v.replace(' ', 'T')).toLocaleString('en-GB'); };

    async function request(url, options) {
      const response = await fetch(url, Object.assign({credentials: 'same-origin', headers: {'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'Content-Type': 'application/json'}}, options || {}));
      let result = {};
      try { result = await response.json(); } catch (e) { /* ignore */ }
      if (!response.ok || !result.success) throw new Error(result.message || result.error || 'Request failed (HTTP ' + response.status + ')');
      return result;
    }

    let latestDevices = [];

    async function load() {
      try {
        const {data} = await request('api/devices.php');
        latestDevices = data.devices;
        document.getElementById('kTotal').textContent = data.summary.total;
        document.getElementById('kOnline').textContent = data.summary.online;
        document.getElementById('kAgents').textContent = data.summary.agents_online + ' / ' + data.summary.stations;
        document.getElementById('keyWarning').hidden = data.agent_key_configured;
        document.getElementById('updatedAt').textContent = 'Updated ' + new Date().toLocaleTimeString();
        const grid = document.getElementById('devGrid');
        if (!data.devices.length) { grid.innerHTML = '<div class="dev-meta">No devices registered yet. Add stations and peripherals first.</div>'; return; }
        const results = {};
        grid.querySelectorAll('.dev-card').forEach((card) => { results[card.dataset.device] = card.querySelector('.dev-result').outerHTML; });
        grid.innerHTML = data.devices.map((d) => {
          const [icon, color] = TYPE[d.type] || TYPE.other;
          const commands = d.type === 'station' ? ['wake', 'lock', 'message', 'logoff', 'restart', 'shutdown'] : ['wake', 'ping'];
          return '<article class="dev-card" data-device="' + esc(d.device) + '" data-name="' + esc(d.name) + '">'
            + '<div class="dev-head"><span class="dev-icon" style="background:' + color + '"><i class="fas ' + icon + '"></i></span><div><div class="dev-name">' + esc((d.code ? d.code + ' · ' : '') + d.name) + '</div>'
            + '<div class="dev-meta">' + esc(d.ip_address || 'No IP') + ' · ' + esc(d.mac_address || 'No MAC') + '</div></div>'
            + '<span class="dev-state ' + (d.online ? 'on' : 'off') + '"><i></i>' + (d.online ? 'Online' : 'Offline') + '</span></div>'
            + (d.type === 'station' ? '<div class="dev-meta">Agent last seen ' + esc(ago(d.last_seen)) + ' · ' + esc(d.status) + '</div>' : '')
            + (d.active_customer ? '<div class="dev-session"><i class="fas fa-user-clock"></i> ' + esc(d.active_customer) + (d.session_ends_at ? ' until ' + esc(new Date(d.session_ends_at.replace(' ', 'T')).toLocaleTimeString([], {hour: '2-digit', minute: '2-digit'})) : '') + '</div>' : '')
            + (CAN_CONTROL ? '<div class="dev-actions">' + commands.map((c) => '<button type="button" data-cmd="' + c + '"' + (DANGER.includes(c) ? ' class="danger"' : '') + '><i class="fas ' + LABELS[c][0] + '"></i>' + LABELS[c][1] + '</button>').join('') + '</div>' : '')
            + (results[d.device] || '<div class="dev-result" aria-live="polite"></div>') + '</article>';
        }).join('');
      } catch (error) {
        document.getElementById('devGrid').innerHTML = '<div class="dev-meta">' + esc(error.message) + '</div>';
      }
    }

    async function loadHistory() {
      try {
        const {data} = await request('api/devices.php?commands=1');
        document.getElementById('historyTable').innerHTML = data.length ? data.map((c) => '<tr><td>' + esc(ago(c.created_at)) + '</td><td>' + esc(c.station_code) + '</td><td style="text-transform:capitalize">' + esc(c.command) + ' <span class="dev-meta">via ' + esc(c.source) + '</span></td>'
          + '<td><span class="status-badge ' + ({succeeded: 'success', failed: 'error', expired: 'error'}[c.status] || 'warning') + '"><span class="status-dot"></span>' + esc(c.status) + '</span>' + (c.result_message ? '<div class="dev-meta">' + esc(c.result_message) + '</div>' : '') + '</td><td>' + esc(c.requested_by || '—') + '</td></tr>').join('')
          : '<tr><td colspan="5" class="dev-meta">No commands yet.</td></tr>';
      } catch (error) { /* ignore */ }
    }

    async function send(device, command, text, resultEl, buttons) {
      if (resultEl) { resultEl.className = 'dev-result wait'; resultEl.textContent = 'Sending ' + command + '…'; }
      buttons.forEach((b) => { b.disabled = true; });
      try {
        const result = await request('api/devices.php', {method: 'POST', body: JSON.stringify({device, command, text})});
        const queued = result.data && result.data.status === 'queued';
        if (resultEl) { resultEl.className = 'dev-result ' + (queued ? 'wait' : 'ok'); resultEl.textContent = result.message; }
        else if (window.ModalController) ModalController.showNotification(result.message, 'success');
      } catch (error) {
        if (resultEl) { resultEl.className = 'dev-result fail'; resultEl.textContent = error.message; }
        else if (window.ModalController) ModalController.showNotification(error.message, 'error');
      } finally {
        buttons.forEach((b) => { b.disabled = false; });
        loadHistory();
        setTimeout(load, 1500);
      }
    }

    document.getElementById('devGrid').addEventListener('click', async (event) => {
      const button = event.target.closest('[data-cmd]');
      if (!button) return;
      const card = button.closest('.dev-card');
      const command = button.dataset.cmd;
      const {ok, text} = await CtlDialog.deviceCommand(command, card.dataset.name);
      if (!ok) return;
      // The grid re-renders every 10 s, so look the card up again after the dialog closes.
      const liveCard = [...document.querySelectorAll('.dev-card')].find((node) => node.dataset.device === card.dataset.device) || card;
      send(card.dataset.device, command, text, liveCard.querySelector('.dev-result'), [...liveCard.querySelectorAll('[data-cmd]')]);
    });

    document.querySelectorAll('[data-bulk]').forEach((button) => button.addEventListener('click', async () => {
      const command = button.dataset.bulk;
      const affected = latestDevices.filter((d) => d.type === 'station' && d.status !== 'maintenance').length;
      const {ok, text} = await CtlDialog.bulkStations(command, affected);
      if (ok) send('all_stations', command, text, null, [button]);
    }));

    const agentForm = document.getElementById('agentForm');
    if (agentForm) agentForm.addEventListener('submit', async (event) => {
      event.preventDefault();
      const select = document.getElementById('agentStation');
      const server = document.getElementById('agentServer').value.trim().replace(/\/+$/, '');
      if (!select.value) { CtlDialog.alert({title: 'No stations yet', message: 'Add the PC on the Stations page first.', tone: 'warning'}); return; }
      if (!select.selectedOptions[0].dataset.mac) {
        CtlDialog.alert({title: 'MAC address needed', message: 'Add this station’s MAC address on the Stations page first. On the PC, run “getmac /v” to find it.', tone: 'warning', icon: 'fa-ethernet'});
        return;
      }
      if (!/^https?:\/\/[^\s/]+/i.test(server) || /^https?:\/\/(localhost|127\.)/i.test(server)) {
        CtlDialog.alert({title: 'Check the server address', message: 'Use the address the PC can reach, e.g. https://yourdomain.com/systems — not localhost.', tone: 'warning', icon: 'fa-globe'});
        return;
      }
      if (/^http:\/\//i.test(server) && !/^http:\/\/(10\.|192\.168\.|172\.(1[6-9]|2\d|3[01])\.)/.test(server)) {
        const go = await CtlDialog.confirm({title: 'Use an insecure address?', message: 'This address is not HTTPS, so the agent key would travel unencrypted over the internet. Use https:// if your host supports it.', tone: 'warning', confirmText: 'Download anyway'});
        if (!go) return;
      }
      const params = new URLSearchParams({station_id: select.value, server_url: server});
      if (document.getElementById('agentRelay').checked) params.set('relay', '1');
      const button = agentForm.querySelector('button[type=submit]');
      button.disabled = true;
      try {
        const response = await fetch('api/agent-package.php?' + params.toString(), {credentials: 'same-origin'});
        if (!response.ok) throw new Error(await response.text());
        const blob = await response.blob();
        const name = (response.headers.get('Content-Disposition') || '').match(/filename="([^"]+)"/);
        const link = document.createElement('a');
        link.href = URL.createObjectURL(blob);
        link.download = name ? name[1] : 'ctechlit-agent.zip';
        document.body.appendChild(link);
        link.click();
        link.remove();
        setTimeout(() => URL.revokeObjectURL(link.href), 2000);
      } catch (error) {
        CtlDialog.alert({title: 'Could not build the installer', message: error.message, tone: 'danger', icon: 'fa-circle-exclamation'});
      } finally {
        button.disabled = false;
      }
    });

    document.getElementById('refreshBtn').addEventListener('click', () => { load(); loadHistory(); });
    load();
    loadHistory();
    setInterval(() => { if (!document.hidden) load(); }, 10000);
  })();
  </script>
</body>
</html>
