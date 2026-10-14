<?php
/**
 * Safe report builder API.
 * Queryable tables, joins, and fields are intentionally defined here rather
 * than accepted from the client.
 */

require_once __DIR__ . '/common.php';
requirePermission('reports.view');
requirePermission('reports.export');

function reportSources(): array
{
    $field = static function (string $label, string $expression, string $type = 'text'): array {
        return ['label' => $label, 'sql' => $expression, 'type' => $type];
    };

    return [
        'customers' => [
            'label' => 'Customers',
            'from' => 'users u',
            'date_field' => 'created_at',
            'fields' => [
                'user_id' => $field('Customer ID', 'u.user_id', 'number'),
                'full_name' => $field('Full name', 'u.full_name'),
                'email' => $field('Email', 'u.email'),
                'phone' => $field('Phone', 'u.phone'),
                'membership_tier' => $field('Membership tier', 'u.membership_tier'),
                'total_visits' => $field('Total visits', 'u.total_visits', 'number'),
                'total_spent' => $field('Total spent', 'u.total_spent', 'number'),
                'is_active' => $field('Active', 'u.is_active', 'number'),
                'created_at' => $field('Joined at', 'u.created_at', 'date'),
                'last_login_at' => $field('Last login', 'u.last_login_at', 'date'),
            ],
        ],
        'staff' => [
            'label' => 'Staff',
            'from' => 'staff s',
            'date_field' => 'created_at',
            'fields' => [
                'staff_id' => $field('Staff ID', 's.staff_id', 'number'),
                'full_name' => $field('Full name', 's.full_name'),
                'email' => $field('Email', 's.email'),
                'phone' => $field('Phone', 's.phone'),
                'role' => $field('Role', 's.role'),
                'is_on_duty' => $field('On duty', 's.is_on_duty', 'number'),
                'is_active' => $field('Active', 's.is_active', 'number'),
                'hired_at' => $field('Hire date', 's.hired_at', 'date'),
                'created_at' => $field('Created at', 's.created_at', 'date'),
            ],
        ],
        'stations' => [
            'label' => 'Stations',
            'from' => 'stations st',
            'date_field' => 'created_at',
            'fields' => [
                'station_id' => $field('Station ID', 'st.station_id', 'number'),
                'station_code' => $field('Station code', 'st.station_code'),
                'name' => $field('Name', 'st.name'),
                'location' => $field('Location', 'st.location'),
                'status' => $field('Status', 'st.status'),
                'last_heartbeat' => $field('Last heartbeat', 'st.last_heartbeat', 'date'),
                'created_at' => $field('Created at', 'st.created_at', 'date'),
            ],
        ],
        'sessions' => [
            'label' => 'Sessions',
            'from' => 'sessions se LEFT JOIN users u ON u.user_id = se.user_id LEFT JOIN stations st ON st.station_id = se.station_id LEFT JOIN staff sf ON sf.staff_id = se.staff_id',
            'date_field' => 'started_at',
            'fields' => [
                'session_id' => $field('Session ID', 'se.session_id', 'number'),
                'customer' => $field('Customer', 'u.full_name'),
                'station' => $field('Station', 'st.station_code'),
                'staff' => $field('Started by', 'sf.full_name'),
                'started_at' => $field('Started at', 'se.started_at', 'date'),
                'ended_at' => $field('Ended at', 'se.ended_at', 'date'),
                'duration_minutes' => $field('Duration (minutes)', 'se.duration_minutes', 'number'),
                'status' => $field('Status', 'se.status'),
            ],
        ],
        'transactions' => [
            'label' => 'Transactions',
            'from' => 'transactions t LEFT JOIN users u ON u.user_id = t.user_id',
            'date_field' => 'created_at',
            'fields' => [
                'transaction_id' => $field('Transaction ID', 't.transaction_id', 'number'),
                'customer' => $field('Customer', 'u.full_name'),
                'amount' => $field('Amount', 't.amount', 'number'),
                'tax_amount' => $field('Tax', 't.tax_amount', 'number'),
                'discount_amount' => $field('Discount', 't.discount_amount', 'number'),
                'total_amount' => $field('Total amount', 't.total_amount', 'number'),
                'currency' => $field('Currency', 't.currency'),
                'status' => $field('Status', 't.status'),
                'receipt_number' => $field('Receipt number', 't.receipt_number'),
                'created_at' => $field('Created at', 't.created_at', 'date'),
            ],
        ],
        'pos_transactions' => [
            'label' => 'POS transactions',
            'from' => 'pos_transactions p LEFT JOIN staff s ON s.staff_id = p.staff_id LEFT JOIN stations st ON st.station_id = p.station_id',
            'date_field' => 'created_at',
            'fields' => [
                'pos_transaction_id' => $field('POS transaction ID', 'p.pos_transaction_id', 'number'),
                'staff' => $field('Staff', 's.full_name'),
                'station' => $field('Station', 'st.station_code'),
                'customer_name' => $field('Customer', 'p.customer_name'),
                'service_type' => $field('Service type', 'p.service_type'),
                'amount' => $field('Amount', 'p.amount', 'number'),
                'payment_method' => $field('Payment method', 'p.payment_method'),
                'status' => $field('Status', 'p.status'),
                'usage_minutes' => $field('Usage (minutes)', 'p.usage_minutes', 'number'),
                'created_at' => $field('Created at', 'p.created_at', 'date'),
            ],
        ],
        'services' => [
            'label' => 'Services',
            'from' => 'services sv',
            'date_field' => 'created_at',
            'fields' => [
                'service_id' => $field('Service ID', 'sv.service_id', 'number'),
                'name' => $field('Name', 'sv.name'),
                'category' => $field('Category', 'sv.category'),
                'base_price' => $field('Base price', 'sv.base_price', 'number'),
                'price_unit' => $field('Price unit', 'sv.price_unit'),
                'is_active' => $field('Active', 'sv.is_active', 'number'),
                'requires_station' => $field('Requires station', 'sv.requires_station', 'number'),
                'created_at' => $field('Created at', 'sv.created_at', 'date'),
            ],
        ],
        'print_jobs' => [
            'label' => 'Print jobs',
            'from' => 'print_jobs pj LEFT JOIN users u ON u.user_id = pj.user_id LEFT JOIN stations st ON st.station_id = pj.station_id',
            'date_field' => 'created_at',
            'fields' => [
                'print_job_id' => $field('Print job ID', 'pj.print_job_id', 'number'),
                'customer' => $field('Customer', 'u.full_name'),
                'station' => $field('Station', 'st.station_code'),
                'file_name' => $field('File name', 'pj.file_name'),
                'pages' => $field('Pages', 'pj.pages', 'number'),
                'copies' => $field('Copies', 'pj.copies', 'number'),
                'color_mode' => $field('Color mode', 'pj.color_mode'),
                'status' => $field('Status', 'pj.status'),
                'created_at' => $field('Created at', 'pj.created_at', 'date'),
                'completed_at' => $field('Completed at', 'pj.completed_at', 'date'),
            ],
        ],
        'inventory' => [
            'label' => 'Inventory products',
            'from' => 'products pr',
            'date_field' => 'created_at',
            'fields' => [
                'product_id' => $field('Product ID', 'pr.product_id', 'number'),
                'name' => $field('Product', 'pr.name'),
                'category' => $field('Category', 'pr.category'),
                'sku' => $field('SKU', 'pr.sku'),
                'cost_price' => $field('Cost price', 'pr.cost_price', 'number'),
                'selling_price' => $field('Selling price', 'pr.selling_price', 'number'),
                'stock_quantity' => $field('Stock quantity', 'pr.stock_quantity', 'number'),
                'min_stock_level' => $field('Minimum stock', 'pr.min_stock_level', 'number'),
                'is_active' => $field('Active', 'pr.is_active', 'number'),
                'created_at' => $field('Created at', 'pr.created_at', 'date'),
            ],
        ],
        'inventory_movements' => [
            'label' => 'Inventory movements',
            'from' => 'inventory_movements im LEFT JOIN products pr ON pr.product_id = im.product_id LEFT JOIN staff s ON s.staff_id = im.created_by',
            'date_field' => 'created_at',
            'fields' => [
                'movement_id' => $field('Movement ID', 'im.movement_id', 'number'),
                'product' => $field('Product', 'pr.name'),
                'movement_type' => $field('Movement type', 'im.movement_type'),
                'quantity' => $field('Quantity', 'im.quantity', 'number'),
                'reference_type' => $field('Reference type', 'im.reference_type'),
                'reference_id' => $field('Reference ID', 'im.reference_id', 'number'),
                'created_by' => $field('Recorded by', 's.full_name'),
                'created_at' => $field('Created at', 'im.created_at', 'date'),
            ],
        ],
        'feedback' => [
            'label' => 'Feedback',
            'from' => 'feedback f LEFT JOIN users u ON u.user_id = f.user_id',
            'date_field' => 'created_at',
            'fields' => [
                'feedback_id' => $field('Feedback ID', 'f.feedback_id', 'number'),
                'customer' => $field('Customer', 'COALESCE(u.full_name, f.customer_name)'),
                'rating' => $field('Rating', 'f.rating', 'number'),
                'category' => $field('Category', 'f.category'),
                'comment' => $field('Comment', 'f.comment'),
                'is_resolved' => $field('Resolved', 'f.is_resolved', 'number'),
                'created_at' => $field('Submitted at', 'f.created_at', 'date'),
            ],
        ],
        'support_tickets' => [
            'label' => 'Support tickets',
            'from' => 'support_tickets st LEFT JOIN users u ON u.user_id = st.user_id LEFT JOIN staff s ON s.staff_id = st.assigned_to',
            'date_field' => 'created_at',
            'fields' => [
                'ticket_id' => $field('Ticket ID', 'st.ticket_id', 'number'),
                'requester_name' => $field('Requester', 'COALESCE(u.full_name, st.requester_name)'),
                'requester_email' => $field('Email', 'st.requester_email'),
                'assigned_to' => $field('Assigned staff', 's.full_name'),
                'subject' => $field('Subject', 'st.subject'),
                'priority' => $field('Priority', 'st.priority'),
                'status' => $field('Status', 'st.status'),
                'category' => $field('Category', 'st.category'),
                'created_at' => $field('Created at', 'st.created_at', 'date'),
                'resolved_at' => $field('Resolved at', 'st.resolved_at', 'date'),
            ],
        ],
        'bookings' => [
            'label' => 'Bookings',
            'from' => 'bookings b LEFT JOIN users u ON u.user_id = b.user_id LEFT JOIN services sv ON sv.service_id = b.service_id LEFT JOIN stations st ON st.station_id = b.station_id LEFT JOIN staff s ON s.staff_id = b.staff_id',
            'date_field' => 'scheduled_at',
            'fields' => [
                'booking_id' => $field('Booking ID', 'b.booking_id', 'number'),
                'customer' => $field('Customer', 'u.full_name'),
                'service' => $field('Service', 'sv.name'),
                'station' => $field('Station', 'st.station_code'),
                'staff' => $field('Staff', 's.full_name'),
                'scheduled_at' => $field('Scheduled at', 'b.scheduled_at', 'date'),
                'duration_minutes' => $field('Duration (minutes)', 'b.duration_minutes', 'number'),
                'status' => $field('Status', 'b.status'),
                'created_at' => $field('Created at', 'b.created_at', 'date'),
            ],
        ],
        'audit_logs' => [
            'label' => 'Audit logs',
            'from' => 'audit_logs al LEFT JOIN staff s ON s.staff_id = al.staff_id',
            'date_field' => 'created_at',
            'fields' => [
                'audit_id' => $field('Audit ID', 'al.audit_id', 'number'),
                'staff_name' => $field('Staff', 's.full_name'),
                'actor_type' => $field('Actor type', 'al.actor_type'),
                'action' => $field('Action', 'al.action'),
                'entity_type' => $field('Entity type', 'al.entity_type'),
                'entity_id' => $field('Entity ID', 'al.entity_id', 'number'),
                'details' => $field('Details', 'al.details'),
                'ip_address' => $field('IP address', 'al.ip_address'),
                'created_at' => $field('Created at', 'al.created_at', 'date'),
            ],
        ],
    ];
}

