<?php
/**
 * CTECHLIT & SYSTEMS — Local Network Device Control
 *
 * Shared hardware-control layer used by the stations, consoles, printers,
 * unified devices API and Jarvis. Commands reach a workstation in this order:
 *   1. Direct push to the station agent's HTTP listener on the LAN (instant).
 *   2. A queued command the agent collects on its next poll (within ~5s).
 * Wake-on-LAN is sent from the server as a UDP broadcast magic packet.
 */

require_once __DIR__ . '/../config/functions.php';

const STATION_COMMANDS = ['lock', 'logoff', 'restart', 'shutdown', 'sleep', 'wake', 'message', 'status'];
const STATION_AGENT_ONLINE_SECONDS = 90;

function deviceEnv(string $key, string $default = ''): string
{
    $value = $_ENV[$key] ?? getenv($key);
    return ($value === false || $value === null || $value === '') ? $default : (string) $value;
}

/**
 * 'lan'   — the server is inside the café network: it can broadcast Wake-on-LAN,
 *           probe devices and push commands straight to agents.
 * 'cloud' — the server is hosted online: everything goes through the agents' outbound
 *           HTTPS check-ins, and LAN-only jobs (Wake-on-LAN, probes) are relayed by an
 *           online agent in the café.
 * Set DEVICE_CONTROL_MODE=lan|cloud in .env, or leave it as auto (based on the server address).
 */
