<?php
/**
 * Peripherals API
 * CRUD operations for peripherals table
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
                if (isset($_GET['type'])) {
                    $where = "WHERE peripheral_type = ?";
                    $params[] = $_GET['type'];
                }
                $peripherals = Database::fetchAll(
                    "SELECT peripheral_id as id, name as label, model, peripheral_type, status 
                     FROM peripherals $where 
                     ORDER BY name",
                    $params
                );
                successResponse($peripherals);
            } elseif (isset($_GET['peripheral_id'])) {
                $peripheral = Database::fetchOne(
                    "SELECT p.*, s.station_code, s.name as station_name
                     FROM peripherals p
                     LEFT JOIN stations s ON s.station_id = p.station_id
                     WHERE p.peripheral_id = ?",
                    [$_GET['peripheral_id']]
                );
                if (!$peripheral) errorResponse('Peripheral not found', 404);
                // Parse config if exists
                if (!empty($peripheral['config'])) {
                    $peripheral['config'] = json_decode($peripheral['config'], true);
                }
                successResponse($peripheral);
            } else {
                $peripherals = Database::fetchAll(
                    "SELECT p.*, s.station_code, s.name as station_name
                     FROM peripherals p
                     LEFT JOIN stations s ON s.station_id = p.station_id
                     ORDER BY p.peripheral_type, p.name"
                );
                successResponse($peripherals);
            }
            break;

        case 'POST':
            validateRequired($input, ['peripheral_type', 'name', 'station_id']);
            
            $peripheralId = Database::insert(
                "INSERT INTO peripherals (peripheral_type, name, model, serial_number, station_id, status, config) 
                 VALUES (?, ?, ?, ?, ?, 'online', ?)",
                [
                    $input['peripheral_type'],
                    $input['name'],
                    $input['model'] ?? null,
                    $input['serial_number'] ?? null,
                    $input['station_id'],
                    $input['config'] ? json_encode($input['config']) : null,
                ]
            );
            
            $peripheral = Database::fetchOne(
                "SELECT p.*, s.station_code, s.name as station_name
                 FROM peripherals p
                 LEFT JOIN stations s ON s.station_id = p.station_id
                 WHERE p.peripheral_id = ?",
                [$peripheralId]
            );
            successResponse($peripheral, 'Peripheral created successfully');
            break;

        case 'PUT':
            validateRequired($input, ['peripheral_id']);
            
            $updates = [];
            $params = [];
            
            $allowedFields = ['name', 'model', 'serial_number', 'station_id', 'status', 'config'];
            foreach ($allowedFields as $field) {
                if (isset($input[$field])) {
                    $updates[] = "$field = ?";
                    $params[] = $field === 'config' ? json_encode($input[$field]) : $input[$field];
                }
            }
            
            if (empty($updates)) errorResponse('No fields to update');
            
            $params[] = $input['peripheral_id'];
            Database::execute("UPDATE peripherals SET " . implode(', ', $updates) . ", updated_at = NOW() WHERE peripheral_id = ?", $params);
            
            $peripheral = Database::fetchOne(
                "SELECT p.*, s.station_code, s.name as station_name
                 FROM peripherals p
                 LEFT JOIN stations s ON s.station_id = p.station_id
                 WHERE p.peripheral_id = ?",
                [$input['peripheral_id']]
            );
            successResponse($peripheral, 'Peripheral updated successfully');
            break;

        case 'DELETE':
            validateRequired($input, ['peripheral_id']);
            Database::execute("DELETE FROM peripherals WHERE peripheral_id = ?", [$input['peripheral_id']]);
            successResponse(null, 'Peripheral deleted successfully');
            break;

        default:
            errorResponse('Method not allowed', 405);
    }
} catch (Exception $e) {
    errorResponse($e->getMessage(), 500);
}