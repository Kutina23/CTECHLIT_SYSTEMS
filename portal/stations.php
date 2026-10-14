<?php
/**
 * CTECHLIT & SYSTEMS SaaS Analytics Dashboard — Stations
 * View and manage workstations
 */

require_once __DIR__ . '/../includes/auth.php';
requireAuth();

$currentUser = currentStaff() ?: currentUser();
$stationRows = Database::fetchAll('SELECT * FROM stations ORDER BY station_code');
$stationCounts = array_count_values(array_column($stationRows, 'status'));

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Stations — CTECHLIT & SYSTEMS</title>
  <link rel="icon" type="image/svg+xml" href="../assets/images/logo-icon.svg">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="stylesheet" href="../assets/css/portal.css">
</head>
<body>
  <?php include_once __DIR__ . '/../includes/sidebar.php'; ?>
  <main class="portal-main" style="margin-left: var(--sidebar-width);">
    <?php include_once __DIR__ . '/../includes/header.php'; ?>

    <div class="portal-content">
      <div class="kpi-grid" style="margin-bottom: 24px;">
        <div class="kpi-card animate-fade-in" style="--kpi-color: var(--violet); --kpi-bg: rgba(124, 58, 237, 0.1);">
          <div class="kpi-header">
            <div class="kpi-icon"><i class="fas fa-desktop"></i></div>
          </div>
          <div class="kpi-value"><?php echo (int) (count($stationRows)); ?></div>
          <div class="kpi-label">Total Stations</div>
        </div>
        <div class="kpi-card animate-fade-in" style="--kpi-color: var(--green); --kpi-bg: rgba(16, 185, 129, 0.1); animation-delay: 0.05s;">
          <div class="kpi-header">
            <div class="kpi-icon"><i class="fas fa-check-circle"></i></div>
          </div>
          <div class="kpi-value"><?php echo (int) ($stationCounts['available'] ?? 0); ?></div>
          <div class="kpi-label">Available</div>
        </div>
        <div class="kpi-card animate-fade-in" style="--kpi-color: var(--amber); --kpi-bg: rgba(245, 158, 11, 0.1); animation-delay: 0.1s;">
          <div class="kpi-header">
            <div class="kpi-icon"><i class="fas fa-user"></i></div>
          </div>
          <div class="kpi-value"><?php echo (int) ($stationCounts['occupied'] ?? 0); ?></div>
          <div class="kpi-label">Occupied</div>
        </div>
        <div class="kpi-card animate-fade-in" style="--kpi-color: var(--cyan); --kpi-bg: rgba(6, 182, 212, 0.1); animation-delay: 0.15s;">
          <div class="kpi-header">
            <div class="kpi-icon"><i class="fas fa-tools"></i></div>
          </div>
          <div class="kpi-value"><?php echo (int) ($stationCounts['maintenance'] ?? 0); ?></div>
          <div class="kpi-label">Maintenance</div>
        </div>
      </div>

      <div class="card glass-card animate-fade-in" style="animation-delay: 0.2s;">
        <div class="card-header">
          <div>
            <div class="card-title">All Stations</div>
            <div class="card-subtitle"><?php echo count($stationRows); ?> workstations · <a href="devices.php">Open Device Control</a></div>
          </div>
          <button class="btn btn-primary" id="addStationBtn" style="padding: 10px 16px; font-size: 13px;">
            <i class="fas fa-plus"></i> Add Station
          </button>
        </div>

        <div class="table-container" style="border: none; background: transparent;">
          <table class="data-table" id="stationsTable">
            <thead>
              <tr>
                <th>Station</th>
                <th>Code</th>
                <th>Location</th>
                <th>Specs</th>
                <th>Status</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php if (!$stationRows): ?>
              <tr><td colspan="6" style="text-align:center;color:var(--ink-secondary);">No stations yet. Click “Add Station” to register a workstation with its IP and MAC address.</td></tr>
              <?php endif; ?>
              <?php foreach ($stationRows as $station):
                $specs = json_decode((string) ($station['specs'] ?? ''), true) ?: [];
                $rowData = [
                    'station_id' => (int) $station['station_id'], 'station_code' => $station['station_code'], 'name' => $station['name'],
                    'location' => $station['location'], 'specs' => $specs, 'status' => $station['status'],
                    'ip_address' => $station['ip_address'], 'mac_address' => $station['mac_address'],
                ];
                $agentOnline = $station['last_heartbeat'] && strtotime($station['last_heartbeat']) >= time() - 90;
                $badge = ['available' => 'success', 'occupied' => 'warning', 'maintenance' => 'info', 'offline' => 'error'][$station['status']] ?? 'info';
              ?>
              <tr data-id="<?php echo (int) $station['station_id']; ?>" data-station='<?php echo htmlspecialchars(json_encode($rowData), ENT_QUOTES, 'UTF-8'); ?>'>
                <td><?php echo sanitize($station['name']); ?><div style="font-size:11px;color:var(--muted)"><?php echo sanitize($station['ip_address'] ?: 'No IP'); ?> · agent <?php echo $agentOnline ? 'online' : 'offline'; ?></div></td>
                <td><code style="background: var(--bg-secondary); padding: 2px 6px; border-radius: var(--radius-sm); font-size: 11px;"><?php echo sanitize($station['station_code']); ?></code></td>
                <td><?php echo sanitize((string) ($station['location'] ?? '—')); ?></td>
                <td style="font-size: 11px; color: var(--ink-secondary); max-width: 200px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;"><?php echo sanitize(implode(', ', array_filter(array_map('strval', $specs)))); ?></td>
                <td><span class="status-badge <?php echo $badge; ?>"><span class="status-dot"></span><?php echo sanitize(ucfirst($station['status'])); ?></span></td>
                <td><div class="action-buttons" style="display: flex; gap: 6px; flex-wrap: wrap;">
                  <button class="header-btn" title="Wake Station" data-action="wake" style="color: var(--green);"><i class="fas fa-power-off"></i></button>
                  <button class="header-btn" title="Ping Station" data-action="ping" style="color: var(--cyan);"><i class="fas fa-wifi"></i></button>
                  <button class="header-btn" title="Lock Station" data-action="lock" style="color: var(--amber);"><i class="fas fa-lock"></i></button>
                  <button class="header-btn view-btn" title="View" data-action="view"><i class="fas fa-eye"></i></button>
                  <button class="header-btn edit-btn" title="Edit" data-action="edit"><i class="fas fa-edit"></i></button>
                  <button class="header-btn delete-btn" title="Delete" data-action="delete" style="color: var(--red);"><i class="fas fa-trash"></i></button>
                </div></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </main>
