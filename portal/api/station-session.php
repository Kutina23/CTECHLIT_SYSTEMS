<?php
/**
 * Station agent heartbeat and access-state endpoint.
 * Authenticated with the shared station agent key and bound to the registered MAC.
 */

require_once __DIR__ . '/../../config/functions.php';
require_once __DIR__ . '/../../includes/device-control.php';
require_once __DIR__ . '/session-lifecycle.php';

header('Content-Type: application/json; charset=utf-8');

function stationAgentResponse(array $payload, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_SLASHES);
    exit;
}

$configuredKey = (string) ($_ENV['STATION_AGENT_API_KEY'] ?? '');
if ($configuredKey === '') {
    $environmentKey = getenv('STATION_AGENT_API_KEY');
    $configuredKey = $environmentKey === false ? '' : (string) $environmentKey;
}
$authorization = trim((string) ($_SERVER['HTTP_AUTHORIZATION'] ?? ''));
if ($authorization === '' && function_exists('getallheaders')) {
    foreach (getallheaders() as $headerName => $headerValue) {
        if (strcasecmp($headerName, 'Authorization') === 0) {
            $authorization = trim((string) $headerValue);
            break;
        }
    }
}
if ($configuredKey === '' || !preg_match('/^Bearer\s+(.+)$/i', $authorization, $matches)
    || !hash_equals($configuredKey, trim($matches[1]))) {
    stationAgentResponse(['success' => false, 'message' => 'Station agent authentication is not configured or failed.'], 401);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    stationAgentResponse(['success' => false, 'message' => 'Method not allowed.'], 405);
}

$input = json_decode((string) file_get_contents('php://input'), true);
if (!is_array($input)) {
    stationAgentResponse(['success' => false, 'message' => 'Invalid JSON request.'], 400);
}

$stationId = filter_var($input['station_id'] ?? null, FILTER_VALIDATE_INT);
$ipAddress = trim((string) ($input['ip_address'] ?? ''));
$macAddress = strtoupper(str_replace('-', ':', trim((string) ($input['mac_address'] ?? ''))));
if (!$stationId || !filter_var($ipAddress, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)
    || !preg_match('/^(?:[0-9A-F]{2}:){5}[0-9A-F]{2}$/', $macAddress)) {
    stationAgentResponse(['success' => false, 'message' => 'A valid station ID, IPv4 address, and MAC address are required.'], 400);
}

try {
    expireScheduledSessions();

    $station = Database::fetchOne(
        'SELECT station_id, station_code, ip_address, mac_address, status
         FROM stations WHERE station_id = ?',
        [$stationId]
    );
    if (!$station) {
        stationAgentResponse(['success' => false, 'message' => 'Station not found.'], 404);
    }

    $registeredMac = strtoupper(str_replace('-', ':', (string) ($station['mac_address'] ?? '')));
    if ($registeredMac === '' || !hash_equals($registeredMac, $macAddress)) {
        stationAgentResponse(['success' => false, 'message' => 'Station MAC address does not match its registered device.'], 403);
    }

    // Results of commands the agent ran since its last check-in.
    $commandResults = is_array($input['command_results'] ?? null) ? array_slice($input['command_results'], 0, 20) : [];
    foreach ($commandResults as $commandResult) {
        $commandId = filter_var($commandResult['command_id'] ?? null, FILTER_VALIDATE_INT);
        if (!$commandId || !deviceCommandsTableExists()) {
            continue;
        }
        $owned = Database::fetchColumn(
            'SELECT COUNT(*) FROM device_commands WHERE command_id = ? AND station_id = ?',
            [$commandId, $stationId]
        );
        if ($owned) {
            completeDeviceCommand((int) $commandId, !empty($commandResult['success']), (string) ($commandResult['message'] ?? ''));
        }
    }

    $reportedStatus = strtolower(trim((string) ($input['status'] ?? 'online')));
    if (!in_array($reportedStatus, ['online', 'offline'], true)) {
        stationAgentResponse(['success' => false, 'message' => 'Invalid station heartbeat status.'], 400);
    }

    Database::execute(
        "UPDATE stations
         SET ip_address = ?, last_heartbeat = NOW(),
             status = CASE
                 WHEN ? = 'offline' THEN 'offline'
                 WHEN status = 'maintenance' THEN 'maintenance'
                 WHEN EXISTS (
                     SELECT 1 FROM sessions s
                     WHERE s.station_id = stations.station_id
                       AND s.status = 'active'
                       AND (s.scheduled_end_at IS NULL OR s.scheduled_end_at > NOW())
                 ) THEN 'occupied'
                 ELSE 'available'
             END
         WHERE station_id = ?",
        [$ipAddress, $reportedStatus, $stationId]
    );

    $activeSession = Database::fetchOne(
        "SELECT session_id, scheduled_end_at
         FROM sessions
         WHERE station_id = ?
           AND status = 'active'
           AND (scheduled_end_at IS NULL OR scheduled_end_at > NOW())
         ORDER BY started_at DESC
         LIMIT 1",
        [$stationId]
    );

    stationAgentResponse([
        'success' => true,
        'station_id' => (int) $stationId,
        'ip_address' => $ipAddress,
        'mac_address' => $macAddress,
        'internet_allowed' => $station['status'] !== 'maintenance' && $activeSession !== null,
        'session_id' => $activeSession ? (int) $activeSession['session_id'] : null,
        'scheduled_end_at' => $activeSession['scheduled_end_at'] ?? null,
        'commands' => $reportedStatus === 'online' ? claimPendingDeviceCommands((int) $stationId) : [],
    ]);
} catch (Throwable $error) {
    error_log('Station session check failed: ' . $error->getMessage());
    stationAgentResponse(['success' => false, 'message' => 'Unable to determine station session state.'], 500);
}
