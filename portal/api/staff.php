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
                $staff = Database::fetchOne("SELECT * FROM staff WHERE staff_id = ?", [$_GET['staff_id']]);
                if (!$staff) errorResponse('Staff not found', 404);
                // Don't return password hash
                unset($staff['password_hash']);
                successResponse($staff);
            } else {
                $staff = Database::fetchAll("SELECT staff_id, email, phone, full_name, role, station_id, is_on_duty, hired_at, is_active, created_at FROM staff ORDER BY created_at DESC");
                successResponse($staff);
            }
            break;

        case 'POST':
            requirePermission('staff.create');
            validateRequired($input, ['email', 'phone', 'full_name', 'role', 'hired_at']);
            
            $passwordHash = password_hash($input['password'] ?? 'TempPass123!', PASSWORD_DEFAULT);
            
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
            successResponse($staff, 'Staff member created successfully');
            break;

        case 'PUT':
            if (!hasPermission('staff.edit') && !hasPermission('staff.assign_permissions')) {
                errorResponse('Forbidden: You do not have permission to edit staff members', 403);
            }
            validateRequired($input, ['staff_id']);

            $staffId = (int) $input['staff_id'];
            $existingStaff = Database::fetchOne("SELECT staff_id FROM staff WHERE staff_id = ?", [$staffId]);
            if (!$existingStaff) errorResponse('Staff not found', 404);
            
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
                $updates[] = "password_hash = ?";
                $params[] = password_hash($input['password'], PASSWORD_DEFAULT);
            }
            
            if (empty($updates)) errorResponse('No fields to update');
            
            $params[] = $staffId;
            Database::execute("UPDATE staff SET " . implode(', ', $updates) . " WHERE staff_id = ?", $params);
            
            $staff = Database::fetchOne("SELECT staff_id, email, phone, full_name, role, station_id, is_on_duty, hired_at, is_active FROM staff WHERE staff_id = ?", [$staffId]);
            if (!$staff) errorResponse('Staff not found', 404);
            successResponse($staff, 'Staff member updated successfully');
            break;

        case 'DELETE':
            requirePermission('staff.delete');
            validateRequired($input, ['staff_id']);
            Database::execute("DELETE FROM staff WHERE staff_id = ?", [$input['staff_id']]);
            successResponse(null, 'Staff member deleted successfully');
            break;

        default:
            errorResponse('Method not allowed', 405);
    }
} catch (Exception $e) {
    errorResponse($e->getMessage(), 500);
}