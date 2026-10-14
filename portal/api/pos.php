<?php
/**
 * POS & Finance API
 *
 * GET  ?catalog=1            services, time packages, products and stations for the register
 * GET  ?customer_id=N        wallet balance, amount owed and credit limit for the selected customer
 * GET  ?dashboard=1          totals and the latest sales
 * GET  ?receipt=N            one sale with its line items (for reprinting)
 * GET  [?staff_id=N]         sale history
 * POST {items:[...], ...}    record a cart sale (cash, mobile money, card, bank, wallet or pay later)
 * POST {action:"assign"}     assign a POS terminal to a staff member
 * PUT / DELETE               update or cancel a sale record
 */

require_once __DIR__ . '/common.php';
require_once __DIR__ . '/session-lifecycle.php';
require_once __DIR__ . '/../../includes/billing.php';

$method = $_SERVER['REQUEST_METHOD'];
$input = $GLOBALS['request_input'] ?? ($_POST ?: ($_GET ?: []));
if (!is_array($input)) {
    $input = [];
}

if (!Database::tableExists('pos_transactions') || !Database::tableExists('pos_terminals') || !Database::tableExists('device_usage_logs') || !Database::tableExists('audit_logs')) {
    errorResponse('POS tables are not initialized. Please run the database schema update first.', 500);
}

const POS_PAYMENT_METHODS = ['cash', 'mobile_money', 'card', 'bank_transfer', 'wallet', 'account'];
const POS_SERVICE_TYPES = ['internet', 'gaming', 'printing', 'retail', 'other'];

function posServiceTypeForCategory(string $category): string
{
    return match ($category) {
        'internet', 'typing', 'consultation' => 'internet',
        'gaming' => 'gaming',
        'printing', 'scanning' => 'printing',
        default => 'other',
    };
}

/**
 * Find or create the customer for a sale.
 */
function posResolveCustomer(array $input): array
{
    $customerId = !empty($input['customer_id']) ? (int) $input['customer_id'] : null;
    $customerName = trim((string) ($input['customer_name'] ?? ''));
    if ($customerId) {
        $customer = Database::fetchOne('SELECT * FROM users WHERE user_id = ? AND is_active = 1 LIMIT 1', [$customerId]);
        if (!$customer) {
            throw new InvalidArgumentException('The selected customer is inactive or no longer exists.');
        }
        return $customer;
    }
    if ($customerName === '') {
        $customerName = 'Walk-in Customer';
    }
    $customer = Database::fetchOne(
        'SELECT * FROM users WHERE (LOWER(full_name) = LOWER(?) OR phone = ? OR email = ?) AND is_active = 1 LIMIT 1',
        [$customerName, $customerName, $customerName]
    );
    if ($customer) {
        return $customer;
    }
    $newId = Database::insert(
        "INSERT INTO users (full_name, email, phone, membership_tier, is_active) VALUES (?, ?, ?, 'standard', 1)",
        [$customerName, !empty($input['email']) ? trim((string) $input['email']) : null, !empty($input['phone']) ? trim((string) $input['phone']) : null]
    );
    return Database::fetchOne('SELECT * FROM users WHERE user_id = ?', [$newId]);
}

/**
 * Price the cart from the catalog. Client prices are only used for custom items.
 */
