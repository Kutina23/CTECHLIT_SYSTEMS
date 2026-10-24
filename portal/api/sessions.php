<?php
/**
 * Sessions API — timed use of café stations.
 *
 * GET  ?active=1                     live sessions with time left, running charge and payment state
 * GET  ?history=1[&search=&station_id=&status=&from=&to=&page=]   finished sessions
 * GET  ?options=1                    customers, stations and the hourly rate for the start form
 * GET  ?session_id=N                 one session
 * POST action=start   {user_id, station_id, duration_minutes?, payment_method?, reference?, notes?}
 *        duration + payment → prepaid fixed-time session; no duration → open session billed when it ends
 * POST action=extend  {session_id, minutes, payment_method?, reference?}
 * POST action=move    {session_id, station_id}
 * POST action=end     {session_id, payment_method?, reference?, lock_station?}
 * POST action=cancel  {session_id, reason}   (no charge; needs sessions.manage)
 * PUT  {session_id, notes}
 */

require_once __DIR__ . '/common.php';
require_once __DIR__ . '/session-lifecycle.php';
require_once __DIR__ . '/../../includes/device-control.php';

$method = $_SERVER['REQUEST_METHOD'];
$input = $GLOBALS['request_input'] ?? [];
if (!is_array($input)) {
    $input = [];
}

const SESSION_SELECT = "SELECT s.session_id, s.user_id, s.station_id, s.staff_id, s.status, s.notes,
        s.started_at, s.ended_at, s.scheduled_end_at,
        u.full_name AS customer_name, u.phone AS customer_phone,
        st.station_code, st.name AS station_name, st.last_heartbeat,
        sf.full_name AS staff_name,
        TIMESTAMPDIFF(MINUTE, s.started_at, COALESCE(s.ended_at, NOW())) AS elapsed_minutes,
        IF(s.scheduled_end_at IS NULL, NULL, TIMESTAMPDIFF(MINUTE, NOW(), s.scheduled_end_at)) AS minutes_left,
        IF(s.scheduled_end_at IS NULL, NULL, TIMESTAMPDIFF(MINUTE, s.started_at, s.scheduled_end_at)) AS booked_minutes,
        COALESCE((SELECT SUM(t.total_amount) FROM transactions t WHERE t.session_id = s.session_id AND t.status = 'completed'), 0) AS paid_amount,
        COALESCE((SELECT SUM(t.total_amount) FROM transactions t WHERE t.session_id = s.session_id AND t.status = 'pending'), 0) AS owed_amount,
        (SELECT t.transaction_id FROM transactions t WHERE t.session_id = s.session_id AND t.status = 'pending' AND t.invoice_id IS NULL ORDER BY t.transaction_id DESC LIMIT 1) AS pending_transaction_id,
        (SELECT COUNT(*) FROM transactions t WHERE t.session_id = s.session_id) AS charge_count,
        COALESCE(ba.balance, 0) AS wallet_balance
    FROM sessions s
    JOIN users u ON u.user_id = s.user_id
    JOIN stations st ON st.station_id = s.station_id
    LEFT JOIN staff sf ON sf.staff_id = s.staff_id
    LEFT JOIN billing_accounts ba ON ba.user_id = s.user_id";

function sessionPresent(array $row): array
{
    $rate = sessionHourlyRate();
    $elapsed = max(0, (int) $row['elapsed_minutes']);
    $row['prepaid'] = (int) $row['charge_count'] > 0 && $row['status'] === 'active';
    // Running charge for open (pay-at-the-end) sessions; prepaid ones were charged up front.
    $row['running_charge'] = $row['status'] === 'active' && (int) $row['charge_count'] === 0 ? round($elapsed / 60 * $rate, 2) : null;
    $row['agent_online'] = $row['last_heartbeat'] !== null && strtotime($row['last_heartbeat']) >= time() - STATION_AGENT_ONLINE_SECONDS;
    unset($row['last_heartbeat']);
    return $row;
}

function sessionFetch(int $sessionId): array
{
    $row = Database::fetchOne(SESSION_SELECT . ' WHERE s.session_id = ?', [$sessionId]);
    if (!$row) {
        throw new InvalidArgumentException('Session not found.');
    }
    return sessionPresent($row);
}

function sessionCharge(int $userId, int $sessionId, int $minutes, string $paymentMethod, ?string $reference, string $label): array
{
    $amount = round($minutes / 60 * sessionHourlyRate(), 2);
    return billingCollectPayment($userId, $amount, $paymentMethod, $label, [
        'session_id' => $sessionId, 'reference' => $reference, 'quantity' => round($minutes / 60, 2), 'unit_price' => sessionHourlyRate(),
    ]);
}

