<?php
/**
 * CTECHLIT & SYSTEMS — Personal Network Devices API
 * BYOD / hotspot devices: registration, paid time, metering, access control.
 *
 * GET  ?list=1 | ?device_id=N | ?user_id=N | ?mac=AA:..  | ?history=N
 * POST action=register  {user_id, device_name, device_type, mac_address, plan_id, payment_method, reference?, auto_renew?}
 * POST action=buy_time  {device_id, plan_id, payment_method, quantity?, reference?}
 * POST action=connect   {device_id, ip_address?}
 * POST action=disconnect|block|unblock {device_id, reason?}
 * POST action=set_speed {device_id, speed_limit_kbps}
 * POST action=set_auto_renew {device_id, auto_renew}
 * POST action=heartbeat {device_id, bytes_since_last}
 */

require_once __DIR__ . '/common.php';
require_once __DIR__ . '/../../includes/personal-devices.php';

$method = $_SERVER['REQUEST_METHOD'];
$input = $GLOBALS['request_input'] ?? [];
if (!is_array($input)) {
    $input = [];
}

const PD_SELECT = "SELECT pd.*, u.full_name AS user_name, u.phone AS user_phone,
                          pp.name AS plan_name, pp.price AS plan_price, pp.duration_minutes,
                          COALESCE(ba.balance, 0) AS wallet_balance,
                          TIMESTAMPDIFF(MINUTE, pd.connected_at, NOW()) AS online_minutes
                   FROM personal_devices pd
                   JOIN users u ON u.user_id = pd.user_id
                   LEFT JOIN pricing_plans pp ON pp.plan_id = pd.plan_id
                   LEFT JOIN billing_accounts ba ON ba.user_id = pd.user_id";

function pdRequireDeviceId(array $input): int
{
    $deviceId = (int) ($input['device_id'] ?? 0);
    if ($deviceId <= 0) {
        throw new InvalidArgumentException("Field 'device_id' is required.");
    }
    return $deviceId;
}