function deviceControlMode(): string
{
    $mode = strtolower(deviceEnv('DEVICE_CONTROL_MODE', 'auto'));
    if (in_array($mode, ['lan', 'cloud'], true)) {
        return $mode;
    }
    $serverIp = (string) ($_SERVER['SERVER_ADDR'] ?? '');
    if ($serverIp === '' || PHP_SAPI === 'cli') {
        return 'lan';
    }
    $isPrivate = !filter_var($serverIp, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
    return $isPrivate ? 'lan' : 'cloud';
}

function normalizeMacAddress(?string $mac): ?string
{
    $hex = strtoupper(preg_replace('/[^0-9A-Fa-f]/', '', (string) $mac));
    if (strlen($hex) !== 12) {
        return null;
    }
    return implode(':', str_split($hex, 2));
}

/**
 * Broadcast addresses for a magic packet: global, configured, and the /24 of the device IP.
 */
function wakeOnLanBroadcasts(?string $deviceIp = null): array
{
    $addresses = ['255.255.255.255'];
    foreach (array_filter(array_map('trim', explode(',', deviceEnv('WOL_BROADCAST_ADDRESSES')))) as $configured) {
        if (filter_var($configured, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $addresses[] = $configured;
        }
    }
    if ($deviceIp && filter_var($deviceIp, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
        $parts = explode('.', $deviceIp);
        $parts[3] = '255';
        $addresses[] = implode('.', $parts);
    }
    return array_values(array_unique($addresses));
}

/**
 * Send a Wake-on-LAN magic packet. Works without the PHP sockets extension.
 */
function sendWakeOnLan(?string $mac, ?string $deviceIp = null): array
{
    $normalized = normalizeMacAddress($mac);
    if ($normalized === null) {
        return ['success' => false, 'message' => 'A valid MAC address is required for Wake-on-LAN.'];
    }

    $macBinary = hex2bin(str_replace(':', '', $normalized));
    $packet = str_repeat("\xFF", 6) . str_repeat($macBinary, 16);
    $context = stream_context_create(['socket' => ['so_broadcast' => true]]);
    $sentTo = [];

    foreach (wakeOnLanBroadcasts($deviceIp) as $broadcast) {
        foreach ([9, 7] as $port) {
            $stream = @stream_socket_client("udp://$broadcast:$port", $errno, $errstr, 1, STREAM_CLIENT_CONNECT, $context);
            if ($stream === false) {
                continue;
            }
            if (@fwrite($stream, $packet) === strlen($packet)) {
                $sentTo[] = "$broadcast:$port";
            }
            fclose($stream);
        }
    }

    if ($sentTo === []) {
        return ['success' => false, 'message' => 'The server could not send the Wake-on-LAN broadcast. Check that the server is on the same network.'];
    }
    return ['success' => true, 'message' => "Wake-on-LAN packet sent to $normalized", 'sent_to' => $sentTo];
}

/**
 * TCP reachability probe across common ports. Returns the first port that answers.
 */
function probeHost(?string $ip, array $ports = [445, 3389, 135, 80, 8080], float $timeout = 1.0): array
{
    if (!$ip || !filter_var($ip, FILTER_VALIDATE_IP)) {
        return ['online' => false, 'message' => 'No valid IP address is configured.'];
    }
    foreach ($ports as $port) {
        $start = microtime(true);
        $connection = @fsockopen($ip, (int) $port, $errno, $errstr, $timeout);
        if ($connection !== false) {
            fclose($connection);
            return [
                'online' => true,
                'port' => (int) $port,
                'response_time_ms' => (int) round((microtime(true) - $start) * 1000),
                'message' => "Reachable at $ip:$port",
            ];
        }
    }
    return ['online' => false, 'message' => "No response from $ip"];
}

function stationAgentOnline(array $station): bool
{
    $heartbeat = $station['last_heartbeat'] ?? null;
    return $heartbeat !== null && strtotime((string) $heartbeat) >= time() - STATION_AGENT_ONLINE_SECONDS;
}

function deviceCommandsTableExists(): bool
{
    static $exists = null;
    if ($exists === null) {
        $exists = Database::tableExists('device_commands');
    }
    return $exists;
}

/**
 * Push a command straight to the station agent's LAN listener.
 */
function pushCommandToAgent(array $station, string $command, array $params, ?int $commandId): ?array
{
    $apiKey = deviceEnv('STATION_AGENT_API_KEY');
    $ip = (string) ($station['ip_address'] ?? '');
    if ($apiKey === '' || !filter_var($ip, FILTER_VALIDATE_IP) || !function_exists('curl_init') || deviceControlMode() === 'cloud') {
        return null; // A hosted server cannot reach private café addresses; use the check-in queue.
    }

    $port = (int) deviceEnv('STATION_AGENT_PORT', '8080');
    $handle = curl_init("http://$ip:$port/ctechlit-agent/command");
    curl_setopt_array($handle, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Authorization: Bearer ' . $apiKey],
        CURLOPT_POSTFIELDS => json_encode(['command' => $command, 'params' => (object) $params, 'command_id' => $commandId]),
        CURLOPT_CONNECTTIMEOUT => 2,
        CURLOPT_TIMEOUT => 5,
        CURLOPT_FOLLOWLOCATION => false,
    ]);
    $body = curl_exec($handle);
    $status = (int) curl_getinfo($handle, CURLINFO_HTTP_CODE);
    curl_close($handle);

    if ($body === false || $status === 0) {
        return null; // Listener unreachable: fall back to the poll queue.
    }
    $result = json_decode((string) $body, true);
    if (!is_array($result)) {
        return null;
    }
    return [
        'success' => !empty($result['success']) && $status < 400,
        'message' => (string) ($result['message'] ?? ($status < 400 ? 'Command delivered' : 'The station agent rejected the command')),
    ];
}

function queueDeviceCommand(int $stationId, string $command, array $params, string $source): ?int
{
    if (!deviceCommandsTableExists()) {
        return null;
    }
    // Drop stale duplicates so a reconnecting agent does not replay old power commands.
    Database::execute(
        "UPDATE device_commands SET status = 'expired'
         WHERE station_id = ? AND status = 'queued' AND expires_at <= NOW()",
        [$stationId]
    );
    return Database::insert(
        "INSERT INTO device_commands (station_id, command, params, status, source, requested_by, expires_at)
         VALUES (?, ?, ?, 'queued', ?, ?, DATE_ADD(NOW(), INTERVAL 2 MINUTE))",
        [
            $stationId,
            $command,
            json_encode((object) $params),
            $source,
            ($_SESSION['user_type'] ?? '') === 'staff' ? (int) $_SESSION['user_id'] : null,
        ]
    );
}

function completeDeviceCommand(int $commandId, bool $success, string $message): void
{
    if (!deviceCommandsTableExists()) {
        return;
    }
    Database::execute(
        "UPDATE device_commands
         SET status = ?, result_message = ?, completed_at = NOW(), delivered_at = COALESCE(delivered_at, NOW())
         WHERE command_id = ? AND status IN ('queued', 'sent')",
        [$success ? 'succeeded' : 'failed', mb_substr($message, 0, 500), $commandId]
    );
}

/**
 * Wait briefly for an agent to report a queued command's result.
 */
function awaitDeviceCommand(int $commandId, float $seconds): ?array
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_write_close(); // Do not block the user's other requests while waiting.
    }
    $deadline = microtime(true) + $seconds;
    do {
        $row = Database::fetchOne(
            'SELECT status, result_message FROM device_commands WHERE command_id = ?',
            [$commandId]
        );
        if ($row && in_array($row['status'], ['succeeded', 'failed'], true)) {
            return ['success' => $row['status'] === 'succeeded', 'message' => (string) $row['result_message']];
        }
        usleep(300000);
    } while (microtime(true) < $deadline);
    return null;
}

