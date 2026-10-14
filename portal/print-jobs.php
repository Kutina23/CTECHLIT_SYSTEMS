<?php
/**
 * CTECHLIT & SYSTEMS — Print Jobs
 * Print queue and job monitoring
 */

require_once __DIR__ . '/../includes/auth.php';
requireAuth();

$currentUser = currentStaff() ?: currentUser();
$pendingJobsPage = $pendingJobsPage ?? false;
$printJobs = portalPrintJobs(50, $pendingJobsPage ? 'queued' : null);
$totalJobs = (int) Database::fetchColumn("SELECT COUNT(*) FROM print_jobs");
$queuedJobs = (int) Database::fetchColumn("SELECT COUNT(*) FROM print_jobs WHERE status = 'queued'");
$completedJobs = (int) Database::fetchColumn("SELECT COUNT(*) FROM print_jobs WHERE status = 'completed'");
$avgWait = (int) Database::fetchColumn("SELECT COALESCE(AVG(TIMESTAMPDIFF(MINUTE, created_at, started_at)), 0) FROM print_jobs WHERE started_at IS NOT NULL");
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?php echo $pendingJobsPage ? 'Pending Jobs' : 'Print Jobs'; ?> — CTECHLIT & SYSTEMS</title>
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
          <div class="kpi-header"><div class="kpi-icon"><i class="fas fa-print"></i></div></div>
          <div class="kpi-value"><?php echo number_format($totalJobs); ?></div>
          <div class="kpi-label">Total Jobs</div>
        </div>

        <div class="kpi-card animate-fade-in" style="--kpi-color: var(--amber); --kpi-bg: rgba(245, 158, 11, 0.1); animation-delay: 0.05s;">
          <div class="kpi-header"><div class="kpi-icon"><i class="fas fa-spinner"></i></div></div>
          <div class="kpi-value"><?php echo number_format($queuedJobs); ?></div>
          <div class="kpi-label">Queued</div>
        </div>

        <div class="kpi-card animate-fade-in" style="--kpi-color: var(--green); --kpi-bg: rgba(16, 185, 129, 0.1); animation-delay: 0.1s;">
          <div class="kpi-header"><div class="kpi-icon"><i class="fas fa-check"></i></div></div>
          <div class="kpi-value"><?php echo number_format($completedJobs); ?></div>
          <div class="kpi-label">Completed</div>
        </div>

        <div class="kpi-card animate-fade-in" style="--kpi-color: var(--cyan); --kpi-bg: rgba(6, 182, 212, 0.1); animation-delay: 0.15s;">
          <div class="kpi-header"><div class="kpi-icon"><i class="fas fa-clock"></i></div></div>
          <div class="kpi-value"><?php echo $avgWait; ?>m</div>
          <div class="kpi-label">Avg Wait</div>
        </div>
      </div>

      <div class="card glass-card animate-fade-in" style="animation-delay: 0.2s;">
        <div class="card-header">
          <div>
            <div class="card-title"><?php echo $pendingJobsPage ? 'Pending Print Jobs' : 'Print Queue'; ?></div>
            <div class="card-subtitle"><?php echo $pendingJobsPage ? 'Jobs waiting to be printed' : 'Current and recent print requests'; ?></div>
          </div>
          <button class="btn btn-primary" id="addPrintJobBtn" style="padding: 10px 16px; font-size: 13px;">
            <i class="fas fa-plus"></i> New Job
          </button>
        </div>

        <div class="table-container" style="border: none; background: transparent;">
          <table class="data-table" id="printJobsTable">
            <thead>
              <tr>
                <th>Job ID</th>
                <th>Customer</th>
                <th>Printer</th>
                <th>Pages</th>
                <th>Type</th>
                <th>Status</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($printJobs as $job): ?>
              <tr data-id="<?php echo $job['print_job_id']; ?>" data-printjob='<?php echo json_encode($job, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>'>
                <td><?php echo sanitize('#PR-' . $job['print_job_id']); ?></td>
                <td><?php echo sanitize($job['user_name'] ?? 'Unknown'); ?></td>
                <td><?php echo sanitize($job['printer_name'] ?? 'N/A'); ?></td>
                <td><?php echo number_format($job['pages'] * $job['copies']); ?></td>
                <td><?php echo sanitize($job['color_mode'] === 'color' ? 'Color' : 'Black & White'); ?></td>
                <td><span class="status-badge <?php echo $job['status'] === 'queued' ? 'warning' : ($job['status'] === 'printing' ? 'info' : ($job['status'] === 'completed' ? 'success' : ($job['status'] === 'failed' ? 'danger' : ($job['status'] === 'cancelled' ? 'secondary' : 'info')))); ?>">
                  <span class="status-dot"></span><?php echo sanitize(ucfirst($job['status'])); ?>
                </span></td>
                <td><div class="action-buttons" style="display: flex; gap: 6px;"><button class="header-btn view-btn" title="View" data-action="view"><i class="fas fa-eye"></i></button><button class="header-btn edit-btn" title="Edit" data-action="edit"><i class="fas fa-edit"></i></button></div></td>
              </tr>
              <?php endforeach; ?>
              <?php if (empty($printJobs)): ?>
              <tr>
                <td colspan="7" style="text-align: center; padding: 24px; color: var(--ink-secondary);"><?php echo $pendingJobsPage ? 'No pending print jobs' : 'No print jobs found'; ?></td>
              </tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </main>