function posPriceItems(array $items): array
{
    if ($items === [] || count($items) > 50) {
        throw new InvalidArgumentException('Add between 1 and 50 items to the sale.');
    }
    $lines = [];
    foreach ($items as $item) {
        if (!is_array($item)) {
            throw new InvalidArgumentException('Invalid cart item.');
        }
        $quantity = round((float) ($item['quantity'] ?? 1), 2);
        if ($quantity <= 0 || $quantity > 10000) {
            throw new InvalidArgumentException('Item quantities must be greater than zero.');
        }
        $type = (string) ($item['type'] ?? 'custom');

        if ($type === 'product') {
            $product = Database::fetchOne('SELECT * FROM products WHERE product_id = ? AND is_active = 1', [(int) ($item['product_id'] ?? 0)]);
            if (!$product) {
                throw new InvalidArgumentException('A product in the cart is no longer available.');
            }
            if ((int) $quantity != $quantity) {
                throw new InvalidArgumentException("{$product['name']} must be sold in whole units.");
            }
            $lines[] = [
                'type' => 'product', 'product_id' => (int) $product['product_id'], 'service_id' => null,
                'name' => $product['name'], 'quantity' => $quantity, 'unit_price' => (float) $product['selling_price'],
                'service_type' => 'retail', 'minutes' => 0,
            ];
            continue;
        }

        if ($type === 'service' && !empty($item['service_id'])) {
            $service = Database::fetchOne('SELECT * FROM services WHERE service_id = ? AND is_active = 1', [(int) $item['service_id']]);
            if (!$service) {
                throw new InvalidArgumentException('A service in the cart is no longer available.');
            }
            $minutes = 0;
            if ($service['price_unit'] === 'per_hour') {
                $minutes = (int) round($quantity * 60);
            } elseif ($service['price_unit'] === 'per_minute') {
                $minutes = (int) $quantity;
            }
            $lines[] = [
                'type' => 'service', 'product_id' => null, 'service_id' => (int) $service['service_id'],
                'name' => $service['name'], 'quantity' => $quantity, 'unit_price' => (float) $service['base_price'],
                'service_type' => posServiceTypeForCategory($service['category']), 'minutes' => $minutes,
            ];
            continue;
        }

        if ($type === 'plan' && !empty($item['plan_id'])) {
            $plan = Database::fetchOne('SELECT * FROM pricing_plans WHERE plan_id = ? AND is_active = 1', [(int) $item['plan_id']]);
            if (!$plan) {
                throw new InvalidArgumentException('A time package in the cart is no longer available.');
            }
            $lines[] = [
                'type' => 'plan', 'product_id' => null, 'service_id' => null,
                'name' => $plan['name'], 'quantity' => $quantity, 'unit_price' => (float) $plan['price'],
                'service_type' => stripos($plan['name'], 'gam') !== false ? 'gaming' : ((int) $plan['duration_minutes'] > 0 ? 'internet' : 'printing'),
                'minutes' => (int) round((int) $plan['duration_minutes'] * $quantity),
            ];
            continue;
        }

        $name = trim((string) ($item['name'] ?? ''));
        $unitPrice = round((float) ($item['unit_price'] ?? 0), 2);
        if ($name === '' || mb_strlen($name) > 120 || $unitPrice < 0 || $unitPrice > 100000) {
            throw new InvalidArgumentException('Custom items need a name and a valid price.');
        }
        $serviceType = in_array($item['service_type'] ?? '', POS_SERVICE_TYPES, true) ? $item['service_type'] : 'other';
        $lines[] = [
            'type' => 'custom', 'product_id' => null, 'service_id' => null,
            'name' => $name, 'quantity' => $quantity, 'unit_price' => $unitPrice,
            'service_type' => $serviceType, 'minutes' => max(0, (int) ($item['minutes'] ?? 0)),
        ];
    }
    foreach ($lines as &$line) {
        $line['total'] = round($line['unit_price'] * $line['quantity'], 2);
    }
    return $lines;
}

/**
 * Start a timed session on a station, or extend the customer's current one.
 */
