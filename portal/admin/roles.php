<?php
/**
 * CTECHLIT & SYSTEMS SaaS Analytics Dashboard — Roles & Permissions
 * View role assignments and effective staff permissions
 */

require_once __DIR__ . '/../../includes/auth.php';
requirePermission('staff.assign_permissions');

$currentUser = currentStaff() ?: currentUser();
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfToken = $_POST['csrf_token'] ?? '';
    if (!verifyCsrfToken($csrfToken)) {
        $error = 'Invalid CSRF token';
    } else {
        $message = 'Role permissions updated successfully';
    }
}

$allRoles = getAllRoles();
$allPermissions = getAllPermissions();

$permissionCategories = [
    'Dashboard' => ['dashboard.view', 'dashboard.stats'],
    'Users' => ['users.view', 'users.create', 'users.edit', 'users.delete'],
    'Stations' => ['stations.view', 'stations.manage', 'stations.control'],
    'Sessions' => ['sessions.view', 'sessions.manage', 'sessions.end'],
    'Services' => ['services.view', 'services.create', 'services.edit', 'services.delete'],
    'Print Jobs' => ['print_jobs.view', 'print_jobs.manage', 'print_jobs.cancel'],
    'Transactions' => ['transactions.view', 'transactions.refund', 'transactions.reports'],
    'POS & Finance' => ['pos.view', 'pos.manage', 'audit.view'],
    'Billing' => ['billing.view', 'billing.manage'],
    'Inventory' => ['inventory.view', 'inventory.manage', 'inventory.adjust'],
    'Staff' => ['staff.view', 'staff.create', 'staff.edit', 'staff.delete', 'staff.assign_permissions'],
    'Settings' => ['settings.view', 'settings.edit'],
    'Reports' => ['reports.view', 'reports.export'],
    'Feedback' => ['feedback.view', 'feedback.respond'],
    'Support' => ['support.view', 'support.manage'],
    'Jarvis' => ['jarvis.view', 'jarvis.use'],
    'Personal Devices' => ['personal_devices.view', 'personal_devices.manage'],
    'Branded Assets' => ['branded_assets.view', 'branded_assets.create', 'branded_assets.edit', 'branded_assets.delete'],
];

$activeStaff = Database::fetchAll(
    "SELECT staff_id, full_name, role
     FROM staff
     WHERE is_active = 1
     ORDER BY role, full_name"
);
$staffOverrides = [];
foreach (Database::fetchAll(
    "SELECT sp.staff_id, sp.permission, sp.granted
     FROM staff_permissions sp
     INNER JOIN staff s ON s.staff_id = sp.staff_id
     WHERE s.is_active = 1"
) as $override) {
    $staffOverrides[(int) $override['staff_id']][$override['permission']] = (bool) $override['granted'];
}

$activeStaffByRole = array_fill_keys(array_keys($allRoles), 0);
$permissionCountsByRole = array_fill_keys(array_keys($allRoles), []);
$grantedStaffByRole = [];
foreach ($allRoles as $roleKey => $_role) {
    $grantedStaffByRole[$roleKey] = array_fill_keys(array_keys($allPermissions), 0);
}
foreach ($activeStaff as &$staffMember) {
    $roleKey = $staffMember['role'];
    if (!isset($allRoles[$roleKey])) {
        $staffMember['effective_permission_count'] = 0;
        continue;
    }

    $activeStaffByRole[$roleKey]++;
    $rolePermissions = $allRoles[$roleKey]['permissions'];
    $effectivePermissions = $rolePermissions === '*'
        ? array_keys($allPermissions)
        : [];
    foreach ($staffOverrides[(int) $staffMember['staff_id']] ?? [] as $permission => $granted) {
        if ($granted && isset($allPermissions[$permission]) && !in_array($permission, $effectivePermissions, true)) {
            $effectivePermissions[] = $permission;
        }
    }

    $staffMember['effective_permission_count'] = count($effectivePermissions);
    $permissionCountsByRole[$roleKey][] = $staffMember['effective_permission_count'];
    foreach ($effectivePermissions as $permission) {
        if (isset($grantedStaffByRole[$roleKey][$permission])) {
            $grantedStaffByRole[$roleKey][$permission]++;
        }
    }
}
unset($staffMember);

