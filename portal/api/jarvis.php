<?php
/**
 * Admin Jarvis assistant API.
 *
 * Actions:
 *   speech  → ElevenLabs text-to-speech (client falls back to the browser voice on 503)
 *   chat    → intent handling: a built-in device-command parser first, then the AI model
 *   execute → runs a validated portal API operation as the signed-in staff member
 */

require_once __DIR__ . '/common.php';
require_once __DIR__ . '/../../includes/device-control.php';

header('Content-Type: application/json; charset=utf-8');

requirePermission('jarvis.use');

$method = $_SERVER['REQUEST_METHOD'];
$action = (string) ($input['action'] ?? '');

// Device commands Jarvis may run without an on-screen confirmation.
const JARVIS_SAFE_DEVICE_COMMANDS = ['wake', 'ping', 'status', 'lock', 'message'];

function jarvisOperationRoutes(): array
{
    return [
        'customers.php' => ['GET', 'POST', 'PUT'],
        'users.php' => ['GET'],
        'staff.php' => ['GET', 'POST', 'PUT'],
        'support.php' => ['GET', 'POST', 'PUT'],
        'services.php' => ['GET', 'POST', 'PUT'],
        'products.php' => ['GET', 'POST', 'PUT'],
        'peripherals.php' => ['GET', 'POST', 'PUT'],
        'sessions.php' => ['GET', 'POST', 'PUT'],
        'stations.php' => ['GET', 'POST'],
        'devices.php' => ['GET', 'POST'],
        'billing.php' => ['GET', 'POST'],
        'orders.php' => ['GET', 'POST', 'PUT'],
        'pos.php' => ['GET', 'POST', 'PUT'],
        'print-jobs.php' => ['GET', 'PUT'],
        'notifications.php' => ['GET', 'POST', 'PUT'],
        'feedback.php' => ['GET', 'POST', 'PUT'],
        'printers.php' => ['GET', 'POST', 'PUT'],
        'game-consoles.php' => ['POST'],
        'reports.php' => ['GET', 'POST'],
        'network-devices.php' => ['GET'],
        'staff-permissions.php' => ['GET', 'PUT'],
        'help-resources.php' => ['GET', 'POST'],
        'personal-devices.php' => ['GET', 'POST'],
        'payments.php' => ['GET', 'POST'],
    ];
}

function jarvisContainsForbiddenKey(array $values): bool
{
    foreach ($values as $key => $value) {
        if (preg_match('/delete|destroy|purge|_method|password|secret|credential|api.?key/i', (string) $key)) {
            return true;
        }
        if (is_array($value) && jarvisContainsForbiddenKey($value)) {
            return true;
        }
    }
    return false;
}

/**
 * Whether a validated operation needs the admin's explicit confirmation.
 */
function jarvisRequiresConfirmation(array $operation): bool
{
    if ($operation['method'] === 'GET') {
        return false;
    }
    if ($operation['requires_local_secret']) {
        return true;
    }
    $data = $operation['data'];
    if ($operation['endpoint'] === 'devices.php') {
        $command = strtolower((string) ($data['command'] ?? ''));
        return ($data['device'] ?? '') === 'all_stations'
            ? !in_array($command, ['wake', 'message'], true)
            : !in_array($command, JARVIS_SAFE_DEVICE_COMMANDS, true);
    }
    if ($operation['endpoint'] === 'stations.php') {
        $stationAction = (string) ($data['action'] ?? '');
        $command = strtolower($stationAction === 'command' ? (string) ($data['command'] ?? '') : $stationAction);
        return !in_array($command, JARVIS_SAFE_DEVICE_COMMANDS, true);
    }
    if ($operation['endpoint'] === 'printers.php' || $operation['endpoint'] === 'game-consoles.php') {
        $actionValue = (string) ($operation['query']['action'] ?? $data['action'] ?? '');
        return !in_array($actionValue, ['test', 'wake', 'status'], true);
    }
    return true;
}

