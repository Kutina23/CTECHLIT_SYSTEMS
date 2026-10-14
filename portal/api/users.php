<?php
/**
 * Users API
 * Simple list endpoint for dropdowns
 */

require_once __DIR__ . '/common.php';

$method = $_SERVER['REQUEST_METHOD'];

if ($method !== 'GET') {
    errorResponse('Method not allowed', 405);
}

if (isset($_GET['list'])) {
    $users = Database::fetchAll(
        "SELECT user_id as id, full_name as label, email, phone FROM users WHERE is_active = 1 ORDER BY full_name"
    );
} else {
    $users = Database::fetchAll(
        "SELECT * FROM users WHERE is_active = 1 ORDER BY full_name"
    );
}

successResponse($users);