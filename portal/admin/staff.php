<?php
/**
 * CTECHLIT & SYSTEMS SaaS Analytics Dashboard — Staff Management
 * Create, edit, and manage staff members with roles and permissions
 */

require_once __DIR__ . '/../../includes/auth.php';
requirePermission('staff.view');

$currentUser = currentStaff() ?: currentUser();
$isAdmin = hasPermission('staff.assign_permissions');

$action = $_GET['action'] ?? 'list';
$message = '';
$error = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfToken = $_POST['csrf_token'] ?? '';
    if (!verifyCsrfToken($csrfToken)) {
        $error = 'Invalid CSRF token';
    } else {
        $postAction = $_POST['action'] ?? $action;
        switch ($postAction) {
            case 'create':
                handleCreateStaff();
                break;
            case 'update':
                handleUpdateStaff();
                break;
            case 'delete':
                handleDeleteStaff();
                break;
            case 'reset_password':
                handleResetStaffPassword();
                break;
            case 'update_permissions':
                handleUpdatePermissions();
                break;
        }
    }
}

// Get staff list
$staffList = Database::fetchAll(
    "SELECT staff_id, email, phone, full_name, role, station_id, is_on_duty, is_active, hired_at, last_login_at
     FROM staff
     ORDER BY hired_at DESC"
);

// Get single staff for editing
$editStaff = null;
if (isset($_GET['id']) && in_array($action, ['edit', 'permissions'])) {
    $editStaff = Database::fetchOne(
        "SELECT * FROM staff WHERE staff_id = ?",
        [$_GET['id']]
    );
    if (!$editStaff) {
        $error = 'Staff member not found';
        $action = 'list';
    }
}

// Get all roles
$allRoles = getAllRoles();

// Get all permissions
$allPermissions = getAllPermissions();

// Group permissions by category
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

// Get staff permissions for editing
$staffPermissions = [];
if ($editStaff) {
    $staffPermissions = getStaffPermissions((int) $editStaff['staff_id']);
}

// Get stations for assignment
$stations = Database::fetchAll("SELECT station_id, station_code, name, location FROM stations ORDER BY station_code");

/**
 * Handle staff creation
 */
function handleCreateStaff(): void
{
    global $error, $message;

    $required = ['full_name', 'email', 'phone', 'password', 'role'];
    $errors = validateRequired(allInput(), $required);

    if (!empty($errors)) {
        $error = 'Please fill in all required fields';
        return;
    }

    $email = sanitize($_POST['email']);
    $phone = sanitize($_POST['phone']);

    // Check if email already exists
    $existing = Database::fetchOne("SELECT staff_id FROM staff WHERE email = ?", [$email]);
    if ($existing) {
        $error = 'Email already exists';
        return;
    }

    $password = hashPassword($_POST['password']);
    $fullName = sanitize($_POST['full_name']);
    $role = sanitize($_POST['role']);
    $stationId = !empty($_POST['station_id']) ? (int) $_POST['station_id'] : null;

    $result = Database::execute(
        "INSERT INTO staff (full_name, email, phone, password_hash, role, station_id, is_active, hired_at)
         VALUES (?, ?, ?, ?, ?, ?, 1, NOW())",
        [$fullName, $email, $phone, $password, $role, $stationId]
    );

    if ($result) {
        $message = 'Staff member created successfully';
    } else {
        $error = 'Failed to create staff member';
    }
}

/**
 * Handle staff update
 */