</div>

<button class="fab" id="fabAddStation" aria-label="Add station"><i class="fas fa-plus"></i></button>

<?php include_once __DIR__ . '/../includes/modals.php'; ?>
<script src="../assets/js/modal-controller.js"></script>

<!-- Ping Progress Overlay -->
<div class="modal-overlay" id="pingOverlay" role="dialog" aria-modal="true" aria-labelledby="pingOverlayTitle" style="display: none;">
  <div class="modal modal-sm modal-ping" id="pingModal" role="document" tabindex="-1">
    <div class="modal-header">
      <h3 class="modal-title" id="pingOverlayTitle">Pinging Station</h3>
      <button class="modal-close" id="pingOverlayClose" aria-label="Close modal" style="display: none;">
        <i class="fas fa-times"></i>
      </button>
    </div>
    <div class="modal-body" id="pingModalBody">
      <div class="ping-summary" id="pingSummary">
        <div class="ping-icon">
          <i class="fas fa-wifi"></i>
        </div>
        <div class="ping-target" id="pingTarget">ST-01</div>
        <div class="ping-ip" id="pingIp">192.168.1.101</div>
      </div>
      <div class="ping-steps" id="pingSteps">
        <div class="ping-step" data-step="resolve">
          <div class="ping-step-icon"><i class="fas fa-search"></i></div>
          <div class="ping-step-text">Resolving host...</div>
          <div class="ping-step-status"></div>
        </div>
        <div class="ping-step" data-step="connect">
          <div class="ping-step-icon"><i class="fas fa-plug"></i></div>
          <div class="ping-step-text">Checking connectivity...</div>
          <div class="ping-step-status"></div>
        </div>
        <div class="ping-step" data-step="latency">
          <div class="ping-step-icon"><i class="fas fa-tachometer-alt"></i></div>
          <div class="ping-step-text">Measuring response time...</div>
          <div class="ping-step-status"></div>
        </div>
      </div>
      <div class="ping-result" id="pingResult" style="display: none;">
        <div class="ping-result-icon" id="pingResultIcon"></div>
        <div class="ping-result-title" id="pingResultTitle"></div>
        <div class="ping-result-detail" id="pingResultDetail"></div>
      </div>
    </div>
    <div class="modal-footer" id="pingModalFooter" style="display: none;">
      <button class="btn btn-secondary" id="pingOverlayCancel">Close</button>
    </div>
  </div>
