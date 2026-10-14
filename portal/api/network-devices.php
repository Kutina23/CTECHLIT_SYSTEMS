<?php
/**
 * CTECHLIT & SYSTEMS — Network Devices API
 * Returns all network-connected devices for visualization
 */

require_once __DIR__ . '/../../includes/auth.php';
requireStaffAuth();

header('Content-Type: application/json');

try {
    $devices = [];
    
    // Get all stations with their status
    $stations = Database::fetchAll(
        "SELECT station_id, station_code, name, location, status, ip_address, mac_address, last_heartbeat,
                specs, created_at
         FROM stations
         ORDER BY station_code ASC"
    );
    
    foreach ($stations as $station) {
        $specs = json_decode((string) $station['specs'], true);
        $lastHeartbeat = $station['last_heartbeat'] ?? null;
        $isOnline = $lastHeartbeat && (strtotime($lastHeartbeat) > strtotime('-5 minutes'));
        
        $devices[] = [
            'id' => 'station-' . $station['station_id'],
            'type' => 'station',
            'name' => $station['name'],
            'code' => $station['station_code'],
            'location' => $station['location'],
            'status' => $station['status'],
            'is_online' => $isOnline,
            'ip_address' => $station['ip_address'],
            'mac_address' => $station['mac_address'],
            'last_heartbeat' => $lastHeartbeat,
            'specs' => is_array($specs) ? $specs : [],
            'icon' => 'fa-desktop',
            'color' => getDeviceColor('station', $station['status'], $isOnline),
        ];
    }
    
    // Get all peripherals (printers, scanners, game consoles, etc.)
    $peripherals = Database::fetchAll(
        "SELECT p.peripheral_id, p.peripheral_type, p.name, p.model, p.serial_number, p.mac_address,
                p.status, p.config, p.last_maintenance, p.installed_at,
                s.station_code, s.name AS station_name, s.location AS station_location
         FROM peripherals p
         LEFT JOIN stations s ON s.station_id = p.station_id
         ORDER BY p.peripheral_type, p.name"
    );
    
    foreach ($peripherals as $peripheral) {
        $config = json_decode((string) $peripheral['config'], true);
        $isOnline = $peripheral['status'] === 'online';
        
        $iconMap = [
            'printer' => 'fa-print',
            'scanner' => 'fa-scanner',
            'game_console' => 'fa-gamepad',
            'router' => 'fa-router',
            'other' => 'fa-microchip',
        ];
        
        $devices[] = [
            'id' => 'peripheral-' . $peripheral['peripheral_id'],
            'type' => $peripheral['peripheral_type'],
            'name' => $peripheral['name'],
            'code' => $peripheral['station_code'] ?? '',
            'model' => $peripheral['model'],
            'serial_number' => $peripheral['serial_number'],
            'mac_address' => $peripheral['mac_address'] ?? null,
            'status' => $peripheral['status'],
            'is_online' => $isOnline,
            'station_code' => $peripheral['station_code'],
            'station_name' => $peripheral['station_name'],
            'location' => $peripheral['station_location'],
            'config' => is_array($config) ? $config : [],
            'icon' => $iconMap[$peripheral['peripheral_type']] ?? 'fa-microchip',
            'color' => getDeviceColor($peripheral['peripheral_type'], $peripheral['status'], $isOnline),
            'peripheral_id' => $peripheral['peripheral_id'],
        ];
    }
    
    // Calculate summary stats
    $stats = [
        'total' => count($devices),
        'online' => count(array_filter($devices, fn($d) => $d['is_online'])),
        'offline' => count(array_filter($devices, fn($d) => !$d['is_online'])),
        'stations' => count(array_filter($devices, fn($d) => $d['type'] === 'station')),
        'printers' => count(array_filter($devices, fn($d) => $d['type'] === 'printer')),
        'scanners' => count(array_filter($devices, fn($d) => $d['type'] === 'scanner')),
        'game_consoles' => count(array_filter($devices, fn($d) => $d['type'] === 'game_console')),
        'routers' => count(array_filter($devices, fn($d) => $d['type'] === 'router')),
    ];
    
    successResponse([
        'devices' => $devices,
        'stats' => $stats,
        'generated_at' => date('Y-m-d H:i:s')
    ]);
    
} catch (Exception $e) {
    errorResponse($e->getMessage(), 500);
}

/**
 * Get color for device based on type, status, and online state
 */
function getDeviceColor(string $type, string $status, bool $isOnline): string
{
    if (!$isOnline) {
        return '#94A3B8'; // Gray for offline
    }
    
    $colors = [
        'station' => [
            'available' => '#10B981', // Green
            'occupied' => '#F59E0B',  // Amber
            'maintenance' => '#EF4444', // Red
            'offline' => '#94A3B8',   // Gray
        ],
        'printer' => [
            'online' => '#10B981',
            'offline' => '#94A3B8',
            'error' => '#EF4444',
            'maintenance' => '#F59E0B',
        ],
        'scanner' => [
            'online' => '#06B6D4',
            'offline' => '#94A3B8',
            'error' => '#EF4444',
            'maintenance' => '#F59E0B',
        ],
        'game_console' => [
            'online' => '#7C3AED',
            'offline' => '#94A3B8',
            'error' => '#EF4444',
            'maintenance' => '#F59E0B',
        ],
        'router' => [
            'online' => '#10B981',
            'offline' => '#94A3B8',
            'error' => '#EF4444',
            'maintenance' => '#F59E0B',
        ],
    ];
    
    return $colors[$type][$status] ?? '#7C3AED';
}