function posStartOrExtendSession(int $customerId, int $stationId, int $staffId, int $minutes, string $label): int
{
    $station = Database::fetchOne('SELECT station_id, status FROM stations WHERE station_id = ? FOR UPDATE', [$stationId]);
    if (!$station) {
        throw new InvalidArgumentException('The selected station does not exist.');
    }
    if ($station['status'] === 'maintenance') {
        throw new InvalidArgumentException('The selected station is under maintenance.');
    }
    $own = Database::fetchOne(
        "SELECT session_id FROM sessions WHERE user_id = ? AND station_id = ? AND status = 'active' ORDER BY started_at DESC LIMIT 1",
        [$customerId, $stationId]
    );
    if ($own) {
        Database::execute(
            "UPDATE sessions SET scheduled_end_at = DATE_ADD(GREATEST(COALESCE(scheduled_end_at, NOW()), NOW()), INTERVAL ? MINUTE)
             WHERE session_id = ? AND status = 'active'",
            [$minutes, $own['session_id']]
        );
        return (int) $own['session_id'];
    }
    $occupied = Database::fetchOne(
        "SELECT u.full_name FROM sessions s JOIN users u ON u.user_id = s.user_id
         WHERE s.station_id = ? AND s.status = 'active' LIMIT 1",
        [$stationId]
    );
    if ($occupied) {
        throw new InvalidArgumentException("That station is in use by {$occupied['full_name']}. Choose another station.");
    }
    $sessionId = Database::insert(
        "INSERT INTO sessions (user_id, station_id, staff_id, status, notes, started_at, scheduled_end_at, created_at)
         VALUES (?, ?, ?, 'active', ?, NOW(), DATE_ADD(NOW(), INTERVAL ? MINUTE), NOW())",
        [$customerId, $stationId, $staffId, 'POS sale - ' . $label, $minutes]
    );
    Database::execute("UPDATE stations SET status = 'occupied' WHERE station_id = ?", [$stationId]);
    return $sessionId;
}

