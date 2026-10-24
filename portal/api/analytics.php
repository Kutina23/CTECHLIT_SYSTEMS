<?php
/**
 * Business analytics API
 *
 * GET ?range=today|7d|30d|90d|month|last_month|ytd|12m   or   ?from=YYYY-MM-DD&to=YYYY-MM-DD
 *     → KPIs with previous-period comparison, revenue trend, categories, payment methods,
 *       day × hour heatmap, station use, top customers & items, printing and receivables.
 * GET ...&export=csv → the same report as a CSV download.
 *
 * Revenue = sales recorded in the period (paid now or on account), less refunds.
 * Wallet top-ups are cash received in advance, not revenue; they are reported separately.
 */

require_once __DIR__ . '/common.php';
requirePermission('reports.view');

const AN_CATEGORY_LABELS = [
    'station_time' => 'Station time', 'printing' => 'Printing', 'devices' => 'Personal devices', 'internet' => 'Internet',
    'gaming' => 'Gaming', 'scanning' => 'Scanning', 'typing' => 'Typing', 'mobile_money' => 'Mobile money',
    'consultation' => 'Consultation', 'retail' => 'Retail', 'other' => 'Other',
];

function anRange(): array
{
    $today = new DateTimeImmutable('today');
    $from = (string) ($_GET['from'] ?? '');
    $to = (string) ($_GET['to'] ?? '');
    if (preg_match('/\A\d{4}-\d{2}-\d{2}\z/', $from) && preg_match('/\A\d{4}-\d{2}-\d{2}\z/', $to)) {
        $start = new DateTimeImmutable($from);
        $end = new DateTimeImmutable($to);
        if ($end < $start) {
            [$start, $end] = [$end, $start];
        }
        if ($end > $today) {
            $end = $today;
        }
        if ($start->diff($end)->days > 731) {
            throw new InvalidArgumentException('Choose a range of up to two years.');
        }
        $key = 'custom';
    } else {
        $key = (string) ($_GET['range'] ?? '30d');
        [$start, $end] = match ($key) {
            'today' => [$today, $today],
            '7d' => [$today->modify('-6 days'), $today],
            '90d' => [$today->modify('-89 days'), $today],
            'month' => [$today->modify('first day of this month'), $today],
            'last_month' => [$today->modify('first day of last month'), $today->modify('last day of last month')],
            'ytd' => [$today->setDate((int) $today->format('Y'), 1, 1), $today],
            '12m' => [$today->modify('first day of this month')->modify('-11 months'), $today],
            default => [$today->modify('-29 days'), $today],
        };
        if (!in_array($key, ['today', '7d', '90d', 'month', 'last_month', 'ytd', '12m'], true)) {
            $key = '30d';
        }
    }
    $days = $start->diff($end)->days + 1;
    // Previous period of the same length immediately before (calendar month for month presets).
    if ($key === 'month' || $key === 'last_month') {
        $prevStart = $start->modify('first day of last month');
        $prevEnd = $prevStart->modify('+' . min($days, (int) $prevStart->format('t')) - 1 . ' days');
    } else {
        $prevEnd = $start->modify('-1 day');
        $prevStart = $prevEnd->modify('-' . ($days - 1) . ' days');
    }
    return [
        'key' => $key, 'from' => $start->format('Y-m-d'), 'to' => $end->format('Y-m-d'), 'days' => $days,
        'prev_from' => $prevStart->format('Y-m-d'), 'prev_to' => $prevEnd->format('Y-m-d'),
        'bucket' => $days > 92 ? 'month' : 'day',
    ];
}

