<?php
/**
 * CTECHLIT & SYSTEMS SaaS Analytics Dashboard — Staff Dashboard
 * Staff member's personal dashboard
 */

require_once __DIR__ . '/../../includes/auth.php';
requireAuth();

$currentUser = currentStaff() ?: currentUser();
$staffStats = portalStaffDashboardStats();

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>My Dashboard — CTECHLIT & SYSTEMS</title>
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
      <!-- Welcome Section -->
      <div class="card glass-card animate-fade-in" style="margin-bottom: 20px; background: linear-gradient(135deg, rgba(124, 58, 237, 0.1), rgba(6, 182, 212, 0.1));">
        <div style="display: flex; align-items: center; gap: 16px;">
          <div style="width: 56px; height: 56px; border-radius: 50%; background: var(--violet); display: flex; align-items: center; justify-content: center; color: white; font-size: 24px; font-weight: 700;">
            <?php echo strtoupper(substr($currentUser['name'] ?? 'U', 0, 1)); ?>
          </div>
          <div>
            <h2 style="font-size: 20px; font-weight: 700; color: var(--ink); margin: 0;">Welcome back, <?php echo sanitize($currentUser['name'] ?? 'User'); ?>!</h2>
            <p style="font-size: 14px; color: var(--ink-secondary); margin: 4px 0 0;">Here's what's happening with your tasks today.</p>
          </div>
        </div>
      </div>

      <!-- KPI Cards -->
      <div class="kpi-grid" style="margin-bottom: 24px;">
        <div class="kpi-card animate-fade-in" style="--kpi-color: var(--violet); --kpi-bg: rgba(124, 58, 237, 0.1);">
          <div class="kpi-header">
            <div class="kpi-icon">
              <i class="fas fa-tasks"></i>
            </div>
            <span class="kpi-badge" style="background: rgba(124, 58, 237, 0.1); color: var(--violet);">+12%</span>
          </div>
          <div class="kpi-value"><?php echo number_format($staffStats['tasks_total']); ?></div>
          <div class="kpi-label">My Tasks</div>
        </div>

        <div class="kpi-card animate-fade-in" style="--kpi-color: var(--green); --kpi-bg: rgba(16, 185, 129, 0.1); animation-delay: 0.05s;">
          <div class="kpi-header">
            <div class="kpi-icon">
              <i class="fas fa-check-circle"></i>
            </div>
            <span class="kpi-badge" style="background: rgba(16, 185, 129, 0.1); color: var(--green);">DB</span>
          </div>
          <div class="kpi-value"><?php echo number_format($staffStats['tasks_completed']); ?></div>
          <div class="kpi-label">Completed</div>
        </div>

        <div class="kpi-card animate-fade-in" style="--kpi-color: var(--amber); --kpi-bg: rgba(245, 158, 11, 0.1); animation-delay: 0.1s;">
          <div class="kpi-header">
            <div class="kpi-icon">
              <i class="fas fa-clock"></i>
            </div>
            <span class="kpi-badge" style="background: rgba(245, 158, 11, 0.1); color: var(--amber);"><?php echo number_format($staffStats['tasks_overdue']); ?></span>
          </div>
          <div class="kpi-value"><?php echo number_format($staffStats['tasks_pending']); ?></div>
          <div class="kpi-label">Pending</div>
        </div>

        <div class="kpi-card animate-fade-in" style="--kpi-color: var(--cyan); --kpi-bg: rgba(6, 182, 212, 0.1); animation-delay: 0.15s;">
          <div class="kpi-header">
            <div class="kpi-icon">
              <i class="fas fa-star"></i>
            </div>
            <span class="kpi-badge" style="background: rgba(6, 182, 212, 0.1); color: var(--cyan);">%</span>
          </div>
          <div class="kpi-value"><?php echo number_format($staffStats['tasks_total'] > 0 ? ($staffStats['tasks_completed'] / $staffStats['tasks_total']) * 100 : 0, 1); ?></div>
          <div class="kpi-label">Performance</div>
        </div>
      </div>

      <!-- Recent Tasks -->
      <div class="card glass-card animate-fade-in" style="animation-delay: 0.2s;">
        <div class="card-header">
          <div>
            <div class="card-title">Recent Tasks</div>
            <div class="card-subtitle">Your latest assigned tasks</div>
          </div>
          <a href="tasks.php" class="header-filter" style="text-decoration: none; font-size: 13px;">View All</a>
        </div>

        <div class="table-wrapper">
          <table class="data-table">
            <thead>
              <tr>
                <th>Task</th>
                <th>Priority</th>
                <th>Status</th>
                <th>Due Date</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach (array_slice($staffStats['latest_tasks'], 0, 5) as $task): ?>
              <tr>
                <td>
                  <div style="display: flex; align-items: center; gap: 10px;">
                    <div style="width: 32px; height: 32px; border-radius: 8px; background: rgba(124, 58, 237, 0.1); display: flex; align-items: center; justify-content: center; color: var(--violet); font-size: 12px;">
                      <i class="fas fa-file-alt"></i>
                    </div>
                    <div>
                      <div style="font-weight: 600; color: var(--ink); font-size: 14px;"><?php echo sanitize($task['subject']); ?></div>
                      <div style="font-size: 12px; color: var(--muted);"><?php echo sanitize(ucfirst($task['priority'] ?? 'medium')); ?></div>
                    </div>
                  </div>
                </td>
                <td><span class="badge <?php echo $task['priority'] === 'high' ? 'badge-warning' : ($task['priority'] === 'urgent' ? 'badge-danger' : 'badge-info'); ?>"><?php echo sanitize(ucfirst($task['priority'] ?? 'medium')); ?></span></td>
                <td><span class="badge <?php echo $task['status'] === 'resolved' || $task['status'] === 'closed' ? 'badge-success' : ($task['status'] === 'in_progress' ? 'badge-info' : 'badge-warning'); ?>"><?php echo sanitize(str_replace('_', ' ', ucfirst($task['status'] ?? 'open'))); ?></span></td>
                <td style="font-size: 13px; color: var(--ink-secondary);"><?php echo sanitize(date('Y-m-d', strtotime($task['created_at']))); ?></td>
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





