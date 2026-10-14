<?php
/**
 * Orders (Transactions) API
 * CRUD operations for transactions table + refund processing
 */

require_once __DIR__ . '/common.php';

$method = $_SERVER['REQUEST_METHOD'];

/**
 * Check that all given fields are present and non-empty.
 */
function requireFields(array $data, array $fields): void
{
    foreach ($fields as $field) {
        if (!isset($data[$field]) || $data[$field] === '') {
            errorResponse("Field '$field' is required");
        }
    }
}

try {
    switch ($method) {
        case 'GET':
            if (isset($_GET['refunds'])) {
                $transactionId = (int) ($_GET['transaction_id'] ?? 0);
                $refunds = refundsTableExists()
                    ? Database::fetchAll(
                        "SELECT r.*, s.full_name AS processed_by_name
                         FROM transaction_refunds r
                         LEFT JOIN staff s ON s.staff_id = r.processed_by
                         WHERE r.transaction_id = ?
                         ORDER BY r.created_at DESC",
                        [$transactionId]
                    )
                    : [];
                successResponse($refunds);
            } elseif (isset($_GET['list'])) {
                $orders = Database::fetchAll(
                    "SELECT t.transaction_id as id, t.receipt_number as name, t.total_amount, t.status, t.created_at, u.full_name as customer_name
                     FROM transactions t
                     JOIN users u ON t.user_id = u.user_id
                     WHERE t.status != 'cancelled'
                     ORDER BY t.created_at DESC
                     LIMIT 50"
                );
                successResponse($orders);
            } elseif (isset($_GET['transaction_id'])) {
                $order = Database::fetchOne(
                    "SELECT t.*, u.full_name as customer_name, u.email as customer_email
                     FROM transactions t
                     JOIN users u ON t.user_id = u.user_id
                     WHERE t.transaction_id = ?",
                    [$_GET['transaction_id']]
                );
                if (!$order) errorResponse('Order not found', 404);

                // Get line items
                $items = Database::fetchAll(
                    "SELECT ti.*, s.name as service_name
                     FROM transaction_items ti
                     LEFT JOIN services s ON ti.service_id = s.service_id
                     WHERE ti.transaction_id = ?",
                    [$_GET['transaction_id']]
                );
                $order['items'] = $items;

                // Get refunds already processed
                $order['refunds'] = refundsTableExists()
                    ? Database::fetchAll(
                        "SELECT r.refund_id, r.amount, r.reason, r.notes, r.created_at,
                                s.full_name AS processed_by_name
                         FROM transaction_refunds r
                         LEFT JOIN staff s ON s.staff_id = r.processed_by
                         WHERE r.transaction_id = ?
                         ORDER BY r.created_at DESC",
                        [$_GET['transaction_id']]
                    )
                    : [];
                successResponse($order);
            } else {
                $orders = Database::fetchAll(
                    "SELECT t.*, u.full_name as customer_name
                     FROM transactions t
                     JOIN users u ON t.user_id = u.user_id
                     ORDER BY t.created_at DESC"
                );
                successResponse($orders);
            }
            break;

        case 'POST':
            // Process a refund against a completed transaction
            if (($input['action'] ?? '') === 'refund') {
                requirePermission('transactions.refund');
                requireFields($input, ['transaction_id', 'refund_amount']);

                $transactionId = (int) $input['transaction_id'];
                $requestedAmount = (float) $input['refund_amount'];

                if ($requestedAmount <= 0) {
                    errorResponse('Refund amount must be greater than zero');
                }

                $transaction = Database::fetchOne(
                    "SELECT * FROM transactions WHERE transaction_id = ?",
                    [$transactionId]
                );
                if (!$transaction) {
                    errorResponse('Transaction not found', 404);
                }

                $status = $transaction['status'] ?? 'pending';
                if ($status !== 'completed' && $status !== 'partially_refunded') {
                    errorResponse("Refunds can only be issued against a completed transaction (current status: {$status})", 400);
                }

                $totalAmount = (float) $transaction['total_amount'];
                $alreadyRefunded = transactionRefundedAmount($transactionId);
                $refundable = round($totalAmount - $alreadyRefunded, 2);

                if ($refundable <= 0) {
                    errorResponse('This transaction has already been fully refunded', 400);
                }

                $refundAmount = round($requestedAmount, 2);
                if ($refundAmount > $refundable) {
                    errorResponse(sprintf('Refund amount exceeds the refundable balance of GHS %.2f', $refundable), 400);
                }

                $remainingAfterRefund = round($totalAmount - ($alreadyRefunded + $refundAmount), 2);
                $newStatus = $remainingAfterRefund <= 0 ? 'refunded' : 'partially_refunded';

                Database::beginTransaction();
                try {
                    if (!refundsTableExists()) {
                        throw new RuntimeException('Refunds are unavailable: run database/migrations/004_transaction_refunds.sql first');
                    }

                    Database::insert(
                        "INSERT INTO transaction_refunds (transaction_id, amount, reason, notes, payment_method, processed_by)
                         VALUES (?, ?, ?, ?, ?, ?)",
                        [
                            $transactionId,
                            $refundAmount,
                            $input['reason'] ?? 'customer_request',
                            $input['notes'] ?? null,
                            $transaction['payment_method'] ?? null,
                            currentStaffId(),
                        ]
                    );

                    Database::execute(
                        "UPDATE transactions SET status = ?, updated_at = NOW() WHERE transaction_id = ?",
                        [$newStatus, $transactionId]
                    );

                    Database::commit();
                } catch (Exception $e) {
                    Database::rollback();
                    throw $e;
                }

                logAuditTrail(
                    'transaction_refund',
                    'transaction',
                    $transactionId,
                    [
                        'amount' => $refundAmount,
                        'reason' => $input['reason'] ?? 'customer_request',
                        'notes' => $input['notes'] ?? null,
                        'new_status' => $newStatus,
                        'refunded_before' => $alreadyRefunded,
                        'refunded_total' => $alreadyRefunded + $refundAmount,
                    ],
                    currentStaffId(),
                    'staff'
                );

                $updated = Database::fetchOne("SELECT * FROM transactions WHERE transaction_id = ?", [$transactionId]);
                $updated['refunded_amount'] = $alreadyRefunded + $refundAmount;
                $updated['refundable_amount'] = $remainingAfterRefund;
                successResponse($updated, 'Refund processed successfully');
            }

            // Refund a single line item (full or partial quantity)
            if (($input['action'] ?? '') === 'refund_item') {
                requirePermission('transactions.refund');
                requireFields($input, ['transaction_id', 'item_id', 'quantity']);

                $transactionId = (int) $input['transaction_id'];
                $itemId = (int) $input['item_id'];
                $quantity = (float) $input['quantity'];

                $item = Database::fetchOne(
                    "SELECT * FROM transaction_items WHERE item_id = ? AND transaction_id = ?",
                    [$itemId, $transactionId]
                );
                if (!$item) {
                    errorResponse('Line item not found on this transaction', 404);
                }

                $refundableQty = round((float) $item['quantity'] - refundItemQuantity($itemId), 2);
                if ($quantity <= 0 || $quantity > $refundableQty) {
                    errorResponse(sprintf('Refundable quantity for this item is %.2f', $refundableQty), 400);
                }

                $refundAmount = round($quantity * (float) $item['unit_price'], 2);
                $totalAmount = transactionTotal($transactionId);
                $alreadyRefunded = transactionRefundedAmount($transactionId);
                $refundable = round($totalAmount - $alreadyRefunded, 2);

                if ($refundAmount > $refundable) {
                    errorResponse(sprintf('Refund amount exceeds the refundable balance of GHS %.2f', $refundable), 400);
                }

                $remainingAfterRefund = round($totalAmount - ($alreadyRefunded + $refundAmount), 2);
                $newStatus = $remainingAfterRefund <= 0 ? 'refunded' : 'partially_refunded';

                if (!Database::tableExists('transaction_item_refunds')) {
                    errorResponse('Line-item refunds are unavailable: run database/migrations/004_transaction_refunds.sql first');
                }

                Database::beginTransaction();
                try {
                    if (!refundsTableExists()) {
                        throw new RuntimeException('Refunds are unavailable: run database/migrations/004_transaction_refunds.sql first');
                    }

                    $refundId = Database::insert(
                        "INSERT INTO transaction_refunds (transaction_id, amount, reason, notes, payment_method, processed_by)
                         VALUES (?, ?, ?, ?, ?, ?)",
                        [
                            $transactionId,
                            $refundAmount,
                            $input['reason'] ?? 'customer_request',
                            sprintf(
                                'Refund of %s x %s%s',
                                rtrim(rtrim(number_format($quantity, 2), '0'), '.'),
                                $item['description'] ?? 'item',
                                !empty($input['notes']) ? ' — ' . $input['notes'] : ''
                            ),
                            null,
                            currentStaffId(),
                        ]
                    );

                    Database::insert(
                        "INSERT INTO transaction_item_refunds (refund_id, item_id, quantity, amount)
                         VALUES (?, ?, ?, ?)",
                        [$refundId, $itemId, $quantity, $refundAmount]
                    );

                    Database::execute(
                        "UPDATE transactions SET status = ?, updated_at = NOW() WHERE transaction_id = ?",
                        [$newStatus, $transactionId]
                    );

                    Database::commit();
                } catch (Exception $e) {
                    Database::rollback();
                    throw $e;
                }

                logAuditTrail(
                    'transaction_refund',
                    'transaction',
                    $transactionId,
                    [
                        'item_id' => $itemId,
                        'quantity' => $quantity,
                        'amount' => $refundAmount,
                        'reason' => $input['reason'] ?? 'customer_request',
                        'new_status' => $newStatus,
                    ],
                    currentStaffId(),
                    'staff'
                );

                $updated = Database::fetchOne("SELECT * FROM transactions WHERE transaction_id = ?", [$transactionId]);
                $updated['refunded_amount'] = $alreadyRefunded + $refundAmount;
                $updated['refundable_amount'] = $remainingAfterRefund;
                successResponse($updated, 'Item refunded successfully');
            }

            // Create a new transaction/order
            requireFields($input, ['user_id', 'items']);

            $items = is_array($input['items'])
                ? $input['items']
                : json_decode((string) $input['items'], true);

            if (!is_array($items) || empty($items)) {
                errorResponse('Invalid items format');
            }

            $totalAmount = 0;
            $lineItems = [];
            foreach ($items as $item) {
                $quantity = (float) ($item['quantity'] ?? 0);
                $unitPrice = (float) ($item['unit_price'] ?? 0);
                if ($quantity <= 0) {
                    errorResponse('Each item needs a quantity greater than zero');
                }
                $total = $quantity * $unitPrice;
                $totalAmount += $total;
                $lineItems[] = [
                    'service_id' => !empty($item['service_id']) ? (int) $item['service_id'] : null,
                    'description' => $item['description'] ?? 'Service',
                    'quantity' => $quantity,
                    'unit_price' => $unitPrice,
                    'total_price' => round($total, 2),
                ];
            }
            $totalAmount = round($totalAmount, 2);

            $staffId = currentStaffId();

            Database::beginTransaction();
            try {
                $transactionId = Database::insert(
                    "INSERT INTO transactions (user_id, amount, total_amount, status, notes, receipt_number)
                     VALUES (?, ?, ?, ?, ?, ?)",
                    [
                        $input['user_id'],
                        $totalAmount,
                        $totalAmount,
                        $input['status'] ?? 'completed',
                        $input['notes'] ?? null,
                        generateReceiptNumber(),
                    ]
                );

                foreach ($lineItems as $item) {
                    Database::insert(
                        "INSERT INTO transaction_items (transaction_id, service_id, description, quantity, unit_price, total_price)
                         VALUES (?, ?, ?, ?, ?, ?)",
                        [$transactionId, $item['service_id'], $item['description'], $item['quantity'], $item['unit_price'], $item['total_price']]
                    );
                }

                Database::commit();
            } catch (Exception $e) {
                Database::rollback();
                throw $e;
            }

            $order = Database::fetchOne(
                "SELECT t.*, u.full_name as customer_name FROM transactions t JOIN users u ON t.user_id = u.user_id WHERE t.transaction_id = ?",
                [$transactionId]
            );
            successResponse($order, 'Order created successfully');
            break;

        case 'PUT':
            requireFields($input, ['transaction_id']);

            $updates = [];
            $params = [];

            $allowedFields = ['status', 'notes'];
            foreach ($allowedFields as $field) {
                if (isset($input[$field])) {
                    $updates[] = "$field = ?";
                    $params[] = $input[$field];
                }
            }

            if (empty($updates)) errorResponse('No fields to update');

            $params[] = $input['transaction_id'];
            Database::execute("UPDATE transactions SET " . implode(', ', $updates) . " WHERE transaction_id = ?", $params);

            $order = Database::fetchOne("SELECT * FROM transactions WHERE transaction_id = ?", [$input['transaction_id']]);
            successResponse($order, 'Order updated successfully');
            break;

        case 'DELETE':
            requireFields($input, ['transaction_id']);

            // Soft delete - mark as cancelled (refunded transactions stay untouched)
            Database::execute(
                "UPDATE transactions SET status = 'cancelled', updated_at = NOW()
                 WHERE transaction_id = ? AND status IN ('pending', 'completed')",
                [$input['transaction_id']]
            );
            successResponse(null, 'Order cancelled successfully');
            break;

        default:
            errorResponse('Method not allowed', 405);
    }
} catch (Exception $e) {
    errorResponse($e->getMessage(), 500);
}
