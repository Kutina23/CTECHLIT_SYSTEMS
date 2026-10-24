<?php
/**
 * Staff API
 * CRUD operations for staff table
 */

require_once __DIR__ . '/common.php';

$method = $_SERVER['REQUEST_METHOD'];
$input = $GLOBALS['request_input'] ?? ($_POST ?: ($_GET ?: []));
if (!is_array($input)) {
    $input = [];
}

try {
    switch ($method) {
        case 'GET':
            if (isset($_GET['list'])) {
                $where = '';
                $params = [];
                if (isset($_GET['role'])) {
                    $where = "WHERE role = ?";
                    $params[] = $_GET['role'];
                }
                $staff = Database::fetchAll(
                    "SELECT staff_id as id, full_name as name, email, role FROM staff $where ORDER BY full_name",
                    $params
                );
                successResponse($staff);
            } elseif (isset($_GET['staff_id'])) {
                if ((int) $_GET['staff_id'] !== (int) $_SESSION['user_id']) {
                    requirePermission('staff.view');
                }
                $staff = Database::fetchOne("SELECT * FROM staff WHERE staff_id = ?", [$_GET['staff_id']]);
                if (!$staff) errorResponse('Staff not found', 404);
                // Don't return password hash
                unset($staff['password_hash']);
                successResponse($staff);
            } else {
                requirePermission('staff.view');
                $staff = Database::fetchAll("SELECT staff_id, email, phone, full_name, role, station_id, is_on_duty, hired_at, is_active, created_at FROM staff ORDER BY created_at DESC");
                successResponse($staff);
            }
            break;

        case 'POST':
            requirePermission('staff.create');
            validateRequired($input, ['email', 'phone', 'full_name', 'role', 'hired_at']);
            assertCanManageStaffAccount(null, (string) $input['role']);
            if (!isValidEmail((string) $input['email'])) {
                errorResponse('Enter a valid email address.', 422);
            }

            // No shared default password: use the one given (if strong enough) or issue a random one-time password.
            $temporaryPassword = null;
            if (trim((string) ($input['password'] ?? '')) !== '') {
                if ($problem = staffPasswordProblem((string) $input['password'])) {
                    errorResponse($problem, 422);
                }
                $passwordHash = hashPassword((string) $input['password']);
            } else {
                $temporaryPassword = substr(strtr(base64_encode(random_bytes(12)), '+/=', 'kQz'), 0, 12) . random_int(10, 99);
                $passwordHash = hashPassword($temporaryPassword);
            }
            
            $staffId = Database::insert(
                "INSERT INTO staff (email, phone, full_name, password_hash, role, station_id, hired_at, is_active, is_on_duty) 
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)",
                [
                    $input['email'],
                    $input['phone'],
                    $input['full_name'],
                    $passwordHash,
                    $input['role'],
                    ($input['station_id'] ?? '') !== '' ? $input['station_id'] : null,
                    $input['hired_at'],
                    $input['is_active'] ?? 1,
                    $input['is_on_duty'] ?? 0,
                ]
            );
            
            $staff = Database::fetchOne("SELECT staff_id, email, phone, full_name, role, station_id, hired_at, is_active, is_on_duty FROM staff WHERE staff_id = ?", [$staffId]);
            logAuditTrail('staff_created', 'staff', (int) $staffId, ['role' => $input['role']]);
            successResponse($staff + ($temporaryPassword ? ['temporary_password' => $temporaryPassword] : []),
                'Staff member created' . ($temporaryPassword ? '. Temporary password: ' . $temporaryPassword . ' — share it privately; it will not be shown again.' : ' successfully'));
            break;

        case 'PUT':
            if (!hasPermission('staff.edit') && !hasPermission('staff.assign_permissions')) {
                errorResponse('Forbidden: You do not have permission to edit staff members', 403);
            }
            validateRequired($input, ['staff_id']);

            $staffId = (int) $input['staff_id'];
            $existingStaff = Database::fetchOne("SELECT staff_id, role FROM staff WHERE staff_id = ?", [$staffId]);
            if (!$existingStaff) errorResponse('Staff not found', 404);
            assertCanManageStaffAccount($existingStaff['role'], isset($input['role']) ? (string) $input['role'] : null);
            if ($staffId === (int) $_SESSION['user_id'] && !currentStaffIsAdmin()
                && ((isset($input['role']) && $input['role'] !== $existingStaff['role']) || (isset($input['is_active']) && !$input['is_active']))) {
                errorResponse('You cannot change your own role or deactivate yourself.', 403);
            }
            if (isset($input['email']) && !isValidEmail((string) $input['email'])) {
                errorResponse('Enter a valid email address.', 422);
            }
            
            $updates = [];
            $params = [];
            
            $allowedFields = ['email', 'phone', 'full_name', 'role', 'station_id', 'hired_at', 'is_active', 'is_on_duty'];
            foreach ($allowedFields as $field) {
                if (isset($input[$field])) {
                    $updates[] = "$field = ?";
                    $params[] = $field === 'station_id' && $input[$field] === ''
                        ? null
                        : $input[$field];
                }
            }
            
            if (!empty($input['password'])) {
                if ($problem = staffPasswordProblem((string) $input['password'])) {
                    errorResponse($problem, 422);
                }
                $updates[] = "password_hash = ?";
                $params[] = hashPassword((string) $input['password']);
            }
            
            if (empty($updates)) errorResponse('No fields to update');
            
            $params[] = $staffId;
            Database::execute("UPDATE staff SET " . implode(', ', $updates) . " WHERE staff_id = ?", $params);
            logAuditTrail('staff_updated', 'staff', $staffId, ['fields' => array_values(array_intersect(array_keys($input), array_merge($allowedFields, ['password'])))]);
            
            $staff = Database::fetchOne("SELECT staff_id, email, phone, full_name, role, station_id, is_on_duty, hired_at, is_active FROM staff WHERE staff_id = ?", [$staffId]);
            if (!$staff) errorResponse('Staff not found', 404);
            successResponse($staff, 'Staff member updated successfully');
            break;

        case 'DELETE':
            requirePermission('staff.delete');
            validateRequired($input, ['staff_id']);
            if ((int) $input['staff_id'] === (int) $_SESSION['user_id']) {
                errorResponse('You cannot delete your own account.', 403);
            }
            $target = Database::fetchOne('SELECT role FROM staff WHERE staff_id = ?', [(int) $input['staff_id']]);
            if (!$target) errorResponse('Staff not found', 404);
            assertCanManageStaffAccount($target['role']);
            Database::execute("DELETE FROM staff WHERE staff_id = ?", [(int) $input['staff_id']]);
            logAuditTrail('staff_deleted', 'staff', (int) $input['staff_id'], ['role' => $target['role']]);
            successResponse(null, 'Staff member deleted successfully');
            break;

        default:
            errorResponse('Method not allowed', 405);
    }
} catch (Throwable $e) {
    error_log('Staff API error: ' . $e->getMessage());
    errorResponse(str_contains($e->getMessage(), 'Duplicate entry') ? 'A staff member with that email already exists.' : 'The staff request could not be completed.', 500);
}