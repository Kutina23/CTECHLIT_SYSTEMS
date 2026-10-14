<?php
/**
 * CTECHLIT & SYSTEMS — Unified Device Control API
 * One endpoint to list and control every networked device: workstations
 * (through the station agent), printers, game consoles and routers.
 *
 * GET  devices.php                      → inventory with live state
 * GET  devices.php?commands=1           → recent command history
 * POST {device:"station:3", command:"lock"}
 * POST {device:"all_stations", command:"message", text:"Closing in 10 minutes"}
 */

require_once __DIR__ . '/common.php';
require_once __DIR__ . '/../../includes/device-control.php';

const PERIPHERAL_COMMANDS = ['wake', 'ping', 'status'];
const BULK_STATION_COMMANDS = ['lock', 'logoff', 'restart', 'shutdown', 'sleep', 'wake', 'message'];

function deviceInventory(): array
{
    $devices = [];
    $stations = Database::fetchAll(
        "SELECT s.station_id, s.station_code, s.name, s.location, s.status, s.ip_address, s.mac_address, s.last_heartbeat,
                (SELECT u.full_name FROM sessions se JOIN users u ON u.user_id = se.user_id
                 WHERE se.station_id = s.station_id AND se.status = 'active' ORDER BY se.started_at DESC LIMIT 1) AS active_customer,
                (SELECT se.scheduled_end_at FROM sessions se
                 WHERE se.station_id = s.station_id AND se.status = 'active' ORDER BY se.started_at DESC LIMIT 1) AS session_ends_at,
                (SELECT TIMESTAMPDIFF(MINUTE, NOW(), se.scheduled_end_at) FROM sessions se
                 WHERE se.station_id = s.station_id AND se.status = 'active' ORDER BY se.started_at DESC LIMIT 1) AS minutes_left,
                TIMESTAMPDIFF(SECOND, s.last_heartbeat, NOW()) AS seconds_since_seen
         FROM stations s ORDER BY s.station_code"
    );
    foreach ($stations as $station) {
        $devices[] = [
            'device' => 'station:' . $station['station_id'],
            'type' => 'station',
            'id' => (int) $station['station_id'],
            'code' => $station['station_code'],
            'name' => $station['name'],
            'location' => $station['location'],
            'status' => $station['status'],
            'online' => stationAgentOnline($station),
            'ip_address' => $station['ip_address'],
            'mac_address' => $station['mac_address'],
            'last_seen' => $station['last_heartbeat'],
            'active_customer' => $station['active_customer'],
            'session_ends_at' => $station['session_ends_at'],
            'minutes_left' => $station['minutes_left'] !== null ? (int) $station['minutes_left'] : null,
            'seconds_since_seen' => $station['seconds_since_seen'] !== null ? (int) $station['seconds_since_seen'] : null,
            'commands' => STATION_COMMANDS,
        ];
    }

    $peripherals = Database::fetchAll(
        "SELECT p.peripheral_id, p.peripheral_type, p.name, p.model, p.status, p.mac_address, p.config, p.updated_at,
                s.station_code
         FROM peripherals p LEFT JOIN stations s ON s.station_id = p.station_id
         ORDER BY p.peripheral_type, p.name"
    );
    foreach ($peripherals as $peripheral) {
        $network = peripheralNetworkConfig($peripheral);
        $devices[] = [
            'device' => 'peripheral:' . $peripheral['peripheral_id'],
            'type' => $peripheral['peripheral_type'],
            'id' => (int) $peripheral['peripheral_id'],
            'code' => $peripheral['station_code'],
            'name' => $peripheral['name'],
            'location' => $peripheral['model'],
            'status' => $peripheral['status'],
            'online' => $peripheral['status'] === 'online',
            'ip_address' => $network['ip'],
            'mac_address' => $network['mac'],
            'last_seen' => $peripheral['updated_at'],
            'active_customer' => null,
            'session_ends_at' => null,
            'minutes_left' => null,
            'seconds_since_seen' => null,
            'commands' => PERIPHERAL_COMMANDS,
        ];
    }
    return $devices;
}

/**
 * Resolve "station:3", "peripheral:7", a station code like "ST-03" or an exact name.
 */
