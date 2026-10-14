<?php
/**
 * Base API Configuration
 */

require_once __DIR__ . '/../../includes/auth.php';
requireStaffAuth();

header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];
if (!isset($GLOBALS['request_input'])) {
    $rawInput = file_get_contents('php://input');
    $decoded = json_decode($rawInput ?: '[]', true);
    $GLOBALS['request_input'] = is_array($decoded) && !empty($decoded) ? $decoded : ($_POST ?: ($_GET ?: []));
    if (!is_array($GLOBALS['request_input'])) {
        $GLOBALS['request_input'] = [];
    }
}
$input = $GLOBALS['request_input'];

if (!function_exists('successResponse')) {
    function successResponse($data = null, $message = 'Success') {
        echo json_encode(['success' => true, 'message' => $message, 'data' => $data]);
        exit;
    }
}

if (!function_exists('errorResponse')) {
    function errorResponse($message, $code = 400) {
        http_response_code($code);
        echo json_encode(['success' => false, 'message' => $message]);
        exit;
    }
}

if (!function_exists('validateRequired')) {
    function validateRequired($data, array $fields) {
        foreach ($fields as $field) {
            if (!is_array($data) || !isset($data[$field]) || $data[$field] === '') {
                errorResponse("Field '$field' is required");
            }
        }
    }
}