function handleUpdateStaff(): void
{
    global $error, $message;

    $staffId = (int) ($_POST['staff_id'] ?? 0);
    if (!$staffId) {
        $error = 'Invalid staff ID';
        return;
    }

    $fullName = sanitize($_POST['full_name']);
    $email = sanitize($_POST['email']);
    $phone = sanitize($_POST['phone']);
    $role = sanitize($_POST['role']);
    $stationId = !empty($_POST['station_id']) ? (int) $_POST['station_id'] : null;
    $isActive = isset($_POST['is_active']) ? 1 : 0;
    $isOnDuty = isset($_POST['is_on_duty']) ? 1 : 0;
    $newPassword = trim((string) ($_POST['new_password'] ?? ''));

    // Check if email is taken by another staff
    $existing = Database::fetchOne(
        "SELECT staff_id FROM staff WHERE email = ? AND staff_id != ?",
        [$email, $staffId]
    );
    if ($existing) {
        $error = 'Email already exists';
        return;
    }

    $sql = "UPDATE staff SET full_name = ?, email = ?, phone = ?, role = ?, station_id = ?, is_active = ?, is_on_duty = ?";
    $params = [$fullName, $email, $phone, $role, $stationId, $isActive, $isOnDuty];

    if ($newPassword !== '') {
        if (strlen($newPassword) < 6) {
            $error = 'New password must be at least 6 characters long';
            return;
        }
        $sql .= ", password_hash = ?";
        $params[] = hashPassword($newPassword);
    }

    $sql .= " WHERE staff_id = ?";
    $params[] = $staffId;

    $result = Database::execute($sql, $params);

    if ($result) {
        $message = $newPassword !== '' ? 'Staff member and password updated successfully' : 'Staff member updated successfully';
    } else {
        $error = 'Failed to update staff member';
    }
}

function handleResetStaffPassword(): void
{
    global $error, $message, $currentUser;

    $staffId = (int) ($_POST['staff_id'] ?? 0);
    $newPassword = trim((string) ($_POST['new_password'] ?? ''));

    if (!$staffId) {
        $error = 'Invalid staff ID';
        return;
    }

    if ($newPassword === '') {
        $error = 'A new password is required';
        return;
    }

    if (strlen($newPassword) < 6) {
        $error = 'Password must be at least 6 characters long';
        return;
    }

    if (!hasPermission('staff.edit') && !hasPermission('staff.assign_permissions')) {
        $error = 'You do not have permission to reset staff passwords';
        return;
    }

    $staff = Database::fetchOne("SELECT staff_id, full_name FROM staff WHERE staff_id = ?", [$staffId]);
    if (!$staff) {
        $error = 'Staff member not found';
        return;
    }

    $result = Database::execute(
        "UPDATE staff SET password_hash = ?, updated_at = NOW() WHERE staff_id = ?",
        [hashPassword($newPassword), $staffId]
    );

    if ($result) {
        logAuditTrail('staff_password_reset', 'staff', $staffId, [
            'reset_by' => $currentUser['staff_id'] ?? null,
            'reset_by_name' => $currentUser['full_name'] ?? null,
        ], (int) ($currentUser['staff_id'] ?? 0), 'staff');
        $message = 'Password reset successfully for ' . sanitize($staff['full_name']);
    } else {
        $error = 'Failed to reset password';
    }
}

/**
 * Handle staff deletion
 */
function handleDeleteStaff(): void
{
    global $error, $message;

    $staffId = (int) ($_POST['staff_id'] ?? 0);
    if (!$staffId) {
        $error = 'Invalid staff ID';
        return;
    }

    // Don't allow deleting yourself
    if ($staffId === ($currentUser['staff_id'] ?? 0)) {
        $error = 'You cannot delete your own account';
        return;
    }

    if (!hasPermission('staff.delete') && !hasPermission('staff.assign_permissions')) {
        $error = 'You do not have permission to delete staff members';
        return;
    }

    $result = Database::execute("DELETE FROM staff WHERE staff_id = ?", [$staffId]);

    if ($result) {
        $message = 'Staff member deleted successfully';
    } else {
        $error = 'Failed to delete staff member';
    }
}

/**
 * Handle permission update
 */