function validateJarvisOperation(array $operation, bool $allowLocalSecret = false): array
{
    $endpoint = (string) ($operation['endpoint'] ?? '');
    $method = strtoupper((string) ($operation['method'] ?? ''));
    $requiresLocalSecret = $operation['requires_local_secret'] ?? false;
    if (!is_bool($requiresLocalSecret)) {
        errorResponse('The local-secret requirement must be a boolean');
    }
    $routes = jarvisOperationRoutes();
    if (!isset($routes[$endpoint]) || !in_array($method, $routes[$endpoint], true)) {
        errorResponse('Jarvis cannot perform that system operation', 403);
    }

    $query = $operation['query'] ?? [];
    $data = $operation['data'] ?? [];
    if (!is_array($query) || !is_array($data)) {
        errorResponse('Operation query and data must be objects');
    }
    if ($endpoint === 'users.php' && $method === 'GET' && empty($query['list'])) {
        errorResponse('Use the safe customer list lookup for this operation', 403);
    }
    if (strlen((string) json_encode([$query, $data])) > 10000) {
        errorResponse('Operation data is too large');
    }
    $secretField = null;
    if ($requiresLocalSecret) {
        if ($endpoint === 'stations.php' && $method === 'POST' && ($data['action'] ?? '') === 'set_password') {
            $secretField = 'new_password';
        } elseif (in_array($endpoint, ['staff.php', 'customers.php'], true) && in_array($method, ['POST', 'PUT'], true)) {
            $secretField = 'password';
        } else {
            errorResponse('Local password entry is only available for staff, customer, and station accounts');
        }
    }
    $safeData = $data;
    if ($allowLocalSecret && $secretField !== null) {
        unset($safeData[$secretField]);
    }
    if (jarvisContainsForbiddenKey($query) || jarvisContainsForbiddenKey($safeData)) {
        errorResponse('Deleting records and changing secrets are not available through Jarvis', 403);
    }

    $postAction = (string) ($data['action'] ?? '');
    $queryAction = (string) ($query['action'] ?? '');
    $allowedActions = [
        'stations.php' => ['POST' => array_merge(['ping', 'command', 'set_password'], STATION_COMMANDS)],
        'sessions.php' => ['POST' => ['start', 'create', 'extend', 'move', 'end', 'settle']],
        'products.php' => ['POST' => ['adjust_stock']],
        'orders.php' => ['POST' => ['refund', 'refund_item']],
        'pos.php' => ['POST' => ['assign', 'sale']],
        'printers.php' => ['POST' => ['test', 'wake', 'configure']],
        'game-consoles.php' => ['POST' => ['status', 'wake', 'set_session']],
        'feedback.php' => ['PUT' => ['testimonial_approval']],
        'personal-devices.php' => ['POST' => ['connect', 'disconnect', 'block', 'unblock', 'set_speed', 'buy_time', 'set_auto_renew']],
        'payments.php' => ['POST' => ['topup', 'online_link', 'verify']],
        'billing.php' => ['POST' => ['topup', 'adjust', 'update_account', 'create_invoice', 'create_manual_invoice', 'record_payment', 'void_invoice', 'settle_wallet']],
    ];
    if (isset($allowedActions[$endpoint][$method])) {
        $actionValue = $endpoint === 'printers.php' ? $queryAction : $postAction;
        $actionRequired = in_array($endpoint, ['billing.php', 'stations.php', 'game-consoles.php', 'payments.php', 'personal-devices.php'], true);
        if (($actionValue === '' && $actionRequired) || ($actionValue !== '' && !in_array($actionValue, $allowedActions[$endpoint][$method], true))) {
            errorResponse('That operation is not available through Jarvis', 403);
        }
    } elseif ($postAction !== '' && !in_array($endpoint, ['support.php', 'customers.php', 'staff.php', 'services.php', 'peripherals.php', 'notifications.php', 'feedback.php'], true)) {
        errorResponse('That operation is not available through Jarvis', 403);
    }

    if ($endpoint === 'devices.php' && $method === 'POST') {
        if (trim((string) ($data['device'] ?? '')) === '' || trim((string) ($data['command'] ?? '')) === '') {
            errorResponse('Device operations need a device and a command');
        }
        $data['source'] = 'jarvis';
    }
    if ($endpoint === 'stations.php' && $method === 'POST') {
        $data['source'] = 'jarvis';
    }

    if ($endpoint === 'print-jobs.php' && $method === 'PUT') {
        $allowedStatuses = ['queued', 'printing', 'completed', 'failed', 'cancelled'];
        if (isset($data['status']) && !in_array($data['status'], $allowedStatuses, true)) {
            errorResponse('That print-job status is not available through Jarvis');
        }
        foreach (['file_name', 'file_path'] as $field) {
            if (array_key_exists($field, $data)) {
                errorResponse('File uploads and path changes are not available through Jarvis');
            }
        }
    }

    if ($allowLocalSecret && $secretField !== null) {
        $secret = (string) ($data[$secretField] ?? '');
        if (strlen($secret) < 6 || strlen($secret) > 255) {
            errorResponse('Enter a password containing 6 to 255 characters');
        }
    }

    $validated = [
        'endpoint' => $endpoint,
        'method' => $method,
        'query' => $query,
        'data' => $data,
        'requires_local_secret' => $requiresLocalSecret,
    ];
    $validated['requires_confirmation'] = jarvisRequiresConfirmation($validated);
    $validated['summary'] = jarvisDescribeOperation($validated);
    return $validated;
}