/**
 * Run a power/session command on a workstation through its agent.
 */
function controlStation(array $station, string $command, array $params = [], string $source = 'portal', float $waitSeconds = 8.0): array
{
    $command = strtolower(trim($command));
    if (!in_array($command, STATION_COMMANDS, true)) {
        return ['success' => false, 'status' => 'rejected', 'message' => "Unsupported station command '$command'. Allowed: " . implode(', ', STATION_COMMANDS)];
    }
    $name = $station['name'] ?? $station['station_code'] ?? 'station';

    if ($command === 'wake') {
        $result = wakeDevice($station['mac_address'] ?? null, $station['ip_address'] ?? null, (int) $station['station_id']);
        $result['status'] = $result['status'] ?? ($result['success'] ? 'sent' : 'failed');
        if ($result['success'] && $result['status'] !== 'queued') {
            $result['message'] = "Wake-on-LAN sent to $name" . (isset($result['relay']) ? " via {$result['relay']}" : '') . '. It usually takes 20–60 seconds to boot.';
        }
        return $result;
    }

    if ($command === 'message') {
        $text = trim((string) ($params['text'] ?? ''));
        if ($text === '' || mb_strlen($text) > 250) {
            return ['success' => false, 'status' => 'rejected', 'message' => 'A message of 1 to 250 characters is required.'];
        }
        $params = ['text' => $text];
    } else {
        $params = [];
    }

    $commandId = queueDeviceCommand((int) $station['station_id'], $command, $params, $source);

    $pushed = pushCommandToAgent($station, $command, $params, $commandId);
    if ($pushed !== null) {
        if ($commandId !== null) {
            completeDeviceCommand($commandId, $pushed['success'], $pushed['message']);
        }
        return [
            'success' => $pushed['success'],
            'status' => $pushed['success'] ? 'executed' : 'failed',
            'message' => $pushed['success'] ? ucfirst($command) . " executed on $name." : $pushed['message'],
            'command_id' => $commandId,
            'delivery' => 'direct',
        ];
    }

    if ($commandId === null) {
        return ['success' => false, 'status' => 'failed', 'message' => "$name's agent could not be reached. Run the database migrations to enable queued delivery."];
    }

    if (!stationAgentOnline($station)) {
        return [
            'success' => true,
            'status' => 'queued',
            'message' => "$name's agent is offline. The $command command is queued for 2 minutes and will run when the agent reconnects.",
            'command_id' => $commandId,
            'delivery' => 'queued',
        ];
    }

    $result = $waitSeconds > 0 ? awaitDeviceCommand($commandId, $waitSeconds) : null;
    if ($result !== null) {
        return [
            'success' => $result['success'],
            'status' => $result['success'] ? 'executed' : 'failed',
            'message' => $result['success'] ? ucfirst($command) . " executed on $name." : ($result['message'] ?: "The $command command failed on $name."),
            'command_id' => $commandId,
            'delivery' => 'poll',
        ];
    }

    return [
        'success' => true,
        'status' => 'queued',
        'message' => "The $command command is queued for $name and will run on the agent's next check-in.",
        'command_id' => $commandId,
        'delivery' => 'queued',
    ];
}

/**
 * Is a station reachable? On the LAN we probe it; when hosted we go by its agent's check-ins.
 * Returns [data, message] for successResponse().
 */