function posRecordCartSale(array $input, int $staffId): array
{
    $paymentMethod = (string) ($input['payment_method'] ?? 'cash');
    if (!in_array($paymentMethod, POS_PAYMENT_METHODS, true)) {
        throw new InvalidArgumentException('Choose a valid payment method.');
    }
    $lines = posPriceItems($input['items']);
    $subtotal = round(array_sum(array_column($lines, 'total')), 2);
    $discount = round(max(0, min((float) ($input['discount_amount'] ?? 0), $subtotal)), 2);
    $total = round($subtotal - $discount, 2);
    $stationId = !empty($input['station_id']) ? (int) $input['station_id'] : null;
    $minutes = array_sum(array_column($lines, 'minutes'));
    if (isset($input['usage_minutes']) && (int) $input['usage_minutes'] > 0 && $minutes === 0) {
        $minutes = (int) $input['usage_minutes'];
    }
    $tendered = isset($input['amount_tendered']) && $input['amount_tendered'] !== '' ? round((float) $input['amount_tendered'], 2) : null;
    if ($paymentMethod === 'cash' && $tendered !== null && $tendered + 0.001 < $total) {
        throw new InvalidArgumentException('Cash received is less than the total due.');
    }
    $reference = isset($input['reference']) && trim((string) $input['reference']) !== '' ? mb_substr(trim((string) $input['reference']), 0, 100) : null;

    $types = array_values(array_unique(array_column($lines, 'service_type')));
    $serviceType = count($types) === 1 ? $types[0] : (in_array('gaming', $types, true) ? 'gaming' : (in_array('internet', $types, true) ? 'internet' : 'other'));
    $timed = $minutes > 0 && (in_array('internet', $types, true) || in_array('gaming', $types, true));
    if ($timed && !$stationId) {
        throw new InvalidArgumentException('Choose a station for the timed internet/gaming time in this sale.');
    }

    $pdo = Database::getInstance();
    $pdo->beginTransaction();
    try {
        $customer = posResolveCustomer($input);
        $customerId = (int) $customer['user_id'];
        $payLater = $paymentMethod === 'account';

        if ($payLater) {
            if ((int) $customer['user_id'] && stripos($customer['full_name'], 'walk-in') !== false) {
                throw new InvalidArgumentException('Select a registered customer to sell on account.');
            }
            billingAssertCanCharge($customerId, $total);
        }

        foreach ($lines as $line) {
            if ($line['type'] === 'product') {
                $stock = (int) Database::fetchColumn('SELECT stock_quantity FROM products WHERE product_id = ? FOR UPDATE', [$line['product_id']]);
                if ($stock < $line['quantity']) {
                    throw new InvalidArgumentException("Only $stock × {$line['name']} left in stock.");
                }
            }
        }

        $sessionId = ($timed && $stationId) ? posStartOrExtendSession($customerId, $stationId, $staffId, $minutes, ucfirst($serviceType)) : null;

        $receipt = generateReceiptNumber();
        $ledgerId = Database::insert(
            "INSERT INTO transactions (user_id, session_id, payment_method, amount, discount_amount, total_amount, currency, status, payment_reference, receipt_number, notes, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())",
            [
                $customerId, $sessionId, $paymentMethod, $subtotal, $discount, $total, siteSetting('currency', 'GHS'),
                $payLater ? 'pending' : 'completed', $reference ?? $receipt, $receipt,
                $payLater ? 'POS sale on account' : 'POS sale',
            ]
        );
        foreach ($lines as $line) {
            Database::execute(
                'INSERT INTO transaction_items (transaction_id, service_id, description, quantity, unit_price, total_price) VALUES (?, ?, ?, ?, ?, ?)',
                [$ledgerId, $line['service_id'], mb_substr($line['name'], 0, 255), $line['quantity'], $line['unit_price'], $line['total']]
            );
            if ($line['type'] === 'product') {
                Database::execute('UPDATE products SET stock_quantity = stock_quantity - ? WHERE product_id = ?', [(int) $line['quantity'], $line['product_id']]);
                Database::execute(
                    "INSERT INTO inventory_movements (product_id, movement_type, quantity, reference_id, reference_type, notes, created_by)
                     VALUES (?, 'sale', ?, ?, 'transaction', ?, ?)",
                    [$line['product_id'], -(int) $line['quantity'], $ledgerId, "POS $receipt", $staffId]
                );
            }
        }

        $walletBalance = null;
        if ($paymentMethod === 'wallet' && $total > 0) {
            $walletBalance = billingChargeWallet($customerId, $total, "POS sale $receipt", $ledgerId)['balance'];
        }

        if (!$payLater) {
            Database::execute(
                'UPDATE users SET total_visits = total_visits + 1, total_spent = total_spent + ? WHERE user_id = ?',
                [$total, $customerId]
            );
        } else {
            Database::execute('UPDATE users SET total_visits = total_visits + 1 WHERE user_id = ?', [$customerId]);
        }

        $posId = Database::insert(
            "INSERT INTO pos_transactions (staff_id, station_id, user_id, customer_name, service_type, amount, discount_amount, amount_tendered,
                                           payment_method, status, receipt_number, ledger_transaction_id, usage_minutes, notes, items, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())",
            [
                $staffId, $stationId, $customerId, $customer['full_name'], $serviceType, $total, $discount, $tendered,
                $paymentMethod, $payLater ? 'pending' : 'paid', $receipt, $ledgerId, $minutes,
                $reference ? "Ref: $reference" : null,
                json_encode(array_map(fn($l) => ['name' => $l['name'], 'quantity' => $l['quantity'], 'unit_price' => $l['unit_price'], 'total' => $l['total']], $lines)),
            ]
        );

        if ($stationId && $minutes > 0) {
            Database::execute(
                "INSERT INTO device_usage_logs (station_id, staff_id, customer_name, service_type, usage_minutes, total_amount, status, notes, started_at, ended_at, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, 'active', ?, NOW(), DATE_ADD(NOW(), INTERVAL ? MINUTE), NOW())",
                [$stationId, $staffId, $customer['full_name'], in_array($serviceType, ['internet', 'gaming', 'printing'], true) ? $serviceType : 'other', $minutes, $total, "POS $receipt", $minutes]
            );
        }

        logAuditTrail('pos_sale_recorded', 'pos_transaction', $posId, [
            'receipt' => $receipt, 'customer_id' => $customerId, 'total' => $total,
            'payment_method' => $paymentMethod, 'station_id' => $stationId, 'minutes' => $minutes, 'items' => count($lines),
        ], $staffId, 'staff');

        $pdo->commit();
    } catch (Throwable $error) {
        $pdo->rollBack();
        throw $error;
    }

    return [
        'transaction_id' => $posId,
        'ledger_transaction_id' => $ledgerId,
        'receipt_number' => $receipt,
        'customer_id' => $customerId,
        'customer_name' => $customer['full_name'],
        'station_id' => $stationId,
        'session_id' => $sessionId,
        'usage_minutes' => $minutes,
        'items' => $lines,
        'subtotal' => $subtotal,
        'discount' => $discount,
        'total' => $total,
        'payment_method' => $paymentMethod,
        'status' => $payLater ? 'pending' : 'paid',
        'amount_tendered' => $tendered,
        'change' => $tendered !== null ? round(max(0, $tendered - $total), 2) : null,
        'wallet_balance' => $walletBalance,
        'reference' => $reference,
        'created_at' => date('Y-m-d H:i:s'),
    ];
}

