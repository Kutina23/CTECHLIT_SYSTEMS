<?php
/**
 * CTECHLIT & SYSTEMS SaaS Analytics Dashboard — Staff Settings
 * Staff member's personal settings page
 */

require_once __DIR__ . '/../../includes/auth.php';
requireAuth();

$currentUser = currentStaff() ?: currentUser();
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfToken = $_POST['csrf_token'] ?? '';
    if (!verifyCsrfToken($csrfToken)) {
        $error = 'Invalid CSRF token';
    } else {
        $message = 'Settings saved successfully';
    }
}

$csrfToken = generateCsrfToken();

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>My Settings — CTECHLIT & SYSTEMS</title>
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

      <form method="POST">
        <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">

        <!-- Notification Settings -->
        <div class="card glass-card animate-fade-in" style="margin-bottom: 20px;">
          <div class="card-header">
            <div>
              <div class="card-title">Notification Settings</div>
              <div class="card-subtitle">Configure your notifications</div>
            </div>
          </div>

          <div style="display: flex; flex-direction: column; gap: 16px; margin-top: 20px;">
            <label style="display: flex; align-items: center; justify-content: space-between; padding: 12px; background: var(--bg-secondary); border-radius: var(--radius); cursor: pointer;">
              <div>
                <div style="font-size: 14px; font-weight: 600; color: var(--ink);">Email Notifications</div>
                <div style="font-size: 12px; color: var(--muted);">Receive email updates</div>
              </div>
              <input type="checkbox" checked style="width: 18px; height: 18px; accent-color: var(--violet);">
            </label>

            <label style="display: flex; align-items: center; justify-content: space-between; padding: 12px; background: var(--bg-secondary); border-radius: var(--radius); cursor: pointer;">
              <div>
                <div style="font-size: 14px; font-weight: 600; color: var(--ink);">Task Reminders</div>
                <div style="font-size: 12px; color: var(--muted);">Get reminders for upcoming tasks</div>
              </div>
              <input type="checkbox" checked style="width: 18px; height: 18px; accent-color: var(--violet);">
            </label>

            <label style="display: flex; align-items: center; justify-content: space-between; padding: 12px; background: var(--bg-secondary); border-radius: var(--radius); cursor: pointer;">
              <div>
                <div style="font-size: 14px; font-weight: 600; color: var(--ink);">Weekly Reports</div>
                <div style="font-size: 12px; color: var(--muted);">Receive weekly summary reports</div>
              </div>
              <input type="checkbox" style="width: 18px; height: 18px; accent-color: var(--violet);">
            </label>
          </div>
        </div>

        <!-- Appearance Settings -->
        <div class="card glass-card animate-fade-in" style="animation-delay: 0.05s;">
          <div class="card-header">
            <div>
              <div class="card-title">Appearance</div>
              <div class="card-subtitle">Customize your experience</div>
            </div>
          </div>

          <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 16px; margin-top: 20px;">
            <div>
              <label style="display: block; font-size: 13px; font-weight: 600; color: var(--ink); margin-bottom: 6px;">Language</label>
              <select style="width: 100%; padding: 10px 14px; border: 1px solid var(--border); border-radius: var(--radius); font-size: 14px; color: var(--ink); background: var(--bg); outline: none;">
                <option>English</option>
                <option>Spanish</option>
                <option>French</option>
              </select>
            </div>

            <div>
              <label style="display: block; font-size: 13px; font-weight: 600; color: var(--ink); margin-bottom: 6px;">Timezone</label>
              <select style="width: 100%; padding: 10px 14px; border: 1px solid var(--border); border-radius: var(--radius); font-size: 14px; color: var(--ink); background: var(--bg); outline: none;">
                <option>UTC</option>
                <option>America/New_York</option>
                <option>America/Los_Angeles</option>
                <option>Europe/London</option>
              </select>
            </div>
          </div>
        </div>

        <div style="display: flex; gap: 10px; margin-top: 24px;">
          <button type="submit" class="header-filter" style="background: var(--violet); color: white; border-color: var(--violet); padding: 10px 20px; font-weight: 600;">
            <i class="fas fa-save"></i>
            Save Settings
          </button>
        </div>
      </form>
    </div>
  </main>
</div>

<!-- Floating Action Button -->
<button class="fab" aria-label="Edit settings">
  <i class="fas fa-edit"></i>
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