function reportDateIsValid(string $date): bool
{
    $parsed = DateTime::createFromFormat('!Y-m-d', $date);
    return $parsed !== false && $parsed->format('Y-m-d') === $date;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (!isset($_GET['catalog'])) {
        errorResponse('Use the report catalog or submit a report configuration.', 400);
    }

    $catalog = [];
    foreach (reportSources() as $key => $source) {
        $fields = [];
        foreach ($source['fields'] as $fieldKey => $field) {
            $fields[] = ['key' => $fieldKey, 'label' => $field['label'], 'type' => $field['type']];
        }
        $catalog[$key] = [
            'label' => $source['label'],
            'date_field' => $source['date_field'],
            'fields' => $fields,
        ];
    }
    successResponse(['sources' => $catalog]);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    errorResponse('Method not allowed', 405);
}

$csrfToken = (string) ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
if (!verifyCsrfToken($csrfToken)) {
    errorResponse('Invalid CSRF token. Refresh the page and try again.', 403);
}

$input = $GLOBALS['request_input'] ?? [];
$sourceKey = is_string($input['source'] ?? null) ? $input['source'] : '';
$sources = reportSources();
if (!isset($sources[$sourceKey])) {
    errorResponse('Choose a valid report data source.');
}
$source = $sources[$sourceKey];
if ($sourceKey === 'audit_logs' && !hasPermission('audit.view')) {
    errorResponse('You do not have permission to view audit logs.', 403);
}
$fields = $source['fields'];
$requestedColumns = $input['columns'] ?? [];
if (!is_array($requestedColumns)) {
    errorResponse('Report columns must be a list.');
}
$columns = array_values(array_unique(array_filter($requestedColumns, static function ($key) use ($fields) {
    return is_string($key) && isset($fields[$key]);
})));
if (!$columns) {
    errorResponse('Select at least one valid report column.');
}

