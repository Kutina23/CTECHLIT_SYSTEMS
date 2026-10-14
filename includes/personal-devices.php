<?php
/**
 * CTECHLIT & SYSTEMS — Personal (BYOD) device time and billing
 *
 * - Time is bought as pricing-plan minutes and paid through billingCollectPayment().
 * - Online devices are metered on the server from elapsed wall-clock time, so a
 *   client cannot under-report usage.
 * - When time runs out, devices with auto-renew buy their plan again from the
 *   owner's wallet; otherwise they are disconnected.
 */

require_once __DIR__ . '/billing.php';

function pdLogEvent(int $deviceId, int $userId, string $type, string $source, string $notes, int $minutes = 0): void
{
    Database::insert(
        'INSERT INTO device_usage_events (device_id, user_id, event_type, trigger_source, minutes_consumed, notes) VALUES (?, ?, ?, ?, ?, ?)',
        [$deviceId, $userId, $type, $source, $minutes, mb_substr($notes, 0, 500)]
    );
}

function pdPlan(int $planId): array
{
    $plan = Database::fetchOne('SELECT plan_id, name, price, duration_minutes FROM pricing_plans WHERE plan_id = ? AND is_active = 1', [$planId]);
    if (!$plan || (int) $plan['duration_minutes'] <= 0) {
        throw new InvalidArgumentException('Choose an active time plan (plans without minutes cannot be used for devices).');
    }
    return $plan;
}

/**
 * Buy plan time for a device. Returns the payment and the device's new minute balance.
 */
