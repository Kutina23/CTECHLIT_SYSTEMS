<?php
/**
 * CTECHLIT & SYSTEMS SaaS Analytics Dashboard — Staff Sessions
 * Staff member's session management page
 */

require_once __DIR__ . '/../../includes/auth.php';
requireAuth();

$currentUser = currentStaff() ?: currentUser();
$sessions = portalStaffSessions();
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfToken = $_POST['csrf_token'] ?? '';
    if (!verifyCsrfToken($csrfToken)) {
        $error = 'Invalid CSRF token';
    } else {
        $message = 'Session terminated successfully';
    }
}

$csrfToken = generateCsrfToken();

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>My Sessions — CTECHLIT & SYSTEMS</title>
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

      <!-- Active Sessions -->
      <div class="card glass-card animate-fade-in" style="margin-bottom: 20px;">
        <div class="card-header">
          <div>
            <div class="card-title">Active Sessions</div>
            <div class="card-subtitle">Your currently active sessions</div>
          </div>
        </div>

        <div class="table-wrapper">
          <table class="data-table">
            <thead>
              <tr>
                <th>Device</th>
                <th>Location</th>
                <th>IP Address</th>
                <th>Last Active</th>
                <th>Status</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($sessions as $session): ?>
              <tr>
                <td>
                  <div style="display: flex; align-items: center; gap: 10px;">
                    <div style="width: 32px; height: 32px; border-radius: 8px; background: rgba(124, 58, 237, 0.1); display: flex; align-items: center; justify-content: center; color: var(--violet); font-size: 12px;">
                      <i class="fas fa-desktop"></i>
                    </div>
                    <div>
                      <div style="font-weight: 600; color: var(--ink); font-size: 14px;"><?php echo sanitize($session['station_name'] ?? 'Station'); ?></div>
                      <div style="font-size: 12px; color: var(--muted);"><?php echo sanitize($session['station_code'] ?? 'N/A'); ?></div>
                    </div>
                  </div>
                </td>
                <td style="font-size: 13px; color: var(--ink-secondary);"><?php echo sanitize($session['user_name'] ?? 'Customer'); ?></td>
                <td style="font-size: 13px; color: var(--ink-secondary);"><?php echo sanitize($session['station_code'] ?? 'N/A'); ?></td>
                <td style="font-size: 13px; color: var(--ink-secondary);"><?php echo sanitize(relativeTime($session['started_at'] ?? date('Y-m-d H:i:s'))); ?></td>
                <td><span class="badge badge-success"><?php echo sanitize(ucfirst($session['status'] ?? 'active')); ?></span></td>
                <td>
                  <form method="POST" style="display: inline;">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                    <button type="submit" class="header-filter" style="padding: 4px 10px; font-size: 12px; background: var(--red); color: white; border-color: var(--red);">
                      <i class="fas fa-times"></i>
                    </button>
                  </form>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Session History -->
      <div class="card glass-card animate-fade-in" style="animation-delay: 0.05s;">
        <div class="card-header">
          <div>
            <div class="card-title">Session History</div>
            <div class="card-subtitle">Recent login activity</div>
          </div>
        </div>

        <div class="table-wrapper">
          <table class="data-table">
            <thead>
              <tr>
                <th>Device</th>
                <th>Location</th>
                <th>IP Address</th>
                <th>Date</th>
                <th>Status</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($sessions as $session): ?>
              <tr>
                <td>
                  <div style="display: flex; align-items: center; gap: 10px;">
                    <div style="width: 32px; height: 32px; border-radius: 8px; background: rgba(124, 58, 237, 0.1); display: flex; align-items: center; justify-content: center; color: var(--violet); font-size: 12px;">
                      <i class="fas fa-desktop"></i>
                    </div>
                    <div>
                      <div style="font-weight: 600; color: var(--ink); font-size: 14px;"><?php echo sanitize($session['station_name'] ?? 'Station'); ?></div>
                      <div style="font-size: 12px; color: var(--muted);"><?php echo sanitize($session['station_code'] ?? 'N/A'); ?></div>
                    </div>
                  </div>
                </td>
                <td style="font-size: 13px; color: var(--ink-secondary);"><?php echo sanitize($session['user_name'] ?? 'Customer'); ?></td>
                <td style="font-size: 13px; color: var(--ink-secondary);"><?php echo sanitize($session['station_code'] ?? 'N/A'); ?></td>
                <td style="font-size: 13px; color: var(--ink-secondary);"><?php echo sanitize(date('Y-m-d H:i', strtotime($session['started_at']))); ?></td>
                <td><span class="badge badge-success"><?php echo sanitize(ucfirst($session['status'] ?? 'active')); ?></span></td>
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
<button class="fab" aria-label="Terminate all sessions">
  <i class="fas fa-sign-out-alt"></i>
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