function handleUpdatePermissions(): void
{
    global $error, $message;

    $staffId = (int) ($_POST['staff_id'] ?? 0);
    if (!$staffId) {
        $error = 'Invalid staff ID';
        return;
    }

    $permissions = $_POST['permissions'] ?? [];

    // Admin/CEO has all permissions by role - clear any stale custom overrides
    $staff = Database::fetchOne("SELECT role FROM staff WHERE staff_id = ?", [$staffId]);
    if ($staff && in_array($staff['role'], ['admin', 'ceo'], true)) {
        Database::execute("DELETE FROM staff_permissions WHERE staff_id = ?", [$staffId]);
        $message = 'Permissions updated successfully — Administrator has all permissions by default';
        return;
    }

    // Remove all existing custom permissions
    Database::execute("DELETE FROM staff_permissions WHERE staff_id = ?", [$staffId]);

    // Add new permissions
    $allPerms = getAllPermissions();
    foreach ($allPerms as $permKey => $permLabel) {
        $granted = in_array($permKey, $permissions) ? 1 : 0;
        Database::execute(
            "INSERT INTO staff_permissions (staff_id, permission, granted) VALUES (?, ?, ?)",
            [$staffId, $permKey, $granted]
        );
    }

    $message = 'Permissions updated successfully';
}