/** SQL expression giving a transaction's revenue category. */
function anCategorySql(): string
{
    return "CASE
        WHEN EXISTS (SELECT 1 FROM print_jobs pj WHERE pj.transaction_id = t.transaction_id) OR t.notes LIKE 'Printing:%' THEN 'printing'
        WHEN t.session_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM pos_transactions p WHERE p.ledger_transaction_id = t.transaction_id) THEN 'station_time'
        WHEN t.notes LIKE 'Device time:%' THEN 'devices'
        ELSE COALESCE(
            (SELECT s.category FROM transaction_items ti JOIN services s ON s.service_id = ti.service_id
             WHERE ti.transaction_id = t.transaction_id ORDER BY ti.total_price DESC LIMIT 1),
            (SELECT s.category FROM transaction_items ti JOIN services s ON s.name = ti.description
             WHERE ti.transaction_id = t.transaction_id ORDER BY ti.total_price DESC LIMIT 1),
            (SELECT p.service_type FROM pos_transactions p WHERE p.ledger_transaction_id = t.transaction_id LIMIT 1),
            CASE WHEN t.session_id IS NOT NULL THEN 'station_time' END,
            'other')
    END";
}

/** Sales counted as revenue: everything except voided (failed) transactions. */
const AN_SALE = "t.status IN ('completed','pending','refunded','partially_refunded')";

function anRefundSql(string $alias = 't'): string
{
    return "CASE WHEN $alias.status = 'refunded' THEN $alias.total_amount
                 WHEN $alias.status = 'partially_refunded' THEN COALESCE((SELECT SUM(r.amount) FROM transaction_refunds r WHERE r.transaction_id = $alias.transaction_id), 0)
                 ELSE 0 END";
}

