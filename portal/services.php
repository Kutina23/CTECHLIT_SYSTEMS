<?php
/**
 * CTECHLIT & SYSTEMS SaaS Analytics Dashboard — Services
 * View and manage services
 */

require_once __DIR__ . '/../includes/auth.php';
requireAuth();

$currentUser = currentStaff() ?: currentUser();

try {
    $services = Database::fetchAll("SELECT service_id, name, category, description, base_price, price_unit, requires_station, estimated_duration, is_active FROM services ORDER BY service_id ASC");
} catch (Throwable $e) {
    logWarning('Unable to load portal services: ' . $e->getMessage());
    $services = [];
}

$serviceIcons = [
    'internet' => 'fa-globe',
    'printing' => 'fa-print',
    'scanning' => 'fa-file-image',
    'gaming' => 'fa-gamepad',
    'typing' => 'fa-keyboard',
    'mobile_money' => 'fa-mobile-screen-button',
    'consultation' => 'fa-comments',
    'other' => 'fa-concierge-bell',
];
$serviceColors = [
    'internet' => ['rgba(124, 58, 237, 0.1)', 'var(--violet)'],
    'printing' => ['rgba(236, 72, 153, 0.1)', 'var(--pink)'],
    'scanning' => ['rgba(16, 185, 129, 0.1)', 'var(--green)'],
    'gaming' => ['rgba(180, 63, 224, 0.1)', 'var(--pink)'],
    'typing' => ['rgba(6, 182, 212, 0.1)', 'var(--cyan)'],
    'mobile_money' => ['rgba(245, 158, 11, 0.1)', 'var(--amber)'],
    'consultation' => ['rgba(239, 68, 68, 0.1)', 'var(--red)'],
    'other' => ['rgba(100, 116, 139, 0.1)', 'var(--muted)'],
];
$priceUnitLabels = [
    'per_hour' => 'Per Hour',
    'per_minute' => 'Per Minute',
    'per_page' => 'Per Page',
    'per_job' => 'Per Job',
    'fixed' => 'Fixed',
];
$activeServices = count(array_filter($services, static fn($service) => (int) ($service['is_active'] ?? 0) === 1));
$stationServices = count(array_filter($services, static fn($service) => (int) ($service['requires_station'] ?? 0) === 1));
$serviceCategories = count(array_unique(array_column($services, 'category')));

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Services — CTECHLIT & SYSTEMS</title>
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
          <div class="kpi-header"><div class="kpi-icon"><i class="fas fa-concierge-bell"></i></div></div>
          <div class="kpi-value"><?php echo number_format(count($services)); ?></div><div class="kpi-label">Total Services</div>
        </div>
        <div class="kpi-card animate-fade-in" style="--kpi-color: var(--green); --kpi-bg: rgba(16, 185, 129, 0.1); animation-delay: 0.05s;">
          <div class="kpi-header"><div class="kpi-icon"><i class="fas fa-check"></i></div></div>
          <div class="kpi-value"><?php echo number_format($activeServices); ?></div><div class="kpi-label">Active</div>
        </div>
        <div class="kpi-card animate-fade-in" style="--kpi-color: var(--cyan); --kpi-bg: rgba(6, 182, 212, 0.1); animation-delay: 0.1s;">
          <div class="kpi-header"><div class="kpi-icon"><i class="fas fa-desktop"></i></div></div>
          <div class="kpi-value"><?php echo number_format($stationServices); ?></div><div class="kpi-label">Require Station</div>
        </div>
        <div class="kpi-card animate-fade-in" style="--kpi-color: var(--amber); --kpi-bg: rgba(245, 158, 11, 0.1); animation-delay: 0.15s;">
          <div class="kpi-header"><div class="kpi-icon"><i class="fas fa-tag"></i></div></div>
          <div class="kpi-value"><?php echo number_format($serviceCategories); ?></div><div class="kpi-label">Categories</div>
        </div>
      </div>

      <div class="card glass-card animate-fade-in" style="animation-delay: 0.2s;">
        <div class="card-header">
          <div><div class="card-title">All Services</div><div class="card-subtitle"><?php echo number_format(count($services)); ?> services available</div></div>
          <button class="btn btn-primary" id="addServiceBtn" style="padding: 10px 16px; font-size: 13px;"><i class="fas fa-plus"></i> Add Service</button>
        </div>

        <div class="table-container" style="border: none; background: transparent;">
          <table class="data-table" id="servicesTable">
            <thead>
              <tr><th>Service</th><th>Category</th><th>Price</th><th>Unit</th><th>Station</th><th>Status</th><th>Actions</th></tr>
            </thead>
            <tbody>
              <?php foreach ($services as $service): ?>
                <?php
                  $serviceId = (int) $service['service_id'];
                  $category = (string) ($service['category'] ?? 'other');
                  $categoryLabel = ucwords(str_replace('_', ' ', $category));
                  $serviceColor = $serviceColors[$category] ?? $serviceColors['other'];
                  $serviceJson = json_encode($service, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
                  $isActive = (int) ($service['is_active'] ?? 0) === 1;
                  $requiresStation = (int) ($service['requires_station'] ?? 0) === 1;
                ?>
                <tr data-id="<?php echo $serviceId; ?>" data-service='<?php echo htmlspecialchars($serviceJson ?: '{}', ENT_QUOTES, 'UTF-8'); ?>'>
                  <td><div class="table-product"><div class="table-product-img" style="background: <?php echo $serviceColor[0]; ?>; color: <?php echo $serviceColor[1]; ?>;"><i class="fas <?php echo $serviceIcons[$category] ?? $serviceIcons['other']; ?>"></i></div><div class="table-product-info"><span class="table-product-name"><?php echo sanitize((string) $service['name']); ?></span><span class="table-product-category"><?php echo sanitize($categoryLabel); ?></span></div></div></td>
                  <td><?php echo sanitize($categoryLabel); ?></td>
                  <td style="font-weight: 600;">GHS <?php echo number_format((float) $service['base_price'], 2); ?></td>
                  <td><?php echo sanitize($priceUnitLabels[$service['price_unit']] ?? ucwords(str_replace('_', ' ', (string) $service['price_unit']))); ?></td>
                  <td><span class="status-badge <?php echo $requiresStation ? 'info' : ''; ?>"><span class="status-dot"></span><?php echo $requiresStation ? 'Required' : 'Not Required'; ?></span></td>
                  <td><span class="status-badge <?php echo $isActive ? 'success' : 'warning'; ?>"><span class="status-dot"></span><?php echo $isActive ? 'Active' : 'Inactive'; ?></span></td>
                  <td><div class="action-buttons" style="display: flex; gap: 6px;"><button class="header-btn view-btn" title="View" data-action="view"><i class="fas fa-eye"></i></button><button class="header-btn edit-btn" title="Edit" data-action="edit"><i class="fas fa-edit"></i></button><button class="header-btn delete-btn" title="Delete" data-action="delete" style="color: var(--red);"><i class="fas fa-trash"></i></button></div></td>
                </tr>
              <?php endforeach; ?>
              <?php if (!$services): ?>
                <tr><td colspan="7" style="text-align: center; padding: 24px;">No services found.</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </main>
</div>

<button class="fab" id="fabAddService" aria-label="Add service"><i class="fas fa-plus"></i></button>

<?php include_once __DIR__ . '/../includes/modals.php'; ?>
<script src="../assets/js/modal-controller.js"></script>
<script>
  window.MODAL_CONFIGS = {
    service: {
      title: 'Service',
      fields: [
        {name: 'name', label: 'Service Name', type: 'text', required: true, placeholder: 'Enter service name'},
        {name: 'category', label: 'Category', type: 'select', required: true, options: [
          {value: 'internet', label: 'Internet'},
          {value: 'printing', label: 'Printing'},
          {value: 'scanning', label: 'Scanning'},
          {value: 'gaming', label: 'Gaming'},
          {value: 'typing', label: 'Typing'},
          {value: 'mobile_money', label: 'Mobile Money'},
          {value: 'consultation', label: 'Consultation'},
          {value: 'other', label: 'Other'},
        ]},
        {name: 'description', label: 'Description', type: 'textarea', required: false, placeholder: 'Service description'},
        {name: 'base_price', label: 'Base Price', type: 'number', required: true, step: '0.01', min: '0', placeholder: '0.00'},
        {name: 'price_unit', label: 'Price Unit', type: 'select', required: true, options: [
          {value: 'per_hour', label: 'Per Hour'},
          {value: 'per_minute', label: 'Per Minute'},
          {value: 'per_page', label: 'Per Page'},
          {value: 'per_job', label: 'Per Job'},
          {value: 'fixed', label: 'Fixed'},
        ]},
        {name: 'requires_station', label: 'Requires Station', type: 'checkbox', required: false},
        {name: 'estimated_duration', label: 'Estimated Duration (minutes)', type: 'number', required: false, min: '0', placeholder: '0'},
        {name: 'is_active', label: 'Active', type: 'checkbox', required: false},
      ],
      api: 'api/services.php',
      table: 'services',
      idField: 'service_id',
    }
  };

  const sidebar = document.getElementById('sidebar');
  const sidebarOverlay = document.getElementById('sidebarOverlay');
  function openSidebar() { sidebar.classList.add('open'); sidebarOverlay.classList.add('active'); document.body.style.overflow = 'hidden'; }
  function closeSidebar() { sidebar.classList.remove('open'); sidebarOverlay.classList.remove('active'); document.body.style.overflow = ''; }
  window.addEventListener('resize', () => { if (window.innerWidth > 768) closeSidebar(); });

  document.addEventListener('DOMContentLoaded', () => {
    const table = document.getElementById('servicesTable');
    const addBtn = document.getElementById('addServiceBtn');
    const fabBtn = document.getElementById('fabAddService');

    function openAddModal() { ModalController.open('service', 'create'); }
    addBtn.addEventListener('click', openAddModal);
    fabBtn.addEventListener('click', openAddModal);

    table.addEventListener('click', (e) => {
      const btn = e.target.closest('button[data-action]');
      if (!btn) return;
      const row = btn.closest('tr');
      const id = row.dataset.id;
      const data = JSON.parse(row.dataset.service);
      const action = btn.dataset.action;

      switch (action) {
        case 'view': ModalController.openView('service', data); break;
        case 'edit': ModalController.open('service', 'edit', data); break;
        case 'delete':
          ModalController.openConfirm({
            title: 'Delete Service', message: `Delete "${data.name}"?`, detail: 'This action cannot be undone.',
            icon: 'danger', confirmText: 'Delete', confirmClass: 'btn-danger',
            onConfirm: () => deleteService(id)
          });
          break;
      }
    });

    async function deleteService(id) {
      try {
        const res = await fetch('api/services.php', {method: 'DELETE', headers: {'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest'}, body: JSON.stringify({service_id: id})});
        const result = await res.json();
        if (!res.ok) throw new Error(result.message || 'Failed to delete');
        ModalController.showNotification('Service deleted successfully', 'success');
        refreshTable();
      } catch (err) { ModalController.showNotification(err.message, 'error'); }
    }
    window.refreshTable = () => location.reload();
    ModalController.setCallback('onSave', (d, m) => console.log('Service saved:', d, m));
  });
</script>
</body>
</html>