try {
    expireScheduledSessions();

    switch ($method) {
        case 'GET':
            requirePermission('pos.view');

            if (isset($_GET['catalog'])) {
                successResponse([
                    'services' => Database::fetchAll(
                        "SELECT service_id, name, category, icon, base_price, price_unit, requires_station
                         FROM services WHERE is_active = 1 ORDER BY category, name"
                    ),
                    'plans' => Database::fetchAll(
                        'SELECT plan_id, name, description, price, duration_minutes FROM pricing_plans WHERE is_active = 1 ORDER BY sort_order, price'
                    ),
                    'products' => Database::fetchAll(
                        'SELECT product_id, name, category, sku, barcode, selling_price, stock_quantity FROM products WHERE is_active = 1 ORDER BY category, name'
                    ),
                    'stations' => Database::fetchAll(
                        "SELECT s.station_id, s.station_code, s.name, s.location, s.status,
                                (SELECT u.full_name FROM sessions se JOIN users u ON u.user_id = se.user_id
                                 WHERE se.station_id = s.station_id AND se.status = 'active' ORDER BY se.started_at DESC LIMIT 1) AS active_customer,
                                (SELECT se.user_id FROM sessions se
                                 WHERE se.station_id = s.station_id AND se.status = 'active' ORDER BY se.started_at DESC LIMIT 1) AS active_user_id,
                                (SELECT se.scheduled_end_at FROM sessions se
                                 WHERE se.station_id = s.station_id AND se.status = 'active' ORDER BY se.started_at DESC LIMIT 1) AS session_ends_at,
                                (SELECT TIMESTAMPDIFF(MINUTE, NOW(), se.scheduled_end_at) FROM sessions se
                                 WHERE se.station_id = s.station_id AND se.status = 'active' ORDER BY se.started_at DESC LIMIT 1) AS minutes_left
                         FROM stations s ORDER BY s.station_code"
                    ),
                ]);
            }

            if (isset($_GET['customer_id'])) {
                $customerId = (int) $_GET['customer_id'];
                $customer = Database::fetchOne('SELECT user_id, full_name, phone, email, membership_tier FROM users WHERE user_id = ?', [$customerId]);
                if (!$customer) {
                    errorResponse('Customer not found', 404);
                }
                $account = billingEnabled() ? Database::fetchOne('SELECT balance, credit_limit, status FROM billing_accounts WHERE user_id = ?', [$customerId]) : null;
                successResponse([
                    'customer' => $customer,
                    'wallet_balance' => (float) ($account['balance'] ?? 0),
                    'credit_limit' => (float) ($account['credit_limit'] ?? 0),
                    'account_status' => $account['status'] ?? 'active',
                    'owed' => billingEnabled() ? billingOutstanding($customerId)['total'] : 0,
                ]);
            }

            if (isset($_GET['receipt'])) {
                $sale = Database::fetchOne(
                    'SELECT pt.*, st.station_code, st.name AS station_name, s.full_name AS staff_name
                     FROM pos_transactions pt
                     LEFT JOIN stations st ON st.station_id = pt.station_id
                     LEFT JOIN staff s ON s.staff_id = pt.staff_id
                     WHERE pt.pos_transaction_id = ?',
                    [(int) $_GET['receipt']]
                );
                if (!$sale) {
                    errorResponse('Sale not found', 404);
                }
                $sale['items'] = json_decode((string) ($sale['items'] ?? ''), true)
                    ?: [['name' => ucfirst($sale['service_type']) . ' service', 'quantity' => 1, 'unit_price' => (float) $sale['amount'], 'total' => (float) $sale['amount']]];
                successResponse($sale);
            }

            if (isset($_GET['dashboard'])) {
                $staffFilter = isset($_GET['mine']) ? ' AND staff_id = ' . (int) $_SESSION['user_id'] : '';
                $stats = [
                    'total_revenue' => (float) Database::fetchColumn("SELECT COALESCE(SUM(amount), 0) FROM pos_transactions WHERE status = 'paid'$staffFilter"),
                    'today_revenue' => (float) Database::fetchColumn("SELECT COALESCE(SUM(amount), 0) FROM pos_transactions WHERE status = 'paid' AND DATE(created_at) = CURDATE()$staffFilter"),
                    'today_sales' => (int) Database::fetchColumn("SELECT COUNT(*) FROM pos_transactions WHERE status IN ('paid','pending') AND DATE(created_at) = CURDATE()$staffFilter"),
                    'today_on_account' => (float) Database::fetchColumn("SELECT COALESCE(SUM(amount), 0) FROM pos_transactions WHERE status = 'pending' AND DATE(created_at) = CURDATE()$staffFilter"),
                    'today_by_method' => Database::fetchAll(
                        "SELECT payment_method, COALESCE(SUM(amount), 0) AS total, COUNT(*) AS count FROM pos_transactions
                         WHERE status IN ('paid','pending') AND DATE(created_at) = CURDATE()$staffFilter GROUP BY payment_method"
                    ),
                    'active_staff' => (int) Database::fetchColumn('SELECT COUNT(*) FROM staff WHERE is_on_duty = 1 AND is_active = 1'),
                    'active_sessions' => (int) Database::fetchColumn("SELECT COUNT(*) FROM sessions WHERE status = 'active'"),
                ];
                $transactions = Database::fetchAll(
                    "SELECT pt.pos_transaction_id, pt.customer_name, pt.service_type, pt.amount, pt.payment_method, pt.status,
                            pt.receipt_number, pt.usage_minutes, pt.created_at, s.full_name AS staff_name, st.station_code
                     FROM pos_transactions pt
                     LEFT JOIN staff s ON s.staff_id = pt.staff_id
                     LEFT JOIN stations st ON st.station_id = pt.station_id
                     WHERE 1 = 1" . str_replace('staff_id', 'pt.staff_id', $staffFilter) . "
                     ORDER BY pt.created_at DESC
                     LIMIT 15"
                );
                successResponse(['stats' => $stats, 'transactions' => $transactions]);
            }

            $staffId = isset($_GET['staff_id']) ? (int) $_GET['staff_id'] : null;
            $rows = Database::fetchAll(
                'SELECT pt.*, s.full_name AS staff_name, st.station_code, st.name AS station_name
                 FROM pos_transactions pt
                 LEFT JOIN staff s ON s.staff_id = pt.staff_id
                 LEFT JOIN stations st ON st.station_id = pt.station_id'
                . ($staffId ? ' WHERE pt.staff_id = ?' : '')
                . ' ORDER BY pt.created_at DESC LIMIT 100',
                $staffId ? [$staffId] : []
            );
            successResponse($rows);
            break;

        case 'POST':
            requirePermission('pos.manage');

            if (($input['action'] ?? null) === 'assign') {
                $staffId = (int) ($input['staff_id'] ?? 0);
                $terminalName = trim((string) ($input['terminal_name'] ?? ''));
                if ($staffId <= 0 || $terminalName === '') {
                    errorResponse('staff_id and terminal_name are required');
                }
                $stationId = !empty($input['station_id']) ? (int) $input['station_id'] : null;
                $serviceFocus = in_array(($input['service_focus'] ?? 'mixed'), ['internet', 'gaming', 'printing', 'mixed'], true) ? $input['service_focus'] : 'mixed';
                $existing = Database::fetchOne('SELECT pos_terminal_id FROM pos_terminals WHERE staff_id = ?', [$staffId]);
                if ($existing) {
                    Database::execute(
                        'UPDATE pos_terminals SET station_id = ?, terminal_name = ?, service_focus = ?, is_active = 1, updated_at = NOW() WHERE staff_id = ?',
                        [$stationId, $terminalName, $serviceFocus, $staffId]
                    );
                } else {
                    Database::execute(
                        'INSERT INTO pos_terminals (staff_id, station_id, terminal_name, service_focus, is_active, assigned_at) VALUES (?, ?, ?, ?, 1, NOW())',
                        [$staffId, $stationId, $terminalName, $serviceFocus]
                    );
                }
                logAuditTrail('pos_terminal_assigned', 'pos_terminal', $staffId, ['terminal_name' => $terminalName, 'station_id' => $stationId, 'service_focus' => $serviceFocus]);
                successResponse(null, 'POS terminal assigned to staff');
            }

            $staffId = ($_SESSION['user_type'] ?? '') === 'staff' ? (int) $_SESSION['user_id'] : 0;
            if ($staffId <= 0) {
                errorResponse('Staff context is required to record a POS sale.', 403);
            }

            // Legacy single-amount sale: convert to a one-line cart.
            if (empty($input['items']) || !is_array($input['items'])) {
                if (!isset($input['amount']) || (float) $input['amount'] <= 0) {
                    errorResponse('Add items to the sale (or provide an amount).');
                }
                $serviceType = in_array($input['service_type'] ?? '', POS_SERVICE_TYPES, true) ? $input['service_type'] : 'other';
                $input['items'] = [[
                    'type' => 'custom',
                    'name' => ucfirst($serviceType) . ' service',
                    'quantity' => 1,
                    'unit_price' => (float) $input['amount'],
                    'service_type' => $serviceType,
                    'minutes' => in_array($serviceType, ['internet', 'gaming'], true) ? (int) ($input['usage_minutes'] ?? 0) : 0,
                ]];
            }

            $sale = posRecordCartSale($input, $staffId);
            $message = $sale['status'] === 'pending'
                ? 'Sale recorded on the customer\'s account.'
                : 'Sale complete' . ($sale['change'] ? '. Change: ' . number_format($sale['change'], 2) : '.');
            successResponse($sale, $message);
            break;

        case 'PUT':
            requirePermission('pos.manage');
            $transactionId = (int) ($input['transaction_id'] ?? 0);
            if ($transactionId <= 0) {
                errorResponse('transaction_id is required');
            }
            $status = (string) ($input['status'] ?? 'paid');
            if (!in_array($status, ['pending', 'paid', 'cancelled', 'refunded'], true)) {
                errorResponse('Invalid status');
            }
            $amount = isset($input['amount']) ? (float) $input['amount'] : null;
            $sql = 'UPDATE pos_transactions SET status = ?';
            $params = [$status];
            if ($amount !== null) {
                $sql .= ', amount = ?';
                $params[] = $amount;
            }
            $params[] = $transactionId;
            Database::execute($sql . ' WHERE pos_transaction_id = ?', $params);
            logAuditTrail('pos_sale_updated', 'pos_transaction', $transactionId, ['status' => $status, 'amount' => $amount]);
            successResponse(null, 'POS transaction updated successfully');
            break;

        case 'DELETE':
            requirePermission('pos.manage');
            $transactionId = (int) ($input['transaction_id'] ?? 0);
            Database::execute("UPDATE pos_transactions SET status = 'cancelled' WHERE pos_transaction_id = ?", [$transactionId]);
            logAuditTrail('pos_sale_cancelled', 'pos_transaction', $transactionId, []);
            successResponse(null, 'POS sale cancelled successfully');
            break;

        default:
            errorResponse('Method not allowed', 405);
    }
} catch (InvalidArgumentException $e) {
    errorResponse($e->getMessage(), 422);
} catch (Throwable $e) {
    error_log('POS API error: ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
    errorResponse('The sale could not be recorded. ' . (($GLOBALS['config']['app']['debug'] ?? false) ? $e->getMessage() : 'Please try again.'), 500);
}
