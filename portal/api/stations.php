<?php
/**
 * CTECHLIT & SYSTEMS — Station Management & Hardware Control
 * CRUD for workstations plus Wake-on-LAN, reachability checks and agent commands.
 */

require_once __DIR__ . '/../../config/functions.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/device-control.php';
requireStaffAuth();

$method = $_SERVER['REQUEST_METHOD'];
$input = jsonInput() ?: $_POST;
$action = strtolower((string) ($input['action'] ?? $_GET['action'] ?? ''));

function stationRow(int $stationId): array
{
    $station = Database::fetchOne(
        "SELECT station_id, station_code, name, location, status, ip_address, mac_address,
                specs, last_heartbeat, created_at, updated_at
         FROM stations WHERE station_id = ?",
        [$stationId]
    );
    if (!$station) {
        errorResponse('Station not found', 404);
    }
    return $station;
}

function presentStation(array $station): array
{
    $specs = json_decode((string) ($station['specs'] ?? ''), true);
    return [
        'station_id' => (int) $station['station_id'],
        'station_code' => $station['station_code'],
        'name' => $station['name'],
        'location' => $station['location'] ?? null,
        'status' => $station['status'],
        'ip_address' => $station['ip_address'],
        'mac_address' => $station['mac_address'],
        'specs' => is_array($specs) ? $specs : [],
        'is_online' => stationAgentOnline($station),
        'last_heartbeat' => $station['last_heartbeat'] ?? null,
        'created_at' => $station['created_at'] ?? null,
        'updated_at' => $station['updated_at'] ?? null,
    ];
}

function stationFieldsFromInput(array $input, bool $partial): array
{
    $fields = [];
    foreach (['station_code', 'name', 'location'] as $key) {
        if (array_key_exists($key, $input)) {
            $fields[$key] = trim((string) $input[$key]) === '' && $key === 'location' ? null : trim((string) $input[$key]);
        }
    }
    if (array_key_exists('status', $input)) {
        $status = strtolower((string) $input['status']);
        if (!in_array($status, ['available', 'occupied', 'maintenance', 'offline'], true)) {
            errorResponse('Status must be available, occupied, maintenance or offline');
        }
        $fields['status'] = $status;
    }
    if (array_key_exists('ip_address', $input)) {
        $ip = trim((string) $input['ip_address']);
        if ($ip !== '' && !filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            errorResponse('Enter a valid IPv4 address, for example 192.168.1.101');
        }
        $fields['ip_address'] = $ip === '' ? null : $ip;
    }
    if (array_key_exists('mac_address', $input)) {
        $raw = trim((string) $input['mac_address']);
        $mac = normalizeMacAddress($raw);
        if ($raw !== '' && $mac === null) {
            errorResponse('Enter a valid MAC address, for example AA:BB:CC:DD:EE:FF');
        }
        $fields['mac_address'] = $mac;
    }
    if (array_key_exists('specs', $input)) {
        $specs = $input['specs'];
        if (is_string($specs)) {
            $specs = trim($specs) === '' ? [] : json_decode($specs, true);
            if (!is_array($specs)) {
                errorResponse('Specifications must be valid JSON, for example {"cpu":"Intel i5","ram":"16GB"}');
            }
        }
        $fields['specs'] = json_encode(is_array($specs) ? $specs : []);
    }
    if (!$partial) {
        foreach (['station_code', 'name'] as $required) {
            if (empty($fields[$required])) {
                errorResponse("Field '$required' is required");
            }
        }
    }
    return $fields;
}

