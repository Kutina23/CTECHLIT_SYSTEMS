<?php
/**
 * CTECHLIT & SYSTEMS — Customer Device Self-Service API
 *
 * Public endpoint for captive portals, customer apps or router integrations.
 * Time is metered on the server, so clients only report data usage.
 *
 *   GET  ?mac=AA:BB:CC:DD:EE:FF            → status, minutes_remaining, speed limit, wallet balance
 *   POST ?mac=...  {action:"heartbeat", bytes_since_last}
 *   POST ?mac=...  {action:"connect", ip_address}   → starts the paid time (renews from wallet if auto-renew is on)
 *   POST ?mac=...  {action:"disconnect"}
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/personal-devices.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

function customerDeviceRespond(array $payload, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_SLASHES);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    customerDeviceRespond([], 204);
}

$hex = strtoupper(preg_replace('/[^0-9A-Fa-f]/', '', (string) ($_GET['mac'] ?? '')));
if (strlen($hex) !== 12) {
    customerDeviceRespond(['success' => false, 'message' => 'A valid mac parameter is required'], 400);
}
$mac = implode(':', str_split($hex, 2));

function customerDeviceState(string $mac): ?array
{
    $device = Database::fetchOne(
        'SELECT pd.device_id, pd.status, pd.minutes_remaining, pd.data_used_mb, pd.speed_limit_kbps, pd.device_name,
                pd.plan_id, pd.auto_renew, pd.connected_at, pd.last_seen_at, COALESCE(ba.balance, 0) AS wallet_balance
         FROM personal_devices pd LEFT JOIN billing_accounts ba ON ba.user_id = pd.user_id
         WHERE pd.mac_address = ?',
        [$mac]
    );
    if (!$device) {
        return null;
    }
    $device = array_merge($device, pdMeter((int) $device['device_id']) ?? []);
    return [
        'status' => $device['status'],
        'internet_allowed' => $device['status'] === 'online' && (int) $device['minutes_remaining'] > 0,
        'minutes_remaining' => (int) $device['minutes_remaining'],
        'data_used_mb' => (int) $device['data_used_mb'],
        'speed_limit_kbps' => (int) ($device['speed_limit_kbps'] ?? 0),
        'device_name' => $device['device_name'],
        'auto_renew' => (bool) $device['auto_renew'],
        'wallet_balance' => (float) $device['wallet_balance'],
        'connected_at' => $device['connected_at'],
        'last_seen_at' => $device['last_seen_at'],
    ];
}

try {
    $deviceId = (int) Database::fetchColumn('SELECT device_id FROM personal_devices WHERE mac_address = ?', [$mac]);
    if (!$deviceId) {
        customerDeviceRespond(['success' => false, 'message' => 'Device not registered. Ask the front desk to register it.'], 404);
    }

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        customerDeviceRespond(['success' => true, 'data' => customerDeviceState($mac)]);
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        customerDeviceRespond(['success' => false, 'message' => 'Method not allowed'], 405);
    }

    $input = json_decode((string) file_get_contents('php://input'), true);
    $input = is_array($input) ? $input : [];
    $action = strtolower((string) ($input['action'] ?? 'heartbeat'));

    if ($action === 'heartbeat') {
        $megabytes = (int) ceil(max(0, (int) ($input['bytes_since_last'] ?? 0)) / 1048576);
        Database::execute('UPDATE personal_devices SET data_used_mb = data_used_mb + ?, last_seen_at = NOW() WHERE device_id = ?', [$megabytes, $deviceId]);
        customerDeviceRespond(['success' => true, 'data' => customerDeviceState($mac)]);
    }

    if ($action === 'connect') {
        try {
            pdConnect($deviceId, isset($input['ip_address']) ? trim((string) $input['ip_address']) : null, 'customer');
        } catch (DomainException $e) {
            customerDeviceRespond(['success' => false, 'message' => $e->getMessage(), 'data' => customerDeviceState($mac)], 402);
        } catch (InvalidArgumentException $e) {
            customerDeviceRespond(['success' => false, 'message' => $e->getMessage()], 403);
        }
        customerDeviceRespond(['success' => true, 'message' => 'Connected', 'data' => customerDeviceState($mac)]);
    }

    if ($action === 'disconnect') {
        pdDisconnect($deviceId, 'Disconnected by customer', 'customer');
        customerDeviceRespond(['success' => true, 'message' => 'Disconnected', 'data' => customerDeviceState($mac)]);
    }

    customerDeviceRespond(['success' => false, 'message' => 'Unknown action'], 400);
} catch (Throwable $e) {
    error_log('customer-device endpoint failed: ' . $e->getMessage());
    customerDeviceRespond(['success' => false, 'message' => 'The device service is unavailable.'], 500);
}