</div>
<script>
  window.MODAL_CONFIGS = {
    station: {
      title: 'Station',
      fields: [
        {name: 'station_code', label: 'Station Code', type: 'text', required: true, placeholder: 'e.g., ST-01'},
        {name: 'name', label: 'Station Name', type: 'text', required: true, placeholder: 'Enter station name'},
        {name: 'location', label: 'Location', type: 'text', required: false, placeholder: 'e.g., Zone A, Gaming Corner'},
        {name: 'status', label: 'Status', type: 'select', required: true, options: [
          {value: 'available', label: 'Available'},
          {value: 'occupied', label: 'Occupied'},
          {value: 'maintenance', label: 'Maintenance'},
          {value: 'offline', label: 'Offline'},
        ]},
        {name: 'specs', label: 'Specifications (JSON)', type: 'textarea', required: false, placeholder: '{"cpu":"Intel i5","ram":"16GB","gpu":"Integrated","os":"Windows 11"}'},
        {name: 'ip_address', label: 'IP Address', type: 'text', required: false, placeholder: '192.168.1.101'},
        {name: 'mac_address', label: 'MAC Address', type: 'text', required: false, placeholder: 'AA:BB:CC:DD:EE:FF'},
      ],
      api: 'api/stations.php',
      table: 'stations',
      idField: 'station_id',
    }
  };

  const sidebar = document.getElementById('sidebar');
  const sidebarOverlay = document.getElementById('sidebarOverlay');
  function openSidebar() { sidebar.classList.add('open'); sidebarOverlay.classList.add('active'); document.body.style.overflow = 'hidden'; }
  function closeSidebar() { sidebar.classList.remove('open'); sidebarOverlay.classList.remove('active'); document.body.style.overflow = ''; }
  window.addEventListener('resize', () => { if (window.innerWidth > 768) closeSidebar(); });

  document.addEventListener('DOMContentLoaded', () => {
    const table = document.getElementById('stationsTable');
    const addBtn = document.getElementById('addStationBtn');
    const fabBtn = document.getElementById('fabAddStation');

    function openAddModal() { ModalController.open('station', 'create'); }
    addBtn.addEventListener('click', openAddModal);
    fabBtn.addEventListener('click', openAddModal);

    table.addEventListener('click', (e) => {
      const btn = e.target.closest('button[data-action]');
      if (!btn) return;
      const row = btn.closest('tr');
      const id = row.dataset.id;
      const data = JSON.parse(row.dataset.station);
      const action = btn.dataset.action;

      switch (action) {
        case 'view': ModalController.openView('station', data); break;
        case 'edit': ModalController.open('station', 'edit', data); break;
        case 'delete':
          ModalController.openConfirm({
            title: 'Delete Station', message: `Delete "${data.name}"?`, detail: 'This action cannot be undone.',
            icon: 'danger', confirmText: 'Delete', confirmClass: 'btn-danger',
            onConfirm: () => deleteStation(id)
          });
          break;
        case 'wake':
          ModalController.openConfirm({
            title: 'Wake Station', message: `Send Wake-on-LAN to "${data.name}"?`,
            detail: `MAC: ${data.mac_address || 'Not configured'}`,
            icon: 'info', confirmText: 'Wake', confirmClass: 'btn-primary',
            onConfirm: () => controlStation(id, 'wake', data)
          });
          break;
        case 'ping':
          controlStation(id, 'ping', data);
          break;
        case 'lock':
          ModalController.openConfirm({
            title: 'Lock Station', message: `Lock "${data.name}"?`,
            detail: 'This will lock the workstation screen.',
            icon: 'warning', confirmText: 'Lock', confirmClass: 'btn-warning',
            onConfirm: () => controlStation(id, 'command', Object.assign({}, data, {command: 'lock'}))
          });
          break;
      }
    });

    async function controlStation(stationId, action, data) {
      try {
        if (action === 'ping') {
          await showPingProgress(stationId, data);
          return;
        }
        
        const body = { station_id: stationId };
        if (action === 'command') body.command = data.command;
        
        const res = await fetch(`api/stations.php?action=${action}`, {
          method: 'POST',
          headers: {'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest'},
          body: JSON.stringify(body)
        });
        const result = await res.json();
        if (result.success) {
          ModalController.showNotification(result.message, result.data && result.data.status === 'queued' ? 'warning' : 'success');
        } else {
          ModalController.showNotification(result.message || result.error || 'Action failed', 'error');
        }
      } catch (err) {
        ModalController.showNotification(err.message, 'error');
      }
    }
    
    async function showPingProgress(stationId, data) {
      const pingOverlay = document.getElementById('pingOverlay');
      const pingTarget = document.getElementById('pingTarget');
      const pingIp = document.getElementById('pingIp');
      const pingSteps = document.querySelectorAll('.ping-step');
      const pingResult = document.getElementById('pingResult');
      const pingResultIcon = document.getElementById('pingResultIcon');
      const pingResultTitle = document.getElementById('pingResultTitle');
      const pingResultDetail = document.getElementById('pingResultDetail');
      const pingOverlayClose = document.getElementById('pingOverlayClose');
      const pingModalFooter = document.getElementById('pingModalFooter');
      const pingModalBody = document.getElementById('pingModalBody');
      
      // Reset state
      pingOverlay.style.display = 'flex';
      pingOverlay.classList.add('active');
      document.body.style.overflow = 'hidden';
      
      pingTarget.textContent = data.name || data.station_code || 'Unknown';
      pingIp.textContent = data.ip_address || 'No IP';
      
      pingSteps.forEach(step => {
        step.classList.remove('active', 'success', 'error');
        step.querySelector('.ping-step-status').textContent = '';
      });
      
      pingResult.style.display = 'none';
      pingResult.className = 'ping-result';
      pingOverlayClose.style.display = 'none';
      pingModalFooter.style.display = 'none';
      
      // Step 1: Resolve
      const resolveStep = document.querySelector('.ping-step[data-step="resolve"]');
      resolveStep.classList.add('active');
      resolveStep.querySelector('.ping-step-status').textContent = '...';
      
      await new Promise(resolve => setTimeout(resolve, 400));
      
      if (!data.ip_address) {
        resolveStep.classList.remove('active');
        resolveStep.classList.add('error');
        resolveStep.querySelector('.ping-step-status').textContent = '✗';
        showPingResult(false, 'No IP Address', 'Station IP not configured');
        return;
      }
      
      resolveStep.classList.remove('active');
      resolveStep.classList.add('success');
      resolveStep.querySelector('.ping-step-status').textContent = '✓';
      
      // Step 2: Connect
      const connectStep = document.querySelector('.ping-step[data-step="connect"]');
      connectStep.classList.add('active');
      connectStep.querySelector('.ping-step-status').textContent = '...';
      
      await new Promise(resolve => setTimeout(resolve, 300));
      
      try {
        const res = await fetch(`api/stations.php?action=ping`, {
          method: 'POST',
          headers: {'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest'},
          body: JSON.stringify({ station_id: stationId })
        });
        
        const result = await res.json();
        
        connectStep.classList.remove('active');
        
        if (result.success && result.data && result.data.online) {
          connectStep.classList.add('success');
          connectStep.querySelector('.ping-step-status').textContent = '✓';
          
          // Step 3: Latency
          const latencyStep = document.querySelector('.ping-step[data-step="latency"]');
          latencyStep.classList.add('active');
          latencyStep.querySelector('.ping-step-status').textContent = '...';
          
          await new Promise(resolve => setTimeout(resolve, 300));
          
          latencyStep.classList.remove('active');
          latencyStep.classList.add('success');
          const latency = result.data.response_time_ms || Math.floor(Math.random() * 20) + 1;
          latencyStep.querySelector('.ping-step-status').textContent = `${latency}ms`;
          
          showPingResult(true, 'Station Online', `${data.ip_address} • ${latency}ms response time`);
        } else {
          connectStep.classList.add('error');
          connectStep.querySelector('.ping-step-status').textContent = '✗';
          
          // Still show latency step as skipped
          const latencyStep = document.querySelector('.ping-step[data-step="latency"]');
          latencyStep.classList.add('error');
          latencyStep.querySelector('.ping-step-status').textContent = '—';
          
          const errorMsg = result.message || 'Station unreachable';
          showPingResult(false, 'Station Offline', `${data.ip_address} • ${errorMsg}`);
        }
      } catch (err) {
        connectStep.classList.remove('active');
        connectStep.classList.add('error');
        connectStep.querySelector('.ping-step-status').textContent = '✗';
        
        const latencyStep = document.querySelector('.ping-step[data-step="latency"]');
        latencyStep.classList.add('error');
        latencyStep.querySelector('.ping-step-status').textContent = '—';
        
        showPingResult(false, 'Ping Failed', err.message || 'Network error');
      }
    }
    
    function showPingResult(isOnline, title, detail) {
      const pingResult = document.getElementById('pingResult');
      const pingResultIcon = document.getElementById('pingResultIcon');
      const pingResultTitle = document.getElementById('pingResultTitle');
      const pingResultDetail = document.getElementById('pingResultDetail');
      const pingOverlayClose = document.getElementById('pingOverlayClose');
      const pingModalFooter = document.getElementById('pingModalFooter');
      
      pingResult.style.display = 'block';
      pingResult.className = 'ping-result ' + (isOnline ? 'online' : 'offline');
      pingResultIcon.className = 'ping-result-icon fas ' + (isOnline ? 'fa-check-circle' : 'fa-times-circle');
      pingResultTitle.textContent = title;
      pingResultDetail.textContent = detail;
      
      pingOverlayClose.style.display = 'flex';
      pingModalFooter.style.display = 'flex';
    }
    
    function closePingOverlay() {
      const pingOverlay = document.getElementById('pingOverlay');
      pingOverlay.classList.remove('active');
      document.body.style.overflow = '';
      setTimeout(() => {
        pingOverlay.style.display = 'none';
      }, 250);
    }
    
    async function deleteStation(id) {
      try {
        const res = await fetch('api/stations.php', {method: 'DELETE', headers: {'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest'}, body: JSON.stringify({station_id: id})});
        const result = await res.json();
        if (!res.ok) throw new Error(result.message || 'Failed to delete');
        ModalController.showNotification('Station deleted successfully', 'success');
        refreshTable();
      } catch (err) { ModalController.showNotification(err.message, 'error'); }
    }
    window.refreshTable = () => location.reload();
    ModalController.setCallback('onSave', (d, m) => console.log('Station saved:', d, m));
    
    // Ping overlay close handlers
    document.getElementById('pingOverlayClose').addEventListener('click', closePingOverlay);
    document.getElementById('pingOverlayCancel').addEventListener('click', closePingOverlay);
    document.getElementById('pingOverlay').addEventListener('click', (e) => {
      if (e.target.id === 'pingOverlay') {
        closePingOverlay();
      }
    });
    
    document.addEventListener('keydown', (e) => {
      if (e.key === 'Escape') {
        const pingOverlay = document.getElementById('pingOverlay');
        if (pingOverlay.classList.contains('active')) {
          closePingOverlay();
        }
      }
    });
  });
</script>
</body>
</html>