// Generate CSRF token
$csrfToken = generateCsrfToken();

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Staff Management — CTECHLIT & SYSTEMS</title>
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

      <!-- Permissions Modal (injected by modal system) -->
      <?php include_once __DIR__ . '/../../includes/modals.php'; ?>

      <?php if ($action === 'list'): ?>
        <!-- Staff List -->
        <div class="card glass-card animate-fade-in">
          <div class="card-header">
            <div>
              <div class="card-title">All Staff Members</div>
              <div class="card-subtitle"><?php echo count($staffList); ?> total staff</div>
            </div>
          </div>
          <div class="table-container">
            <table class="data-table">
              <thead>
                <tr>
                  <th>Name</th>
                  <th>Email</th>
                  <th>Role</th>
                  <th>Status</th>
                  <th>Last Login</th>
                  <th>Actions</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($staffList as $staff): ?>
                  <tr>
                    <td>
                      <div class="table-product">
                        <div class="user-avatar" style="width: 36px; height: 36px; font-size: 13px;">
                          <?php echo strtoupper(substr($staff['full_name'], 0, 1)); ?>
                        </div>
                        <div class="table-product-info">
                          <span class="table-product-name"><?php echo sanitize($staff['full_name']); ?></span>
                          <span class="table-product-category"><?php echo sanitize($staff['phone']); ?></span>
                        </div>
                      </div>
                    </td>
                    <td><?php echo sanitize($staff['email']); ?></td>
                    <td>
                      <span class="status-badge info">
                        <?php echo getRoleLabel($staff['role']); ?>
                      </span>
                    </td>
                    <td>
                      <span class="status-badge <?php echo $staff['is_active'] ? 'success' : 'error'; ?>">
                        <span class="status-dot"></span>
                        <?php echo $staff['is_active'] ? 'Active' : 'Inactive'; ?>
                      </span>
                    </td>
                    <td><?php echo $staff['last_login_at'] ? date('M d, Y H:i', strtotime($staff['last_login_at'])) : 'Never'; ?></td>
                    <td>
                      <div style="display: flex; gap: 6px;">
                        <a href="?action=edit&id=<?php echo $staff['staff_id']; ?>" class="header-btn" style="width: 32px; height: 32px; font-size: 12px;" title="Edit">
                          <i class="fas fa-edit"></i>
                        </a>
                        <?php if ($isAdmin): ?>
                          <a href="?action=permissions&id=<?php echo $staff['staff_id']; ?>" class="header-btn" style="width: 32px; height: 32px; font-size: 12px;" title="Permissions">
                            <i class="fas fa-key"></i>
                          </a>
                        <?php endif; ?>
                        <form method="POST" style="display: inline;" onsubmit="const resetPassword = prompt('Enter a new password for <?php echo addslashes($staff['full_name']); ?>'); if (resetPassword === null || resetPassword.trim() === '') { return false; } this.new_password.value = resetPassword; return true;">
                          <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                          <input type="hidden" name="action" value="reset_password">
                          <input type="hidden" name="staff_id" value="<?php echo $staff['staff_id']; ?>">
                          <input type="hidden" name="new_password" value="">
                          <button type="submit" class="header-btn" style="width: 32px; height: 32px; font-size: 12px; color: var(--amber);" title="Reset Password">
                            <i class="fas fa-user-lock"></i>
                          </button>
                        </form>
                        <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this staff member?');">
                          <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                          <input type="hidden" name="action" value="delete">
                          <input type="hidden" name="staff_id" value="<?php echo $staff['staff_id']; ?>">
                          <button type="submit" class="header-btn" style="width: 32px; height: 32px; font-size: 12px; color: var(--red);" title="Delete">
                            <i class="fas fa-trash"></i>
                          </button>
                        </form>
                      </div>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>

      <?php elseif ($action === 'create' || $action === 'edit'): ?>
        <!-- Create/Edit Form -->
        <div class="card glass-card animate-fade-in" style="max-width: 800px;">
          <div class="card-header">
            <div>
              <div class="card-title"><?php echo $action === 'create' ? 'Add New Staff' : 'Edit Staff'; ?></div>
              <div class="card-subtitle"><?php echo $action === 'create' ? 'Create a new staff account' : 'Update staff information'; ?></div>
            </div>
          </div>

          <form method="POST" style="margin-top: 20px;">
            <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
            <input type="hidden" name="action" value="<?php echo $action; ?>">
            <?php if ($editStaff): ?>
              <input type="hidden" name="staff_id" value="<?php echo $editStaff['staff_id']; ?>">
            <?php endif; ?>

            <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 16px;">
              <div style="grid-column: span 2;">
                <label style="display: block; font-size: 13px; font-weight: 600; color: var(--ink); margin-bottom: 6px;">Full Name *</label>
                <input type="text" name="full_name" value="<?php echo $editStaff['full_name'] ?? ''; ?>" required
                  style="width: 100%; padding: 10px 14px; border: 1px solid var(--border); border-radius: var(--radius); font-size: 14px; color: var(--ink); background: var(--bg); outline: none; transition: var(--transition);"
                  onfocus="this.style.borderColor='var(--violet-light)'; this.style.boxShadow='0 0 0 3px rgba(124, 58, 237, 0.1)'"
                  onblur="this.style.borderColor='var(--border)'; this.style.boxShadow='none'">
              </div>

              <div>
                <label style="display: block; font-size: 13px; font-weight: 600; color: var(--ink); margin-bottom: 6px;">Email *</label>
                <input type="email" name="email" value="<?php echo $editStaff['email'] ?? ''; ?>" required
                  style="width: 100%; padding: 10px 14px; border: 1px solid var(--border); border-radius: var(--radius); font-size: 14px; color: var(--ink); background: var(--bg); outline: none; transition: var(--transition);"
                  onfocus="this.style.borderColor='var(--violet-light)'; this.style.boxShadow='0 0 0 3px rgba(124, 58, 237, 0.1)'"
                  onblur="this.style.borderColor='var(--border)'; this.style.boxShadow='none'">
              </div>

              <div>
                <label style="display: block; font-size: 13px; font-weight: 600; color: var(--ink); margin-bottom: 6px;">Phone *</label>
                <input type="text" name="phone" value="<?php echo $editStaff['phone'] ?? ''; ?>" required
                  style="width: 100%; padding: 10px 14px; border: 1px solid var(--border); border-radius: var(--radius); font-size: 14px; color: var(--ink); background: var(--bg); outline: none; transition: var(--transition);"
                  onfocus="this.style.borderColor='var(--violet-light)'; this.style.boxShadow='0 0 0 3px rgba(124, 58, 237, 0.1)'"
                  onblur="this.style.borderColor='var(--border)'; this.style.boxShadow='none'">
              </div>

              <?php if ($action === 'create'): ?>
                <div>
                  <label style="display: block; font-size: 13px; font-weight: 600; color: var(--ink); margin-bottom: 6px;">Password *</label>
                  <input type="password" name="password" required
                    style="width: 100%; padding: 10px 14px; border: 1px solid var(--border); border-radius: var(--radius); font-size: 14px; color: var(--ink); background: var(--bg); outline: none; transition: var(--transition);"
                    onfocus="this.style.borderColor='var(--violet-light)'; this.style.boxShadow='0 0 0 3px rgba(124, 58, 237, 0.1)'"
                    onblur="this.style.borderColor='var(--border)'; this.style.boxShadow='none'">
                </div>
              <?php endif; ?>

              <div>
                <label style="display: block; font-size: 13px; font-weight: 600; color: var(--ink); margin-bottom: 6px;">Role *</label>
                <select name="role" required
                  style="width: 100%; padding: 10px 14px; border: 1px solid var(--border); border-radius: var(--radius); font-size: 14px; color: var(--ink); background: var(--bg); outline: none; transition: var(--transition);">
                  <?php foreach ($allRoles as $roleKey => $role): ?>
                    <option value="<?php echo $roleKey; ?>" <?php echo ($editStaff['role'] ?? '') === $roleKey ? 'selected' : ''; ?>>
                      <?php echo $role['label']; ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>

              <div>
                <label style="display: block; font-size: 13px; font-weight: 600; color: var(--ink); margin-bottom: 6px;">Station</label>
                <select name="station_id"
                  style="width: 100%; padding: 10px 14px; border: 1px solid var(--border); border-radius: var(--radius); font-size: 14px; color: var(--ink); background: var(--bg); outline: none; transition: var(--transition);">
                  <option value="">— None —</option>
                  <?php foreach ($stations as $station): ?>
                    <option value="<?php echo $station['station_id']; ?>" <?php echo ($editStaff['station_id'] ?? '') == $station['station_id'] ? 'selected' : ''; ?>>
                      <?php echo sanitize($station['station_code'] . ' — ' . $station['name']); ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>

              <?php if ($editStaff): ?>
                <div>
                  <label style="display: block; font-size: 13px; font-weight: 600; color: var(--ink); margin-bottom: 6px;">New Password</label>
                  <input type="password" name="new_password" placeholder="Leave blank to keep current password"
                    style="width: 100%; padding: 10px 14px; border: 1px solid var(--border); border-radius: var(--radius); font-size: 14px; color: var(--ink); background: var(--bg); outline: none; transition: var(--transition);"
                    onfocus="this.style.borderColor='var(--violet-light)'; this.style.boxShadow='0 0 0 3px rgba(124, 58, 237, 0.1)'"
                    onblur="this.style.borderColor='var(--border)'; this.style.boxShadow='none'">
                </div>
              <?php endif; ?>

              <?php if ($editStaff): ?>
                <div style="grid-column: span 2;">
                  <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                    <input type="checkbox" name="is_active" <?php echo $editStaff['is_active'] ? 'checked' : ''; ?>
                      style="width: 18px; height: 18px; accent-color: var(--violet);">
                    <span style="font-size: 14px; color: var(--ink);">Active</span>
                  </label>
                </div>
                <div style="grid-column: span 2;">
                  <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                    <input type="checkbox" name="is_on_duty" <?php echo $editStaff['is_on_duty'] ? 'checked' : ''; ?>
                      style="width: 18px; height: 18px; accent-color: var(--violet);">
                    <span style="font-size: 14px; color: var(--ink);">On Duty</span>
                  </label>
                </div>
              <?php endif; ?>
            </div>

            <div style="display: flex; gap: 10px; margin-top: 24px; padding-top: 20px; border-top: 1px solid var(--border);">
              <button type="submit" class="header-filter" style="background: var(--violet); color: white; border-color: var(--violet); padding: 10px 20px; font-weight: 600;">
                <i class="fas fa-save"></i>
                <?php echo $action === 'create' ? 'Create Staff' : 'Update Staff'; ?>
              </button>
              <a href="staff.php" class="header-filter" style="text-decoration: none;">
                Cancel
              </a>
            </div>
          </form>
        </div>

      <?php elseif ($action === 'permissions' && $editStaff && $isAdmin): ?>
        <!-- Permissions Editor (Modal-based - will redirect to modal) -->
        <script>
          // Load permissions and open modal on page load
          document.addEventListener('DOMContentLoaded', () => {
            openPermissionsModal(<?php echo $editStaff['staff_id']; ?>, <?php echo json_encode($editStaff); ?>);
          });
        </script>
        <div class="card glass-card animate-fade-in" style="max-width: 800px; text-align: center; padding: 60px 20px;">
          <i class="fas fa-spinner fa-spin" style="font-size: 32px; color: var(--violet); margin-bottom: 16px;"></i>
          <h3 style="margin-bottom: 8px;">Loading Permissions Editor...</h3>
          <p style="color: var(--muted);">Opening modal to manage permissions for <?php echo sanitize($editStaff['full_name']); ?></p>
        </div>
      <?php endif; ?>
    </div>
  </main>
