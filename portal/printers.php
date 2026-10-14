<?php
/**
 * CTECHLIT & SYSTEMS SaaS Analytics Dashboard — Printers
 * View and manage network printers
 */

require_once __DIR__ . '/../includes/auth.php';
requireAuth();

$currentUser = currentStaff() ?: currentUser();

// Load printers
$printers = [];
$printerStats = ['total' => 0, 'online' => 0, 'offline' => 0, 'error' => 0, 'maintenance' => 0];
try {
    $printers = Database::fetchAll(
        "SELECT p.peripheral_id, p.name, p.model, p.serial_number, p.status, p.config, p.installed_at,
                s.station_code, s.name AS station_name, s.location AS station_location
         FROM peripherals p
         LEFT JOIN stations s ON s.station_id = p.station_id
         WHERE p.peripheral_type = 'printer'
         ORDER BY p.name ASC"
    );

    foreach ($printers as &$printer) {
        $config = json_decode((string) $printer['config'], true);
        $printer['config'] = is_array($config) ? $config : [];
        $printer['ip_address'] = $printer['config']['ip_address'] ?? '';
        $printer['protocol'] = $printer['config']['protocol'] ?? 'ipp';
    }

    $printerStats['total'] = count($printers);
    $printerStats['online'] = count(array_filter($printers, fn($p) => $p['status'] === 'online'));
    $printerStats['offline'] = count(array_filter($printers, fn($p) => $p['status'] === 'offline'));
    $printerStats['error'] = count(array_filter($printers, fn($p) => $p['status'] === 'error'));
    $printerStats['maintenance'] = count(array_filter($printers, fn($p) => $p['status'] === 'maintenance'));
} catch (Throwable $e) {
    logWarning('Unable to load printers: ' . $e->getMessage());
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Printers — CTECHLIT & SYSTEMS</title>
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
            <div class="kpi-icon"><i class="fas fa-print"></i></div>
          </div>
          <div class="kpi-value"><?php echo $printerStats['total']; ?></div>
          <div class="kpi-label">Total Printers</div>
        </div>
        <div class="kpi-card animate-fade-in" style="--kpi-color: var(--green); --kpi-bg: rgba(16, 185, 129, 0.1); animation-delay: 0.05s;">
          <div class="kpi-header">
            <div class="kpi-icon"><i class="fas fa-check-circle"></i></div>
          </div>
          <div class="kpi-value"><?php echo $printerStats['online']; ?></div>
          <div class="kpi-label">Online</div>
        </div>
        <div class="kpi-card animate-fade-in" style="--kpi-color: var(--amber); --kpi-bg: rgba(245, 158, 11, 0.1); animation-delay: 0.1s;">
          <div class="kpi-header">
            <div class="kpi-icon"><i class="fas fa-exclamation-triangle"></i></div>
          </div>
          <div class="kpi-value"><?php echo $printerStats['error']; ?></div>
          <div class="kpi-label">Errors</div>
        </div>
        <div class="kpi-card animate-fade-in" style="--kpi-color: var(--cyan); --kpi-bg: rgba(6, 182, 212, 0.1); animation-delay: 0.15s;">
          <div class="kpi-header">
            <div class="kpi-icon"><i class="fas fa-tools"></i></div>
          </div>
          <div class="kpi-value"><?php echo $printerStats['maintenance']; ?></div>
          <div class="kpi-label">Maintenance</div>
        </div>
      </div>

      <div class="card glass-card animate-fade-in" style="animation-delay: 0.2s;">
        <div class="card-header">
          <div>
            <div class="card-title">All Printers</div>
            <div class="card-subtitle"><?php echo $printerStats['total']; ?> network printers</div>
          </div>
          <button class="btn btn-primary" id="addPrinterBtn" style="padding: 10px 16px; font-size: 13px;">
            <i class="fas fa-plus"></i> Add Printer
          </button>
        </div>

        <div class="table-container" style="border: none; background: transparent;">
          <table class="data-table" id="printersTable">
            <thead>
              <tr>
                <th>Printer</th>
                <th>Model</th>
                <th>Station</th>
                <th>Location</th>
                <th>IP Address</th>
                <th>Status</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($printers as $printer): ?>
                <?php
                  $statusClass = match($printer['status']) {
                    'online' => 'success',
                    'offline' => 'danger',
                    'error' => 'danger',
                    'maintenance' => 'warning',
                    default => 'muted',
                  };
                  $rowData = [
                    'peripheral_id' => $printer['peripheral_id'],
                    'peripheral_type' => 'printer',
                    'name' => $printer['name'],
                    'model' => $printer['model'],
                    'serial_number' => $printer['serial_number'],
                    'station_id' => $printer['station_code'] ? null : null,
                    'status' => $printer['status'],
                    'station_code' => $printer['station_code'],
                    'station_name' => $printer['station_name'],
                    'location' => $printer['station_location'],
                    'ip_address' => $printer['ip_address'],
                    'protocol' => $printer['protocol'],
                    'mac_address' => $printer['mac_address'] ?? '',
                    'config' => $printer['config'],
                  ];
                ?>
                <tr data-id="<?php echo $printer['peripheral_id']; ?>" data-printer='<?php echo htmlspecialchars(json_encode($rowData), ENT_QUOTES, 'UTF-8'); ?>'>
                  <td>
                    <div style="display: flex; align-items: center; gap: 10px;">
                      <div class="device-icon" style="width: 32px; height: 32px; border-radius: var(--radius-sm); background: rgba(124, 58, 237, 0.10); border: 1px solid rgba(124, 58, 237, 0.25); display: flex; align-items: center; justify-content: center; color: var(--violet); font-size: 13px; flex-shrink: 0;">
                        <i class="fas fa-print"></i>
                      </div>
                      <div>
                        <div style="font-weight: 600; color: var(--ink); font-size: 13px;"><?php echo sanitize($printer['name']); ?></div>
                        <?php if ($printer['serial_number']): ?>
                          <div style="font-size: 11px; color: var(--muted); font-family: 'SF Mono', Monaco, monospace;"><?php echo sanitize($printer['serial_number']); ?></div>
                        <?php endif; ?>
                      </div>
                    </div>
                  </td>
                  <td style="font-size: 12px; color: var(--ink-secondary);"><?php echo sanitize($printer['model'] ?? '—'); ?></td>
                  <td style="font-size: 12px; color: var(--ink-secondary);"><?php echo sanitize($printer['station_code'] ?? '—'); ?></td>
                  <td style="font-size: 12px; color: var(--ink-secondary);"><?php echo sanitize($printer['station_location'] ?? '—'); ?></td>
                  <td>
                    <?php if ($printer['ip_address']): ?>
                      <code style="background: var(--bg-secondary); padding: 2px 6px; border-radius: var(--radius-sm); font-size: 11px; font-family: 'SF Mono', Monaco, monospace;"><?php echo sanitize($printer['ip_address']); ?></code>
                    <?php else: ?>
                      <span style="font-size: 11px; color: var(--muted);">Not configured</span>
                    <?php endif; ?>
                  </td>
                  <td><span class="status-badge <?php echo $statusClass; ?>"><span class="status-dot"></span><?php echo ucfirst($printer['status']); ?></span></td>
                  <td><div class="action-buttons" style="display: flex; gap: 6px; flex-wrap: wrap;">
                    <button class="header-btn" title="Wake Printer" data-action="wake" style="color: var(--green);"><i class="fas fa-power-off"></i></button>
                    <button class="header-btn" title="Test Connection" data-action="test" style="color: var(--cyan);"><i class="fas fa-wifi"></i></button>
                    <button class="header-btn" title="Configure" data-action="configure" style="color: var(--amber);"><i class="fas fa-cog"></i></button>
                    <button class="header-btn view-btn" title="View" data-action="view"><i class="fas fa-eye"></i></button>
                    <button class="header-btn edit-btn" title="Edit" data-action="edit"><i class="fas fa-edit"></i></button>
                    <button class="header-btn delete-btn" title="Delete" data-action="delete" style="color: var(--red);"><i class="fas fa-trash"></i></button>
                  </div></td>
                </tr>
              <?php endforeach; ?>
              <?php if (empty($printers)): ?>
                <tr>
                  <td colspan="7" style="text-align: center; padding: 40px; color: var(--muted);">
                    <i class="fas fa-print" style="font-size: 32px; margin-bottom: 12px; display: block; opacity: 0.5;"></i>
                    No printers configured. Click "Add Printer" to add one.
                  </td>
                </tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </main>

<?php include_once __DIR__ . '/../includes/modals.php'; ?>
<script src="../assets/js/modal-controller.js"></script>
<script>
  window.MODAL_CONFIGS = {
    printer: {
      title: 'Printer',
      fields: [
        {name: 'peripheral_type', label: 'Type', type: 'hidden', default: 'printer'},
        {name: 'name', label: 'Printer Name', type: 'text', required: true, placeholder: 'e.g., HP LaserJet Pro'},
        {name: 'model', label: 'Model', type: 'text', required: false, placeholder: 'e.g., MFP M428fdw'},
        {name: 'serial_number', label: 'Serial Number', type: 'text', required: false, placeholder: 'e.g., SN123456'},
        {name: 'station_id', label: 'Attached Station', type: 'select', required: false, options: [], ajax: 'api/stations.php?list=1'},
        {name: 'status', label: 'Status', type: 'select', required: true, options: [
          {value: 'online', label: 'Online'},
          {value: 'offline', label: 'Offline'},
          {value: 'error', label: 'Error'},
          {value: 'maintenance', label: 'Maintenance'},
        ]},
        {name: 'ip_address', label: 'IP Address', type: 'text', required: false, placeholder: '192.168.1.201'},
        {name: 'mac_address', label: 'MAC Address', type: 'text', required: false, placeholder: 'AA:BB:CC:DD:EE:FF'},
        {name: 'protocol', label: 'Protocol', type: 'select', required: false, options: [
          {value: 'ipp', label: 'IPP'},
          {value: 'lpd', label: 'LPD'},
        ]},
      ],
      api: 'api/peripherals.php',
      table: 'peripherals',
      idField: 'peripheral_id',
    }
  };

  const sidebar = document.getElementById('sidebar');
  const sidebarOverlay = document.getElementById('sidebarOverlay');
  function openSidebar() { sidebar.classList.add('open'); sidebarOverlay.classList.add('active'); document.body.style.overflow = 'hidden'; }
  function closeSidebar() { sidebar.classList.remove('open'); sidebarOverlay.classList.remove('active'); document.body.style.overflow = ''; }
  window.addEventListener('resize', () => { if (window.innerWidth > 768) closeSidebar(); });

  document.addEventListener('DOMContentLoaded', () => {
    const table = document.getElementById('printersTable');
    const addBtn = document.getElementById('addPrinterBtn');

    function openAddModal() { ModalController.open('printer', 'create'); }
    addBtn.addEventListener('click', openAddModal);

    table.addEventListener('click', async (e) => {
      const btn = e.target.closest('button[data-action]');
      if (!btn) return;
      const row = btn.closest('tr');
      const id = row.dataset.id;
      const data = JSON.parse(row.dataset.printer);
      const action = btn.dataset.action;

      switch (action) {
        case 'view':
          ModalController.openView('printer', data);
          break;
        case 'edit':
          ModalController.open('printer', 'edit', data);
          break;
        case 'delete':
          ModalController.openConfirm({
            title: 'Delete Printer', message: `Delete "${data.name}"?`, detail: 'This action cannot be undone.',
            icon: 'danger', confirmText: 'Delete', confirmClass: 'btn-danger',
            onConfirm: () => deletePrinter(id)
          });
          break;
        case 'wake':
          await wakePrinter(id, data);
          break;
        case 'test':
          await testPrinter(id, data);
          break;
        case 'configure':
          ModalController.open('printer', 'edit', data);
          break;
      }
    });

    async function deletePrinter(id) {
      try {
        const res = await fetch('api/peripherals.php', {method: 'DELETE', headers: {'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest'}, body: JSON.stringify({peripheral_id: id})});
        const result = await res.json();
        if (!res.ok) throw new Error(result.message || 'Failed to delete');
        ModalController.showNotification('Printer deleted successfully', 'success');
        setTimeout(() => location.reload(), 800);
      } catch (err) { ModalController.showNotification(err.message, 'error'); }
    }

    async function wakePrinter(id, data) {
      try {
        ModalController.showNotification('Sending Wake-on-LAN to printer...', 'info');
        const res = await fetch('api/printers.php?action=wake', {
          method: 'POST',
          headers: {'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest'},
          body: JSON.stringify({peripheral_id: id})
        });
        const result = await res.json();
        if (result.success) {
          ModalController.showNotification(result.message || 'Printer wake signal sent', 'success');
        } else {
          ModalController.showNotification(result.message || 'Failed to wake printer', 'error');
        }
      } catch (err) {
        ModalController.showNotification(err.message, 'error');
      }
    }

    async function testPrinter(id, data) {
      try {
        ModalController.showNotification('Testing printer connection...', 'info');
        const res = await fetch('api/printers.php?action=test', {
          method: 'POST',
          headers: {'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest'},
          body: JSON.stringify({peripheral_id: id})
        });
        const result = await res.json();
        if (result.success && result.data && result.data.reachable) {
          ModalController.showNotification(`Printer reachable — ${result.data.status || 'OK'}`, 'success');
        } else {
          ModalController.showNotification(result.message || 'Printer not reachable', 'error');
        }
      } catch (err) {
        ModalController.showNotification(err.message, 'error');
      }
    }

    window.refreshTable = () => location.reload();
    ModalController.setCallback('onSave', (d, m) => console.log('Printer saved:', d, m));
  });
</script>
</body>
</html>