function sessionRequireId(array $input): int
{
    $id = (int) ($input['session_id'] ?? 0);
    if ($id <= 0) {
        throw new InvalidArgumentException("Field 'session_id' is required.");
    }
    return $id;
}

try {
    expireScheduledSessions();

    if ($method === 'GET') {
        requirePermission('sessions.view');

        if (isset($_GET['options'])) {
            successResponse([
                'hourly_rate' => sessionHourlyRate(),
                'customers' => Database::fetchAll(
                    'SELECT u.user_id, u.full_name, u.phone, COALESCE(ba.balance, 0) AS wallet_balance
                     FROM users u LEFT JOIN billing_accounts ba ON ba.user_id = u.user_id
                     WHERE u.is_active = 1 ORDER BY u.full_name'
                ),
                'stations' => Database::fetchAll(
                    "SELECT st.station_id, st.station_code, st.name, st.status,
                            st.last_heartbeat >= DATE_SUB(NOW(), INTERVAL 90 SECOND) AS agent_online,
                            EXISTS(SELECT 1 FROM sessions s WHERE s.station_id = st.station_id AND s.status = 'active') AS in_use
                     FROM stations st ORDER BY st.station_code"
                ),
            ]);
        }

        if (isset($_GET['session_id'])) {
            successResponse(sessionFetch((int) $_GET['session_id']));
        }

        if (isset($_GET['history'])) {
            $where = ["s.status IN ('completed', 'cancelled')"];
            $params = [];
            if (in_array($_GET['status'] ?? '', ['completed', 'cancelled'], true)) {
                $where = ['s.status = ?'];
                $params[] = $_GET['status'];
            }
            if (trim((string) ($_GET['search'] ?? '')) !== '') {
                $like = '%' . addcslashes(trim((string) $_GET['search']), '%_\\') . '%';
                $where[] = '(u.full_name LIKE ? OR u.phone LIKE ? OR st.station_code LIKE ? OR s.session_id = ?)';
                array_push($params, $like, $like, $like, (int) $_GET['search']);
            }
            if (!empty($_GET['station_id'])) {
                $where[] = 's.station_id = ?';
                $params[] = (int) $_GET['station_id'];
            }
            foreach (['from' => '>=', 'to' => '<='] as $key => $operator) {
                $date = (string) ($_GET[$key] ?? '');
                if (preg_match('/\A\d{4}-\d{2}-\d{2}\z/', $date)) {
                    $where[] = "DATE(s.started_at) $operator ?";
                    $params[] = $date;
                }
            }
            $page = max(1, (int) ($_GET['page'] ?? 1));
            $perPage = 25;
            $whereSql = ' WHERE ' . implode(' AND ', $where);
            $total = (int) Database::fetchColumn(
                'SELECT COUNT(*) FROM sessions s JOIN users u ON u.user_id = s.user_id JOIN stations st ON st.station_id = s.station_id' . $whereSql,
                $params
            );
            $totals = Database::fetchOne(
                "SELECT COALESCE(SUM(TIMESTAMPDIFF(MINUTE, s.started_at, s.ended_at)), 0) AS minutes,
                        COALESCE(SUM((SELECT SUM(t.total_amount) FROM transactions t WHERE t.session_id = s.session_id AND t.status = 'completed')), 0) AS paid,
                        COALESCE(SUM((SELECT SUM(t.total_amount) FROM transactions t WHERE t.session_id = s.session_id AND t.status = 'pending')), 0) AS owed
                 FROM sessions s JOIN users u ON u.user_id = s.user_id JOIN stations st ON st.station_id = s.station_id" . $whereSql,
                $params
            );
            $rows = Database::fetchAll(
                SESSION_SELECT . $whereSql . ' ORDER BY COALESCE(s.ended_at, s.started_at) DESC LIMIT ' . $perPage . ' OFFSET ' . (($page - 1) * $perPage),
                $params
            );
            successResponse([
                'sessions' => array_map('sessionPresent', $rows),
                'total' => $total,
                'page' => $page,
                'pages' => max(1, (int) ceil($total / $perPage)),
                'totals' => $totals,
            ]);
        }

        $active = array_map('sessionPresent', Database::fetchAll(SESSION_SELECT . " WHERE s.status = 'active' ORDER BY s.started_at DESC"));
        successResponse([
            'sessions' => $active,
            'hourly_rate' => sessionHourlyRate(),
            'stats' => [
                'active' => count($active),
                'ending_soon' => count(array_filter($active, fn($s) => $s['minutes_left'] !== null && (int) $s['minutes_left'] <= 10)),
                'stations_free' => (int) Database::fetchColumn(
                    "SELECT COUNT(*) FROM stations st WHERE st.status <> 'maintenance'
                     AND NOT EXISTS (SELECT 1 FROM sessions s WHERE s.station_id = st.station_id AND s.status = 'active')"
                ),
                'completed_today' => (int) Database::fetchColumn("SELECT COUNT(*) FROM sessions WHERE status = 'completed' AND DATE(ended_at) = CURDATE()"),
                'revenue_today' => (float) Database::fetchColumn(
                    "SELECT COALESCE(SUM(total_amount), 0) FROM transactions WHERE session_id IS NOT NULL AND status = 'completed' AND DATE(created_at) = CURDATE()"
                ),
                'unpaid_total' => (float) Database::fetchColumn(
                    "SELECT COALESCE(SUM(total_amount), 0) FROM transactions WHERE session_id IS NOT NULL AND status = 'pending'"
                ),
                'avg_minutes_today' => (int) Database::fetchColumn(
                    "SELECT COALESCE(ROUND(AVG(TIMESTAMPDIFF(MINUTE, started_at, ended_at))), 0) FROM sessions WHERE status = 'completed' AND DATE(ended_at) = CURDATE()"
                ),
            ],
        ]);
    }

    if ($method === 'PUT') {
        requirePermission('sessions.manage');
        $sessionId = sessionRequireId($input);
        Database::execute('UPDATE sessions SET notes = ? WHERE session_id = ?', [mb_substr(trim((string) ($input['notes'] ?? '')), 0, 1000) ?: null, $sessionId]);
        successResponse(sessionFetch($sessionId), 'Notes saved');
    }

    if ($method !== 'POST') {
        errorResponse('Method not allowed', 405);
    }

    $action = (string) ($input['action'] ?? 'start');
    $reference = isset($input['reference']) && trim((string) $input['reference']) !== '' ? mb_substr(trim((string) $input['reference']), 0, 100) : null;
    $paymentMethod = (string) ($input['payment_method'] ?? '');

    switch ($action) {
        case 'start':
        case 'create':
            requirePermission('sessions.manage');
            $userId = (int) ($input['user_id'] ?? 0);
            $stationId = (int) ($input['station_id'] ?? 0);
            $minutes = (int) ($input['duration_minutes'] ?? 0);
            if ($userId <= 0 || $stationId <= 0) {
                throw new InvalidArgumentException('Choose a customer and a station.');
            }
            if ($minutes < 0 || $minutes > 24 * 60) {
                throw new InvalidArgumentException('Duration must be up to 24 hours.');
            }
            if ($minutes > 0 && $paymentMethod === '') {
                throw new InvalidArgumentException('Choose how the customer pays for the booked time, or start an open session.');
            }
            $result = billingAtomic(function () use ($userId, $stationId, $minutes, $paymentMethod, $reference, $input) {
                if (!Database::fetchOne('SELECT user_id FROM users WHERE user_id = ? AND is_active = 1', [$userId])) {
                    throw new InvalidArgumentException('That customer is inactive or no longer exists.');
                }
                $station = Database::fetchOne('SELECT * FROM stations WHERE station_id = ? FOR UPDATE', [$stationId]);
                if (!$station) {
                    throw new InvalidArgumentException('Station not found.');
                }
                if ($station['status'] === 'maintenance') {
                    throw new InvalidArgumentException("{$station['station_code']} is under maintenance.");
                }
                $busy = Database::fetchOne(
                    "SELECT u.full_name FROM sessions s JOIN users u ON u.user_id = s.user_id WHERE s.station_id = ? AND s.status = 'active' LIMIT 1",
                    [$stationId]
                );
                if ($busy) {
                    throw new InvalidArgumentException("{$station['station_code']} is in use by {$busy['full_name']}.");
                }
                assertSessionCapacity();
                $sessionId = Database::insert(
                    "INSERT INTO sessions (user_id, station_id, staff_id, status, started_at, scheduled_end_at, notes)
                     VALUES (?, ?, ?, 'active', NOW(), IF(? > 0, DATE_ADD(NOW(), INTERVAL ? MINUTE), NULL), ?)",
                    [$userId, $stationId, (int) $_SESSION['user_id'], $minutes, $minutes, mb_substr(trim((string) ($input['notes'] ?? '')), 0, 1000) ?: null]
                );
                Database::execute("UPDATE stations SET status = 'occupied' WHERE station_id = ?", [$stationId]);
                $payment = $minutes > 0
                    ? sessionCharge($userId, $sessionId, $minutes, $paymentMethod, $reference, "{$station['station_code']} time: $minutes min")
                    : null;
                return ['session_id' => $sessionId, 'payment' => $payment, 'station_code' => $station['station_code']];
            });
            logAuditTrail('session_started', 'session', $result['session_id'], ['minutes' => $minutes, 'payment' => $paymentMethod ?: 'pay at end']);
            successResponse(
                sessionFetch($result['session_id']) + ['payment' => $result['payment']],
                $minutes > 0
                    ? "Session started on {$result['station_code']} for $minutes min" . ($result['payment']['status'] === 'pending' ? ' (charged to account).' : " — paid {$result['payment']['receipt_number']}.")
                    : "Open session started on {$result['station_code']}. The charge is worked out when it ends."
            );

        case 'extend':
            requirePermission('sessions.manage');
            $sessionId = sessionRequireId($input);
            $minutes = (int) ($input['minutes'] ?? 0);
            if ($minutes < 5 || $minutes > 12 * 60) {
                throw new InvalidArgumentException('Add between 5 minutes and 12 hours.');
            }
            $result = billingAtomic(function () use ($sessionId, $minutes, $paymentMethod, $reference) {
                $session = Database::fetchOne(
                    "SELECT s.*, st.station_code, (SELECT COUNT(*) FROM transactions t WHERE t.session_id = s.session_id) AS charges
                     FROM sessions s JOIN stations st ON st.station_id = s.station_id WHERE s.session_id = ? AND s.status = 'active' FOR UPDATE",
                    [$sessionId]
                );
                if (!$session) {
                    throw new InvalidArgumentException('Only active sessions can be extended.');
                }
                if ($session['scheduled_end_at'] === null) {
                    throw new InvalidArgumentException('This is an open session — it already runs until you end it.');
                }
                $prepaid = (int) $session['charges'] > 0;
                if ($prepaid && $paymentMethod === '') {
                    throw new InvalidArgumentException('Choose how the customer pays for the extra time.');
                }
                Database::execute(
                    'UPDATE sessions SET scheduled_end_at = DATE_ADD(GREATEST(scheduled_end_at, NOW()), INTERVAL ? MINUTE) WHERE session_id = ?',
                    [$minutes, $sessionId]
                );
                $payment = $prepaid
                    ? sessionCharge((int) $session['user_id'], $sessionId, $minutes, $paymentMethod, $reference, "{$session['station_code']} extra time: $minutes min")
                    : null;
                return ['payment' => $payment];
            });
            logAuditTrail('session_extended', 'session', $sessionId, ['minutes' => $minutes, 'payment' => $paymentMethod ?: null]);
            successResponse(sessionFetch($sessionId) + ['payment' => $result['payment']], "Added $minutes minutes.");

        case 'move':
            requirePermission('sessions.manage');
            $sessionId = sessionRequireId($input);
            $target = (int) ($input['station_id'] ?? 0);
            $moved = billingAtomic(function () use ($sessionId, $target) {
                $session = Database::fetchOne("SELECT * FROM sessions WHERE session_id = ? AND status = 'active' FOR UPDATE", [$sessionId]);
                if (!$session) {
                    throw new InvalidArgumentException('Only active sessions can be moved.');
                }
                $station = Database::fetchOne('SELECT * FROM stations WHERE station_id = ? FOR UPDATE', [$target]);
                if (!$station || $station['status'] === 'maintenance' || (int) $target === (int) $session['station_id']) {
                    throw new InvalidArgumentException('Choose a different station that is not under maintenance.');
                }
                if (Database::fetchColumn("SELECT COUNT(*) FROM sessions WHERE station_id = ? AND status = 'active'", [$target])) {
                    throw new InvalidArgumentException("{$station['station_code']} is already in use.");
                }
                Database::execute('UPDATE sessions SET station_id = ? WHERE session_id = ?', [$target, $sessionId]);
                Database::execute("UPDATE stations SET status = 'occupied' WHERE station_id = ?", [$target]);
                Database::execute(
                    "UPDATE stations SET status = 'available' WHERE station_id = ? AND status = 'occupied'
                     AND NOT EXISTS (SELECT 1 FROM sessions s WHERE s.station_id = stations.station_id AND s.status = 'active')",
                    [$session['station_id']]
                );
                return ['from' => (int) $session['station_id'], 'to' => $station['station_code']];
            });
            logAuditTrail('session_moved', 'session', $sessionId, $moved);
            successResponse(sessionFetch($sessionId), "Session moved to {$moved['to']}.");

        case 'end':
            if (!hasPermission('sessions.end') && !hasPermission('sessions.manage')) {
                errorResponse('You need the End Sessions permission.', 403);
            }
            $sessionId = sessionRequireId($input);
            $result = finishSession($sessionId);
            if (!$result) {
                throw new InvalidArgumentException('That session has already ended.');
            }
            $settlement = null;
            if ($result['charge_status'] === 'pending' && $result['transaction_id'] && $paymentMethod !== '') {
                $settlement = billingSettleTransaction((int) $result['transaction_id'], $paymentMethod, $reference);
            }
            $locked = null;
            if (!empty($input['lock_station'])) {
                $station = Database::fetchOne('SELECT * FROM stations WHERE station_id = ?', [$result['station_id']]);
                $locked = $station ? controlStation($station, 'lock', [], 'portal', 0)['message'] : null;
            }
            logAuditTrail('session_ended', 'session', $sessionId, ['minutes' => $result['duration'], 'amount' => $result['amount'], 'payment' => $paymentMethod ?: null]);

            $status = $settlement['status'] ?? $result['charge_status'];
            $message = match (true) {
                $result['prepaid'] => "Session ended after {$result['duration']} min (time was paid in advance).",
                $result['amount'] <= 0 => 'Session ended — no charge.',
                $status === 'completed' && $result['charge_status'] === 'completed' => 'Session ended. ' . number_format($result['amount'], 2) . ' was paid from the wallet automatically.',
                $status === 'completed' => 'Session ended and ' . number_format($result['amount'], 2) . ' collected.',
                default => 'Session ended. ' . number_format($result['amount'], 2) . ' added to the customer\'s unpaid charges.',
            };
            successResponse(sessionFetch($sessionId) + ['end' => $result, 'settlement' => $settlement, 'lock' => $locked], $message);

        case 'settle':
            requirePermission('sessions.manage');
            $sessionId = sessionRequireId($input);
            $txId = (int) Database::fetchColumn(
                "SELECT transaction_id FROM transactions WHERE session_id = ? AND status = 'pending' AND invoice_id IS NULL ORDER BY transaction_id DESC LIMIT 1",
                [$sessionId]
            );
            if (!$txId) {
                throw new InvalidArgumentException('This session has no unpaid charge to collect.');
            }
            if ($paymentMethod === '' || $paymentMethod === 'account') {
                throw new InvalidArgumentException('Choose how the customer is paying.');
            }
            $settlement = billingSettleTransaction($txId, $paymentMethod, $reference);
            successResponse(sessionFetch($sessionId) + ['settlement' => $settlement], 'Payment of ' . number_format($settlement['amount'], 2) . ' recorded.');

        case 'cancel':
            requirePermission('sessions.manage');
            $sessionId = sessionRequireId($input);
            $reason = trim((string) ($input['reason'] ?? ''));
            if ($reason === '') {
                throw new InvalidArgumentException('Give a reason for cancelling (e.g. started by mistake).');
            }
            $session = Database::fetchOne("SELECT * FROM sessions WHERE session_id = ? AND status = 'active'", [$sessionId]);
            if (!$session) {
                throw new InvalidArgumentException('Only active sessions can be cancelled.');
            }
            Database::execute(
                "UPDATE sessions SET status = 'cancelled', ended_at = NOW(), notes = CONCAT(COALESCE(notes, ''), ?) WHERE session_id = ?",
                ["\nCancelled: " . mb_substr($reason, 0, 300), $sessionId]
            );
            Database::execute(
                "UPDATE stations SET status = 'available' WHERE station_id = ? AND status = 'occupied'
                 AND NOT EXISTS (SELECT 1 FROM sessions s WHERE s.station_id = stations.station_id AND s.status = 'active')",
                [$session['station_id']]
            );
            logAuditTrail('session_cancelled', 'session', $sessionId, ['reason' => $reason]);
            successResponse(sessionFetch($sessionId), 'Session cancelled without a charge. Refund any prepayment from Transactions if needed.');

        default:
            errorResponse('Unknown session action', 400);
    }
} catch (InvalidArgumentException $e) {
    errorResponse($e->getMessage(), 422);
} catch (Throwable $e) {
    error_log('Sessions API error: ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
    errorResponse('The session request could not be completed.', 500);
}
