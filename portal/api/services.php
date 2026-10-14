<?php
/**
 * Services API
 * CRUD operations for services table
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
                $services = Database::fetchAll(
                    "SELECT service_id as id, name, base_price, price_unit FROM services WHERE is_active = 1 ORDER BY name"
                );
                successResponse($services);
            } elseif (isset($_GET['service_id'])) {
                $service = Database::fetchOne("SELECT * FROM services WHERE service_id = ?", [$_GET['service_id']]);
                if (!$service) errorResponse('Service not found', 404);
                successResponse($service);
            } else {
                $services = Database::fetchAll("SELECT * FROM services ORDER BY created_at DESC");
                successResponse($services);
            }
            break;

        case 'POST':
            validateRequired($input, ['name', 'category', 'base_price', 'price_unit']);
            
            $serviceId = Database::insert(
                "INSERT INTO services (name, category, description, base_price, price_unit, requires_station, estimated_duration, is_active) 
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
                [
                    $input['name'],
                    $input['category'],
                    $input['description'] ?? null,
                    $input['base_price'],
                    $input['price_unit'],
                    $input['requires_station'] ?? 0,
                    $input['estimated_duration'] ?? null,
                    $input['is_active'] ?? 1,
                ]
            );
            
            $service = Database::fetchOne("SELECT * FROM services WHERE service_id = ?", [$serviceId]);
            successResponse($service, 'Service created successfully');
            break;

        case 'PUT':
            validateRequired($input, ['service_id']);
            
            $updates = [];
            $params = [];
            
            $allowedFields = ['name', 'category', 'description', 'base_price', 'price_unit', 'requires_station', 'estimated_duration', 'is_active'];
            foreach ($allowedFields as $field) {
                if (isset($input[$field])) {
                    $updates[] = "$field = ?";
                    $params[] = $input[$field];
                }
            }
            
            if (empty($updates)) errorResponse('No fields to update');
            
            $params[] = $input['service_id'];
            Database::execute("UPDATE services SET " . implode(', ', $updates) . " WHERE service_id = ?", $params);
            
            $service = Database::fetchOne("SELECT * FROM services WHERE service_id = ?", [$input['service_id']]);
            successResponse($service, 'Service updated successfully');
            break;

        case 'DELETE':
            validateRequired($input, ['service_id']);
            Database::execute("DELETE FROM services WHERE service_id = ?", [$input['service_id']]);
            successResponse(null, 'Service deleted successfully');
            break;

        default:
            errorResponse('Method not allowed', 405);
    }
} catch (Exception $e) {
    errorResponse($e->getMessage(), 500);
}