/**
 * Plain-language description shown on the confirmation card.
 */
function jarvisDescribeOperation(array $operation): string
{
    $data = $operation['data'];
    if ($operation['endpoint'] === 'devices.php' && $operation['method'] === 'POST') {
        $device = (string) ($data['device'] ?? '');
        $label = $device === 'all_stations' ? 'all stations' : jarvisDeviceLabel($device);
        $command = strtolower((string) ($data['command'] ?? ''));
        return $command === 'message'
            ? "Show the message “" . mb_substr((string) ($data['text'] ?? ''), 0, 120) . "” on $label"
            : ucfirst($command) . " $label";
    }
    if ($operation['endpoint'] === 'billing.php' && $operation['method'] === 'POST') {
        $amount = isset($data['amount']) ? 'GHS ' . number_format((float) $data['amount'], 2) : '';
        return match ($data['action'] ?? '') {
            'topup' => "Top up customer #" . (int) ($data['user_id'] ?? 0) . "'s wallet with $amount",
            'adjust' => "Adjust customer #" . (int) ($data['user_id'] ?? 0) . "'s balance by $amount",
            'create_invoice' => "Create an invoice for customer #" . (int) ($data['user_id'] ?? 0) . "'s unbilled charges",
            'record_payment' => "Record a $amount payment on invoice #" . (int) ($data['invoice_id'] ?? 0),
            'void_invoice' => "Void invoice #" . (int) ($data['invoice_id'] ?? 0),
            'settle_wallet' => "Pay customer #" . (int) ($data['user_id'] ?? 0) . "'s open charges from their wallet",
            default => 'Update billing account #' . (int) ($data['user_id'] ?? 0),
        };
    }
    $verb = ['POST' => 'Create/run', 'PUT' => 'Update', 'GET' => 'Look up'][$operation['method']] ?? $operation['method'];
    $subject = str_replace(['.php', '-'], ['', ' '], $operation['endpoint']);
    $actionLabel = $data['action'] ?? $operation['query']['action'] ?? null;
    return trim("$verb $subject" . ($actionLabel ? " ($actionLabel)" : ''));
}

function jarvisDeviceLabel(string $reference): string
{
    if (preg_match('/\A(station|peripheral):(\d+)\z/', $reference, $m)) {
        $row = $m[1] === 'station'
            ? Database::fetchOne('SELECT station_code AS code, name FROM stations WHERE station_id = ?', [(int) $m[2]])
            : Database::fetchOne('SELECT peripheral_type AS code, name FROM peripherals WHERE peripheral_id = ?', [(int) $m[2]]);
        if ($row) {
            return $m[1] === 'station' ? "{$row['code']} ({$row['name']})" : $row['name'];
        }
    }
    return $reference;
}

function redactJarvisResult(mixed $value): mixed
{
    if (!is_array($value)) {
        return $value;
    }
    $redacted = [];
    foreach ($value as $key => $item) {
        if (is_string($key) && preg_match('/password|secret|token|credential|api.?key/i', $key)) {
            continue;
        }
        $redacted[$key] = redactJarvisResult($item);
    }
    return $redacted;
}

/**
 * Call a portal API as the signed-in staff member over the loopback interface.
 */