try {
    if ($method === 'GET') {
        requirePermission('personal_devices.view');
        pdMeterAll(); // keep remaining minutes current without relying on the cron job

        if (isset($_GET['history'])) {
            successResponse(Database::fetchAll(
                'SELECT event_type, trigger_source, minutes_consumed, notes, created_at FROM device_usage_events
                 WHERE device_id = ? ORDER BY event_id DESC LIMIT 50',
                [(int) $_GET['history']]
            ));
        }
        if (isset($_GET['mac'])) {
            $device = Database::fetchOne(PD_SELECT . ' WHERE pd.mac_address = ?', [normalizeMacForDevices((string) $_GET['mac'])]);
            $device ? successResponse($device) : errorResponse('Device not registered', 404);
        }
        if (isset($_GET['device_id'])) {
            $device = Database::fetchOne(PD_SELECT . ' WHERE pd.device_id = ?', [(int) $_GET['device_id']]);
            $device ? successResponse($device) : errorResponse('Device not found', 404);
        }
        if (isset($_GET['user_id'])) {
            successResponse(Database::fetchAll(PD_SELECT . ' WHERE pd.user_id = ? ORDER BY pd.created_at DESC', [(int) $_GET['user_id']]));
        }
        $devices = Database::fetchAll(PD_SELECT . " ORDER BY FIELD(pd.status, 'online', 'offline', 'suspended', 'blocked'), pd.updated_at DESC");
        successResponse([
            'devices' => $devices,
            'stats' => [
                'total' => count($devices),
                'online' => count(array_filter($devices, fn($d) => $d['status'] === 'online')),
                'offline' => count(array_filter($devices, fn($d) => $d['status'] === 'offline')),
                'blocked' => count(array_filter($devices, fn($d) => $d['status'] === 'blocked')),
                'low_time' => count(array_filter($devices, fn($d) => $d['status'] === 'online' && (int) $d['minutes_remaining'] <= 10)),
            ],
            'plans' => Database::fetchAll('SELECT plan_id, name, price, duration_minutes FROM pricing_plans WHERE is_active = 1 AND duration_minutes > 0 ORDER BY sort_order, price'),
        ]);
    }

    if ($method !== 'POST') {
        errorResponse('Method not allowed', 405);
    }

    requirePermission('personal_devices.manage');
    $action = (string) ($input['action'] ?? 'register');
    $reference = isset($input['reference']) && trim((string) $input['reference']) !== '' ? mb_substr(trim((string) $input['reference']), 0, 100) : null;

    switch ($action) {
        case 'register':
            $userId = (int) ($input['user_id'] ?? 0);
            $name = trim((string) ($input['device_name'] ?? ''));
            $mac = normalizeMacForDevices((string) ($input['mac_address'] ?? ''));
            $type = in_array($input['device_type'] ?? '', ['phone', 'laptop', 'tablet', 'other'], true) ? $input['device_type'] : 'phone';
            if ($userId <= 0 || $name === '' || mb_strlen($name) > 100) {
                throw new InvalidArgumentException('Choose a customer and give the device a name.');
            }
            if ($mac === '') {
                throw new InvalidArgumentException('Enter a valid MAC address, e.g. AA:BB:CC:DD:EE:FF.');
            }
            if (Database::fetchColumn('SELECT device_id FROM personal_devices WHERE mac_address = ?', [$mac])) {
                throw new InvalidArgumentException('A device with that MAC address is already registered.');
            }
            $planId = (int) ($input['plan_id'] ?? 0);
            $paymentMethod = (string) ($input['payment_method'] ?? 'cash');

            $result = billingAtomic(function () use ($userId, $name, $mac, $type, $planId, $paymentMethod, $reference, $input) {
                $deviceId = Database::insert(
                    "INSERT INTO personal_devices (user_id, device_name, device_type, mac_address, plan_id, auto_renew, status)
                     VALUES (?, ?, ?, ?, ?, ?, 'offline')",
                    [$userId, $name, $type, $mac, $planId ?: null, !empty($input['auto_renew']) ? 1 : 0]
                );
                $purchase = $planId > 0 ? pdPurchaseTime($deviceId, $planId, $paymentMethod, $reference) : null;
                return ['device_id' => $deviceId, 'purchase' => $purchase];
            });
            logAuditTrail('personal_device_registered', 'personal_device', $result['device_id'], ['user_id' => $userId, 'mac' => $mac, 'plan_id' => $planId]);
            successResponse($result, $result['purchase']
                ? "Device registered with {$result['purchase']['minutes_added']} minutes ({$result['purchase']['payment']['receipt_number']})."
                : 'Device registered. Sell it a plan before connecting.');

        case 'buy_time':
            $deviceId = pdRequireDeviceId($input);
            $purchase = pdPurchaseTime($deviceId, (int) ($input['plan_id'] ?? 0), (string) ($input['payment_method'] ?? 'cash'), $reference, 'staff', (int) ($input['quantity'] ?? 1));
            logAuditTrail('personal_device_time_sold', 'personal_device', $deviceId, ['minutes' => $purchase['minutes_added'], 'method' => $purchase['payment']['payment_method']]);
            successResponse($purchase, "Added {$purchase['minutes_added']} minutes. {$purchase['minutes_remaining']} minutes left"
                . ($purchase['payment']['status'] === 'pending' ? ' (charged to account).' : '.'));

        case 'connect':
            $deviceId = pdRequireDeviceId($input);
            $device = pdConnect($deviceId, isset($input['ip_address']) ? trim((string) $input['ip_address']) : null);
            successResponse(['device_id' => $deviceId, 'status' => 'online', 'minutes_remaining' => (int) $device['minutes_remaining'], 'speed_limit_kbps' => (int) ($device['speed_limit_kbps'] ?? 0)], 'Device connected');

        case 'disconnect':
            $deviceId = pdRequireDeviceId($input);
            pdDisconnect($deviceId, mb_substr((string) ($input['reason'] ?? 'Disconnected by staff'), 0, 200));
            successResponse(null, 'Device disconnected');

        case 'block':
        case 'unblock':
            $deviceId = pdRequireDeviceId($input);
            if ($action === 'block') {
                pdMeter($deviceId, 'staff');
            }
            $device = Database::fetchOne('SELECT user_id FROM personal_devices WHERE device_id = ?', [$deviceId]);
            if (!$device) {
                throw new InvalidArgumentException('Device not found.');
            }
            Database::execute(
                'UPDATE personal_devices SET status = ?, last_metered_at = NULL, disconnected_at = IF(? = \'block\', NOW(), disconnected_at) WHERE device_id = ?',
                [$action === 'block' ? 'blocked' : 'offline', $action, $deviceId]
            );
            pdLogEvent($deviceId, (int) $device['user_id'], $action === 'block' ? 'blocked' : 'resumed', 'staff', (string) ($input['reason'] ?? ($action === 'block' ? 'Blocked by staff' : 'Unblocked by staff')));
            successResponse(null, $action === 'block' ? 'Device blocked' : 'Device unblocked');

        case 'set_speed':
            $deviceId = pdRequireDeviceId($input);
            $speed = max(0, min(1000000, (int) ($input['speed_limit_kbps'] ?? 0)));
            $device = Database::fetchOne('SELECT user_id FROM personal_devices WHERE device_id = ?', [$deviceId]);
            if (!$device) {
                throw new InvalidArgumentException('Device not found.');
            }
            Database::execute('UPDATE personal_devices SET speed_limit_kbps = ? WHERE device_id = ?', [$speed ?: null, $deviceId]);
            Database::insert(
                "INSERT INTO device_usage_events (device_id, user_id, event_type, speed_limit_kbps, trigger_source, notes) VALUES (?, ?, 'speed_change', ?, 'staff', ?)",
                [$deviceId, $device['user_id'], $speed ?: null, $speed ? "Limited to $speed kbps" : 'Speed limit removed']
            );
            successResponse(null, $speed ? "Speed limited to $speed kbps" : 'Speed limit removed');

        case 'set_auto_renew':
            $deviceId = pdRequireDeviceId($input);
            $enabled = filter_var($input['auto_renew'] ?? false, FILTER_VALIDATE_BOOLEAN);
            Database::execute('UPDATE personal_devices SET auto_renew = ? WHERE device_id = ?', [$enabled ? 1 : 0, $deviceId]);
            successResponse(['auto_renew' => $enabled], $enabled ? 'Auto-renew from wallet enabled' : 'Auto-renew disabled');

        case 'heartbeat':
            $deviceId = pdRequireDeviceId($input);
            $megabytes = (int) ceil(max(0, (int) ($input['bytes_since_last'] ?? 0)) / 1048576);
            Database::execute('UPDATE personal_devices SET data_used_mb = data_used_mb + ?, last_seen_at = NOW() WHERE device_id = ?', [$megabytes, $deviceId]);
            $device = pdMeter($deviceId);
            successResponse([
                'device_id' => $deviceId,
                'status' => $device['status'] ?? 'offline',
                'minutes_remaining' => (int) ($device['minutes_remaining'] ?? 0),
                'disconnected' => ($device['status'] ?? '') !== 'online',
                'speed_limit_kbps' => (int) ($device['speed_limit_kbps'] ?? 0),
            ]);

        default:
            errorResponse('Unknown action', 400);
    }
} catch (DomainException $e) {
    errorResponse($e->getMessage(), 402);
} catch (InvalidArgumentException $e) {
    errorResponse($e->getMessage(), 422);
} catch (Throwable $e) {
    error_log('Personal devices API error: ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
    errorResponse('The device request could not be completed.', 500);
}

function normalizeMacForDevices(string $mac): string
{
    $hex = strtoupper(preg_replace('/[^0-9A-Fa-f]/', '', $mac));
    return strlen($hex) === 12 ? implode(':', str_split($hex, 2)) : '';
}