function resolveDevice(string $reference): ?array
{
    $reference = trim($reference);
    if (preg_match('/\A(station|peripheral):(\d+)\z/i', $reference, $m)) {
        $type = strtolower($m[1]);
        $row = $type === 'station'
            ? Database::fetchOne('SELECT * FROM stations WHERE station_id = ?', [(int) $m[2]])
            : Database::fetchOne('SELECT * FROM peripherals WHERE peripheral_id = ?', [(int) $m[2]]);
        return $row ? ['type' => $type, 'row' => $row] : null;
    }
    $station = Database::fetchOne(
        'SELECT * FROM stations WHERE station_code = ? OR LOWER(name) = LOWER(?) LIMIT 1',
        [strtoupper($reference), $reference]
    );
    if ($station) {
        return ['type' => 'station', 'row' => $station];
    }
    // "station 3", "PC 3", "st3" → the station whose code ends in 3.
    if (preg_match('/\A(?:station|st|pc|computer)?\s*-?\s*#?(\d{1,3})\z/i', $reference, $m)) {
        foreach (Database::fetchAll('SELECT * FROM stations ORDER BY station_code') as $candidate) {
            if (preg_match('/(\d+)\s*\z/', $candidate['station_code'], $code) && (int) $code[1] === (int) $m[1]) {
                return ['type' => 'station', 'row' => $candidate];
            }
        }
    }
    $peripheral = Database::fetchOne('SELECT * FROM peripherals WHERE LOWER(name) = LOWER(?) LIMIT 1', [$reference]);
    return $peripheral ? ['type' => 'peripheral', 'row' => $peripheral] : null;
}

$method = $_SERVER['REQUEST_METHOD'];

try {
    if ($method === 'GET') {
        requirePermission('stations.view');
        if (isset($_GET['commands'])) {
            $history = deviceCommandsTableExists()
                ? Database::fetchAll(
                    "SELECT dc.command_id, dc.command, dc.status, dc.source, dc.result_message, dc.created_at, dc.completed_at,
                            s.station_code, s.name AS station_name, st.full_name AS requested_by
                     FROM device_commands dc
                     JOIN stations s ON s.station_id = dc.station_id
                     LEFT JOIN staff st ON st.staff_id = dc.requested_by
                     ORDER BY dc.command_id DESC LIMIT 25"
                )
                : [];
            successResponse($history);
        }
        $devices = deviceInventory();
        successResponse([
            'devices' => $devices,
            'summary' => [
                'total' => count($devices),
                'online' => count(array_filter($devices, fn($d) => $d['online'])),
                'stations' => count(array_filter($devices, fn($d) => $d['type'] === 'station')),
                'agents_online' => count(array_filter($devices, fn($d) => $d['type'] === 'station' && $d['online'])),
            ],
            'agent_key_configured' => deviceEnv('STATION_AGENT_API_KEY') !== '',
            'control_mode' => deviceControlMode(),
            'generated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    if ($method !== 'POST') {
        errorResponse('Method not allowed', 405);
    }

    requirePermission('stations.control');
    $command = strtolower(trim((string) ($input['command'] ?? '')));
    $reference = trim((string) ($input['device'] ?? ''));
    $source = in_array($input['source'] ?? '', ['jarvis', 'pos'], true) ? $input['source'] : 'portal';
    $params = ['text' => (string) ($input['text'] ?? '')];

    if ($reference === '' || $command === '') {
        errorResponse("Fields 'device' and 'command' are required");
    }

    if ($reference === 'all_stations') {
        if (!in_array($command, BULK_STATION_COMMANDS, true)) {
            errorResponse('Bulk commands allowed: ' . implode(', ', BULK_STATION_COMMANDS));
        }
        $results = [];
        foreach (Database::fetchAll("SELECT * FROM stations WHERE status <> 'maintenance' ORDER BY station_code") as $station) {
            $result = controlStation($station, $command, $params, $source, 0);
            $results[] = ['station' => $station['station_code'], 'success' => $result['success'], 'status' => $result['status'] ?? null, 'message' => $result['message']];
        }
        logAuditTrail('bulk_station_command_' . $command, 'station', null, ['count' => count($results), 'source' => $source]);
        $ok = count(array_filter($results, fn($r) => $r['success']));
        successResponse(['results' => $results], "Sent $command to $ok of " . count($results) . ' stations.');
    }

    $device = resolveDevice($reference);
    if ($device === null) {
        errorResponse("No device matches '$reference'.", 404);
    }

    if ($device['type'] === 'station') {
        $station = $device['row'];
        if ($command === 'ping') {
            successResponse(...stationReachability($station));
        }
        $result = controlStation($station, $command, $params, $source);
        logAuditTrail('station_command_' . $command, 'station', (int) $station['station_id'], [
            'status' => $result['status'] ?? null, 'delivery' => $result['delivery'] ?? null, 'source' => $source,
        ]);
    } else {
        $peripheral = $device['row'];
        if (!in_array($command, PERIPHERAL_COMMANDS, true)) {
            errorResponse("{$peripheral['name']} supports: " . implode(', ', PERIPHERAL_COMMANDS));
        }
        $result = controlPeripheral($peripheral, $command);
        logAuditTrail('peripheral_command_' . $command, 'peripheral', (int) $peripheral['peripheral_id'], ['success' => $result['success'], 'source' => $source]);
    }

    jsonResponse([
        'success' => (bool) $result['success'],
        'message' => $result['message'],
        'data' => array_diff_key($result, ['success' => 1, 'message' => 1]),
    ], $result['success'] ? 200 : (($result['status'] ?? '') === 'rejected' ? 422 : 502));
} catch (Throwable $e) {
    error_log('Devices API error: ' . $e->getMessage());
    errorResponse('The device request could not be completed.', 500);
}
