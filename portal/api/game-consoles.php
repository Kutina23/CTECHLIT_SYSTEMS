<?php
/**
 * CTECHLIT & SYSTEMS — Game Console Control API
 * Handles Wake-on-LAN and basic controls for PS5, Xbox, etc.
 */

require_once __DIR__ . '/../../includes/auth.php';
requireStaffAuth();

header('Content-Type: application/json');

try {
    $method = $_SERVER['REQUEST_METHOD'];
    $input = jsonInput() ?: $_POST;

    switch ($method) {
        case 'POST':
            requirePermission('stations.control');
            $action = (string) ($input['action'] ?? input('action', ''));
            if (empty($input['peripheral_id'])) {
                errorResponse("Field 'peripheral_id' is required");
            }
            
            if ($action === 'wake') {
                validateRequired($input, ['peripheral_id']);
                $peripheralId = (int) $input['peripheral_id'];
                
                $console = Database::fetchOne(
                    "SELECT p.peripheral_id, p.peripheral_type, p.name, p.model, p.mac_address, p.status, p.config,
                            s.station_code, s.name AS station_name
                     FROM peripherals p
                     LEFT JOIN stations s ON s.station_id = p.station_id
                     WHERE p.peripheral_id = ? AND p.peripheral_type = 'game_console'",
                    [$peripheralId]
                );
                
                if (!$console) {
                    errorResponse('Game console not found', 404);
                }
                
                if (empty($console['mac_address'])) {
                    errorResponse('Console MAC address not configured. Please add the MAC address in peripheral settings.');
                }
                
                $result = wakeGameConsole($console);
                
                if ($result['success']) {
                    logAuditTrail(
                        'wake_game_console',
                        'peripheral',
                        $peripheralId,
                        [
                            'console_name' => $console['name'],
                            'model' => $console['model'],
                            'mac_address' => $console['mac_address'],
                            'station_code' => $console['station_code']
                        ]
                    );
                    
                    successResponse(
                        ['wake_sent' => true, 'mac_address' => $console['mac_address']],
                        "Wake-on-LAN packet sent to {$console['name']} ({$console['model']})"
                    );
                } else {
                    jsonResponse([
                        'success' => false,
                        'message' => $result['message'],
                        'data' => ['wake_sent' => false]
                    ], 200);
                }
                break;
            }
            
            if ($action === 'status') {
                validateRequired($input, ['peripheral_id']);
                $peripheralId = (int) $input['peripheral_id'];
                
                $console = Database::fetchOne(
                    "SELECT p.peripheral_id, p.name, p.model, p.status, p.config, p.mac_address,
                            p.last_maintenance, p.installed_at,
                            s.station_code, s.name AS station_name, s.location AS station_location
                     FROM peripherals p
                     LEFT JOIN stations s ON s.station_id = p.station_id
                     WHERE p.peripheral_id = ? AND p.peripheral_type = 'game_console'",
                    [$peripheralId]
                );
                
                if (!$console) {
                    errorResponse('Game console not found', 404);
                }
                
                $config = json_decode((string) $console['config'], true);
                
                successResponse([
                    'peripheral_id' => (int) $console['peripheral_id'],
                    'name' => $console['name'],
                    'model' => $console['model'],
                    'status' => $console['status'],
                    'mac_address' => $console['mac_address'],
                    'station_code' => $console['station_code'],
                    'station_name' => $console['station_name'],
                    'location' => $console['station_location'],
                    'config' => is_array($config) ? $config : [],
                    'last_maintenance' => $console['last_maintenance'],
                    'installed_at' => $console['installed_at']
                ]);
                break;
            }
            
            if ($action === 'set_session') {
                validateRequired($input, ['peripheral_id', 'user_name', 'duration_minutes']);
                $peripheralId = (int) $input['peripheral_id'];
                $userName = trim((string) $input['user_name']);
                $durationMinutes = (int) $input['duration_minutes'];
                
                $console = Database::fetchOne(
                    "SELECT p.peripheral_id, p.name, p.model, p.station_id,
                            s.station_code, s.name AS station_name
                     FROM peripherals p
                     LEFT JOIN stations s ON s.station_id = p.station_id
                     WHERE p.peripheral_id = ? AND p.peripheral_type = 'game_console'",
                    [$peripheralId]
                );
                
                if (!$console) {
                    errorResponse('Game console not found', 404);
                }
                
                // Create a service usage record for gaming session
                if (Database::tableExists('service_usage')) {
                    $serviceId = Database::fetchColumn(
                        "SELECT service_id FROM services WHERE category = 'gaming' AND is_active = 1 LIMIT 1"
                    );
                    
                    if ($serviceId) {
                        Database::execute(
                            "INSERT INTO service_usage (service_id, user_id, station_id, staff_id, quantity, unit_price, total_price, status, metadata)
                             VALUES (?, ?, ?, ?, ?, ?, ?, 'in_progress', ?)",
                            [
                                (int) $serviceId,
                                0, // guest/walk-in user
                                (int) $console['station_id'],
                                $_SESSION['user_id'] ?? null,
                                1.00,
                                0.00,
                                0.00,
                                json_encode([
                                    'console_name' => $console['name'],
                                    'model' => $console['model'],
                                    'user_name' => $userName,
                                    'duration_minutes' => $durationMinutes,
                                    'started_at' => date('Y-m-d H:i:s')
                                ])
                            ]
                        );
                    }
                }
                
                logAuditTrail(
                    'game_session_start',
                    'peripheral',
                    $peripheralId,
                    [
                        'console_name' => $console['name'],
                        'user_name' => $userName,
                        'duration_minutes' => $durationMinutes,
                        'station_code' => $console['station_code']
                    ]
                );
                
                successResponse([
                    'session_started' => true,
                    'console' => $console['name'],
                    'user_name' => $userName,
                    'duration_minutes' => $durationMinutes
                ], "Gaming session started on {$console['name']}");
                break;
            }
            
            errorResponse('Invalid action. Allowed: wake, status, set_session');
            break;
            
        default:
            errorResponse('Method not allowed', 405);
    }
} catch (Exception $e) {
    errorResponse($e->getMessage(), 500);
}

/**
 * Wake game console via Wake-on-LAN
 */
function wakeGameConsole(array $console): array
{
    require_once __DIR__ . '/../../includes/device-control.php';
    $network = peripheralNetworkConfig($console);
    return wakeDevice($console['mac_address'] ?? null, $network['ip']);
}
