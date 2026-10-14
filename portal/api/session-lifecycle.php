<?php
/**
 * Shared session completion and scheduled-expiry handling.
 */

require_once __DIR__ . '/../../config/functions.php';
require_once __DIR__ . '/../../includes/billing.php';

function parseSessionDateTime($value): ?string
{
    if ($value === null || $value === '') {
        return null;
    }

    if (!is_string($value)) {
        throw new InvalidArgumentException('Session date and time values must be strings.');
    }

    foreach (['!Y-m-d\TH:i', '!Y-m-d H:i:s'] as $format) {
        $dateTime = DateTimeImmutable::createFromFormat($format, $value);
        $errors = DateTimeImmutable::getLastErrors();
        if ($dateTime && ($errors === false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0))) {
            return $dateTime->format('Y-m-d H:i:s');
        }
    }

    throw new InvalidArgumentException('Session date and time values must use a valid date and time.');
}

/**
 * Price per hour of station time (Settings → hourly_rate).
 */
function sessionHourlyRate(): float
{
    static $rate = null;
    if ($rate === null) {
        $value = Database::fetchColumn("SELECT setting_value FROM system_settings WHERE setting_key = 'hourly_rate' LIMIT 1");
        $rate = is_numeric($value) && (float) $value > 0 ? (float) $value : 2.00;
    }
    return $rate;
}

function finishSession(int $sessionId, bool $expiredOnly = false): ?array
{
    Database::beginTransaction();

    try {
        $expiryCondition = $expiredOnly
            ? " AND scheduled_end_at IS NOT NULL AND scheduled_end_at <= NOW()"
            : '';
        $session = Database::fetchOne(
            "SELECT session_id, user_id, station_id, started_at, scheduled_end_at,
                    TIMESTAMPDIFF(MINUTE, started_at, NOW()) AS elapsed_minutes
             FROM sessions
             WHERE session_id = ? AND status = 'active'$expiryCondition
             FOR UPDATE",
            [$sessionId]
        );

        if (!$session) {
            Database::rollback();
            return null;
        }

        Database::execute(
            "UPDATE sessions SET status = 'completed', ended_at = NOW()
             WHERE session_id = ? AND status = 'active'",
            [$sessionId]
        );

        $hasTransaction = (bool) Database::fetchColumn(
            'SELECT transaction_id FROM transactions WHERE session_id = ? LIMIT 1',
            [$sessionId]
        );
        $amount = 0.0;
        $sessionTransactionId = null;
        $duration = max(0, (int) ($session['elapsed_minutes'] ?? 0));

        if (!$hasTransaction && $duration > 0) {
            $amount = round(($duration / 60) * sessionHourlyRate(), 2);
            $sessionTransactionId = Database::insert(
                "INSERT INTO transactions
                    (user_id, session_id, amount, total_amount, status, payment_reference, notes)
                 VALUES (?, ?, ?, ?, 'pending', ?, ?)",
                [$session['user_id'], $sessionId, $amount, $amount, 'SESS-' . $sessionId, "Station session ($duration min)"]
            );
            // Customers with a funded wallet pay automatically; otherwise the charge stays unbilled.
            billingAutoPayTransaction((int) $session['user_id'], $sessionTransactionId, $amount);
        }

        $activeSessionsRemain = (int) Database::fetchColumn(
            "SELECT COUNT(*) FROM sessions
             WHERE station_id = ? AND status = 'active'",
            [$session['station_id']]
        );
        if ($activeSessionsRemain === 0) {
            Database::execute(
                "UPDATE stations SET status = 'available' WHERE station_id = ? AND status = 'occupied'",
                [$session['station_id']]
            );
        }

        $chargeStatus = $sessionTransactionId
            ? (string) Database::fetchColumn('SELECT status FROM transactions WHERE transaction_id = ?', [$sessionTransactionId])
            : null;

        Database::commit();
        return [
            'session_id' => $sessionId,
            'user_id' => (int) $session['user_id'],
            'duration' => $duration,
            'amount' => $amount,
            'station_id' => (int) $session['station_id'],
            'prepaid' => $hasTransaction,
            'transaction_id' => $sessionTransactionId,
            // 'completed' = paid from the wallet automatically; 'pending' = still to collect.
            'charge_status' => $chargeStatus,
        ];
    } catch (Throwable $error) {
        Database::rollback();
        throw $error;
    }
}

function expireScheduledSessions(): int
{
    $expiredSessions = Database::fetchAll(
        "SELECT session_id FROM sessions
         WHERE status = 'active'
           AND scheduled_end_at IS NOT NULL
           AND scheduled_end_at <= NOW()
         ORDER BY scheduled_end_at ASC"
    );

    $completed = 0;
    foreach ($expiredSessions as $session) {
        if (finishSession((int) $session['session_id'], true) !== null) {
            $completed++;
        }
    }

    return $completed;
}