try {
    switch ($method) {
        case 'GET':
            requirePermission('stations.view');
            if (isset($_GET['station_id']) && ($action === '' || $action === 'status')) {
                successResponse(presentStation(stationRow((int) $_GET['station_id'])));
            }
            $stations = Database::fetchAll(
                "SELECT station_id, station_code, name, location, status, ip_address, mac_address,
                        specs, last_heartbeat, created_at, updated_at
                 FROM stations ORDER BY station_code ASC"
            );
            successResponse(array_map('presentStation', $stations));
            break;

        case 'POST':
            if ($action === '' || $action === 'create') {
                requirePermission('stations.manage');
                $fields = stationFieldsFromInput($input, false);
                $fields['status'] = $fields['status'] ?? 'available';
                $columns = array_keys($fields);
                $stationId = Database::insert(
                    'INSERT INTO stations (' . implode(', ', $columns) . ') VALUES (' . implode(', ', array_fill(0, count($columns), '?')) . ')',
                    array_values($fields)
                );
                logAuditTrail('station_created', 'station', $stationId, ['station_code' => $fields['station_code']]);
                successResponse(presentStation(stationRow($stationId)), 'Station created successfully');
            }

            if ($action === 'heartbeat') {
                requirePermission('stations.manage');
                $stationId = (int) ($input['station_id'] ?? 0);
                $status = strtolower(trim((string) ($input['status'] ?? '')));
                if (!in_array($status, ['available', 'occupied', 'maintenance', 'offline'], true)) {
                    errorResponse('Status must be available, occupied, maintenance or offline');
                }
                stationRow($stationId);
                Database::execute('UPDATE stations SET status = ? WHERE station_id = ?', [$status, $stationId]);
                logAuditTrail('station_status_set', 'station', $stationId, ['status' => $status]);
                successResponse(['station_id' => $stationId, 'status' => $status], 'Station status updated');
            }

            requirePermission('stations.control');
            $stationId = (int) ($input['station_id'] ?? 0);
            if ($stationId <= 0) {
                errorResponse("Field 'station_id' is required");
            }
            $station = stationRow($stationId);

            if ($action === 'ping') {
                successResponse(...stationReachability($station));
            }

            if ($action === 'set_password') {
                $username = trim((string) ($input['username'] ?? ''));
                $newPassword = (string) ($input['new_password'] ?? '');
                if (!preg_match('/\A[A-Za-z0-9._ -]{1,64}\z/', $username) || strlen($newPassword) < 6 || strlen($newPassword) > 127) {
                    errorResponse('Provide a valid Windows username and a password of 6 to 127 characters');
                }
                // Sent only over the direct agent channel; never written to the command queue.
                $result = pushCommandToAgent($station, 'set_password', ['username' => $username, 'password' => $newPassword], null);
                if ($result === null) {
                    errorResponse(deviceControlMode() === 'cloud'
                        ? 'Remote password changes only work when the server is on the café network (passwords are never stored in the online command queue). Change it at the PC instead.'
                        : "{$station['name']}'s agent is not reachable on the network, so the password was not changed.", 502);
                }
                logAuditTrail('station_set_password', 'station', $stationId, ['username' => $username, 'success' => $result['success']]);
                jsonResponse(['success' => $result['success'], 'message' => $result['message'], 'data' => ['username' => $username]], $result['success'] ? 200 : 502);
            }

            // Convenience aliases: action=wake|lock|restart|... is the same as action=command.
            $command = $action === 'command' ? (string) ($input['command'] ?? '') : $action;
            if (!in_array(strtolower($command), STATION_COMMANDS, true)) {
                errorResponse('Invalid action. Allowed: create, ping, heartbeat, command (' . implode(', ', STATION_COMMANDS) . ')');
            }
            $source = in_array($input['source'] ?? '', ['jarvis', 'pos'], true) ? $input['source'] : 'portal';
            $result = controlStation($station, $command, is_array($input['params'] ?? null) ? $input['params'] : ['text' => $input['text'] ?? ''], $source);

            logAuditTrail('station_command_' . strtolower($command), 'station', $stationId, [
                'station_code' => $station['station_code'],
                'status' => $result['status'] ?? null,
                'delivery' => $result['delivery'] ?? null,
                'source' => $source,
            ]);

            jsonResponse([
                'success' => (bool) $result['success'],
                'message' => $result['message'],
                'data' => [
                    'command' => strtolower($command),
                    'status' => $result['status'] ?? null,
                    'command_id' => $result['command_id'] ?? null,
                    'delivery' => $result['delivery'] ?? null,
                ],
            ], $result['success'] ? 200 : 502);
            break;

        case 'PUT':
            requirePermission('stations.manage');
            $stationId = (int) ($input['station_id'] ?? 0);
            stationRow($stationId);
            $fields = stationFieldsFromInput($input, true);
            if ($fields === []) {
                errorResponse('No fields to update');
            }
            $assignments = implode(', ', array_map(fn($column) => "$column = ?", array_keys($fields)));
            Database::execute("UPDATE stations SET $assignments WHERE station_id = ?", [...array_values($fields), $stationId]);
            logAuditTrail('station_updated', 'station', $stationId, array_keys($fields));
            successResponse(presentStation(stationRow($stationId)), 'Station updated successfully');
            break;

        case 'DELETE':
            requirePermission('stations.manage');
            $stationId = (int) ($input['station_id'] ?? 0);
            $station = stationRow($stationId);
            $active = (int) Database::fetchColumn("SELECT COUNT(*) FROM sessions WHERE station_id = ? AND status = 'active'", [$stationId]);
            if ($active > 0) {
                errorResponse('End the active session on this station before deleting it.', 409);
            }
            Database::execute('DELETE FROM stations WHERE station_id = ?', [$stationId]);
            logAuditTrail('station_deleted', 'station', $stationId, ['station_code' => $station['station_code']]);
            successResponse(null, 'Station deleted');
            break;

        default:
            errorResponse('Method not allowed', 405);
    }
} catch (PDOException $e) {
    if ((int) ($e->errorInfo[1] ?? 0) === 1062) {
        errorResponse('A station with that code or MAC address already exists.', 409);
    }
    error_log('Stations API error: ' . $e->getMessage());
    errorResponse('The station request could not be completed.', 500);
}