function pdPurchaseTime(int $deviceId, int $planId, string $method, ?string $reference = null, string $source = 'staff', int $quantity = 1): array
{
    $quantity = max(1, min(24, $quantity));
    return billingAtomic(function () use ($deviceId, $planId, $method, $reference, $source, $quantity) {
        $device = Database::fetchOne('SELECT * FROM personal_devices WHERE device_id = ? FOR UPDATE', [$deviceId]);
        if (!$device) {
            throw new InvalidArgumentException('Device not found.');
        }
        if ($device['status'] === 'blocked') {
            throw new InvalidArgumentException('This device is blocked. Unblock it before selling time.');
        }
        $plan = pdPlan($planId);
        $minutes = (int) $plan['duration_minutes'] * $quantity;
        $amount = billingMoney((float) $plan['price'] * $quantity);
        $payment = billingCollectPayment(
            (int) $device['user_id'], $amount, $method,
            "Device time: {$plan['name']}" . ($quantity > 1 ? " × $quantity" : '') . " ({$device['device_name']})",
            ['reference' => $reference, 'quantity' => $quantity, 'unit_price' => (float) $plan['price']]
        );
        Database::execute(
            'UPDATE personal_devices
             SET plan_id = ?, minutes_purchased = minutes_purchased + ?, minutes_remaining = minutes_remaining + ?,
                 status = IF(status = \'suspended\', \'offline\', status)
             WHERE device_id = ?',
            [$planId, $minutes, $minutes, $deviceId]
        );
        pdLogEvent($deviceId, (int) $device['user_id'], $source === 'auto' ? 'auto_renewed' : 'time_purchased', $source === 'auto' ? 'system' : 'staff',
            "{$plan['name']} +$minutes min via " . str_replace('_', ' ', $method) . " ({$payment['receipt_number']})");
        $remaining = (int) Database::fetchColumn('SELECT minutes_remaining FROM personal_devices WHERE device_id = ?', [$deviceId]);
        return ['payment' => $payment, 'minutes_added' => $minutes, 'minutes_remaining' => $remaining, 'plan' => $plan];
    });
}

/**
 * Renew from the owner's wallet when allowed and affordable.
 */
function pdTryAutoRenew(array $device): bool
{
    if (empty($device['auto_renew']) || empty($device['plan_id']) || !billingEnabled()) {
        return false;
    }
    $plan = Database::fetchOne('SELECT price, duration_minutes FROM pricing_plans WHERE plan_id = ? AND is_active = 1', [(int) $device['plan_id']]);
    $balance = (float) Database::fetchColumn('SELECT balance FROM billing_accounts WHERE user_id = ? AND status = \'active\'', [(int) $device['user_id']]);
    if (!$plan || (int) $plan['duration_minutes'] <= 0 || $balance + 0.001 < (float) $plan['price']) {
        return false;
    }
    try {
        pdPurchaseTime((int) $device['device_id'], (int) $device['plan_id'], 'wallet', null, 'auto');
        return true;
    } catch (Throwable $error) {
        error_log('Personal device auto-renew failed for device ' . $device['device_id'] . ': ' . $error->getMessage());
        return false;
    }
}

/**
 * Charge elapsed minutes to an online device; renew or disconnect when time runs out.
 * Returns the refreshed device row.
 */
function pdMeter(int $deviceId, string $source = 'system'): ?array
{
    $result = billingAtomic(function () use ($deviceId, $source) {
        $device = Database::fetchOne(
            'SELECT *, TIMESTAMPDIFF(MINUTE, COALESCE(last_metered_at, connected_at, NOW()), NOW()) AS elapsed
             FROM personal_devices WHERE device_id = ? FOR UPDATE',
            [$deviceId]
        );
        if (!$device || $device['status'] !== 'online') {
            return $device;
        }
        $elapsed = max(0, (int) $device['elapsed']);
        $remaining = (int) $device['minutes_remaining'];
        $overflow = max(0, $elapsed - $remaining); // minutes used beyond the paid balance
        if ($elapsed > 0) {
            $remaining = max(0, $remaining - $elapsed);
            // Advance the meter by whole minutes only so partial minutes carry over.
            Database::execute(
                'UPDATE personal_devices SET minutes_remaining = ?,
                        last_metered_at = DATE_ADD(COALESCE(last_metered_at, connected_at, NOW()), INTERVAL ? MINUTE)
                 WHERE device_id = ?',
                [$remaining, $elapsed, $deviceId]
            );
        }
        if ($remaining > 0) {
            return null;
        }
        $device['minutes_remaining'] = 0;
        if (pdTryAutoRenew($device)) {
            if ($overflow > 0) {
                Database::execute('UPDATE personal_devices SET minutes_remaining = GREATEST(0, minutes_remaining - ?) WHERE device_id = ?', [$overflow, $deviceId]);
            }
            return null;
        }
        Database::execute(
            "UPDATE personal_devices SET status = 'offline', disconnected_at = NOW(), last_metered_at = NULL WHERE device_id = ?",
            [$deviceId]
        );
        pdLogEvent($deviceId, (int) $device['user_id'], 'plan_expired', $source === 'cron' ? 'cron' : 'system', 'Disconnected: time used up', $elapsed);
        return null;
    });
    return $result ?? Database::fetchOne('SELECT * FROM personal_devices WHERE device_id = ?', [$deviceId]);
}

function pdMeterAll(string $source = 'system'): int
{
    $count = 0;
    foreach (Database::fetchAll("SELECT device_id FROM personal_devices WHERE status = 'online'") as $row) {
        pdMeter((int) $row['device_id'], $source);
        $count++;
    }
    return $count;
}

/**
 * Bring a device online (staff or captive portal). Renews from the wallet first if time is used up.
 */
function pdConnect(int $deviceId, ?string $ipAddress, string $source = 'staff'): array
{
    $device = Database::fetchOne('SELECT * FROM personal_devices WHERE device_id = ?', [$deviceId]);
    if (!$device) {
        throw new InvalidArgumentException('Device not found.');
    }
    if (in_array($device['status'], ['blocked', 'suspended'], true)) {
        throw new InvalidArgumentException("This device is {$device['status']}.");
    }
    if ((int) $device['minutes_remaining'] <= 0 && !pdTryAutoRenew($device)) {
        throw new DomainException('No time left on this device. Sell a plan (or top up the wallet with auto-renew on) first.');
    }
    if ($ipAddress !== null && $ipAddress !== '' && !filter_var($ipAddress, FILTER_VALIDATE_IP)) {
        throw new InvalidArgumentException('Enter a valid IP address.');
    }
    Database::execute(
        "UPDATE personal_devices
         SET status = 'online', ip_address = COALESCE(NULLIF(?, ''), ip_address),
             connected_at = NOW(), last_seen_at = NOW(), last_metered_at = NOW()
         WHERE device_id = ?",
        [$ipAddress, $deviceId]
    );
    pdLogEvent($deviceId, (int) $device['user_id'], 'connect', $source, 'Connected' . ($ipAddress ? " at $ipAddress" : ''));
    return Database::fetchOne('SELECT * FROM personal_devices WHERE device_id = ?', [$deviceId]);
}

function pdDisconnect(int $deviceId, string $reason, string $source = 'staff'): void
{
    pdMeter($deviceId, $source); // charge time used up to now
    $device = Database::fetchOne('SELECT user_id, status FROM personal_devices WHERE device_id = ?', [$deviceId]);
    if (!$device) {
        throw new InvalidArgumentException('Device not found.');
    }
    if ($device['status'] === 'online') {
        Database::execute(
            "UPDATE personal_devices SET status = 'offline', disconnected_at = NOW(), last_seen_at = NOW(), last_metered_at = NULL WHERE device_id = ?",
            [$deviceId]
        );
    }
    pdLogEvent($deviceId, (int) $device['user_id'], 'disconnect', $source, $reason);
}