$where = [];
$params = [];
$dateFrom = is_string($input['date_from'] ?? null) ? trim($input['date_from']) : '';
$dateTo = is_string($input['date_to'] ?? null) ? trim($input['date_to']) : '';
$dateField = $source['date_field'];
if ($dateFrom !== '' || $dateTo !== '') {
    if ($dateField === '' || !isset($fields[$dateField])) {
        errorResponse('Date filtering is not available for this report source.');
    }
    if ($dateFrom !== '') {
        if (!reportDateIsValid($dateFrom)) errorResponse('Enter a valid start date.');
        $where[] = $fields[$dateField]['sql'] . ' >= ?';
        $params[] = $dateFrom . ' 00:00:00';
    }
    if ($dateTo !== '') {
        if (!reportDateIsValid($dateTo)) errorResponse('Enter a valid end date.');
        $where[] = $fields[$dateField]['sql'] . ' <= ?';
        $params[] = $dateTo . ' 23:59:59';
    }
    if ($dateFrom !== '' && $dateTo !== '' && $dateFrom > $dateTo) {
        errorResponse('The start date must be on or before the end date.');
    }
}

$filters = $input['filters'] ?? [];
if (!is_array($filters) || count($filters) > 10) {
    errorResponse('A report can contain up to 10 additional filters.');
}
$operators = [
    'equals' => '=',
    'not_equals' => '!=',
    'greater' => '>',
    'less' => '<',
    'on_or_after' => '>=',
    'on_or_before' => '<=',
];
foreach ($filters as $filter) {
    if (!is_array($filter)) errorResponse('Invalid report filter.');
    if (!is_string($filter['field'] ?? null) || !is_string($filter['operator'] ?? null)
        || !is_scalar($filter['value'] ?? '')) {
        errorResponse('Invalid report filter.');
    }
    $fieldKey = $filter['field'];
    $operator = $filter['operator'];
    $value = trim((string) ($filter['value'] ?? ''));
    if ($value === '') continue;
    if (strlen($value) > 255) errorResponse('Report filter values cannot exceed 255 characters.');
    if (!isset($fields[$fieldKey])) errorResponse('A report filter refers to an unavailable field.');
    if ($operator === 'contains') {
        $where[] = $fields[$fieldKey]['sql'] . ' LIKE ?';
        $params[] = '%' . $value . '%';
    } elseif (isset($operators[$operator])) {
        $where[] = $fields[$fieldKey]['sql'] . ' ' . $operators[$operator] . ' ?';
        $params[] = $value;
    } else {
        errorResponse('Choose a valid report filter operation.');
    }
}

