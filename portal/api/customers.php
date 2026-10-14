<?php
/**
 * Customers API
 * CRUD operations for users table
 */

require_once __DIR__ . '/common.php';
requirePermission('users.view');

function deleteUserCompletely(int $userId): bool
{
    $tables = ['service_usage', 'print_jobs', 'transactions', 'sessions'];

    foreach ($tables as $table) {
        if (Database::tableExists($table)) {
            Database::execute("DELETE FROM `$table` WHERE user_id = ?", [$userId]);
        }
    }

    return Database::execute("DELETE FROM users WHERE user_id = ?", [$userId]) > 0;
}

$method = $_SERVER['REQUEST_METHOD'];
$input = $GLOBALS['request_input'] ?? ($_POST ?: ($_GET ?: []));
if (!is_array($input)) {
    $input = [];
}

try {
    switch ($method) {
        case 'GET':
            if (isset($_GET['search'])) {
                $query = trim((string) $_GET['search']);
                if ($query === '') {
                    successResponse([]);
                }

                $likeTerm = '%' . str_replace('%', '\\%', $query) . '%';
                $users = Database::fetchAll(
                    "SELECT user_id as id, full_name as name, email, phone, membership_tier
                     FROM users
                     WHERE is_active = 1
                       AND (
                            full_name LIKE ? OR
                            email LIKE ? OR
                            phone LIKE ?
                       )
                     ORDER BY 
                        CASE WHEN full_name LIKE ? THEN 0 ELSE 1 END,
                        full_name ASC
                     LIMIT 8",
                    [$likeTerm, $likeTerm, $likeTerm, $likeTerm]
                );
                successResponse($users);
            } elseif (isset($_GET['list'])) {
                // Return list for select dropdowns
                $users = Database::fetchAll(
                    "SELECT user_id as id, full_name as name, email FROM users WHERE is_active = 1 ORDER BY full_name"
                );
                successResponse($users);
            } elseif (isset($_GET['user_id'])) {
                // Get single customer
                $user = Database::fetchOne(
                    "SELECT user_id, email, phone, full_name, membership_tier, total_visits, total_spent,
                            created_at, updated_at, last_login_at, is_active
                     FROM users WHERE user_id = ?",
                    [$_GET['user_id']]
                );
                if (!$user) errorResponse('Customer not found', 404);
                successResponse($user);
            } else {
                // List all customers
                $customers = Database::fetchAll(
                    "SELECT u.*, 
                        COALESCE(t.order_count, 0) as order_count,
                        COALESCE(t.total_spent, 0) as total_spent
                     FROM users u
                     LEFT JOIN (
                         SELECT user_id, COUNT(*) as order_count, SUM(total_amount) as total_spent
                         FROM transactions
                         WHERE status = 'completed'
                         GROUP BY user_id
                     ) t ON u.user_id = t.user_id
                     ORDER BY u.created_at DESC"
                );
                successResponse($customers);
            }
            break;

        case 'POST':
            requirePermission('users.create');
            validateRequired($input, ['full_name']);

            $email = !empty($input['email']) ? trim((string) $input['email']) : null;
            $phone = !empty($input['phone']) ? trim((string) $input['phone']) : null;

            // Hash password if provided
            $passwordHash = null;
            if (!empty($input['password'])) {
                $passwordHash = password_hash($input['password'], PASSWORD_DEFAULT);
            }

            $userId = Database::insert(
                "INSERT INTO users (full_name, email, phone, password_hash, membership_tier, is_active) VALUES (?, ?, ?, ?, ?, ?)",
                [
                    trim((string) $input['full_name']),
                    $email,
                    $phone,
                    $passwordHash,
                    $input['membership_tier'] ?? 'standard',
                    $input['is_active'] ?? 1,
                ]
            );

            $user = Database::fetchOne("SELECT * FROM users WHERE user_id = ?", [$userId]);
            successResponse($user, 'Customer created successfully');
            break;

        case 'PUT':
            requirePermission('users.edit');
            validateRequired($input, ['user_id']);
            
            $updates = [];
            $params = [];
            
            $allowedFields = ['full_name', 'email', 'phone', 'membership_tier', 'is_active'];
            foreach ($allowedFields as $field) {
                if (isset($input[$field])) {
                    $updates[] = "$field = ?";
                    if (in_array($field, ['email', 'phone'], true)) {
                        $value = trim((string) $input[$field]);
                        $params[] = $value === '' ? null : $value;
                    } else {
                        $params[] = $input[$field];
                    }
                }
            }
            
            if (!empty($input['password'])) {
                $updates[] = "password_hash = ?";
                $params[] = password_hash($input['password'], PASSWORD_DEFAULT);
            }
            
            if (empty($updates)) errorResponse('No fields to update');
            
            $params[] = $input['user_id'];
            Database::execute("UPDATE users SET " . implode(', ', $updates) . " WHERE user_id = ?", $params);
            
            $user = Database::fetchOne("SELECT * FROM users WHERE user_id = ?", [$input['user_id']]);
            successResponse($user, 'Customer updated successfully');
            break;

        case 'DELETE':
            requirePermission('users.delete');
            validateRequired($input, ['user_id']);

            $userId = (int) $input['user_id'];
            if (!$userId) {
                errorResponse('Invalid user ID');
            }

            if (!deleteUserCompletely($userId)) {
                errorResponse('Customer not found or could not be deleted', 404);
            }

            successResponse(null, 'Customer deleted successfully');
            break;

        default:
            errorResponse('Method not allowed', 405);
    }
} catch (Exception $e) {
    errorResponse($e->getMessage(), 500);
}