function stationReachability(array $station): array
{
    $agentOnline = stationAgentOnline($station);
    $name = $station['name'] ?? $station['station_code'];
    if (deviceControlMode() === 'cloud') {
        $seen = $station['last_heartbeat'] ? (int) Database::fetchColumn('SELECT TIMESTAMPDIFF(SECOND, ?, NOW())', [$station['last_heartbeat']]) : null;
        return [
            ['online' => $agentOnline, 'agent_online' => $agentOnline, 'seconds_since_seen' => $seen, 'checked_at' => date('Y-m-d H:i:s')],
            $agentOnline
                ? "$name is online (its agent checked in {$seen}s ago)."
                : "$name is offline — its agent " . ($seen === null ? 'has never connected.' : 'last checked in ' . round($seen / 60) . ' min ago.'),
        ];
    }
    $probe = probeHost($station['ip_address'], [445, 3389, 135, (int) deviceEnv('STATION_AGENT_PORT', '8080')]);
    return [
        ['online' => $probe['online'], 'agent_online' => $agentOnline, 'response_time_ms' => $probe['response_time_ms'] ?? null, 'checked_at' => date('Y-m-d H:i:s')],
        "$name: " . $probe['message'],
    ];
}

/**
 * Pick an online agent in the café to run a LAN-only job, preferring one on the
 * same /24 subnet as the target device.
 */
