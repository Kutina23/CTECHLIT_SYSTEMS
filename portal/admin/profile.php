<?php
/**
 * Admin Profile Management
 */

require_once __DIR__ . '/../../includes/auth.php';
requirePermission('staff.view');

$currentUser = currentStaff() ?: currentUser();
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfToken = $_POST['csrf_token'] ?? '';
    if (!verifyCsrfToken($csrfToken)) {
        $error = 'Invalid CSRF token.';
    } else {
        $fullName = trim((string) ($_POST['full_name'] ?? ''));
        $email = trim((string) ($_POST['email'] ?? ''));
        $phone = trim((string) ($_POST['phone'] ?? ''));
        $newPassword = trim((string) ($_POST['new_password'] ?? ''));
        $staffId = (int) ($currentUser['staff_id'] ?? 0);
        $currentRole = (string) ($currentUser['role'] ?? 'admin');
        $role = trim((string) ($_POST['role'] ?? $currentRole));
        $roleChanged = $role !== $currentRole;

        if ($fullName === '' || $email === '') {
            $error = 'Full name and email are required.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please enter a valid email address.';
        } elseif ($phone !== '' && !preg_match('/^(?:\+?233|0)?(24|25|53|54|55|56|57|58|59|30|31|32|33|34|35|36|37|38|39)\d{7}$/', preg_replace('/\s+/', '', $phone))) {
            $error = 'Please enter a valid phone number.';
        } elseif ($newPassword !== '' && strlen($newPassword) < 6) {
            $error = 'New password must be at least 6 characters long.';
        } elseif (!isset(getAllRoles()[$role])) {
            $error = 'Please select a valid staff role.';
        } elseif ($roleChanged && !hasPermission('staff.assign_permissions')) {
            $error = 'You do not have permission to change your staff role.';
        } else {
            try {
                Database::beginTransaction();

                if ($roleChanged) {
                    $effectivePermissions = getStaffPermissions($staffId);
                    foreach (getAllPermissions() as $permission => $_label) {
                        Database::execute(
                            "INSERT INTO staff_permissions (staff_id, permission, granted) VALUES (?, ?, ?)
                             ON DUPLICATE KEY UPDATE granted = VALUES(granted)",
                            [$staffId, $permission, in_array($permission, $effectivePermissions, true) ? 1 : 0]
                        );
                    }
                }

                $sql = "UPDATE staff SET full_name = ?, email = ?, phone = ?, updated_at = NOW()";
                $params = [$fullName, $email, $phone];
                if ($roleChanged) {
                    $sql .= ', role = ?';
                    $params[] = $role;
                }
                if ($newPassword !== '') {
                    $sql .= ', password_hash = ?';
                    $params[] = hashPassword($newPassword);
                }
                $sql .= ' WHERE staff_id = ?';
                $params[] = $staffId;
                Database::execute($sql, $params);

                Database::commit();
                logAuditTrail('admin_profile_updated', 'staff', $staffId, [
                    'full_name' => $fullName,
                    'email' => $email,
                    'phone' => $phone,
                    'role' => $role,
                ], $staffId, 'staff');

                $currentUser = Database::fetchOne(
                    "SELECT staff_id, email, phone, full_name, role, station_id, is_on_duty, is_active FROM staff WHERE staff_id = ?",
                    [$staffId]
                );
                $message = 'Profile updated successfully. Your current permissions are unchanged.';
            } catch (Throwable $exception) {
                Database::rollback();
                error_log('Admin profile update failed: ' . $exception->getMessage());
                $error = 'Unable to update your profile. Please try again.';
            }
        }
    }
}

$canChangeRole = hasPermission('staff.assign_permissions');
$staffRoles = getAllRoles();
$csrfToken = generateCsrfToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Admin Profile — CTECHLIT</title>
  <link rel="icon" type="image/svg+xml" href="../../assets/images/logo-icon.svg">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="stylesheet" href="../../assets/css/portal.css">