$csrfToken = generateCsrfToken();

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Roles & Permissions — CTECHLIT & SYSTEMS</title>
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

      <!-- Roles Overview -->
      <div class="kpi-grid" style="margin-bottom: 24px;">
        <?php foreach ($allRoles as $roleKey => $role): ?>
          <?php
            $adminPermissionCount = $role['permissions'] === '*'
                ? count($allPermissions)
                : 0;
            $assignedCounts = $permissionCountsByRole[$roleKey] ?? [];
            $effectivePermissionCount = $assignedCounts
                ? (min($assignedCounts) === max($assignedCounts)
                    ? (string) min($assignedCounts)
                    : min($assignedCounts) . '–' . max($assignedCounts))
                : (string) $adminPermissionCount;
            $permissionCountLabel = $assignedCounts
                ? (count($assignedCounts) === 1 || min($assignedCounts) === max($assignedCounts)
                    ? 'effective permissions per staff member'
                    : 'effective permissions per staff member (range)')
                : ($role['permissions'] === '*' ? 'administrator permissions' : 'effective permissions assigned');
          ?>
          <div class="kpi-card animate-fade-in" style="--kpi-color: var(--violet); --kpi-bg: rgba(124, 58, 237, 0.1);">
            <div class="kpi-header">
              <div class="kpi-icon">
                <i class="fas fa-user-shield"></i>
              </div>
            </div>
            <div class="kpi-value" style="font-size: 20px;"><?php echo $role['label']; ?></div>
            <div class="kpi-label"><?php echo $effectivePermissionCount; ?> <?php echo $permissionCountLabel; ?></div>
            <div style="font-size:11px; color:var(--ink-secondary); margin-top:5px;">
              <?php echo number_format($activeStaffByRole[$roleKey] ?? 0); ?> active staff assigned
            </div>
          </div>
        <?php endforeach; ?>
      </div>

      <!-- Permissions Matrix -->
      <div class="card glass-card animate-fade-in" style="animation-delay: 0.2s;">
        <div class="card-header">
          <div>
            <div class="card-title">Permissions Matrix</div>
            <div class="card-subtitle">Only administrator accounts have automatic access. Every permission shown for other roles is explicitly assigned to staff by an administrator.</div>
          </div>
        </div>

        <div class="table-container" style="margin-top: 16px;">
          <table class="data-table">
            <thead>
              <tr>
                <th>Permission</th>
                <?php foreach ($allRoles as $roleKey => $role): ?>
                  <th style="text-align: center;"><?php echo $role['label']; ?></th>
                <?php endforeach; ?>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($permissionCategories as $category => $perms): ?>
                <?php foreach ($perms as $permKey): ?>
                  <?php if (isset($allPermissions[$permKey])): ?>
                    <tr>
                      <td>
                        <div style="font-weight: 500; color: var(--ink);"><?php echo $allPermissions[$permKey]; ?></div>
                        <div style="font-size: 11px; color: var(--muted);"><?php echo $category; ?></div>
                      </td>
                      <?php foreach ($allRoles as $roleKey => $role): ?>
                        <td style="text-align: center;">
                          <?php
                          $roleDefault = $role['permissions'] === '*';
                          $staffCount = $activeStaffByRole[$roleKey] ?? 0;
                          $grantedCount = $grantedStaffByRole[$roleKey][$permKey] ?? 0;
                          ?>
                          <?php if ($staffCount === 0): ?>
                            <?php if ($roleDefault): ?>
                              <span class="status-badge success" style="display: inline-flex;"><span class="status-dot"></span>Yes (administrator)</span>
                            <?php else: ?>
                              <span style="color:var(--ink-secondary);">No staff assigned</span>
                            <?php endif; ?>
                          <?php elseif ($grantedCount === $staffCount): ?>
                            <span class="status-badge success" style="display: inline-flex;">
                              <span class="status-dot"></span>Yes <small style="margin-left:4px;"><?php echo $grantedCount; ?>/<?php echo $staffCount; ?></small>
                            </span>
                          <?php elseif ($grantedCount > 0): ?>
                            <span class="status-badge warning" style="display: inline-flex;">
                              <span class="status-dot"></span>Mixed <small style="margin-left:4px;"><?php echo $grantedCount; ?>/<?php echo $staffCount; ?></small>
                            </span>
                          <?php else: ?>
                            <span class="status-badge error" style="display: inline-flex;">
                              <span class="status-dot"></span>No <small style="margin-left:4px;">0/<?php echo $staffCount; ?></small>
                            </span>
                          <?php endif; ?>
                        </td>
                      <?php endforeach; ?>
                    </tr>
                  <?php endif; ?>
                <?php endforeach; ?>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>

      <div class="card glass-card animate-fade-in" style="margin-top:20px;">
        <div class="card-header">
          <div>
            <div class="card-title">Active staff permission assignments</div>
            <div class="card-subtitle">Effective permission totals from administrator accounts and explicit staff assignments.</div>
          </div>
        </div>
        <div class="table-container" style="margin-top:16px;">
          <table class="data-table">
            <thead><tr><th>Staff member</th><th>Role</th><th>Effective permissions</th></tr></thead>
            <tbody>
              <?php foreach ($activeStaff as $staffMember): ?>
                <tr>
                  <td><?php echo sanitize($staffMember['full_name']); ?></td>
                  <td><?php echo sanitize($allRoles[$staffMember['role']]['label'] ?? ucfirst($staffMember['role'])); ?></td>
                  <td><?php echo number_format((int) $staffMember['effective_permission_count']); ?> / <?php echo count($allPermissions); ?></td>
                </tr>
              <?php endforeach; ?>
              <?php if (!$activeStaff): ?>
                <tr><td colspan="3" style="text-align:center; padding:24px; color:var(--ink-secondary);">No active staff members found.</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
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