function findRelayAgent(?string $targetIp = null, ?int $excludeStationId = null): ?array
{
    $agents = Database::fetchAll(
        'SELECT station_id, station_code, name, ip_address, last_heartbeat FROM stations
         WHERE last_heartbeat >= DATE_SUB(NOW(), INTERVAL ? SECOND) AND station_id <> ?
         ORDER BY last_heartbeat DESC',
        [STATION_AGENT_ONLINE_SECONDS, (int) $excludeStationId]
    );
    if ($agents === []) {
        return null;
    }
    if ($targetIp && filter_var($targetIp, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
        $prefix = implode('.', array_slice(explode('.', $targetIp), 0, 3)) . '.';
        foreach ($agents as $agent) {
            if (str_starts_with((string) $agent['ip_address'], $prefix)) {
                return $agent;
            }
        }
    }
    return $agents[0];
}

/**
 * Run a LAN job (wol, probe) on a café agent and wait for its answer.
 */
function relayLanCommand(string $command, array $params, ?string $targetIp, ?int $excludeStationId = null, float $waitSeconds = 9.0): array
{
    $relay = findRelayAgent($targetIp, $excludeStationId);
    if ($relay === null) {
        return [
            'success' => false,
            'status' => 'no_relay',
            'message' => 'The server is online, so this needs a PC in the café to relay it, but no station agent is connected right now. '
                . 'Keep one PC with the agent switched on (for example the front-desk PC installed with -RelayOnly).',
        ];
    }
    $commandId = queueDeviceCommand((int) $relay['station_id'], $command, $params, 'system');
    if ($commandId === null) {
        return ['success' => false, 'status' => 'failed', 'message' => 'Run the database migrations to enable relayed commands.'];
    }
    $result = awaitDeviceCommand($commandId, $waitSeconds);
    if ($result === null) {
        return [
            'success' => true,
            'status' => 'queued',
            'message' => "Sent to {$relay['station_code']} to relay; it will run on that PC's next check-in.",
            'relay' => $relay['station_code'],
        ];
    }
    return $result + ['status' => $result['success'] ? 'executed' : 'failed', 'relay' => $relay['station_code']];
}

/**
 * Wake a device: broadcast from the server on the LAN, or relay through a café agent when hosted.
 */
function wakeDevice(?string $mac, ?string $ip, ?int $excludeStationId = null): array
{
    $normalized = normalizeMacAddress($mac);
    if ($normalized === null) {
        return ['success' => false, 'message' => 'A valid MAC address is required for Wake-on-LAN.'];
    }
    if (deviceControlMode() === 'lan') {
        return sendWakeOnLan($normalized, $ip);
    }
    $broadcast = null;
    if ($ip && filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
        $parts = explode('.', $ip);
        $parts[3] = '255';
        $broadcast = implode('.', $parts);
    }
    $result = relayLanCommand('wol', ['mac' => $normalized, 'broadcast' => $broadcast], $ip, $excludeStationId);
    if ($result['success'] && ($result['status'] ?? '') === 'executed') {
        $result['message'] = "Wake-on-LAN sent to $normalized via {$result['relay']}.";
    }
    return $result;
}

/**
 * Check whether a LAN address answers on any of the given TCP ports.
 */
function probeDevice(?string $ip, array $ports): array
{
    if (!$ip || !filter_var($ip, FILTER_VALIDATE_IP)) {
        return ['online' => false, 'message' => 'No valid IP address is configured.'];
    }
    if (deviceControlMode() === 'lan') {
        return probeHost($ip, $ports);
    }
    $result = relayLanCommand('probe', ['ip' => $ip, 'ports' => array_values(array_map('intval', $ports))], $ip);
    // 'failed' here means the agent ran the probe and nothing answered.
    if (!in_array($result['status'] ?? '', ['executed', 'failed'], true) || !isset($result['relay'])) {
        return ['online' => false, 'unknown' => true, 'message' => $result['message']];
    }
    return [
        'online' => $result['success'],
        'message' => ($result['message'] ?: ($result['success'] ? "Reachable at $ip" : "No response from $ip")) . " (checked by {$result['relay']})",
    ];
}

/**
 * Pending commands handed to an agent on poll; marks them as sent.
 */
function claimPendingDeviceCommands(int $stationId): array
{
    if (!deviceCommandsTableExists()) {
        return [];
    }
    Database::execute(
        "UPDATE device_commands SET status = 'expired'
         WHERE station_id = ? AND status IN ('queued', 'sent') AND expires_at <= NOW()",
        [$stationId]
    );
    $commands = Database::fetchAll(
        "SELECT command_id, command, params FROM device_commands
         WHERE station_id = ? AND status = 'queued'
         ORDER BY command_id ASC LIMIT 5",
        [$stationId]
    );
    if ($commands === []) {
        return [];
    }
    $ids = array_map(fn($row) => (int) $row['command_id'], $commands);
    Database::execute(
        'UPDATE device_commands SET status = \'sent\', delivered_at = NOW() WHERE command_id IN (' . implode(',', $ids) . ')'
    );
    return array_map(fn($row) => [
        'command_id' => (int) $row['command_id'],
        'command' => $row['command'],
        'params' => json_decode((string) $row['params'], true) ?: new stdClass(),
    ], $commands);
}

/**
 * Read a peripheral's network config, merging the mac_address column when present.
 */
function peripheralNetworkConfig(array $peripheral): array
{
    $config = is_array($peripheral['config'] ?? null)
        ? $peripheral['config']
        : (json_decode((string) ($peripheral['config'] ?? ''), true) ?: []);
    return [
        'ip' => isset($config['ip']) && filter_var($config['ip'], FILTER_VALIDATE_IP) ? $config['ip'] : null,
        'mac' => normalizeMacAddress($peripheral['mac_address'] ?? null) ?? normalizeMacAddress($config['mac'] ?? null),
        'protocol' => $config['protocol'] ?? null,
    ];
}

/**
 * Control a printer, game console, router or other LAN peripheral.
 */
function controlPeripheral(array $peripheral, string $command): array
{
    $command = strtolower(trim($command));
    $network = peripheralNetworkConfig($peripheral);
    $name = $peripheral['name'] ?? 'device';

    if ($command === 'wake') {
        $result = wakeDevice($network['mac'], $network['ip']);
        if (!$result['success'] && $network['mac'] === null) {
            $result['message'] = "$name has no MAC address configured, so it cannot be woken over the network.";
        }
        return $result;
    }

    if ($command === 'ping' || $command === 'status') {
        $ports = match ($peripheral['peripheral_type'] ?? 'other') {
            'printer' => [631, 9100, 515, 80],
            'game_console' => [9295, 9304, 997, 5050, 80],
            'router' => [80, 443, 53, 22],
            default => [80, 443, 22, 8080],
        };
        $probe = probeDevice($network['ip'], $ports);
        if (!empty($probe['unknown'])) {
            return ['success' => false, 'online' => null, 'message' => $probe['message']];
        }
        if ($network['ip'] !== null && Database::tableExists('peripherals')) {
            Database::execute(
                "UPDATE peripherals SET status = ? WHERE peripheral_id = ? AND status <> 'maintenance'",
                [$probe['online'] ? 'online' : 'offline', (int) $peripheral['peripheral_id']]
            );
        }
        return [
            'success' => true,
            'online' => $probe['online'],
            'message' => $probe['online'] ? "$name is online ({$probe['message']})." : "$name is not responding" . ($network['ip'] ? " at {$network['ip']}." : ' — no IP address is configured.'),
            'response_time_ms' => $probe['response_time_ms'] ?? null,
        ];
    }

    return ['success' => false, 'message' => "$name supports only wake and ping over the network."];
}
