<?php
/**
 * CTECHLIT & SYSTEMS SaaS Analytics Dashboard — Portal Dashboard
 * Clean modern admin UI with glass effects
 */

require_once __DIR__ . '/../includes/auth.php';

// Redirect to portal auth if not authenticated as staff
if (!isAuthenticated() || $_SESSION['user_type'] !== 'staff') {
    redirect('auth.php');
}

$currentUser = currentStaff() ?: currentUser();
$userType = $_SESSION['user_type'] ?? 'user';
$isAdmin = hasPermission('staff.assign_permissions');
$portalStats = portalSummaryStats();
$topProducts = portalTopProducts(5);
$recentActivity = portalRecentActivity();
$revenueSeries = portalRevenueSeries(7);

$canControlDevices = hasPermission('stations.control');
$canViewPersonal = hasPermission('personal_devices.view');

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Dashboard — CTECHLIT & SYSTEMS</title>
  <link rel="icon" type="image/svg+xml" href="../assets/images/logo-icon.svg">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="stylesheet" href="../assets/css/portal.css">
  <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
</head>
<body>
  <?php include_once __DIR__ . '/../includes/sidebar.php'; ?>
  <!-- Main Content -->
  <main class="portal-main" style="margin-left: var(--sidebar-width);">
    <!-- Header -->
    <?php include_once __DIR__ . '/../includes/header.php'; ?>

    <!-- Dashboard Content -->
    <div class="portal-content">
      <!-- KPI Cards -->
      <div class="kpi-grid">
        <div class="kpi-card animate-fade-in" style="--kpi-color: var(--violet); --kpi-bg: rgba(124, 58, 237, 0.1);">
          <div class="kpi-header">
            <div class="kpi-icon">
              <i class="fas fa-dollar-sign"></i>
            </div>
            <div class="kpi-trend up">
              <i class="fas fa-arrow-up"></i>
              <span>12.5%</span>
            </div>
          </div>
          <div class="kpi-value">GHS <?php echo number_format($portalStats['total_revenue'], 2); ?></div>
          <div class="kpi-label">Total Revenue</div>
        </div>

        <div class="kpi-card animate-fade-in" style="--kpi-color: var(--cyan); --kpi-bg: rgba(6, 182, 212, 0.1); animation-delay: 0.05s;">
          <div class="kpi-header">
            <div class="kpi-icon">
              <i class="fas fa-users"></i>
            </div>
            <div class="kpi-trend up">
              <i class="fas fa-arrow-up"></i>
              <span>Live</span>
            </div>
          </div>
          <div class="kpi-value"><?php echo number_format($portalStats['total_customers']); ?></div>
          <div class="kpi-label">Active Users</div>
        </div>

        <div class="kpi-card animate-fade-in" style="--kpi-color: var(--green); --kpi-bg: rgba(16, 185, 129, 0.1); animation-delay: 0.1s;">
          <div class="kpi-header">
            <div class="kpi-icon">
              <i class="fas fa-percentage"></i>
            </div>
            <div class="kpi-trend up">
              <i class="fas fa-arrow-up"></i>
              <span>DB</span>
            </div>
          </div>
          <div class="kpi-value"><?php echo number_format($portalStats['conversion_rate'], 2); ?>%</div>
          <div class="kpi-label">Conversion Rate</div>
        </div>

        <div class="kpi-card animate-fade-in" style="--kpi-color: var(--amber); --kpi-bg: rgba(245, 158, 11, 0.1); animation-delay: 0.15s;">
          <div class="kpi-header">
            <div class="kpi-icon">
              <i class="fas fa-shopping-bag"></i>
            </div>
            <div class="kpi-trend down">
              <i class="fas fa-arrow-down"></i>
              <span>Avg</span>
            </div>
          </div>
          <div class="kpi-value">GHS <?php echo number_format($portalStats['avg_order_value'], 2); ?></div>
          <div class="kpi-label">Avg Order Value</div>
        </div>
      </div>

      <!-- Hardware Control Center -->
      <section class="card glass-card animate-fade-in hcc" style="animation-delay: 0.18s;" aria-labelledby="hccTitle">
        <div class="card-header">
          <div>
            <div class="card-title" id="hccTitle"><i class="fas fa-network-wired" style="margin-right: 8px; color: var(--violet);"></i>Hardware Control Center</div>
            <div class="card-subtitle">Live status of every device on the network · <span id="hccUpdated">connecting…</span></div>
          </div>
          <div class="hcc-stats">
            <span class="network-stat-badge online"><i class="fas fa-circle"></i> <span id="onlineCount">—</span> Online</span>
            <span class="network-stat-badge offline"><i class="fas fa-circle"></i> <span id="offlineCount">—</span> Offline</span>
            <span class="network-stat-badge total"><i class="fas fa-desktop"></i> <span id="agentCount">—</span> Agents</span>
          </div>
        </div>
        <?php if ($canControlDevices): ?>
        <div class="hcc-bulk" role="group" aria-label="Commands for all stations">
          <button type="button" class="btn btn-secondary small" data-bulk="wake"><i class="fas fa-power-off"></i> Wake all</button>
          <button type="button" class="btn btn-secondary small" data-bulk="message"><i class="fas fa-message"></i> Message all</button>
          <button type="button" class="btn btn-secondary small" data-bulk="lock"><i class="fas fa-lock"></i> Lock all</button>
          <button type="button" class="btn btn-danger small" data-bulk="shutdown"><i class="fas fa-plug-circle-xmark"></i> Shut down all</button>
          <a class="btn btn-secondary small" href="devices.php" style="margin-left:auto"><i class="fas fa-sliders"></i> Device Control</a>
        </div>
        <?php endif; ?>
        <div class="hcc-grid" id="hccGrid" aria-live="polite"><div class="hcc-empty">Loading devices…</div></div>
        <?php if ($canViewPersonal): ?>
        <div class="hcc-personal">
          <div class="hcc-personal-head">
            <strong><i class="fas fa-mobile-alt"></i> Personal devices</strong>
            <span id="pdSummary" class="hcc-muted">—</span>
            <a href="personal-devices.php" class="hcc-link">Manage <i class="fas fa-arrow-right"></i></a>
          </div>
          <div class="hcc-personal-list" id="pdList"></div>
        </div>
        <?php endif; ?>
      </section>

      <!-- Charts Row -->
      <div class="dashboard-grid">
        <!-- Sales Overview Chart -->
        <div class="card glass-card animate-fade-in" style="animation-delay: 0.2s;">
          <div class="card-header">
            <div>
              <div class="card-title">Sales Overview</div>
              <div class="card-subtitle">Revenue trends over the last 7 days</div>
            </div>
            <button class="card-action">
              <i class="fas fa-ellipsis-h"></i>
            </button>
          </div>
          <div class="chart-container">
            <canvas id="salesChart"></canvas>
          </div>
        </div>

        <!-- Top Products -->
        <div class="card glass-card animate-fade-in" style="animation-delay: 0.25s;">
          <div class="card-header">
            <div>
              <div class="card-title">Top Products</div>
              <div class="card-subtitle">Best selling items this month</div>
            </div>
            <button class="card-action">
              <i class="fas fa-ellipsis-h"></i>
            </button>
          </div>
          <div class="table-container" style="border: none; background: transparent;">
            <table class="data-table">
              <thead>
                <tr>
                  <th>Product</th>
                  <th>Sales</th>
                  <th>Revenue</th>
                  <th>Trend</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($topProducts as $product): ?>
                <tr>
                  <td>
                    <div class="table-product">
                      <div class="table-product-img" style="background: rgba(124, 58, 237, 0.1); color: var(--violet);">
                        <i class="fas fa-box"></i>
                      </div>
                      <div class="table-product-info">
                        <span class="table-product-name"><?php echo sanitize($product['name']); ?></span>
                        <span class="table-product-category"><?php echo sanitize($product['category']); ?></span>
                      </div>
                    </div>
                  </td>
                  <td><?php echo (int) ($product['total_sold'] ?? 0); ?></td>
                  <td>GHS <?php echo number_format((float) ($product['total_revenue'] ?? 0), 2); ?></td>
                  <td><span class="status-badge <?php echo ($product['total_revenue'] ?? 0) > 0 ? 'success' : 'neutral'; ?>"><span class="status-dot"></span><?php echo ($product['total_revenue'] ?? 0) > 0 ? 'Selling' : 'No sales'; ?></span></td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <!-- Recent Activity -->
      <div class="card glass-card animate-fade-in" style="animation-delay: 0.3s;">
        <div class="card-header">
          <div>
            <div class="card-title">Recent Activity</div>
            <div class="card-subtitle">Latest transactions and events</div>
          </div>
          <button class="card-action">
            <i class="fas fa-ellipsis-h"></i>
          </button>
        </div>
        <div class="activity-list">
          <?php foreach ($recentActivity as $activity): ?>
          <div class="activity-item">
            <div class="activity-icon revenue">
              <i class="fas fa-dollar-sign"></i>
            </div>
            <div class="activity-content">
              <div class="activity-text">
                <strong><?php echo sanitize($activity['summary'] ?? 'Recent activity'); ?></strong> — GHS <?php echo number_format((float) ($activity['total_amount'] ?? 0), 2); ?> from <?php echo sanitize($activity['full_name'] ?? 'Customer'); ?>
              </div>
              <div class="activity-time"><?php echo sanitize(relativeTime($activity['created_at'] ?? date('Y-m-d H:i:s'))); ?></div>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
  </main>
