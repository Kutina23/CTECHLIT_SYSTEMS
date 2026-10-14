<?php
/**
 * CTECHLIT & SYSTEMS — Customer Billing API
 *
 * GET  ?summary=1                       totals, overdue invoices, top balances
 * GET  ?accounts=1[&search=]            customers with wallet balance and amount owed
 * GET  ?user_id=N                       account overview (wallet, unbilled, invoices, ledger)
 * GET  ?invoices=1[&status=&user_id=]   invoice list (status may be "overdue")
 * GET  ?invoice_id=N                    invoice with items and payments
 * POST action=topup|adjust|update_account|create_invoice|record_payment|void_invoice|settle_wallet
 */

require_once __DIR__ . '/common.php';
require_once __DIR__ . '/../../includes/billing.php';

if (!billingEnabled()) {
    errorResponse('Billing tables are missing. Run database/migrations/018_device_commands_billing_pos.php.', 500);
}

$method = $_SERVER['REQUEST_METHOD'];

function billingRequireUser(array $input): int
{
    $userId = (int) ($input['user_id'] ?? 0);
    if ($userId <= 0) {
        throw new InvalidArgumentException("Field 'user_id' is required.");
    }
    return $userId;
}

try {
    if ($method === 'GET') {
        requirePermission('billing.view');

        if (isset($_GET['invoice_id'])) {
            successResponse(billingInvoice((int) $_GET['invoice_id']));
        }

        if (isset($_GET['user_id'])) {
            successResponse(billingAccountOverview((int) $_GET['user_id']));
        }

        if (isset($_GET['invoices'])) {
            $where = [];
            $params = [];
            $status = (string) ($_GET['status'] ?? '');
            if ($status === 'overdue') {
                $where[] = "i.status IN ('issued','partially_paid') AND i.due_date < CURDATE()";
            } elseif (in_array($status, ['draft', 'issued', 'partially_paid', 'paid', 'void'], true)) {
                $where[] = 'i.status = ?';
                $params[] = $status;
            } elseif ($status === 'open') {
                $where[] = "i.status IN ('issued','partially_paid')";
            }
            if (!empty($_GET['user_id'])) {
                $where[] = 'i.user_id = ?';
                $params[] = (int) $_GET['user_id'];
            }
            if (trim((string) ($_GET['search'] ?? '')) !== '') {
                $like = '%' . addcslashes(trim((string) $_GET['search']), '%_\\') . '%';
                $where[] = '(i.invoice_number LIKE ? OR u.full_name LIKE ? OR u.phone LIKE ?)';
                array_push($params, $like, $like, $like);
            }
            foreach (['from' => '>=', 'to' => '<='] as $key => $operator) {
                $date = (string) ($_GET[$key] ?? '');
                if (preg_match('/\A\d{4}-\d{2}-\d{2}\z/', $date)) {
                    $where[] = "i.issue_date $operator ?";
                    $params[] = $date;
                }
            }
            $invoices = Database::fetchAll(
                'SELECT i.invoice_id, i.invoice_number, i.user_id, u.full_name AS customer_name, i.issue_date, i.due_date,
                        i.total_amount, i.amount_paid, (i.total_amount - i.amount_paid) AS balance_due, '
                . billingInvoiceStatusSql() . ' AS display_status
                 FROM invoices i JOIN users u ON u.user_id = i.user_id'
                . ($where ? ' WHERE ' . implode(' AND ', $where) : '')
                . ' ORDER BY i.invoice_id DESC LIMIT 200',
                $params
            );
            successResponse($invoices);
        }

        if (isset($_GET['accounts'])) {
            $search = trim((string) ($_GET['search'] ?? ''));
            $params = [];
            $filter = '';
            if ($search !== '') {
                $like = '%' . addcslashes($search, '%_\\') . '%';
                $filter = 'WHERE u.full_name LIKE ? OR u.phone LIKE ? OR u.email LIKE ? OR ba.account_number LIKE ?';
                $params = [$like, $like, $like, $like];
            }
            $accounts = Database::fetchAll(
                "SELECT u.user_id, u.full_name, u.phone, u.email, u.membership_tier, u.is_active,
                        ba.account_number, COALESCE(ba.balance, 0) AS balance, COALESCE(ba.credit_limit, 0) AS credit_limit,
                        COALESCE(ba.status, 'active') AS account_status,
                        COALESCE(un.unbilled, 0) AS unbilled,
                        COALESCE(inv.invoiced, 0) AS invoiced,
                        COALESCE(un.unbilled, 0) + COALESCE(inv.invoiced, 0) AS owed,
                        COALESCE(inv.overdue, 0) AS overdue
                 FROM users u
                 LEFT JOIN billing_accounts ba ON ba.user_id = u.user_id
                 LEFT JOIN (
                     SELECT user_id, SUM(total_amount) AS unbilled FROM transactions
                     WHERE status = 'pending' AND invoice_id IS NULL GROUP BY user_id
                 ) un ON un.user_id = u.user_id
                 LEFT JOIN (
                     SELECT user_id, SUM(total_amount - amount_paid) AS invoiced,
                            SUM(CASE WHEN due_date < CURDATE() THEN total_amount - amount_paid ELSE 0 END) AS overdue
                     FROM invoices WHERE status IN ('issued','partially_paid') GROUP BY user_id
                 ) inv ON inv.user_id = u.user_id
                 $filter
                 ORDER BY owed DESC, u.full_name ASC
                 LIMIT 300",
                $params
            );
            successResponse($accounts);
        }

        $summary = [
            'wallet_total' => (float) Database::fetchColumn('SELECT COALESCE(SUM(balance), 0) FROM billing_accounts'),
            'unbilled_total' => (float) Database::fetchColumn("SELECT COALESCE(SUM(total_amount), 0) FROM transactions WHERE status = 'pending' AND invoice_id IS NULL"),
            'open_invoices' => (int) Database::fetchColumn("SELECT COUNT(*) FROM invoices WHERE status IN ('issued','partially_paid')"),
            'receivable_total' => (float) Database::fetchColumn("SELECT COALESCE(SUM(total_amount - amount_paid), 0) FROM invoices WHERE status IN ('issued','partially_paid')"),
            'overdue_total' => (float) Database::fetchColumn("SELECT COALESCE(SUM(total_amount - amount_paid), 0) FROM invoices WHERE status IN ('issued','partially_paid') AND due_date < CURDATE()"),
            'overdue_count' => (int) Database::fetchColumn("SELECT COUNT(*) FROM invoices WHERE status IN ('issued','partially_paid') AND due_date < CURDATE()"),
            'collected_this_month' => (float) Database::fetchColumn(
                "SELECT COALESCE(SUM(amount), 0) FROM invoice_payments WHERE created_at >= DATE_FORMAT(CURDATE(), '%Y-%m-01')"
            ) + (float) Database::fetchColumn(
                "SELECT COALESCE(SUM(amount), 0) FROM billing_ledger WHERE entry_type = 'topup' AND created_at >= DATE_FORMAT(CURDATE(), '%Y-%m-01')"
            ),
        ];
        successResponse($summary);
    }

    if ($method !== 'POST') {
        errorResponse('Method not allowed', 405);
    }

    requirePermission('billing.manage');
    $action = (string) ($input['action'] ?? '');
    $reference = isset($input['reference']) && trim((string) $input['reference']) !== '' ? mb_substr(trim((string) $input['reference']), 0, 100) : null;

    switch ($action) {
        case 'topup':
            $userId = billingRequireUser($input);
            $result = billingTopUp($userId, (float) ($input['amount'] ?? 0), (string) ($input['payment_method'] ?? 'cash'), $reference);
            if (!empty($input['settle'])) {
                $result['settlement'] = billingSettleFromWallet($userId);
            }
            successResponse($result, 'Wallet topped up. New balance: ' . number_format($result['balance'], 2));

        case 'adjust':
            $userId = billingRequireUser($input);
            $result = billingAdjust($userId, (float) ($input['amount'] ?? 0), (string) ($input['reason'] ?? ''));
            successResponse($result, 'Balance adjusted. New balance: ' . number_format($result['balance'], 2));

        case 'update_account':
            $userId = billingRequireUser($input);
            billingEnsureAccount($userId);
            $fields = [];
            $params = [];
            if (isset($input['credit_limit'])) {
                $limit = billingMoney($input['credit_limit']);
                if ($limit < 0 || $limit > 100000) {
                    throw new InvalidArgumentException('Credit limit must be between 0 and 100,000.');
                }
                $fields[] = 'credit_limit = ?';
                $params[] = $limit;
            }
            if (isset($input['payment_terms_days'])) {
                $fields[] = 'payment_terms_days = ?';
                $params[] = max(0, min(365, (int) $input['payment_terms_days']));
            }
            if (isset($input['status'])) {
                if (!in_array($input['status'], ['active', 'suspended', 'closed'], true)) {
                    throw new InvalidArgumentException('Status must be active, suspended or closed.');
                }
                $fields[] = 'status = ?';
                $params[] = $input['status'];
            }
            if (isset($input['auto_pay_sessions'])) {
                $fields[] = 'auto_pay_sessions = ?';
                $params[] = filter_var($input['auto_pay_sessions'], FILTER_VALIDATE_BOOLEAN) ? 1 : 0;
            }
            if (array_key_exists('notes', $input)) {
                $fields[] = 'notes = ?';
                $params[] = mb_substr(trim((string) $input['notes']), 0, 1000) ?: null;
            }
            if ($fields === []) {
                throw new InvalidArgumentException('No account settings to update.');
            }
            $params[] = $userId;
            Database::execute('UPDATE billing_accounts SET ' . implode(', ', $fields) . ' WHERE user_id = ?', $params);
            logAuditTrail('billing_account_updated', 'billing_account', $userId, array_keys($input));
            successResponse(billingAccountOverview($userId)['account'], 'Billing account updated');

        case 'create_invoice':
            $userId = billingRequireUser($input);
            $ids = isset($input['transaction_ids']) && is_array($input['transaction_ids']) ? $input['transaction_ids'] : null;
            $invoice = billingCreateInvoice($userId, $ids, (int) ($input['due_days'] ?? 0), (float) ($input['discount_amount'] ?? 0), (string) ($input['notes'] ?? ''));
            successResponse($invoice, "Invoice {$invoice['invoice_number']} created for " . number_format((float) $invoice['total_amount'], 2));

        case 'create_manual_invoice':
            $userId = billingRequireUser($input);
            $invoice = billingCreateManualInvoice($userId, is_array($input['items'] ?? null) ? $input['items'] : [], (int) ($input['due_days'] ?? 0), (float) ($input['discount_amount'] ?? 0), (string) ($input['notes'] ?? ''));
            logAuditTrail('invoice_created_manual', 'invoice', (int) $invoice['invoice_id'], ['user_id' => $userId, 'total' => $invoice['total_amount']]);
            successResponse($invoice, "Invoice {$invoice['invoice_number']} created for " . number_format((float) $invoice['total_amount'], 2));

        case 'record_payment':
            $invoiceId = (int) ($input['invoice_id'] ?? 0);
            $invoice = billingRecordPayment($invoiceId, (float) ($input['amount'] ?? 0), (string) ($input['payment_method'] ?? 'cash'), $reference);
            $message = $invoice['status'] === 'paid'
                ? "Invoice {$invoice['invoice_number']} is fully paid."
                : 'Payment recorded. Balance due: ' . number_format((float) $invoice['balance_due'], 2);
            successResponse($invoice, $message);

        case 'void_invoice':
            $reason = trim((string) ($input['reason'] ?? ''));
            if ($reason === '') {
                throw new InvalidArgumentException('A reason is required to void an invoice.');
            }
            $invoice = billingVoidInvoice((int) ($input['invoice_id'] ?? 0), $reason);
            successResponse($invoice, "Invoice {$invoice['invoice_number']} voided. Its charges are unbilled again.");

        case 'settle_wallet':
            $userId = billingRequireUser($input);
            $result = billingSettleFromWallet($userId);
            successResponse($result, $result['paid'] > 0
                ? 'Paid ' . number_format($result['paid'], 2) . ' from the wallet. Remaining balance: ' . number_format($result['balance'], 2)
                : 'Nothing was paid: the wallet balance does not cover the oldest open charge.');

        default:
            errorResponse('Unknown billing action', 400);
    }
} catch (InvalidArgumentException $e) {
    errorResponse($e->getMessage(), 422);
} catch (Throwable $e) {
    error_log('Billing API error: ' . $e->getMessage());
    errorResponse('The billing request could not be completed.', 500);
}