</head>
<body>
  <?php include_once __DIR__ . '/../../includes/sidebar.php'; ?>
  <main class="portal-main" style="margin-left: var(--sidebar-width);">
    <?php include_once __DIR__ . '/../../includes/header.php'; ?>

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
        <input type="hidden" name="csrf_token" value="<?php echo sanitize($csrfToken); ?>">

        <div class="card glass-card animate-fade-in" style="margin-bottom: 24px;">
          <div class="card-header">
            <div>
              <div class="card-title">Admin Profile</div>
              <div class="card-subtitle">Manage your account details and staff role</div>
            </div>
          </div>

          <div style="display: flex; align-items: center; gap: 20px; margin-top: 20px; padding-bottom: 20px; border-bottom: 1px solid var(--border);">
            <div style="width: 80px; height: 80px; border-radius: 50%; background: linear-gradient(135deg, var(--violet), var(--amber)); display: flex; align-items: center; justify-content: center; color: white; font-size: 32px; font-weight: 700;">
              <?php echo strtoupper(substr($currentUser['full_name'] ?? 'A', 0, 1)); ?>
            </div>
            <div>
              <div style="font-size: 18px; font-weight: 700; color: var(--ink);"><?php echo sanitize($currentUser['full_name'] ?? 'Admin'); ?></div>
              <div style="font-size: 14px; color: var(--ink-secondary);"><?php echo sanitize($currentUser['email'] ?? ''); ?></div>
              <div style="font-size: 13px; color: var(--muted); margin-top: 4px; text-transform: capitalize;">
                <?php echo sanitize($currentUser['role'] ?? 'admin'); ?>
              </div>
            </div>
          </div>

          <div style="display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 16px; margin-top: 20px;">
            <div>
              <label style="display:block; font-size:13px; font-weight:600; color:var(--ink); margin-bottom:6px;">Full Name</label>
              <input type="text" name="full_name" value="<?php echo sanitize($currentUser['full_name'] ?? ''); ?>" class="form-input" required>
            </div>

            <div>
              <label style="display:block; font-size:13px; font-weight:600; color:var(--ink); margin-bottom:6px;">Email Address</label>
              <input type="email" name="email" value="<?php echo sanitize($currentUser['email'] ?? ''); ?>" class="form-input" required>
            </div>

            <div>
              <label style="display:block; font-size:13px; font-weight:600; color:var(--ink); margin-bottom:6px;">Phone Number</label>
              <input type="tel" name="phone" value="<?php echo sanitize($currentUser['phone'] ?? ''); ?>" class="form-input">
            </div>

            <div>
              <label style="display:block; font-size:13px; font-weight:600; color:var(--ink); margin-bottom:6px;">Role</label>
              <?php if ($canChangeRole): ?>
              <select name="role" class="form-select form-input">
                <?php foreach ($staffRoles as $roleKey => $roleDetails): ?>
                <option value="<?php echo sanitize($roleKey); ?>" <?php echo (($currentUser['role'] ?? 'admin') === $roleKey) ? 'selected' : ''; ?>>
                  <?php echo sanitize($roleDetails['label']); ?>
                </option>
                <?php endforeach; ?>
              </select>
              <small class="form-help">Changing your role will not change your current permissions.</small>
              <?php else: ?>
              <input type="text" value="<?php echo sanitize(getRoleLabel($currentUser['role'] ?? 'admin')); ?>" class="form-input" disabled>
              <?php endif; ?>
            </div>
          </div>
        </div>

        <div class="card glass-card animate-fade-in" style="animation-delay: 0.05s;">
          <div class="card-header">
            <div>
              <div class="card-title">Security</div>
              <div class="card-subtitle">Update your admin password</div>
            </div>
          </div>

          <div style="display: grid; grid-template-columns: 1fr; gap: 16px; margin-top: 20px;">
            <div>
              <label style="display:block; font-size:13px; font-weight:600; color:var(--ink); margin-bottom:6px;">New Password</label>
              <input type="password" name="new_password" placeholder="Leave blank to keep current password" class="form-input">
            </div>
          </div>
        </div>

        <div style="display: flex; gap: 10px; margin-top: 24px;">
          <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save Changes</button>
        </div>
      </form>
    </div>
  </main>
</body>
</html>