</div>

<!-- Floating Action Button -->
<button class="fab" aria-label="Add new">
  <i class="fas fa-plus"></i>
</button>

<?php include_once __DIR__ . '/../includes/modals.php'; ?>
<script src="../assets/js/modal-controller.js"></script>

<!-- Hardware action dialog -->
<div class="modal-overlay" id="hccModal" role="dialog" aria-modal="true" aria-labelledby="hccModalTitle">
  <div class="modal modal-sm">
    <div class="modal-header">
      <h3 class="modal-title" id="hccModalTitle">Device</h3>
      <button class="modal-close" type="button" data-close aria-label="Close"><i class="fas fa-times"></i></button>
    </div>
    <div class="modal-body">
      <div class="device-action-summary">
        <div class="device-action-icon" id="hccModalIcon"><i class="fas fa-desktop"></i></div>
        <div class="device-action-info">
          <div class="device-action-name" id="hccModalName">—</div>
          <div class="device-action-meta" id="hccModalMeta">—</div>
        </div>
      </div>
      <div class="hcc-actions" id="hccActions"></div>
      <div class="hcc-result" id="hccResult" aria-live="polite"></div>
    </div>
    <div class="modal-footer"><button type="button" class="btn btn-secondary" data-close>Close</button></div>
  </div>