</div>

<?php include_once __DIR__ . '/../includes/modals.php'; ?>
<script src="../assets/js/modal-controller.js"></script>
<script>
  window.MODAL_CONFIGS = {
    print_job: {
      title: 'Print Job',
      fields: [
        {name: 'user_id', label: 'Customer', type: 'select', required: true, options: [], ajax: 'api/users.php?list=1'},
        {name: 'station_id', label: 'Station', type: 'select', required: true, options: [], ajax: 'api/stations.php?list=1'},
        {name: 'peripheral_id', label: 'Printer', type: 'select', required: true, options: [], ajax: 'api/peripherals.php?list=1&type=printer'},
        {name: 'file', label: 'Select File From Device', type: 'file', requiredOnCreate: true, accept: '.pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt,.rtf,.jpg,.jpeg,.png'},
        {name: 'pages', label: 'Pages', type: 'number', required: true, min: '1', placeholder: '1'},
        {name: 'copies', label: 'Copies', type: 'number', required: true, min: '1', placeholder: '1'},
        {name: 'color_mode', label: 'Color Mode', type: 'select', required: true, options: [
          {value: 'bw', label: 'Black & White'},
          {value: 'color', label: 'Color'},
        ]},
        {name: 'duplex', label: 'Double-sided', type: 'checkbox', required: false},
        {name: 'priority', label: 'Priority', type: 'number', required: false, min: '0', max: '10', placeholder: '0'},
      ],
      api: 'api/print-jobs.php',
      table: 'print_jobs',
      idField: 'print_job_id',
    }
  };

  const sidebar = document.getElementById('sidebar');
  const sidebarOverlay = document.getElementById('sidebarOverlay');
  function openSidebar() { sidebar.classList.add('open'); sidebarOverlay.classList.add('active'); document.body.style.overflow = 'hidden'; }
  function closeSidebar() { sidebar.classList.remove('open'); sidebarOverlay.classList.remove('active'); document.body.style.overflow = ''; }
  window.addEventListener('resize', () => { if (window.innerWidth > 768) closeSidebar(); });

  document.addEventListener('DOMContentLoaded', () => {
    const table = document.getElementById('printJobsTable');
    const addBtn = document.getElementById('addPrintJobBtn');

    function openAddModal() { ModalController.open('print_job', 'create'); }
    addBtn.addEventListener('click', openAddModal);

    table.addEventListener('click', (e) => {
      const btn = e.target.closest('button[data-action]');
      if (!btn) return;
      const row = btn.closest('tr');
      const id = row.dataset.id;
      const data = JSON.parse(row.dataset.printjob);
      const action = btn.dataset.action;

      switch (action) {
        case 'view': ModalController.openView('print_job', data); break;
        case 'edit': ModalController.open('print_job', 'edit', data); break;
      }
    });

    window.refreshTable = () => location.reload();
    ModalController.setCallback('onSave', (d, m) => console.log('Print job saved:', d, m));
  });
</script>
</body>
</html>