<?php
/**
 * Staff Permissions API
 * Manage custom permissions for staff members
 */

// Suppress PHP errors from being output as HTML
ini_set('display_errors', '0');
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING & ~E_DEPRECATED);

// Shared API helpers and auth checks
require_once __DIR__ . '/common.php';
requirePermission('staff.assign_permissions');

// Ensure JSON content type is set early
header('Content-Type: application/json');

try {
    switch ($_SERVER['REQUEST_METHOD']) {
        case 'GET':
            if (isset($_GET['staff_id'])) {
                $staffId = (int) $_GET['staff_id'];
                
                // Get staff role
                $targetStaff = Database::fetchOne("SELECT role FROM staff WHERE staff_id = ?", [$staffId]);
                if (!$targetStaff) errorResponse('Staff not found', 404);
                
                // Get all permissions
                $allPerms = getAllPermissions();
                
                // Get custom permissions
                $customPerms = Database::fetchAll(
                    "SELECT permission, granted FROM staff_permissions WHERE staff_id = ?",
                    [$staffId]
                );
                $customMap = [];
                foreach ($customPerms as $p) {
                    $customMap[$p['permission']] = (bool) $p['granted'];
                }
                
                $result = [];
                foreach ($allPerms as $key => $label) {
                    $granted = in_array($targetStaff['role'], ['admin', 'ceo'], true) || ($customMap[$key] ?? false) || ($customMap['*'] ?? false);
                    $result[] = [
                        'key' => $key,
                        'label' => $label,
                        'granted' => $granted,
                        'is_override' => isset($customMap[$key]) || isset($customMap['*'])
                    ];
                }

                if ($customMap['*'] ?? false) {
                    array_unshift($result, [
                        'key' => '*',
                        'label' => 'Full Access (all permissions)',
                        'granted' => true,
                        'is_override' => true
                    ]);
                }
                
                successResponse('Permissions loaded', [
                    'staff_id' => $staffId,
                    'role' => $targetStaff['role'],
                    'permissions' => $result
                ]);
            } else {
                errorResponse('staff_id required', 400);
            }
            break;

        case 'PUT':
            $input = $GLOBALS['request_input'] ?? ($_POST ?: ($_GET ?: []));
            if (!is_array($input)) {
                $input = [];
            }
            validateRequired($input, ['staff_id']);
            $staffId = (int) $input['staff_id'];
            
            $targetStaff = Database::fetchOne("SELECT role FROM staff WHERE staff_id = ?", [$staffId]);
            if (!$targetStaff) errorResponse('Staff not found', 404);
            
            // Admin/CEO access is unconditional; individual permission sets apply to non-admin staff.
            if (in_array($targetStaff['role'], ['admin', 'ceo'], true)) {
                Database::execute(
                    "UPDATE staff_permissions SET granted = 0 WHERE staff_id = ?",
                    [$staffId]
                );
                successResponse(null, 'Administrator has all permissions by default');
            }
            
            $permissions = $input['permissions'] ?? [];
            if (!is_array($permissions)) {
                errorResponse('Permissions must be submitted as a list.');
            }
            $allPerms = getAllPermissions();
            $permissionSet = [];
            $hasWildcard = false;
            foreach ($permissions as $permission) {
                if (!is_string($permission)) {
                    errorResponse('The permission list contains an invalid permission.');
                }
                if ($permission === '*') {
                    $hasWildcard = true;
                    continue;
                }
                if (!isset($allPerms[$permission])) {
                    errorResponse('The permission list contains an invalid permission.');
                }
                $permissionSet[$permission] = true;
            }

            Database::beginTransaction();
            try {
                if ($hasWildcard) {
                    // Grant wildcard and all known permissions
                    Database::execute(
                        "INSERT INTO staff_permissions (staff_id, permission, granted) VALUES (?, ?, ?)
                         ON DUPLICATE KEY UPDATE granted = VALUES(granted)",
                        [$staffId, '*', 1]
                    );
                    foreach ($allPerms as $permKey => $_label) {
                        Database::execute(
                            "INSERT INTO staff_permissions (staff_id, permission, granted) VALUES (?, ?, ?)
                             ON DUPLICATE KEY UPDATE granted = VALUES(granted)",
                            [$staffId, $permKey, 1]
                        );
                    }
                } else {
                    Database::execute(
                        "UPDATE staff_permissions SET granted = 0 WHERE staff_id = ? AND permission = '*'",
                        [$staffId]
                    );
                    foreach ($allPerms as $permKey => $_label) {
                        Database::execute(
                            "INSERT INTO staff_permissions (staff_id, permission, granted) VALUES (?, ?, ?)
                             ON DUPLICATE KEY UPDATE granted = VALUES(granted)",
                            [$staffId, $permKey, isset($permissionSet[$permKey]) ? 1 : 0]
                        );
                    }
                }
                Database::commit();
            } catch (Throwable $e) {
                Database::rollback();
                throw $e;
            }
            
            successResponse(null, 'Permissions updated successfully');
            break;

        default:
            errorResponse('Method not allowed', 405);
    }
} catch (Exception $e) {
    errorResponse($e->getMessage(), 500);
}