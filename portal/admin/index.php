<?php
/**
 * CTECHLIT & SYSTEMS SaaS Analytics Dashboard — Admin Dashboard
 * Admin overview with system stats and management shortcuts
 */

require_once __DIR__ . '/../../includes/auth.php';
requirePermission('dashboard.view');

$currentUser = currentStaff() ?: currentUser();
$isAdmin = hasPermission('staff.assign_permissions');
$adminStats = portalSummaryStats();
$adminActivity = portalAdminActivity(5);

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Admin Dashboard — CTECHLIT & SYSTEMS</title>
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
      <!-- KPI Cards -->
      <div class="kpi-grid">
        <div class="kpi-card animate-fade-in" style="--kpi-color: var(--violet); --kpi-bg: rgba(124, 58, 237, 0.1);">
          <div class="kpi-header">
            <div class="kpi-icon">
              <i class="fas fa-users"></i>
            </div>
            <div class="kpi-trend up">
              <i class="fas fa-arrow-up"></i>
              <span>5.2%</span>
            </div>
          </div>
          <div class="kpi-value"><?php echo number_format($adminStats['total_staff']); ?></div>
          <div class="kpi-label">Total Staff</div>
        </div>

        <div class="kpi-card animate-fade-in" style="--kpi-color: var(--green); --kpi-bg: rgba(16, 185, 129, 0.1); animation-delay: 0.05s;">
          <div class="kpi-header">
            <div class="kpi-icon">
              <i class="fas fa-user-check"></i>
            </div>
            <div class="kpi-trend up">
              <i class="fas fa-arrow-up"></i>
              <span>Live</span>
            </div>
          </div>
          <div class="kpi-value"><?php echo number_format($adminStats['total_staff']); ?></div>
          <div class="kpi-label">Active Staff</div>
        </div>

        <div class="kpi-card animate-fade-in" style="--kpi-color: var(--cyan); --kpi-bg: rgba(6, 182, 212, 0.1); animation-delay: 0.1s;">
          <div class="kpi-header">
            <div class="kpi-icon">
              <i class="fas fa-desktop"></i>
            </div>
            <div class="kpi-trend up">
              <i class="fas fa-arrow-up"></i>
              <span>Now</span>
            </div>
          </div>
          <div class="kpi-value"><?php echo number_format($adminStats['active_stations']); ?></div>
          <div class="kpi-label">Active Stations</div>
        </div>

        <div class="kpi-card animate-fade-in" style="--kpi-color: var(--amber); --kpi-bg: rgba(245, 158, 11, 0.1); animation-delay: 0.15s;">
          <div class="kpi-header">
            <div class="kpi-icon">
              <i class="fas fa-clock"></i>
            </div>
            <div class="kpi-trend down">
              <i class="fas fa-arrow-down"></i>
              <span><?php echo number_format($adminStats['active_sessions']); ?></span>
            </div>
          </div>
          <div class="kpi-value"><?php echo number_format($adminStats['active_sessions']); ?></div>
          <div class="kpi-label">Active Sessions</div>
        </div>
      </div>

      <!-- Quick Actions -->
      <div class="dashboard-grid">
        <div class="card glass-card animate-fade-in" style="animation-delay: 0.2s;">
          <div class="card-header">
            <div>
              <div class="card-title">Quick Actions</div>
              <div class="card-subtitle">Common administrative tasks</div>
            </div>
          </div>
          <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 12px; margin-top: 16px;">
            <a href="staff.php?action=create" class="card" style="text-align: center; padding: 20px; text-decoration: none; border: 1px dashed var(--border);">
              <div style="width: 48px; height: 48px; border-radius: 50%; background: rgba(124, 58, 237, 0.1); color: var(--violet); display: flex; align-items: center; justify-content: center; margin: 0 auto 10px; font-size: 20px;">
                <i class="fas fa-user-plus"></i>
              </div>
              <div style="font-weight: 600; font-size: 14px; color: var(--ink);">Add Staff</div>
              <div style="font-size: 12px; color: var(--muted); margin-top: 2px;">Create new account</div>
            </a>
            <a href="roles.php" class="card" style="text-align: center; padding: 20px; text-decoration: none; border: 1px dashed var(--border);">
              <div style="width: 48px; height: 48px; border-radius: 50%; background: rgba(6, 182, 212, 0.1); color: var(--cyan); display: flex; align-items: center; justify-content: center; margin: 0 auto 10px; font-size: 20px;">
                <i class="fas fa-key"></i>
              </div>
              <div style="font-weight: 600; font-size: 14px; color: var(--ink);">Permissions</div>
              <div style="font-size: 12px; color: var(--muted); margin-top: 2px;">Manage roles</div>
            </a>
            <a href="reports.php" class="card" style="text-align: center; padding: 20px; text-decoration: none; border: 1px dashed var(--border);">
              <div style="width: 48px; height: 48px; border-radius: 50%; background: rgba(16, 185, 129, 0.1); color: var(--green); display: flex; align-items: center; justify-content: center; margin: 0 auto 10px; font-size: 20px;">
                <i class="fas fa-chart-bar"></i>
              </div>
              <div style="font-weight: 600; font-size: 14px; color: var(--ink);">Reports</div>
              <div style="font-size: 12px; color: var(--muted); margin-top: 2px;">View analytics</div>
            </a>
            <a href="settings.php" class="card" style="text-align: center; padding: 20px; text-decoration: none; border: 1px dashed var(--border);">
              <div style="width: 48px; height: 48px; border-radius: 50%; background: rgba(245, 158, 11, 0.1); color: var(--amber); display: flex; align-items: center; justify-content: center; margin: 0 auto 10px; font-size: 20px;">
                <i class="fas fa-cog"></i>
              </div>
              <div style="font-weight: 600; font-size: 14px; color: var(--ink);">Settings</div>
              <div style="font-size: 12px; color: var(--muted); margin-top: 2px;">System config</div>
            </a>
          </div>
        </div>

        <!-- System Status -->
        <div class="card glass-card animate-fade-in" style="animation-delay: 0.25s;">
          <div class="card-header">
            <div>
              <div class="card-title">System Status</div>
              <div class="card-subtitle">Real-time system health</div>
            </div>
          </div>
          <div style="display: flex; flex-direction: column; gap: 16px; margin-top: 16px;">
            <div style="display: flex; align-items: center; justify-content: space-between;">
              <div style="display: flex; align-items: center; gap: 10px;">
                <div style="width: 8px; height: 8px; border-radius: 50%; background: var(--green);"></div>
                <span style="font-size: 13px; color: var(--ink);">Database</span>
              </div>
              <span class="status-badge success"><span class="status-dot"></span>Online</span>
            </div>
            <div style="display: flex; align-items: center; justify-content: space-between;">
              <div style="display: flex; align-items: center; gap: 10px;">
                <div style="width: 8px; height: 8px; border-radius: 50%; background: var(--green);"></div>
                <span style="font-size: 13px; color: var(--ink);">API Server</span>
              </div>
              <span class="status-badge success"><span class="status-dot"></span>Online</span>
            </div>
            <div style="display: flex; align-items: center; justify-content: space-between;">
              <div style="display: flex; align-items: center; gap: 10px;">
                <div style="width: 8px; height: 8px; border-radius: 50%; background: var(--amber);"></div>
                <span style="font-size: 13px; color: var(--ink);">Storage</span>
              </div>
              <span class="status-badge warning"><span class="status-dot"></span>78% Used</span>
            </div>
            <div style="display: flex; align-items: center; justify-content: space-between;">
              <div style="display: flex; align-items: center; gap: 10px;">
                <div style="width: 8px; height: 8px; border-radius: 50%; background: var(--green);"></div>
                <span style="font-size: 13px; color: var(--ink);">Backup</span>
              </div>
              <span class="status-badge success"><span class="status-dot"></span>Completed</span>
            </div>
          </div>
        </div>
      </div>

      <!-- Recent Staff Activity -->
      <div class="card glass-card animate-fade-in" style="animation-delay: 0.3s;">
        <div class="card-header">
          <div>
            <div class="card-title">Recent Staff Activity</div>
            <div class="card-subtitle">Latest actions by staff members</div>
          </div>
          <a href="staff.php" style="font-size: 13px; color: var(--violet); text-decoration: none; font-weight: 500;">View All</a>
        </div>
        <div class="activity-list">
          <?php foreach ($adminActivity as $activity): ?>
          <div class="activity-item">
            <div class="activity-icon user">
              <i class="fas fa-<?php echo $activity['action_type'] === 'created' ? 'user-plus' : 'edit'; ?>"></i>
            </div>
            <div class="activity-content">
              <div class="activity-text">
                <strong><?php echo sanitize($activity['staff_name']); ?></strong> 
                <?php echo sanitize($activity['summary']); ?>
              </div>
              <div class="activity-time"><?php echo sanitize(relativeTime($activity['created_at'])); ?></div>
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