</div>
<style>
  .hcc-stats { display: flex; gap: 8px; flex-wrap: wrap; }
  .hcc-bulk { display: flex; flex-wrap: wrap; gap: 8px; margin: 4px 0 16px; }
  .hcc-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(190px, 1fr)); gap: 12px; }
  .hcc-empty { grid-column: 1 / -1; padding: 24px; text-align: center; color: var(--muted); }
  .hcc-tile { position: relative; display: flex; flex-direction: column; gap: 6px; padding: 14px; border: 1px solid rgba(148,163,184,0.25); border-radius: 14px; background: rgba(255,255,255,0.72); text-align: left; font: inherit; color: var(--ink); cursor: pointer; transition: transform 0.15s ease, box-shadow 0.15s ease, border-color 0.15s ease; }
  .hcc-tile:hover { transform: translateY(-2px); border-color: rgba(124,58,237,0.4); box-shadow: 0 10px 22px rgba(124,58,237,0.12); }
  .hcc-tile-top { display: flex; align-items: center; gap: 10px; }
  .hcc-icon { display: grid; place-items: center; flex: 0 0 auto; width: 36px; height: 36px; border-radius: 10px; color: #fff; font-size: 15px; }
  .hcc-name { font-weight: 700; font-size: 13.5px; line-height: 1.25; }
  .hcc-muted { font-size: 11.5px; color: var(--muted); }
  .hcc-dot { position: absolute; top: 12px; right: 12px; width: 9px; height: 9px; border-radius: 50%; background: #94A3B8; }
  .hcc-tile.on .hcc-dot { background: #10B981; box-shadow: 0 0 0 3px rgba(16,185,129,0.18); }
  .hcc-session { font-size: 11.5px; padding: 4px 8px; border-radius: 8px; background: rgba(245,158,11,0.12); color: #92400E; }
  .hcc-actions { display: grid; grid-template-columns: repeat(2, 1fr); gap: 8px; margin-top: 14px; }
  .hcc-actions button { display: flex; align-items: center; gap: 8px; padding: 10px; border: 1px solid rgba(148,163,184,0.35); border-radius: 10px; background: #fff; font: 600 12.5px Inter, sans-serif; color: var(--ink); cursor: pointer; text-align: left; }
  .hcc-actions button:hover:not(:disabled) { border-color: var(--violet); color: var(--violet); }
  .hcc-actions button.danger:hover:not(:disabled) { border-color: #EF4444; color: #EF4444; }
  .hcc-actions button:disabled { opacity: 0.55; cursor: progress; }
  .hcc-message { margin-top: 12px; }
  .hcc-result { min-height: 18px; margin-top: 12px; font-size: 12.5px; }
  .hcc-result.ok { color: #059669; } .hcc-result.wait { color: #B45309; } .hcc-result.fail { color: #DC2626; }
  .hcc-personal { margin-top: 18px; padding-top: 14px; border-top: 1px solid rgba(148,163,184,0.2); }
  .hcc-personal-head { display: flex; align-items: center; gap: 12px; flex-wrap: wrap; margin-bottom: 10px; font-size: 13.5px; }
  .hcc-link { margin-left: auto; font-size: 12.5px; font-weight: 600; color: var(--violet); text-decoration: none; }
  .hcc-personal-list { display: flex; flex-wrap: wrap; gap: 8px; }
  .hcc-chip { display: inline-flex; align-items: center; gap: 8px; padding: 7px 10px; border-radius: 10px; background: rgba(255,255,255,0.75); border: 1px solid rgba(148,163,184,0.25); font-size: 12.5px; }
  .hcc-chip.low { border-color: rgba(245,158,11,0.6); }
  .hcc-chip button { border: 0; background: transparent; color: var(--muted); cursor: pointer; padding: 2px 4px; }
  .hcc-chip button:hover { color: var(--violet); }
</style>
<script>
  const revenueLabels = <?php echo json_encode($revenueSeries['labels']); ?>;
  const revenueData = <?php echo json_encode($revenueSeries['revenue']); ?>;
  const orderData = <?php echo json_encode($revenueSeries['orders']); ?>;

  // Sales Chart
  const ctx = document.getElementById('salesChart').getContext('2d');
  const salesChart = new Chart(ctx, {
    type: 'line',
    data: {
      labels: revenueLabels,
      datasets: [{
        label: 'Revenue',
        data: revenueData,
        borderColor: '#7C3AED',
        backgroundColor: 'rgba(124, 58, 237, 0.1)',
        borderWidth: 2,
        fill: true,
        tension: 0.4,
        pointRadius: 4,
        pointBackgroundColor: '#7C3AED',
        pointBorderColor: '#fff',
        pointBorderWidth: 2,
        pointHoverRadius: 6,
      }, {
        label: 'Orders',
        data: orderData,
        borderColor: '#06B6D4',
        backgroundColor: 'rgba(6, 182, 212, 0.05)',
        borderWidth: 2,
        fill: true,
        tension: 0.4,
        pointRadius: 4,
        pointBackgroundColor: '#06B6D4',
        pointBorderColor: '#fff',
        pointBorderWidth: 2,
        pointHoverRadius: 6,
        yAxisID: 'y1',
      }]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      interaction: {
        mode: 'index',
        intersect: false,
      },
      plugins: {
        legend: {
          display: true,
          position: 'top',
          align: 'end',
          labels: {
            usePointStyle: true,
            pointStyle: 'circle',
            padding: 20,
            font: {
              family: "'Inter', sans-serif",
              size: 12,
              weight: '500',
            },
            color: '#64748B',
          }
        },
        tooltip: {
          backgroundColor: 'rgba(30, 41, 59, 0.9)',
          titleFont: {
            family: "'Inter', sans-serif",
            size: 13,
            weight: '600',
          },
          bodyFont: {
            family: "'Inter', sans-serif",
            size: 12,
          },
          padding: 12,
          cornerRadius: 8,
          displayColors: true,
        }
      },
      scales: {
        x: {
          grid: {
            display: false,
          },
          ticks: {
            font: {
              family: "'Inter', sans-serif",
              size: 12,
            },
            color: '#94A3B8',
          }
        },
        y: {
          position: 'left',
          grid: {
            color: 'rgba(226, 232, 240, 0.5)',
          },
          ticks: {
            font: {
              family: "'Inter', sans-serif",
              size: 12,
            },
            color: '#94A3B8',
            callback: function(value) {
              return 'GHS ' + value.toLocaleString();
            }
          }
        },
        y1: {
          position: 'right',
          grid: {
            display: false,
          },
          ticks: {
            font: {
              family: "'Inter', sans-serif",
              size: 12,
            },
            color: '#94A3B8',
          }
        }
      }
    }
  });

  // ============================================================
  // Hardware Control Center (stations via agents, peripherals via LAN, personal devices)
  // ============================================================
  (function () {
    const CAN_CONTROL = <?php echo $canControlDevices ? 'true' : 'false'; ?>;
    const CAN_PERSONAL = <?php echo $canViewPersonal ? 'true' : 'false'; ?>;
    const CAN_MANAGE_PERSONAL = <?php echo hasPermission('personal_devices.manage') ? 'true' : 'false'; ?>;
    const TYPE = {station: ['fa-desktop', '#7C3AED', 'Station'], printer: ['fa-print', '#0891B2', 'Printer'], game_console: ['fa-gamepad', '#DB2777', 'Game console'], router: ['fa-wifi', '#059669', 'Router'], scanner: ['fa-file-image', '#0E7490', 'Scanner'], other: ['fa-microchip', '#64748B', 'Device']};
    const ACTIONS = {
      wake: ['fa-power-off', 'Power on'], ping: ['fa-satellite-dish', 'Check connection'], lock: ['fa-lock', 'Lock screen'],
      message: ['fa-message', 'Show message'], logoff: ['fa-right-from-bracket', 'Sign user out'], sleep: ['fa-moon', 'Sleep'],
      restart: ['fa-rotate-right', 'Restart'], shutdown: ['fa-plug-circle-xmark', 'Shut down'], test: ['fa-print', 'Test printer'], configure: ['fa-gear', 'Configure']
    };
    const DANGER = ['logoff', 'restart', 'shutdown', 'sleep'];
    const esc = (v) => { const d = document.createElement('div'); d.textContent = v == null ? '' : String(v); return d.innerHTML; };
    const $ = (id) => document.getElementById(id);
    const seen = (s) => s == null ? 'agent never connected' : s < 60 ? 'seen ' + s + 's ago' : s < 3600 ? 'seen ' + Math.round(s / 60) + ' min ago' : 'seen ' + Math.round(s / 3600) + ' h ago';
    let devices = [];
    let current = null;

    async function request(url, options) {
      const response = await fetch(url, Object.assign({credentials: 'same-origin', headers: {'Accept': 'application/json', 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest'}}, options || {}));
      let result = {};
      try { result = await response.json(); } catch (e) { /* ignore */ }
      if (!response.ok || !result.success) throw new Error(result.message || result.error || 'Request failed (HTTP ' + response.status + ')');
      return result;
    }

    async function loadDevices() {
      try {
        const {data} = await request('api/devices.php');
        devices = data.devices;
        $('onlineCount').textContent = data.summary.online;
        $('offlineCount').textContent = data.summary.total - data.summary.online;
        $('agentCount').textContent = data.summary.agents_online + '/' + data.summary.stations;
        $('hccUpdated').textContent = 'updated ' + new Date().toLocaleTimeString([], {hour: '2-digit', minute: '2-digit', second: '2-digit'});
        renderDevices();
      } catch (error) {
        $('hccGrid').innerHTML = '<div class="hcc-empty">' + esc(error.message) + '</div>';
      }
    }

    function renderDevices() {
      if (!devices.length) {
        $('hccGrid').innerHTML = '<div class="hcc-empty"><i class="fas fa-network-wired"></i> No devices yet. Add stations and printers to get started.</div>';
        return;
      }
      $('hccGrid').innerHTML = devices.map((d) => {
        const [icon, color, label] = TYPE[d.type] || TYPE.other;
        const sub = d.type === 'station' ? (d.ip_address || 'No IP') + ' · ' + seen(d.seconds_since_seen) : label + (d.ip_address ? ' · ' + d.ip_address : '');
        return '<button type="button" class="hcc-tile ' + (d.online ? 'on' : 'off') + '" data-device="' + esc(d.device) + '" aria-label="' + esc((d.code ? d.code + ' ' : '') + d.name + ', ' + (d.online ? 'online' : 'offline')) + '">'
          + '<span class="hcc-dot" aria-hidden="true"></span>'
          + '<span class="hcc-tile-top"><span class="hcc-icon" style="background:' + color + '"><i class="fas ' + icon + '"></i></span>'
          + '<span><span class="hcc-name">' + esc(d.type === 'station' ? d.code : d.name) + '</span><br><span class="hcc-muted">' + esc(d.type === 'station' ? d.name : (d.code || label)) + '</span></span></span>'
          + '<span class="hcc-muted">' + esc(sub) + '</span>'
          + (d.active_customer ? '<span class="hcc-session"><i class="fas fa-user-clock"></i> ' + esc(d.active_customer) + (d.minutes_left != null ? ' · ' + Math.max(0, d.minutes_left) + ' min' : '') + '</span>' : '')
          + '</button>';
      }).join('');
    }

    // ---------- Device dialog ----------
    const modal = $('hccModal');
    function openDevice(device) {
      current = device;
      const [icon, color, label] = TYPE[device.type] || TYPE.other;
      $('hccModalTitle').textContent = label + ' controls';
      $('hccModalIcon').innerHTML = '<i class="fas ' + icon + '" style="color:' + color + '"></i>';
      $('hccModalName').textContent = (device.code && device.type === 'station' ? device.code + ' · ' : '') + device.name;
      $('hccModalMeta').textContent = [device.ip_address || 'No IP', device.mac_address || 'No MAC', device.online ? 'Online' : 'Offline'].join(' • ');
      setResult('', '');
      let actions;
      if (device.type === 'station') actions = ['wake', 'ping', 'lock', 'message', 'logoff', 'sleep', 'restart', 'shutdown'];
      else if (device.type === 'printer') actions = ['wake', 'ping', 'test', 'configure'];
      else actions = ['wake', 'ping'];
      $('hccActions').innerHTML = CAN_CONTROL
        ? actions.map((a) => '<button type="button" data-cmd="' + a + '"' + (DANGER.includes(a) ? ' class="danger"' : '') + '><i class="fas ' + ACTIONS[a][0] + '"></i><span>' + ACTIONS[a][1] + '</span></button>').join('')
        : '<p class="hcc-muted" style="grid-column:1/-1">You can view devices but need the “Control Stations” permission to send commands.</p>';
      if (device.type === 'station' && !device.online && CAN_CONTROL) {
        setResult('The station agent is offline. Power on uses Wake-on-LAN; other commands are queued for 2 minutes.', 'wait');
      }
      modal.classList.add('active');
      document.body.style.overflow = 'hidden';
      const first = modal.querySelector('[data-cmd]');
      if (first) first.focus();
    }
    function closeDevice() {
      modal.classList.remove('active');
      document.body.style.overflow = '';
      current = null;
    }
    function setResult(text, kind) {
      $('hccResult').textContent = text;
      $('hccResult').className = 'hcc-result ' + (kind || '');
    }

    async function send(device, command, text) {
      const buttons = [...modal.querySelectorAll('[data-cmd]')];
      buttons.forEach((b) => { b.disabled = true; });
      setResult('Sending “' + (ACTIONS[command] ? ACTIONS[command][1] : command) + '”…', 'wait');
      try {
        const result = command === 'test'
          ? await request('api/printers.php?action=test', {method: 'POST', body: JSON.stringify({peripheral_id: device.id})})
          : await request('api/devices.php', {method: 'POST', body: JSON.stringify({device: device.device, command, text: text || ''})});
        const pending = result.data && (result.data.status === 'queued' || result.data.online === false);
        setResult(result.message, pending ? 'wait' : 'ok');
      } catch (error) {
        setResult(error.message, 'fail');
      } finally {
        buttons.forEach((b) => { b.disabled = false; });
        setTimeout(loadDevices, 1500);
      }
    }

    $('hccActions').addEventListener('click', async (event) => {
      const button = event.target.closest('[data-cmd]');
      if (!button || !current) return;
      const command = button.dataset.cmd;
      if (command === 'configure') { window.location.href = 'printers.php'; return; }
      const device = current;
      const label = (device.code && device.type === 'station' ? device.code + ' · ' : '') + device.name;
      const {ok, text} = await CtlDialog.deviceCommand(command, label);
      if (ok && current === device) send(device, command, text);
    });
    $('hccGrid').addEventListener('click', (event) => {
      const tile = event.target.closest('[data-device]');
      if (!tile) return;
      const device = devices.find((d) => d.device === tile.dataset.device);
      if (device) openDevice(device);
    });
    modal.addEventListener('click', (event) => { if (event.target === modal || event.target.closest('[data-close]')) closeDevice(); });
    document.addEventListener('keydown', (event) => { if (event.key === 'Escape' && modal.classList.contains('active')) closeDevice(); });

    document.querySelectorAll('[data-bulk]').forEach((button) => button.addEventListener('click', async () => {
      const command = button.dataset.bulk;
      const affected = devices.filter((d) => d.type === 'station' && d.status !== 'maintenance').length;
      const {ok, text} = await CtlDialog.bulkStations(command, affected);
      if (!ok) return;
      button.disabled = true;
      try {
        const result = await request('api/devices.php', {method: 'POST', body: JSON.stringify({device: 'all_stations', command, text})});
        ModalController.showNotification(result.message, 'success');
      } catch (error) {
        ModalController.showNotification(error.message, 'error');
      } finally {
        button.disabled = false;
        setTimeout(loadDevices, 1500);
      }
    }));

    // ---------- Personal devices ----------
    async function loadPersonal() {
      if (!CAN_PERSONAL) return;
      try {
        const {data} = await request('api/personal-devices.php?list=1');
        const online = data.devices.filter((d) => d.status === 'online');
        $('pdSummary').textContent = data.stats.online + ' online of ' + data.stats.total + (data.stats.low_time ? ' · ' + data.stats.low_time + ' running low' : '');
        $('pdList').innerHTML = online.length ? online.map((d) => '<span class="hcc-chip' + (Number(d.minutes_remaining) <= 10 ? ' low' : '') + '"><i class="fas fa-' + (d.device_type === 'laptop' ? 'laptop' : d.device_type === 'tablet' ? 'tablet-screen-button' : 'mobile-screen') + '"></i>'
          + '<span><strong>' + esc(d.device_name) + '</strong> · ' + esc(d.user_name) + ' · ' + Number(d.minutes_remaining) + ' min</span>'
          + (CAN_MANAGE_PERSONAL ? '<button type="button" data-pd-disconnect="' + d.device_id + '" title="Disconnect" aria-label="Disconnect ' + esc(d.device_name) + '"><i class="fas fa-power-off"></i></button>' : '')
          + '</span>').join('') : '<span class="hcc-muted">No personal devices online.</span>';
      } catch (error) {
        $('pdSummary').textContent = error.message;
      }
    }
    if (CAN_PERSONAL) {
      $('pdList').addEventListener('click', async (event) => {
        const button = event.target.closest('[data-pd-disconnect]');
        if (!button) return;
        button.disabled = true;
        try {
          const result = await request('api/personal-devices.php', {method: 'POST', body: JSON.stringify({action: 'disconnect', device_id: Number(button.dataset.pdDisconnect)})});
          ModalController.showNotification(result.message, 'success');
          loadPersonal();
        } catch (error) {
          ModalController.showNotification(error.message, 'error');
          button.disabled = false;
        }
      });
    }

    loadDevices();
    loadPersonal();
    setInterval(() => { if (!document.hidden && !modal.classList.contains('active')) { loadDevices(); loadPersonal(); } }, 10000);
  })();
</script>
</body>
</html>