function anTotals(string $from, string $to): array
{
    $sales = Database::fetchOne(
        'SELECT COALESCE(SUM(t.total_amount), 0) AS gross, COALESCE(SUM(' . anRefundSql() . '), 0) AS refunds,
                COUNT(*) AS transactions, COUNT(DISTINCT t.user_id) AS customers,
                COALESCE(SUM(CASE WHEN t.status = \'pending\' THEN t.total_amount END), 0) AS on_account
         FROM transactions t WHERE ' . AN_SALE . ' AND DATE(t.created_at) BETWEEN ? AND ?',
        [$from, $to]
    );
    $sessions = Database::fetchOne(
        "SELECT COUNT(*) AS sessions,
                COALESCE(SUM(COALESCE(duration_minutes, TIMESTAMPDIFF(MINUTE, started_at, COALESCE(ended_at, NOW())))), 0) AS minutes
         FROM sessions WHERE status <> 'cancelled' AND DATE(started_at) BETWEEN ? AND ?",
        [$from, $to]
    );
    $topups = Database::fetchOne(
        "SELECT COALESCE(SUM(amount), 0) AS amount, COUNT(*) AS count FROM billing_ledger WHERE entry_type = 'topup' AND DATE(created_at) BETWEEN ? AND ?",
        [$from, $to]
    );
    $net = (float) $sales['gross'] - (float) $sales['refunds'];
    $count = (int) $sales['transactions'];
    return [
        'revenue' => round($net, 2),
        'gross' => (float) $sales['gross'],
        'refunds' => (float) $sales['refunds'],
        'transactions' => $count,
        'avg_ticket' => $count ? round($net / $count, 2) : 0.0,
        'customers' => (int) $sales['customers'],
        'new_customers' => (int) Database::fetchColumn('SELECT COUNT(*) FROM users WHERE DATE(created_at) BETWEEN ? AND ?', [$from, $to]),
        'on_account' => (float) $sales['on_account'],
        'sessions' => (int) $sessions['sessions'],
        'station_hours' => round((float) $sessions['minutes'] / 60, 1),
        'avg_session_minutes' => $sessions['sessions'] ? (int) round($sessions['minutes'] / $sessions['sessions']) : 0,
        'topups' => (float) $topups['amount'],
        'print_sides' => (int) Database::fetchColumn("SELECT COALESCE(SUM(pages * copies), 0) FROM print_jobs WHERE status = 'completed' AND DATE(created_at) BETWEEN ? AND ?", [$from, $to]),
    ];
}

function anSeries(string $from, string $to, string $bucket): array
{
    $group = $bucket === 'month' ? "DATE_FORMAT(t.created_at, '%Y-%m-01')" : 'DATE(t.created_at)';
    $rows = Database::fetchAll(
        "SELECT $group AS bucket, COALESCE(SUM(t.total_amount - " . anRefundSql() . "), 0) AS revenue, COUNT(*) AS transactions
         FROM transactions t WHERE " . AN_SALE . " AND DATE(t.created_at) BETWEEN ? AND ? GROUP BY bucket",
        [$from, $to]
    );
    $byBucket = array_column($rows, null, 'bucket');
    $out = [];
    $cursor = new DateTimeImmutable($bucket === 'month' ? substr($from, 0, 7) . '-01' : $from);
    $end = new DateTimeImmutable($to);
    while ($cursor <= $end) {
        $key = $cursor->format('Y-m-d');
        $out[] = [
            'date' => $key,
            'label' => $bucket === 'month' ? $cursor->format('M Y') : $cursor->format('D j M'),
            'revenue' => round((float) ($byBucket[$key]['revenue'] ?? 0), 2),
            'transactions' => (int) ($byBucket[$key]['transactions'] ?? 0),
        ];
        $cursor = $cursor->modify($bucket === 'month' ? '+1 month' : '+1 day');
    }
    return $out;
}

function anChange(float $current, float $previous): ?float
{
    if ($previous == 0.0) {
        return $current == 0.0 ? 0.0 : null;
    }
    return round(($current - $previous) / abs($previous) * 100, 1);
}

try {
    $range = anRange();
    [$from, $to] = [$range['from'], $range['to']];
    $current = anTotals($from, $to);
    $previous = anTotals($range['prev_from'], $range['prev_to']);
    $changes = [];
    foreach ($current as $key => $value) {
        $changes[$key] = anChange((float) $value, (float) $previous[$key]);
    }

    $stationCount = max(1, (int) Database::fetchColumn("SELECT COUNT(*) FROM stations WHERE status <> 'maintenance'"));
    $openHours = (float) (siteSetting('business_hours_per_day', '12') ?: 12);
    $capacityHours = $stationCount * $openHours * $range['days'];
    $current['utilization'] = $capacityHours > 0 ? round(min(100, $current['station_hours'] / $capacityHours * 100), 1) : 0;
    $prevCapacity = $stationCount * $openHours * $range['days'];
    $previous['utilization'] = $prevCapacity > 0 ? round(min(100, $previous['station_hours'] / $prevCapacity * 100), 1) : 0;
    $changes['utilization'] = anChange($current['utilization'], $previous['utilization']);

    $categorySql = anCategorySql();
    $categories = Database::fetchAll(
        "SELECT category, SUM(net) AS revenue, COUNT(*) AS transactions FROM (
            SELECT ($categorySql) AS category, t.total_amount - " . anRefundSql() . " AS net
            FROM transactions t WHERE " . AN_SALE . " AND DATE(t.created_at) BETWEEN ? AND ?
         ) x GROUP BY category ORDER BY revenue DESC",
        [$from, $to]
    );
    foreach ($categories as &$category) {
        $category['label'] = AN_CATEGORY_LABELS[$category['category']] ?? ucwords(str_replace('_', ' ', (string) $category['category']));
        $category['revenue'] = round((float) $category['revenue'], 2);
    }
    unset($category);

    $methods = Database::fetchAll(
        "SELECT COALESCE(NULLIF(t.payment_method, ''), 'unrecorded') AS method, COUNT(*) AS transactions,
                COALESCE(SUM(t.total_amount - " . anRefundSql() . "), 0) AS revenue
         FROM transactions t WHERE " . AN_SALE . " AND DATE(t.created_at) BETWEEN ? AND ?
         GROUP BY method ORDER BY revenue DESC",
        [$from, $to]
    );

    $heatmap = Database::fetchAll(
        "SELECT (DAYOFWEEK(t.created_at) + 5) % 7 AS dow, HOUR(t.created_at) AS hour, COUNT(*) AS transactions,
                COALESCE(SUM(t.total_amount), 0) AS revenue
         FROM transactions t WHERE " . AN_SALE . " AND DATE(t.created_at) BETWEEN ? AND ? GROUP BY dow, hour",
        [$from, $to]
    );

    $stations = Database::fetchAll(
        "SELECT st.station_id, st.station_code, st.name, st.status,
                COUNT(s.session_id) AS sessions,
                COALESCE(SUM(COALESCE(s.duration_minutes, TIMESTAMPDIFF(MINUTE, s.started_at, COALESCE(s.ended_at, NOW())))), 0) AS minutes,
                COALESCE((SELECT SUM(t.total_amount) FROM transactions t JOIN sessions s2 ON s2.session_id = t.session_id
                          WHERE s2.station_id = st.station_id AND " . AN_SALE . " AND DATE(t.created_at) BETWEEN ? AND ?), 0) AS revenue
         FROM stations st
         LEFT JOIN sessions s ON s.station_id = st.station_id AND s.status <> 'cancelled' AND DATE(s.started_at) BETWEEN ? AND ?
         GROUP BY st.station_id ORDER BY minutes DESC, st.station_code",
        [$from, $to, $from, $to]
    );
    foreach ($stations as &$station) {
        $station['hours'] = round($station['minutes'] / 60, 1);
        $station['utilization'] = round(min(100, $station['minutes'] / 60 / max(1, $openHours * $range['days']) * 100), 1);
    }
    unset($station);

    $customers = Database::fetchAll(
        "SELECT u.user_id, u.full_name, u.phone, COUNT(*) AS transactions,
                COALESCE(SUM(t.total_amount - " . anRefundSql() . "), 0) AS revenue, MAX(t.created_at) AS last_visit
         FROM transactions t JOIN users u ON u.user_id = t.user_id
         WHERE " . AN_SALE . " AND DATE(t.created_at) BETWEEN ? AND ? AND u.full_name <> 'Walk-in Customer'
         GROUP BY u.user_id ORDER BY revenue DESC LIMIT 8",
        [$from, $to]
    );
    $returning = (int) Database::fetchColumn(
        "SELECT COUNT(DISTINCT t.user_id) FROM transactions t JOIN users u ON u.user_id = t.user_id
         WHERE " . AN_SALE . " AND DATE(t.created_at) BETWEEN ? AND ? AND DATE(u.created_at) < ?",
        [$from, $to, $from]
    );

    $items = Database::fetchAll(
        "SELECT ti.description, SUM(ti.quantity) AS quantity, SUM(ti.total_price) AS revenue, COUNT(DISTINCT ti.transaction_id) AS transactions
         FROM transaction_items ti JOIN transactions t ON t.transaction_id = ti.transaction_id
         WHERE " . AN_SALE . " AND t.status <> 'refunded' AND DATE(t.created_at) BETWEEN ? AND ?
         GROUP BY CASE WHEN ti.description LIKE 'Printing:%' THEN 'Printing' WHEN ti.description LIKE 'Device time:%' THEN SUBSTRING_INDEX(ti.description, ' (', 1) ELSE ti.description END
         ORDER BY revenue DESC LIMIT 8",
        [$from, $to]
    );
    foreach ($items as &$item) {
        if (str_starts_with($item['description'], 'Printing:')) {
            $item['description'] = 'Printing';
        } elseif (str_starts_with($item['description'], 'Device time:')) {
            $item['description'] = explode(' (', $item['description'])[0];
        }
    }
    unset($item);

    $printing = Database::fetchOne(
        "SELECT COUNT(*) AS jobs,
                COALESCE(SUM(CASE WHEN status = 'completed' THEN pages * copies END), 0) AS sides,
                COALESCE(SUM(CASE WHEN status = 'completed' AND color_mode = 'color' THEN pages * copies END), 0) AS color_sides,
                COALESCE(SUM(CASE WHEN payment_status IN ('paid','on_account') THEN amount END), 0) AS revenue,
                SUM(status = 'failed') AS failed, SUM(status = 'cancelled') AS cancelled,
                COALESCE(SUM(CASE WHEN payment_status = 'unpaid' AND status <> 'cancelled' THEN amount END), 0) AS uncollected
         FROM print_jobs WHERE DATE(created_at) BETWEEN ? AND ?",
        [$from, $to]
    );

    $receivables = [
        'on_account' => (float) Database::fetchColumn("SELECT COALESCE(SUM(total_amount), 0) FROM transactions WHERE status = 'pending'"),
        'invoices_due' => (float) Database::fetchColumn("SELECT COALESCE(SUM(total_amount - amount_paid), 0) FROM invoices WHERE status IN ('issued','partially_paid')"),
        'invoices_overdue' => (float) Database::fetchColumn("SELECT COALESCE(SUM(total_amount - amount_paid), 0) FROM invoices WHERE status IN ('issued','partially_paid') AND due_date < CURDATE()"),
        'wallet_balances' => (float) Database::fetchColumn('SELECT COALESCE(SUM(balance), 0) FROM billing_accounts WHERE balance > 0'),
    ];

    $report = [
        'range' => $range,
        'currency' => siteSetting('currency_symbol', siteSetting('currency', 'GHS')),
        'current' => $current,
        'previous' => $previous,
        'changes' => $changes,
        'capacity' => ['stations' => $stationCount, 'hours_per_day' => $openHours],
        'series' => anSeries($from, $to, $range['bucket']),
        'previous_series' => anSeries($range['prev_from'], $range['prev_to'], $range['bucket']),
        'categories' => $categories,
        'methods' => $methods,
        'heatmap' => $heatmap,
        'stations' => $stations,
        'customers' => $customers,
        'customer_mix' => ['new' => $current['new_customers'], 'returning' => $returning],
        'items' => $items,
        'printing' => $printing,
        'receivables' => $receivables,
    ];

    if (($_GET['export'] ?? '') === 'csv') {
        $out = fopen('php://temp', 'r+');
        $put = static fn(array $row) => fputcsv($out, $row, ',', '"', '');
        $put(['CTECHLIT & SYSTEMS analytics', $from . ' to ' . $to]);
        $put([]);
        $put(['Metric', 'This period', 'Previous period', 'Change %']);
        $labels = ['revenue' => 'Net revenue', 'gross' => 'Gross sales', 'refunds' => 'Refunds', 'transactions' => 'Transactions', 'avg_ticket' => 'Average sale',
            'customers' => 'Customers served', 'new_customers' => 'New customers', 'on_account' => 'Sold on account', 'sessions' => 'Sessions',
            'station_hours' => 'Station hours', 'utilization' => 'Station utilisation %', 'topups' => 'Wallet top-ups', 'print_sides' => 'Printed sides'];
        foreach ($labels as $key => $label) {
            $put([$label, $current[$key], $previous[$key], $changes[$key] ?? '']);
        }
        $put([]);
        $put([$range['bucket'] === 'month' ? 'Month' : 'Date', 'Net revenue', 'Transactions']);
        foreach ($report['series'] as $row) {
            $put([$row['date'], $row['revenue'], $row['transactions']]);
        }
        $put([]);
        $put(['Category', 'Net revenue', 'Transactions']);
        foreach ($categories as $row) {
            $put([$row['label'], $row['revenue'], $row['transactions']]);
        }
        $put([]);
        $put(['Payment method', 'Net revenue', 'Transactions']);
        foreach ($methods as $row) {
            $put([$row['method'], $row['revenue'], $row['transactions']]);
        }
        $put([]);
        $put(['Station', 'Sessions', 'Hours', 'Utilisation %', 'Revenue']);
        foreach ($stations as $row) {
            $put([$row['station_code'] . ' ' . $row['name'], $row['sessions'], $row['hours'], $row['utilization'], $row['revenue']]);
        }
        $put([]);
        $put(['Top customer', 'Phone', 'Transactions', 'Net revenue']);
        foreach ($customers as $row) {
            $put([$row['full_name'], $row['phone'], $row['transactions'], $row['revenue']]);
        }
        rewind($out);
        $csv = stream_get_contents($out);
        fclose($out);
        logAuditTrail('analytics_exported', 'report', null, ['from' => $from, 'to' => $to]);
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="analytics-' . $from . '-to-' . $to . '.csv"');
        header('Cache-Control: private, no-store');
        echo "\xEF\xBB\xBF" . $csv;
        exit;
    }

    successResponse($report);
} catch (InvalidArgumentException $e) {
    errorResponse($e->getMessage(), 422);
} catch (Throwable $e) {
    error_log('Analytics API error: ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
    errorResponse('Analytics could not be loaded.', 500);
}