function executeJarvisOperation(array $operation): array
{
    $https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    $port = (int) ($_SERVER['SERVER_PORT'] ?? ($https ? 443 : 80));
    $host = preg_replace('/[^A-Za-z0-9.:\[\]-]/', '', (string) ($_SERVER['HTTP_HOST'] ?? 'localhost'));
    $apiPath = 'api/' . preg_replace('/\.php$/', '', $operation['endpoint']);
    $url = ($https ? 'https' : 'http') . '://127.0.0.1:' . $port . base_path($apiPath);
    if ($operation['query']) {
        $url .= '?' . http_build_query($operation['query']);
    }

    $headers = [
        'Host: ' . $host,
        'Accept: application/json',
        'X-Requested-With: XMLHttpRequest',
        'Cookie: ' . session_name() . '=' . session_id(),
    ];
    $curlOptions = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => $operation['method'],
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT => 45,
        CURLOPT_FOLLOWLOCATION => false,
        // Loopback only: the certificate is issued for the public hostname, not 127.0.0.1.
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => 0,
    ];
    if ($operation['method'] !== 'GET') {
        $headers[] = 'Content-Type: application/json';
        $headers[] = 'X-CSRF-Token: ' . (string) ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
        $curlOptions[CURLOPT_POSTFIELDS] = json_encode($operation['data'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
    $curlOptions[CURLOPT_HTTPHEADER] = $headers;

    if (session_status() === PHP_SESSION_ACTIVE) {
        session_write_close();
    }
    $ch = curl_init($url);
    curl_setopt_array($ch, $curlOptions);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($response === false) {
        error_log('Jarvis internal API request failed: ' . $curlError);
        errorResponse('The system operation could not reach the portal API', 502);
    }
    $result = json_decode($response, true);
    if (!is_array($result)) {
        error_log('Jarvis internal API returned a non-JSON response for ' . $operation['endpoint'] . ' (HTTP ' . $httpCode . ')');
        errorResponse('The system returned an unreadable response', 502);
    }
    if ($httpCode < 200 || $httpCode >= 300 || empty($result['success'])) {
        errorResponse((string) ($result['message'] ?? $result['error'] ?? 'The system operation failed'), $httpCode >= 400 ? $httpCode : 400);
    }

    $summary = json_encode([
        'message' => $result['message'] ?? 'Operation completed',
        'data' => redactJarvisResult($result['data'] ?? []),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    return [
        'message' => (string) ($result['message'] ?? 'Operation completed'),
        'result' => substr((string) $summary, 0, 8000),
        'data' => redactJarvisResult($result['data'] ?? []),
    ];
}

/**
 * Current device inventory as compact context for the model and the local parser.
 */
function jarvisDeviceContext(): array
{
    $stations = Database::fetchAll('SELECT station_id, station_code, name, status, last_heartbeat FROM stations ORDER BY station_code LIMIT 60');
    $peripherals = Database::fetchAll('SELECT peripheral_id, peripheral_type, name, status FROM peripherals ORDER BY peripheral_type, name LIMIT 40');
    return ['stations' => $stations, 'peripherals' => $peripherals];
}

/**
 * Find a device mentioned in speech: "station 3", "ST-03", "PC 2", "gaming station 1", or a name.
 */
function jarvisMatchDevice(string $text, array $context): ?array
{
    $lower = strtolower($text);
    if (preg_match('/\b(?:all|every)\s+(?:the\s+)?(?:stations|computers|pcs|machines|workstations)\b/', $lower)) {
        return ['device' => 'all_stations', 'label' => 'all stations', 'type' => 'all'];
    }
    // Names first so "gaming station 1" beats the bare "station 1".
    $candidates = [];
    foreach ($context['stations'] as $station) {
        $candidates[] = ['needle' => strtolower($station['name']), 'device' => 'station:' . $station['station_id'], 'label' => "{$station['station_code']} ({$station['name']})", 'type' => 'station'];
    }
    foreach ($context['peripherals'] as $peripheral) {
        $candidates[] = ['needle' => strtolower($peripheral['name']), 'device' => 'peripheral:' . $peripheral['peripheral_id'], 'label' => $peripheral['name'], 'type' => $peripheral['peripheral_type']];
    }
    usort($candidates, fn($a, $b) => strlen($b['needle']) <=> strlen($a['needle']));
    foreach ($candidates as $candidate) {
        if ($candidate['needle'] !== '' && str_contains($lower, $candidate['needle'])) {
            return $candidate;
        }
    }
    if (preg_match('/\b(?:st|station|pc|computer|workstation|machine)[\s#-]*(?:number\s+)?(\d{1,3}|one|two|three|four|five|six|seven|eight|nine|ten)\b/', $lower, $m)) {
        $words = ['one' => 1, 'two' => 2, 'three' => 3, 'four' => 4, 'five' => 5, 'six' => 6, 'seven' => 7, 'eight' => 8, 'nine' => 9, 'ten' => 10];
        $number = $words[$m[1]] ?? (int) $m[1];
        foreach ($context['stations'] as $station) {
            if (preg_match('/(\d+)\s*$/', $station['station_code'], $codeNumber) && (int) $codeNumber[1] === $number) {
                return ['device' => 'station:' . $station['station_id'], 'label' => "{$station['station_code']} ({$station['name']})", 'type' => 'station'];
            }
        }
    }
    return null;
}

/**
 * Built-in understanding of hardware requests so device control works instantly and
 * even when the AI provider is unavailable. Returns null when the text is not a device command.
 */
function jarvisLocalIntent(string $text, array $context): ?array
{
    $lower = strtolower(trim($text));

    if (preg_match('/\b(?:send|show|display|tell|notify|message)\b/', $lower)
        && preg_match('/(?:saying|that|message|:)\s*["“]?(.+?)["”]?\s*$/i', $text, $messageMatch)) {
        $device = jarvisMatchDevice($lower, $context);
        $messageText = trim($messageMatch[1]);
        if ($device && $device['type'] !== 'all' && $device['type'] !== 'station') {
            $device = null;
        }
        if ($device && $messageText !== '' && mb_strlen($messageText) <= 250) {
            return [
                'reply' => "Sending that message to {$device['label']}.",
                'operation' => ['endpoint' => 'devices.php', 'method' => 'POST', 'query' => [], 'data' => ['device' => $device['device'], 'command' => 'message', 'text' => $messageText]],
            ];
        }
    }

    $verbs = [
        'shutdown' => '/\b(?:shut\s*down|turn\s+off|power\s+off|switch\s+off)\b/',
        'restart' => '/\b(?:restart|reboot)\b/',
        'logoff' => '/\b(?:log\s*(?:off|out)|sign\s*(?:off|out))\b/',
        'lock' => '/\block\b/',
        'sleep' => '/\b(?:sleep|hibernate|suspend)\b/',
        'wake' => '/\b(?:wake(?:\s+up)?|turn\s+on|power\s+on|switch\s+on|boot(?:\s+up)?)\b/',
        'ping' => '/\b(?:ping|is\s+.+\s+(?:online|up|reachable)|check\s+(?:if|whether|on|the\s+connection))\b/',
    ];
    foreach ($verbs as $command => $pattern) {
        if (!preg_match($pattern, $lower)) {
            continue;
        }
        $device = jarvisMatchDevice($lower, $context);
        if ($device === null) {
            return null; // Let the model ask which device.
        }
        if ($device['type'] === 'all' && $command === 'ping') {
            return null;
        }
        if ($device['type'] !== 'station' && $device['type'] !== 'all' && !in_array($command, ['wake', 'ping'], true)) {
            return ['reply' => "{$device['label']} can only be woken or checked over the network.", 'operation' => null];
        }
        $phrases = [
            'shutdown' => 'Shutting down', 'restart' => 'Restarting', 'logoff' => 'Signing the user out of',
            'lock' => 'Locking', 'sleep' => 'Putting to sleep', 'wake' => 'Waking', 'ping' => 'Checking',
        ];
        return [
            'reply' => "{$phrases[$command]} {$device['label']}.",
            'operation' => ['endpoint' => 'devices.php', 'method' => 'POST', 'query' => [], 'data' => ['device' => $device['device'], 'command' => $command]],
        ];
    }

    if (preg_match('/\b(?:which|what|how many|list|show|status of)\b.*\b(?:stations?|devices?|computers?|pcs?|printers?|consoles?)\b.*\b(?:online|offline|status|available|up|running|connected)\b/', $lower)
        || preg_match('/\b(?:device|station|network)\s+status\b/', $lower)) {
        $online = [];
        $offline = [];
        foreach ($context['stations'] as $station) {
            $isOnline = $station['last_heartbeat'] !== null && strtotime($station['last_heartbeat']) >= time() - STATION_AGENT_ONLINE_SECONDS;
            if ($isOnline) {
                $online[] = $station['station_code'];
            } else {
                $offline[] = $station['station_code'];
            }
        }
        $total = count($context['stations']);
        $reply = $total === 0
            ? 'No stations are registered yet.'
            : count($online) . " of $total stations are online" . ($online ? ': ' . implode(', ', $online) . '.' : '.')
                . ($offline ? ' Offline: ' . implode(', ', $offline) . '.' : '');
        $printersOnline = count(array_filter($context['peripherals'], fn($p) => $p['status'] === 'online'));
        if ($context['peripherals']) {
            $reply .= " $printersOnline of " . count($context['peripherals']) . ' other devices are online.';
        }
        return ['reply' => $reply, 'operation' => null];
    }

    return null;
}

/**
 * Pull a JSON object out of a model reply that may include code fences or prose.
 */
function jarvisParseModelJson(string $content): ?array
{
    $content = trim($content);
    $content = preg_replace('/\A```(?:json)?\s*|\s*```\z/i', '', $content);
    $decoded = json_decode($content, true);
    if (is_array($decoded)) {
        return $decoded;
    }
    $start = strpos($content, '{');
    $end = strrpos($content, '}');
    if ($start !== false && $end !== false && $end > $start) {
        $decoded = json_decode(substr($content, $start, $end - $start + 1), true);
        if (is_array($decoded)) {
            return $decoded;
        }
    }
    return null;
}

if ($method !== 'POST') {
    errorResponse('Method not allowed', 405);
}

if (!verifyCsrfToken((string) ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ''))) {
    errorResponse('Your session token is invalid. Refresh the page and try again.', 403);
}

if ($action === 'speech') {
    $text = trim((string) ($input['text'] ?? ''));
    if ($text === '' || strlen($text) > 1200) {
        errorResponse('Speech text must contain 1 to 1200 characters');
    }

    $speechApiKey = $_ENV['ELEVENLABS_API_KEY'] ?? '';
    $voiceId = $_ENV['JARVIS_TTS_VOICE_ID'] ?? '';
    $modelId = $_ENV['JARVIS_TTS_MODEL_ID'] ?? 'eleven_multilingual_v2';
    if ($speechApiKey === '' || $voiceId === '' || str_starts_with($speechApiKey, 'your_')) {
        errorResponse('Jarvis voice is not configured. Set ELEVENLABS_API_KEY and JARVIS_TTS_VOICE_ID.', 503);
    }
    if (!preg_match('/\A[A-Za-z0-9_-]{1,64}\z/', $voiceId)
        || !preg_match('/\A[A-Za-z0-9_-]{1,64}\z/', $modelId)) {
        errorResponse('Jarvis voice configuration is invalid', 503);
    }

    $speechPayload = json_encode([
        'text' => $text,
        'model_id' => $modelId,
        'voice_settings' => ['stability' => 0.45, 'similarity_boost' => 0.8, 'style' => 0.25, 'use_speaker_boost' => true],
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    if (session_status() === PHP_SESSION_ACTIVE) {
        session_write_close();
    }
    $speechHandle = curl_init('https://api.elevenlabs.io/v1/text-to-speech/' . rawurlencode($voiceId) . '?output_format=mp3_44100_128');
    curl_setopt_array($speechHandle, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => ['Accept: audio/mpeg', 'Content-Type: application/json', 'xi-api-key: ' . $speechApiKey],
        CURLOPT_POSTFIELDS => $speechPayload,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_FOLLOWLOCATION => false,
    ]);
    $speechResponse = curl_exec($speechHandle);
    $speechStatus = (int) curl_getinfo($speechHandle, CURLINFO_HTTP_CODE);
    $speechError = curl_error($speechHandle);
    curl_close($speechHandle);

    if ($speechResponse === false || $speechStatus < 200 || $speechStatus >= 300 || strlen((string) $speechResponse) > 5 * 1024 * 1024) {
        error_log('Jarvis voice request failed: ' . ($speechError !== '' ? $speechError : 'HTTP ' . $speechStatus));
        // 503 tells the browser to use its built-in voice for the rest of the session.
        $hint = match (true) {
            $speechError !== '' => 'Could not connect to ElevenLabs.',
            $speechStatus === 401 => 'ElevenLabs rejected the API key.',
            $speechStatus === 429 => 'ElevenLabs usage limit reached.',
            default => 'ElevenLabs voice generation failed (HTTP ' . $speechStatus . ').',
        };
        errorResponse($hint . ' Using the browser voice instead.', 503);
    }

    header('Content-Type: audio/mpeg');
    header('Cache-Control: no-store');
    header('X-Content-Type-Options: nosniff');
    echo $speechResponse;
    exit;
}

if ($action === 'chat') {
    $messages = $input['messages'] ?? [];
    if (!is_array($messages) || $messages === [] || count($messages) > 12) {
        errorResponse('A conversation with up to 12 recent messages is required');
    }

    $conversation = [];
    foreach ($messages as $message) {
        if (!is_array($message) || !in_array($message['role'] ?? '', ['user', 'assistant'], true)) {
            errorResponse('The conversation contains an invalid message');
        }
        $content = trim((string) ($message['content'] ?? ''));
        if ($content === '' || strlen($content) > 2000) {
            errorResponse('Messages must contain 1 to 2000 characters');
        }
        $conversation[] = ['role' => $message['role'], 'content' => $content];
    }

    $deviceContext = jarvisDeviceContext();
    $lastMessage = end($conversation);
    $isResultSummary = str_starts_with($lastMessage['content'], '[SYSTEM RESULT]');

    if ($lastMessage['role'] === 'user' && !$isResultSummary) {
        $local = jarvisLocalIntent($lastMessage['content'], $deviceContext);
        if ($local !== null) {
            $operation = $local['operation'] ? validateJarvisOperation($local['operation']) : null;
            successResponse(['reply' => $local['reply'], 'operation' => $operation, 'source' => 'local']);
        }
    }

    $aiApiUrl = $_ENV['AI_API_URL'] ?? '';
    $aiApiKey = $_ENV['AI_API_KEY'] ?? '';
    $aiModel = $_ENV['AI_MODEL'] ?? 'gpt-4o-mini';

    if ($aiApiUrl === '' || $aiApiKey === '' || str_starts_with($aiApiKey, 'your_')) {
        if ($isResultSummary) {
            successResponse(['reply' => 'Done. The results are shown below.', 'operation' => null, 'source' => 'local']);
        }
        successResponse([
            'reply' => 'My AI service is not configured yet, but I can still control devices. Try “lock station 2”, “wake all stations”, or “which stations are online”.',
            'operation' => null,
            'source' => 'local',
        ]);
    }

    $inventoryLines = [];
    foreach ($deviceContext['stations'] as $station) {
        $isOnline = $station['last_heartbeat'] !== null && strtotime($station['last_heartbeat']) >= time() - STATION_AGENT_ONLINE_SECONDS;
        $inventoryLines[] = "station:{$station['station_id']} = {$station['station_code']} \"{$station['name']}\" ({$station['status']}, agent " . ($isOnline ? 'online' : 'offline') . ')';
    }
    foreach ($deviceContext['peripherals'] as $peripheral) {
        $inventoryLines[] = "peripheral:{$peripheral['peripheral_id']} = {$peripheral['peripheral_type']} \"{$peripheral['name']}\" ({$peripheral['status']})";
    }
    $inventory = $inventoryLines ? implode("\n", $inventoryLines) : 'No devices registered.';
    $now = date('l j F Y, H:i');
    $currency = siteSetting('currency', 'GHS');

    $systemPrompt = <<<PROMPT
You are Jarvis, the voice assistant for the CTECHLIT & SYSTEMS internet café portal. It is $now. Currency: $currency.
Speak naturally and briefly (one to three short sentences) because replies are read aloud. Never use markdown.
You can answer questions and operate portal activities except deleting records. Only propose operations from the catalog below using the exact endpoint, method and fields. Never invent routes or claim success before an operation runs.
GET operations are read-only and run immediately. The portal decides which POST/PUT operations need the admin's confirmation; never say an action is complete in the same reply that proposes it.
Never propose DELETE or any delete-like action. If something cannot be done, say so plainly.

Response format — valid JSON only, no code fences:
{"reply":"spoken response","operation":{"endpoint":"devices.php","method":"POST","query":{},"data":{"device":"station:3","command":"lock"}}}
Use "operation":null for normal replies or when you need to ask a clarifying question.

Networked hardware (live inventory — use these device references):
$inventory

Operation catalog (API path /portal/api/<endpoint>):
- devices.php: GET (inventory with live status) or GET query commands=1 (recent command history). POST data {device, command, text?}. device is "station:<id>", "peripheral:<id>" or "all_stations". Station commands: lock, logoff, restart, shutdown, sleep, wake, message (needs text), status, ping. Peripheral commands: wake, ping, status. Use this for all hardware control.
- billing.php: GET query summary=1 (totals, overdue), accounts=1&search=<name>, user_id=<id> (balance, statement, unbilled charges, invoices), invoices=1&status=<issued|partially_paid|paid|overdue|void>, invoice_id=<id>. POST data action=topup {user_id, amount, payment_method cash|mobile_money|card|bank_transfer, reference?}; action=adjust {user_id, amount (+credit/-debit), reason}; action=update_account {user_id, credit_limit?, payment_terms_days?, status? active|suspended, auto_pay_sessions?}; action=create_invoice {user_id, due_days?, discount_amount?, notes?} (bills all unbilled charges); action=create_manual_invoice {user_id, items:[{description, quantity, unit_price}], due_days?, notes?}; action=record_payment {invoice_id, amount, payment_method cash|mobile_money|card|bank_transfer|wallet, reference?}; action=void_invoice {invoice_id, reason}; action=settle_wallet {user_id}.
- customers.php: GET search=<text>, list=1, or user_id=<id>; POST create customer {full_name, phone?, email?}; PUT update customer {user_id, ...}. users.php: GET list=1 only.
- staff.php: GET list or staff_id; POST create staff; PUT update staff.
- support.php: GET tickets or ticket_id; POST create/assign; PUT update.
- services.php, products.php, peripherals.php: GET list or id; POST create; PUT update. products.php POST action=adjust_stock.
- sessions.php: GET query active=1 (live sessions), history=1, or session_id; POST action=start {user_id, station_id, duration_minutes? (0 = open, pay at end), payment_method? cash|mobile_money|card|bank_transfer|wallet|account (required with a duration)}; action=extend {session_id, minutes, payment_method? if prepaid}; action=move {session_id, station_id}; action=end {session_id, payment_method?, lock_station?}; action=settle {session_id, payment_method}; PUT {session_id, notes}.
- stations.php: GET list or station_id. Prefer devices.php for control.
- orders.php: GET orders, transaction_id, or refunds; POST create an order, action=refund, or action=refund_item; PUT update transaction status or notes.
- pos.php: GET query dashboard=1 or history; POST action=sale {customer_id or customer_name, items:[{type:"service"|"product", name, quantity, unit_price, service_type?, product_id?}], station_id?, usage_minutes?, payment_method cash|mobile_money|card|bank_transfer|wallet|account}.
- print-jobs.php: GET queue/job/history; PUT update status or priority. No file uploads.
- notifications.php: GET; POST send a system notification; PUT mark read or mark_all_read.
- feedback.php: GET; POST create; PUT update or action=testimonial_approval.
- printers.php: GET status; POST query action=test/wake/configure; PUT update connection config.
- game-consoles.php: POST action=status, wake, or set_session.
- reports.php: GET catalog; POST generate a report. staff-permissions.php: GET or PUT a complete permission list for a staff_id.
- network-devices.php: GET network device status. help-resources.php: GET or create. personal-devices.php: GET query list=1, user_id or device_id; POST action=buy_time {device_id, plan_id, payment_method cash|mobile_money|card|bank_transfer|wallet|account, quantity?}, connect {device_id}, disconnect, block, unblock, set_speed {device_id, speed_limit_kbps}, set_auto_renew {device_id, auto_renew}.
- payments.php: POST action=topup {user_id, amount, payment_method cash|mobile_money|card|bank_transfer, reference?} for a counter wallet top-up; action=online_link {user_id, amount, purpose wallet_topup|invoice, invoice_id?} to create a Paystack Mobile Money/card payment link; action=verify {reference}. GET query config=1.
To find a customer's user_id, first GET customers.php with search. For password changes or new accounts that need a password, never ask for the password in chat: set requires_local_secret=true and omit the password field.
Use query for GET parameters and printers.php POST query.action; use data for POST/PUT bodies.
When a message starts with [SYSTEM RESULT], it contains data from an operation that just ran: summarize it for the admin in plain speech and set operation to null.
PROMPT;

    $payload = [
        'model' => $aiModel,
        'messages' => array_merge([['role' => 'system', 'content' => $systemPrompt]], $conversation),
        'max_tokens' => 700,
        'temperature' => 0.3,
    ];

    if (session_status() === PHP_SESSION_ACTIVE) {
        session_write_close();
    }
    $ch = curl_init($aiApiUrl);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Authorization: Bearer ' . $aiApiKey],
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_CONNECTTIMEOUT => 8,
        CURLOPT_TIMEOUT => 40,
    ]);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($response === false || $httpCode < 200 || $httpCode >= 300) {
        error_log('Jarvis AI request failed: ' . ($curlError !== '' ? $curlError : 'HTTP ' . $httpCode . ' ' . substr((string) $response, 0, 300)));
        $reason = match (true) {
            $httpCode === 429 && str_contains((string) $response, 'credit') => 'The AI account has run out of credits.',
            $httpCode === 429 => 'The AI service is rate-limiting requests.',
            $httpCode === 401 => 'The AI API key was rejected.',
            default => 'My AI service is unavailable right now.',
        };
        errorResponse($reason . ' Device commands like “lock station 2” and “which stations are online” still work.', 502);
    }

    $result = json_decode($response, true);
    $content = is_array($result) ? ($result['choices'][0]['message']['content'] ?? '') : '';
    $assistantResponse = is_string($content) ? jarvisParseModelJson($content) : null;
    if ($assistantResponse === null && is_string($content) && trim($content) !== '') {
        $assistantResponse = ['reply' => trim($content), 'operation' => null]; // Model replied in prose.
    }
    if (!is_array($assistantResponse) || !is_string($assistantResponse['reply'] ?? null) || trim($assistantResponse['reply']) === '') {
        errorResponse('Jarvis returned an empty response. Please try again.', 502);
    }

    $operation = null;
    if (!$isResultSummary && isset($assistantResponse['operation']) && is_array($assistantResponse['operation'])) {
        $operation = validateJarvisOperation($assistantResponse['operation']);
    }

    successResponse(['reply' => trim($assistantResponse['reply']), 'operation' => $operation, 'source' => 'ai']);
}

if ($action === 'execute') {
    $rawOperation = is_array($input['operation'] ?? null) ? $input['operation'] : [];
    $operation = validateJarvisOperation($rawOperation, true);
    if ($operation['requires_confirmation'] && ($input['confirmed'] ?? false) !== true) {
        errorResponse('Confirm this operation before it can be performed', 403);
    }
    successResponse(executeJarvisOperation($operation));
}

errorResponse('Unknown Jarvis action', 400);
