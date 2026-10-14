<?php
/**
 * CTECHLIT & SYSTEMS SaaS Analytics Dashboard — Staff Tasks
 * Staff member's task management page
 */

require_once __DIR__ . '/../../includes/auth.php';
requireAuth();

$currentUser = currentStaff() ?: currentUser();
$taskStats = portalStaffDashboardStats();
$tasks = $taskStats['latest_tasks'];
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfToken = $_POST['csrf_token'] ?? '';
    if (!verifyCsrfToken($csrfToken)) {
        $error = 'Invalid CSRF token';
    } else {
        $message = 'Task updated successfully';
    }
}

$csrfToken = generateCsrfToken();

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>My Tasks — CTECHLIT & SYSTEMS</title>
  <link rel="icon" type="image/svg+xml" href="../../assets/images/logo-icon.svg">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="stylesheet" href="../../assets/css/portal.css">
</head>
<body>
  <?php include_once __DIR__ . '/../../includes/sidebar.php'; ?>
  <!-- Main Content -->
  <main class="portal-main" style="margin-left: var(--sidebar-width);">
    <!-- Header -->
    <?php include_once __DIR__ . '/../../includes/header.php'; ?>

    <!-- Dashboard Content -->
    <div class="portal-content">
      <?php if ($message): ?>
        <div class="card animate-fade-in" style="background: rgba(16, 185, 129, 0.1); border: 1px solid rgba(16, 185, 129, 0.2); color: var(--green); margin-bottom: 20px; padding: 14px 20px; border-radius: var(--radius-lg);">
          <i class="fas fa-check-circle" style="margin-right: 8px;"></i>
          <?php echo sanitize($message); ?>
        </div>
      <?php endif; ?>

      <?php if ($error): ?>
        <div class="card animate-fade-in" style="background: rgba(239, 68, 68, 0.1); border: 1px solid rgba(239, 68, 68, 0.2); color: var(--red); margin-bottom: 20px; padding: 14px 20px; border-radius: var(--radius-lg);">
          <i class="fas fa-exclamation-circle" style="margin-right: 8px;"></i>
          <?php echo sanitize($error); ?>
        </div>
      <?php endif; ?>

      <!-- Task Stats -->
      <div class="kpi-grid" style="margin-bottom: 24px;">
        <div class="kpi-card animate-fade-in" style="--kpi-color: var(--violet); --kpi-bg: rgba(124, 58, 237, 0.1);">
          <div class="kpi-header">
            <div class="kpi-icon">
              <i class="fas fa-list"></i>
            </div>
          </div>
          <div class="kpi-value"><?php echo number_format($taskStats['tasks_total']); ?></div>
          <div class="kpi-label">Total Tasks</div>
        </div>

        <div class="kpi-card animate-fade-in" style="--kpi-color: var(--amber); --kpi-bg: rgba(245, 158, 11, 0.1); animation-delay: 0.05s;">
          <div class="kpi-header">
            <div class="kpi-icon">
              <i class="fas fa-spinner"></i>
            </div>
          </div>
          <div class="kpi-value"><?php echo number_format($taskStats['tasks_pending']); ?></div>
          <div class="kpi-label">In Progress</div>
        </div>

        <div class="kpi-card animate-fade-in" style="--kpi-color: var(--green); --kpi-bg: rgba(16, 185, 129, 0.1); animation-delay: 0.1s;">
          <div class="kpi-header">
            <div class="kpi-icon">
              <i class="fas fa-check"></i>
            </div>
          </div>
          <div class="kpi-value"><?php echo number_format($taskStats['tasks_completed']); ?></div>
          <div class="kpi-label">Completed</div>
        </div>

        <div class="kpi-card animate-fade-in" style="--kpi-color: var(--red); --kpi-bg: rgba(239, 68, 68, 0.1); animation-delay: 0.15s;">
          <div class="kpi-header">
            <div class="kpi-icon">
              <i class="fas fa-exclamation-triangle"></i>
            </div>
          </div>
          <div class="kpi-value"><?php echo number_format($taskStats['tasks_overdue']); ?></div>
          <div class="kpi-label">Overdue</div>
        </div>
      </div>

      <!-- Tasks Table -->
      <div class="card glass-card animate-fade-in" style="animation-delay: 0.2s;">
        <div class="card-header">
          <div>
            <div class="card-title">My Tasks</div>
            <div class="card-subtitle">All assigned tasks</div>
          </div>
          <div style="display: flex; gap: 8px;">
            <select class="header-filter" style="padding: 6px 12px; font-size: 13px;">
              <option>All Status</option>
              <option>Pending</option>
              <option>In Progress</option>
              <option>Completed</option>
            </select>
          </div>
        </div>

        <div class="table-wrapper">
          <table class="data-table">
            <thead>
              <tr>
                <th>Task</th>
                <th>Priority</th>
                <th>Status</th>
                <th>Due Date</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($tasks as $task): ?>
              <tr>
                <td>
                  <div style="display: flex; align-items: center; gap: 10px;">
                    <div style="width: 32px; height: 32px; border-radius: 8px; background: rgba(124, 58, 237, 0.1); display: flex; align-items: center; justify-content: center; color: var(--violet); font-size: 12px;">
                      <i class="fas fa-file-alt"></i>
                    </div>
                    <div>
                      <div style="font-weight: 600; color: var(--ink); font-size: 14px;"><?php echo sanitize($task['subject']); ?></div>
                      <div style="font-size: 12px; color: var(--muted);"><?php echo sanitize($task['category'] ?? 'General'); ?></div>
                    </div>
                  </div>
                </td>
                <td><span class="badge <?php echo ($task['priority'] ?? 'medium') === 'high' ? 'badge-warning' : (($task['priority'] ?? 'medium') === 'urgent' ? 'badge-danger' : 'badge-info'); ?>"><?php echo sanitize(ucfirst($task['priority'] ?? 'medium')); ?></span></td>
                <td><span class="badge <?php echo ($task['status'] ?? 'open') === 'resolved' || ($task['status'] ?? 'open') === 'closed' ? 'badge-success' : (($task['status'] ?? 'open') === 'in_progress' ? 'badge-info' : 'badge-warning'); ?>"><?php echo sanitize(str_replace('_', ' ', ucfirst($task['status'] ?? 'open'))); ?></span></td>
                <td style="font-size: 13px; color: var(--ink-secondary);"><?php echo sanitize(date('Y-m-d', strtotime($task['created_at']))); ?></td>
                <td>
                  <button class="header-filter" style="padding: 4px 10px; font-size: 12px;">
                    <i class="fas fa-check"></i>
                  </button>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </main>
</div>

<!-- Floating Action Button -->
<button class="fab" aria-label="Add new task">
  <i class="fas fa-plus"></i>
</button>

<script>
  // Sidebar Toggle
  const sidebar = document.getElementById('sidebar');
  const sidebarOverlay = document.getElementById('sidebarOverlay');

  function openSidebar() {
    sidebar.classList.add('open');
    sidebarOverlay.classList.add('active');
    document.body.style.overflow = 'hidden';
  }

  function closeSidebar() {
    sidebar.classList.remove('open');
    sidebarOverlay.classList.remove('active');
    document.body.style.overflow = '';
  }

  window.addEventListener('resize', () => {
    if (window.innerWidth > 768) {
      closeSidebar();
    }
  });
</script>
</body>
</html>