</div>

<!-- Floating Action Button -->
<button class="fab" aria-label="Add new" onclick="window.location.href='?action=create'">
  <i class="fas fa-plus"></i>
</button>

<script src="../../assets/js/modal-controller.js"></script>
<script>
  // Permission categories and labels
  const PERMISSION_CATEGORIES = {
    'Dashboard': ['dashboard.view', 'dashboard.stats'],
    'Users': ['users.view', 'users.create', 'users.edit', 'users.delete'],
    'Stations': ['stations.view', 'stations.manage', 'stations.control'],
    'Sessions': ['sessions.view', 'sessions.manage', 'sessions.end'],
    'Services': ['services.view', 'services.create', 'services.edit', 'services.delete'],
    'Print Jobs': ['print_jobs.view', 'print_jobs.manage', 'print_jobs.cancel'],
    'Transactions': ['transactions.view', 'transactions.refund', 'transactions.reports'],
    'POS & Finance': ['pos.view', 'pos.manage', 'audit.view'],
    'Billing': ['billing.view', 'billing.manage'],
    'Inventory': ['inventory.view', 'inventory.manage', 'inventory.adjust'],
    'Staff': ['staff.view', 'staff.create', 'staff.edit', 'staff.delete', 'staff.assign_permissions'],
    'Settings': ['settings.view', 'settings.edit'],
    'Reports': ['reports.view', 'reports.export'],
    'Feedback': ['feedback.view', 'feedback.respond'],
    'Support': ['support.view', 'support.manage'],
    'Jarvis': ['jarvis.view', 'jarvis.use'],
    'Personal Devices': ['personal_devices.view', 'personal_devices.manage'],
    'Branded Assets': ['branded_assets.view', 'branded_assets.create', 'branded_assets.edit', 'branded_assets.delete'],
  };

  const PERMISSION_LABELS = {
    'dashboard.view': 'View Dashboard', 'dashboard.stats': 'View Statistics',
    'users.view': 'View Users', 'users.create': 'Create Users', 'users.edit': 'Edit Users', 'users.delete': 'Delete Users',
    'stations.view': 'View Stations', 'stations.manage': 'Manage Stations', 'stations.control': 'Control Stations',
    'sessions.view': 'View Sessions', 'sessions.manage': 'Manage Sessions', 'sessions.end': 'End Sessions',
    'services.view': 'View Services', 'services.create': 'Create Services', 'services.edit': 'Edit Services', 'services.delete': 'Delete Services',
    'print_jobs.view': 'View Print Jobs', 'print_jobs.manage': 'Manage Print Jobs', 'print_jobs.cancel': 'Cancel Print Jobs',
    'transactions.view': 'View Transactions', 'transactions.refund': 'Process Refunds', 'transactions.reports': 'View Financial Reports',
    'inventory.view': 'View Inventory', 'inventory.manage': 'Manage Inventory', 'inventory.adjust': 'Adjust Stock',
    'staff.view': 'View Staff', 'staff.create': 'Create Staff', 'staff.edit': 'Edit Staff', 'staff.delete': 'Delete Staff', 'staff.assign_permissions': 'Assign Permissions',
    'settings.view': 'View Settings', 'settings.edit': 'Edit Settings',
    'reports.view': 'View Reports', 'reports.export': 'Export Reports',
    'feedback.view': 'View Feedback', 'feedback.respond': 'Respond to Feedback',
    'support.view': 'View Support Tickets', 'support.manage': 'Manage Support Tickets',
    'jarvis.view': 'View Jarvis Assistant', 'jarvis.use': 'Use Jarvis Assistant',
    'billing.view': 'View Customer Billing', 'billing.manage': 'Manage Billing, Invoices & Payments',
    'personal_devices.view': 'View Personal Devices', 'personal_devices.manage': 'Manage Personal Devices',
    'branded_assets.view': 'View Branded Assets', 'branded_assets.create': 'Generate Branded Assets', 'branded_assets.edit': 'Edit Branded Asset Templates', 'branded_assets.delete': 'Delete Branded Asset Templates',
  };

  let currentStaffId = null;
  let currentStaffRole = null;

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

  // Permissions Modal Functions
  async function openPermissionsModal(staffId, staffData) {
    currentStaffId = staffId;
    currentStaffRole = staffData.role;

    try {
      const res = await fetch(`../../portal/api/staff-permissions.php?staff_id=${staffId}`, {
        headers: {'X-Requested-With': 'XMLHttpRequest'}
      });
      const result = await res.json();
      if (!res.ok) throw new Error(result.message || 'Failed to load permissions');

      const payload = result?.data ?? result;
      const permissions = Array.isArray(payload?.permissions) ? payload.permissions : [];
      const role = payload?.role ?? staffData.role;

      renderPermissionsModal(staffData, permissions, role);
    } catch (err) {
      console.error(err);
      ModalController.showNotification(err.message, 'error');
    }
  }

  function renderPermissionsModal(staffData, permissions, role) {
    const isAdminRole = role === 'admin' || role === 'ceo';

    let html = `
      <div class="permissions-modal" style="max-height: 70vh; overflow-y: auto;">
        <div style="margin-bottom: 16px; padding: 12px; border-radius: var(--radius); background: ${isAdminRole ? 'rgba(245, 158, 11, 0.1)' : 'rgba(6, 182, 212, 0.1)'}; border: 1px solid ${isAdminRole ? 'var(--amber)' : 'var(--cyan)'};">
          <div style="display: flex; align-items: center; gap: 8px;">
            <i class="fas fa-${isAdminRole ? 'user-shield' : 'info-circle'}" style="color: ${isAdminRole ? 'var(--amber)' : 'var(--cyan)'};"></i>
            <div>
              <strong>${staffData.full_name}</strong> (${getRoleLabel(role)})
              ${isAdminRole ? '<br><small style="color: var(--amber);">Administrator has all permissions by default. Custom overrides can be set below.</small>' : ''}
            </div>
          </div>
        </div>
        <form id="permissionsForm">
    `;

    Object.entries(PERMISSION_CATEGORIES).forEach(([category, perms]) => {
      const catPerms = perms.filter(p => permissions.some(perm => perm.key === p));
      if (!catPerms.length) return;

      html += `
        <fieldset style="margin-bottom: 20px; padding: 0; border: none;">
          <legend style="font-size: 13px; font-weight: 600; color: var(--ink); margin-bottom: 12px; padding-bottom: 8px; border-bottom: 1px solid var(--border);">${category}</legend>
          <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 8px;">
      `;

      catPerms.forEach(permKey => {
        const perm = permissions.find(p => p.key === permKey);
        const granted = perm ? perm.granted : false;
        const isOverride = perm && perm.is_override;
        const label = PERMISSION_LABELS[permKey] || permKey;

        html += `
          <label style="display: flex; align-items: center; gap: 8px; padding: 8px 12px; border-radius: var(--radius-sm); background: var(--bg-secondary); cursor: pointer; transition: var(--transition); position: relative;"
            onmouseover="this.style.background='var(--border-light)'"
            onmouseout="this.style.background='var(--bg-secondary)'">
          <input type="checkbox" name="permissions[]" value="${permKey}" ${granted ? 'checked' : ''}
            style="width: 16px; height: 16px; accent-color: var(--violet);"
            ${isAdminRole ? 'disabled' : ''}>
          <span style="font-size: 13px; color: var(--ink); flex: 1;">${label}</span>
          ${isOverride ? '<span style="font-size: 10px; color: var(--violet); background: rgba(124, 58, 237, 0.1); padding: 2px 6px; border-radius: 999px;">Custom</span>' : ''}
        </label>
        `;
      });

      html += `
          </div>
        </fieldset>
      `;
    });

    html += `
        </form>
      </div>
    `;

    // Use the view modal for permissions
    const viewModalOverlay = document.getElementById('viewModalOverlay');
    const viewModalBody = document.getElementById('viewModalBody');
    const viewModalTitle = document.getElementById('viewModalTitle');

    viewModalTitle.textContent = `Permissions - ${staffData.full_name}`;
    viewModalBody.innerHTML = html;

    // Replace footer with save button
    const viewFooter = viewModalOverlay.querySelector('.modal-footer');
    viewFooter.innerHTML = `
      <button class="btn btn-secondary" id="permCancelBtn">Cancel</button>
      <button class="btn btn-primary" id="permSaveBtn" ${isAdminRole ? 'disabled' : ''}>
        <i class="fas fa-save"></i> ${isAdminRole ? 'Admin - Read Only' : 'Save Permissions'}
      </button>
    `;

    // Bind events
    document.getElementById('permCancelBtn').onclick = () => closePermissionsModal();
    document.getElementById('permSaveBtn').onclick = () => savePermissions();

    viewModalOverlay.classList.add('active');
    document.body.style.overflow = 'hidden';
  }

  function closePermissionsModal() {
    const viewModalOverlay = document.getElementById('viewModalOverlay');
    viewModalOverlay.classList.remove('active');
    document.body.style.overflow = '';
    currentStaffId = null;
    currentStaffRole = null;
  }

  async function savePermissions() {
    const form = document.getElementById('permissionsForm');
    const checkboxes = form.querySelectorAll('input[name="permissions[]"]:checked');
    const permissions = Array.from(checkboxes).map(cb => cb.value);

    try {
      const res = await fetch('../../portal/api/staff-permissions.php', {
        method: 'PUT',
        headers: {'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest'},
        body: JSON.stringify({staff_id: currentStaffId, permissions})
      });
      const result = await res.json();
      if (!res.ok) throw new Error(result.message || 'Failed to save');

      ModalController.showNotification('Permissions saved successfully', 'success');
      closePermissionsModal();
      // Refresh page after a short delay
      setTimeout(() => location.reload(), 1000);
    } catch (err) {
      ModalController.showNotification(err.message, 'error');
    }
  }

  function getRoleLabel(role) {
    const labels = {ceo: 'Chief Executive Officer', admin: 'Administrator', manager: 'Manager', attendant: 'Attendant', technician: 'Technician'};
    return labels[role] || role;
  }
</script>
</body>
</html>