$groupBy = is_string($input['group_by'] ?? null) ? $input['group_by'] : '';
$measure = is_string($input['measure'] ?? null) ? $input['measure'] : '';
$aggregate = is_string($input['aggregate'] ?? null) ? $input['aggregate'] : 'count';
$direction = strtolower(is_string($input['direction'] ?? null) ? $input['direction'] : 'desc') === 'asc' ? 'ASC' : 'DESC';
$limitValue = $input['limit'] ?? 500;
if (!is_numeric($limitValue)) errorResponse('Choose a valid report row limit.');
$limit = (int) $limitValue;
$limit = max(1, min($limit, 5000));

try {
    $whereSql = $where ? ' WHERE ' . implode(' AND ', $where) : '';
    if ($groupBy !== '') {
        if (!isset($fields[$groupBy])) errorResponse('Choose a valid grouping field.');
        if (!in_array($aggregate, ['count', 'sum', 'avg', 'min', 'max'], true)) {
            errorResponse('Choose a valid report calculation.');
        }
        if ($aggregate !== 'count' && (!isset($fields[$measure]) || $fields[$measure]['type'] !== 'number')) {
            errorResponse('Choose a numeric measure for this calculation.');
        }
        if ($aggregate === 'count' && $measure !== '' && !isset($fields[$measure])) {
            errorResponse('Choose a valid measure field.');
        }
        $measureSql = $measure === '' ? '*' : $fields[$measure]['sql'];
        $aggregateSql = strtoupper($aggregate) . '(' . $measureSql . ')';
        $query = 'SELECT ' . $fields[$groupBy]['sql'] . ' AS group_label, ' . $aggregateSql . ' AS value'
            . ' FROM ' . $source['from'] . $whereSql
            . ' GROUP BY ' . $fields[$groupBy]['sql']
            . ' ORDER BY value ' . $direction . ', group_label ASC LIMIT ' . ($limit + 1);
        $rows = Database::fetchAll($query, $params);
        $truncated = count($rows) > $limit;
        if ($truncated) array_pop($rows);
        $dataColumns = [
            ['key' => 'group_label', 'label' => $fields[$groupBy]['label'], 'type' => $fields[$groupBy]['type']],
            ['key' => 'value', 'label' => strtoupper($aggregate) . ($measure !== '' ? ' ' . $fields[$measure]['label'] : ' records'), 'type' => 'number'],
        ];
        successResponse([
            'columns' => $dataColumns,
            'rows' => $rows,
            'truncated' => $truncated,
            'measure_label' => $dataColumns[1]['label'],
        ]);
    }

    $selectParts = [];
    $dataColumns = [];
    foreach ($columns as $columnKey) {
        $selectParts[] = $fields[$columnKey]['sql'] . ' AS `' . $columnKey . '`';
        $dataColumns[] = ['key' => $columnKey, 'label' => $fields[$columnKey]['label'], 'type' => $fields[$columnKey]['type']];
    }
    $sortKey = (string) ($input['sort'] ?? $columns[0]);
    if (!isset($fields[$sortKey])) $sortKey = $columns[0];
    $query = 'SELECT ' . implode(', ', $selectParts)
        . ' FROM ' . $source['from'] . $whereSql
        . ' ORDER BY ' . $fields[$sortKey]['sql'] . ' ' . $direction
        . ' LIMIT ' . ($limit + 1);
    $rows = Database::fetchAll($query, $params);
    $truncated = count($rows) > $limit;
    if ($truncated) array_pop($rows);

    successResponse([
        'columns' => $dataColumns,
        'rows' => $rows,
        'truncated' => $truncated,
    ]);
} catch (Throwable $e) {
    error_log('Report generation failed: ' . $e->getMessage());
    errorResponse('The report could not be generated. Check the selected fields and try again.', 500);
}
