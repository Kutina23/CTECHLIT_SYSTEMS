<?php
/**
 * CTECHLIT & SYSTEMS — Customer Billing
 *
 * Model:
 *  - Every customer can have a billing account with a prepaid wallet balance.
 *  - Unpaid charges are `transactions` rows with status 'pending' (pay-later POS sales,
 *    completed sessions that were not paid). Uninvoiced ones are "unbilled".
 *  - Invoices group unbilled charges; payments (cash, MoMo, card, bank, wallet) settle them.
 *  - Every wallet movement is recorded in `billing_ledger` with the running balance.
 */

require_once __DIR__ . '/../config/functions.php';

const BILLING_PAYMENT_METHODS = ['cash', 'mobile_money', 'card', 'bank_transfer', 'wallet', 'other'];

function billingEnabled(): bool
{
    static $enabled = null;
    if ($enabled === null) {
        $enabled = Database::tableExists('billing_accounts') && Database::tableExists('invoices') && Database::tableExists('billing_ledger');
    }
    return $enabled;
}

/**
 * Run $work atomically, joining an outer transaction when one is already open.
 */
function billingAtomic(callable $work): mixed
{
    $pdo = Database::getInstance();
    if ($pdo->inTransaction()) {
        return $work();
    }
    $pdo->beginTransaction();
    try {
        $result = $work();
        $pdo->commit();
        return $result;
    } catch (Throwable $error) {
        $pdo->rollBack();
        throw $error;
    }
}

function billingMoney(mixed $value): float
{
    return round((float) $value, 2);
}

function billingStaffId(): ?int
{
    return ($_SESSION['user_type'] ?? '') === 'staff' && !empty($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;
}

function billingEnsureAccount(int $userId): array
{
    $customer = Database::fetchOne('SELECT user_id, full_name FROM users WHERE user_id = ?', [$userId]);
    if (!$customer) {
        throw new InvalidArgumentException('Customer not found.');
    }
    Database::execute(
        'INSERT IGNORE INTO billing_accounts (user_id, account_number) VALUES (?, ?)',
        [$userId, 'ACC-' . str_pad((string) $userId, 6, '0', STR_PAD_LEFT)]
    );
    return Database::fetchOne('SELECT * FROM billing_accounts WHERE user_id = ?', [$userId]);
}

/**
 * Move money in or out of the wallet. Positive = credit, negative = debit.
 */
function billingPostLedger(int $userId, string $type, float $amount, string $description, array $links = []): array
{
    return billingAtomic(function () use ($userId, $type, $amount, $description, $links) {
        billingEnsureAccount($userId);
        $account = Database::fetchOne('SELECT balance, status FROM billing_accounts WHERE user_id = ? FOR UPDATE', [$userId]);
        $newBalance = billingMoney((float) $account['balance'] + $amount);
        if ($amount < 0 && $newBalance < 0 && empty($links['allow_negative'])) {
            throw new InvalidArgumentException('Insufficient wallet balance. Available: ' . number_format((float) $account['balance'], 2) . '.');
        }
        Database::execute('UPDATE billing_accounts SET balance = ? WHERE user_id = ?', [$newBalance, $userId]);
        $entryId = Database::insert(
            'INSERT INTO billing_ledger (user_id, entry_type, amount, balance_after, payment_method, reference, description, invoice_id, transaction_id, staff_id)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $userId, $type, billingMoney($amount), $newBalance,
                $links['payment_method'] ?? null, $links['reference'] ?? null, mb_substr($description, 0, 255),
                $links['invoice_id'] ?? null, $links['transaction_id'] ?? null, billingStaffId(),
            ]
        );
        return ['entry_id' => $entryId, 'balance' => $newBalance];
    });
}

function billingTopUp(int $userId, float $amount, string $method, ?string $reference = null): array
{
    $amount = billingMoney($amount);
    if ($amount <= 0 || $amount > 100000) {
        throw new InvalidArgumentException('Top-up amount must be between 0.01 and 100,000.');
    }
    if (!in_array($method, ['cash', 'mobile_money', 'card', 'bank_transfer', 'other'], true)) {
        throw new InvalidArgumentException('Choose cash, mobile money, card or bank transfer for top-ups.');
    }
    $result = billingPostLedger($userId, 'topup', $amount, 'Wallet top-up (' . str_replace('_', ' ', $method) . ')', [
        'payment_method' => $method, 'reference' => $reference,
    ]);
    logAuditTrail('billing_topup', 'billing_account', $userId, ['amount' => $amount, 'method' => $method]);
    return $result;
}

function billingAdjust(int $userId, float $amount, string $reason): array
{
    $amount = billingMoney($amount);
    if ($amount == 0.0 || abs($amount) > 100000) {
        throw new InvalidArgumentException('Adjustment must be a non-zero amount up to 100,000.');
    }
    if (trim($reason) === '') {
        throw new InvalidArgumentException('A reason is required for balance adjustments.');
    }
    $result = billingPostLedger($userId, 'adjustment', $amount, 'Adjustment: ' . trim($reason));
    logAuditTrail('billing_adjustment', 'billing_account', $userId, ['amount' => $amount, 'reason' => $reason]);
    return $result;
}

/**
 * Pay for a sale or charge from the wallet. Returns the new balance.
 */
function billingChargeWallet(int $userId, float $amount, string $description, ?int $transactionId = null, ?int $invoiceId = null): array
{
    $amount = billingMoney($amount);
    if ($amount <= 0) {
        throw new InvalidArgumentException('Charge amount must be greater than zero.');
    }
    $account = billingEnsureAccount($userId);
    if ($account['status'] !== 'active') {
        throw new InvalidArgumentException('This billing account is ' . $account['status'] . '.');
    }
    return billingPostLedger($userId, $invoiceId ? 'payment' : 'charge', -$amount, $description, [
        'payment_method' => 'wallet', 'transaction_id' => $transactionId, 'invoice_id' => $invoiceId,
    ]);
}

/**
 * Take payment for something the customer is buying and record it in the ledger.
 *
 * $method: cash | mobile_money | card | bank_transfer → paid now
 *          wallet  → deducted from the prepaid balance
 *          account → added to unbilled charges (credit limit enforced)
 * Returns transaction_id, receipt_number, status ('completed' | 'pending') and wallet_balance.
 */
function billingCollectPayment(int $userId, float $amount, string $method, string $description, array $options = []): array
{
    $amount = billingMoney($amount);
    if ($amount < 0) {
        throw new InvalidArgumentException('Amount cannot be negative.');
    }
    if (!in_array($method, ['cash', 'mobile_money', 'card', 'bank_transfer', 'wallet', 'account'], true)) {
        throw new InvalidArgumentException('Choose cash, mobile money, card, bank transfer, wallet or on account.');
    }
    return billingAtomic(function () use ($userId, $amount, $method, $description, $options) {
        if ($method === 'account') {
            billingAssertCanCharge($userId, $amount);
        }
        $receipt = generateReceiptNumber();
        $payLater = $method === 'account';
        $transactionId = Database::insert(
            "INSERT INTO transactions (user_id, session_id, payment_method, amount, total_amount, currency, status, payment_reference, receipt_number, notes)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
            [
                $userId, $options['session_id'] ?? null, $method, $amount, $amount, siteSetting('currency', 'GHS'),
                $payLater ? 'pending' : 'completed', $options['reference'] ?? $receipt, $receipt, mb_substr($description, 0, 255),
            ]
        );
        Database::execute(
            'INSERT INTO transaction_items (transaction_id, description, quantity, unit_price, total_price) VALUES (?, ?, ?, ?, ?)',
            [$transactionId, mb_substr($description, 0, 255), $options['quantity'] ?? 1, $options['unit_price'] ?? $amount, $amount]
        );
        $walletBalance = null;
        if ($method === 'wallet' && $amount > 0) {
            $walletBalance = billingChargeWallet($userId, $amount, $description, $transactionId)['balance'];
        }
        Database::execute(
            'UPDATE users SET total_visits = total_visits + 1, total_spent = total_spent + ? WHERE user_id = ?',
            [$payLater ? 0 : $amount, $userId]
        );
        return [
            'transaction_id' => $transactionId,
            'receipt_number' => $receipt,
            'status' => $payLater ? 'pending' : 'completed',
            'amount' => $amount,
            'payment_method' => $method,
            'wallet_balance' => $walletBalance,
        ];
    });
}

/**
 * Settle an existing unpaid charge (e.g. a session that just ended) at the counter.
 * 'account' leaves it unbilled for a later invoice.
 */
function billingSettleTransaction(int $transactionId, string $method, ?string $reference = null): array
{
    if (!in_array($method, ['cash', 'mobile_money', 'card', 'bank_transfer', 'wallet', 'account'], true)) {
        throw new InvalidArgumentException('Choose cash, mobile money, card, bank transfer, wallet or on account.');
    }
    return billingAtomic(function () use ($transactionId, $method, $reference) {
        $tx = Database::fetchOne('SELECT * FROM transactions WHERE transaction_id = ? FOR UPDATE', [$transactionId]);
        if (!$tx) {
            throw new InvalidArgumentException('Charge not found.');
        }
        if ($tx['status'] !== 'pending') {
            return ['transaction_id' => $transactionId, 'status' => $tx['status'], 'amount' => (float) $tx['total_amount'], 'already_settled' => true];
        }
        if ($tx['invoice_id']) {
            throw new InvalidArgumentException('This charge is on an invoice; record the payment against the invoice instead.');
        }
        $amount = billingMoney((float) $tx['total_amount']);
        if ($method === 'account') {
            return ['transaction_id' => $transactionId, 'status' => 'pending', 'amount' => $amount, 'payment_method' => 'account'];
        }
        $walletBalance = null;
        if ($method === 'wallet' && $amount > 0) {
            $walletBalance = billingChargeWallet((int) $tx['user_id'], $amount, (string) ($tx['notes'] ?: 'Charge #' . $transactionId), $transactionId)['balance'];
        }
        Database::execute(
            "UPDATE transactions SET status = 'completed', payment_method = ?, payment_reference = COALESCE(?, payment_reference),
                    receipt_number = COALESCE(receipt_number, ?) WHERE transaction_id = ?",
            [$method, $reference, generateReceiptNumber(), $transactionId]
        );
        Database::execute('UPDATE users SET total_spent = total_spent + ? WHERE user_id = ?', [$amount, $tx['user_id']]);
        return ['transaction_id' => $transactionId, 'status' => 'completed', 'amount' => $amount, 'payment_method' => $method, 'wallet_balance' => $walletBalance];
    });
}

/**
 * Invoice custom line items (not from existing charges), e.g. a typing or design job.
 */
function billingCreateManualInvoice(int $userId, array $items, int $dueDays = 0, float $discount = 0.0, string $notes = ''): array
{
    $lines = [];
    foreach (array_slice($items, 0, 50) as $item) {
        $description = trim((string) ($item['description'] ?? ''));
        $quantity = round((float) ($item['quantity'] ?? 1), 2);
        $unitPrice = round((float) ($item['unit_price'] ?? 0), 2);
        if ($description === '' && $unitPrice == 0.0) {
            continue;
        }
        if ($description === '' || mb_strlen($description) > 255 || $quantity <= 0 || $unitPrice < 0 || $unitPrice > 100000) {
            throw new InvalidArgumentException('Each line needs a description, a quantity above zero and a valid price.');
        }
        $lines[] = ['description' => $description, 'quantity' => $quantity, 'unit_price' => $unitPrice, 'total' => round($quantity * $unitPrice, 2)];
    }
    if ($lines === []) {
        throw new InvalidArgumentException('Add at least one line item.');
    }
    return billingAtomic(function () use ($userId, $lines, $dueDays, $discount, $notes) {
        billingEnsureAccount($userId);
        $total = billingMoney(array_sum(array_column($lines, 'total')));
        $transactionId = Database::insert(
            "INSERT INTO transactions (user_id, amount, total_amount, currency, status, payment_reference, notes)
             VALUES (?, ?, ?, ?, 'pending', ?, ?)",
            [$userId, $total, $total, siteSetting('currency', 'GHS'), 'MANUAL-' . date('YmdHis'), mb_substr($lines[0]['description'] . (count($lines) > 1 ? ' + ' . (count($lines) - 1) . ' more' : ''), 0, 255)]
        );
        foreach ($lines as $line) {
            Database::execute(
                'INSERT INTO transaction_items (transaction_id, description, quantity, unit_price, total_price) VALUES (?, ?, ?, ?, ?)',
                [$transactionId, $line['description'], $line['quantity'], $line['unit_price'], $line['total']]
            );
        }
        $invoice = billingCreateInvoice($userId, [$transactionId], $dueDays, $discount, $notes);
        // Show each line on the invoice instead of the single grouped charge.
        Database::execute('DELETE FROM invoice_items WHERE invoice_id = ?', [$invoice['invoice_id']]);
        foreach ($lines as $line) {
            Database::execute(
                'INSERT INTO invoice_items (invoice_id, transaction_id, description, quantity, unit_price, total_price) VALUES (?, ?, ?, ?, ?, ?)',
                [$invoice['invoice_id'], $transactionId, $line['description'], $line['quantity'], $line['unit_price'], $line['total']]
            );
        }
        return billingInvoice((int) $invoice['invoice_id']);
    });
}

function billingUnbilledCharges(int $userId): array
{
    return Database::fetchAll(
        "SELECT t.transaction_id, t.session_id, t.total_amount, t.notes, t.payment_reference, t.created_at,
                COALESCE(
                    (SELECT GROUP_CONCAT(ti.description SEPARATOR ', ') FROM transaction_items ti WHERE ti.transaction_id = t.transaction_id),
                    t.notes,
                    IF(t.session_id IS NULL, 'Charge', CONCAT('Station session #', t.session_id))
                ) AS description
         FROM transactions t
         WHERE t.user_id = ? AND t.status = 'pending' AND t.invoice_id IS NULL AND t.total_amount > 0
         ORDER BY t.created_at ASC",
        [$userId]
    );
}

function billingInvoiceStatusSql(string $alias = 'i'): string
{
    return "CASE WHEN $alias.status IN ('issued','partially_paid') AND $alias.due_date < CURDATE() THEN 'overdue' ELSE $alias.status END";
}

function billingOutstanding(int $userId): array
{
    $unbilled = (float) Database::fetchColumn(
        "SELECT COALESCE(SUM(total_amount), 0) FROM transactions WHERE user_id = ? AND status = 'pending' AND invoice_id IS NULL",
        [$userId]
    );
    $invoiced = (float) Database::fetchColumn(
        "SELECT COALESCE(SUM(total_amount - amount_paid), 0) FROM invoices WHERE user_id = ? AND status IN ('issued','partially_paid')",
        [$userId]
    );
    return ['unbilled' => billingMoney($unbilled), 'invoiced' => billingMoney($invoiced), 'total' => billingMoney($unbilled + $invoiced)];
}

/**
 * Record a pay-later charge against the customer's account, enforcing the credit limit.
 */
function billingAssertCanCharge(int $userId, float $amount): void
{
    $account = billingEnsureAccount($userId);
    if ($account['status'] !== 'active') {
        throw new InvalidArgumentException('This billing account is ' . $account['status'] . ', so pay-later is not allowed.');
    }
    $limit = (float) $account['credit_limit'];
    if ($limit <= 0) {
        throw new InvalidArgumentException('Pay-later needs a credit limit on this customer. Set one under Customer Billing → Account settings.');
    }
    $owed = billingOutstanding($userId)['total'];
    if ($owed + $amount > $limit + 0.001) {
        throw new InvalidArgumentException('This sale would exceed the credit limit (' . number_format($limit, 2) . '). Currently owed: ' . number_format($owed, 2) . '.');
    }
}

function billingNextInvoiceNumber(): string
{
    $prefix = 'INV-' . date('Ym') . '-';
    $last = Database::fetchColumn(
        'SELECT invoice_number FROM invoices WHERE invoice_number LIKE ? ORDER BY invoice_id DESC LIMIT 1',
        [$prefix . '%']
    );
    $next = $last ? ((int) substr((string) $last, strlen($prefix))) + 1 : 1;
    return $prefix . str_pad((string) $next, 4, '0', STR_PAD_LEFT);
}

function billingCreateInvoice(int $userId, ?array $transactionIds = null, int $dueDays = 0, float $discount = 0.0, string $notes = ''): array
{
    return billingAtomic(function () use ($userId, $transactionIds, $dueDays, $discount, $notes) {
        $account = billingEnsureAccount($userId);
        $charges = billingUnbilledCharges($userId);
        if ($transactionIds !== null) {
            $wanted = array_map('intval', $transactionIds);
            $charges = array_values(array_filter($charges, fn($charge) => in_array((int) $charge['transaction_id'], $wanted, true)));
        }
        if ($charges === []) {
            throw new InvalidArgumentException('This customer has no unbilled charges to invoice.');
        }

        $subtotal = billingMoney(array_sum(array_map(fn($charge) => (float) $charge['total_amount'], $charges)));
        $discount = billingMoney(max(0, min($discount, $subtotal)));
        $total = billingMoney($subtotal - $discount);
        $dueDays = $dueDays > 0 ? min($dueDays, 365) : (int) $account['payment_terms_days'];

        $invoiceId = Database::insert(
            "INSERT INTO invoices (invoice_number, user_id, status, issue_date, due_date, subtotal, discount_amount, total_amount, notes, created_by)
             VALUES (?, ?, 'issued', CURDATE(), DATE_ADD(CURDATE(), INTERVAL ? DAY), ?, ?, ?, ?, ?)",
            [billingNextInvoiceNumber(), $userId, $dueDays, $subtotal, $discount, $total, trim($notes) ?: null, billingStaffId()]
        );
        foreach ($charges as $charge) {
            Database::execute(
                'INSERT INTO invoice_items (invoice_id, transaction_id, description, quantity, unit_price, total_price) VALUES (?, ?, ?, 1, ?, ?)',
                [$invoiceId, $charge['transaction_id'], mb_substr((string) $charge['description'], 0, 255), $charge['total_amount'], $charge['total_amount']]
            );
            Database::execute('UPDATE transactions SET invoice_id = ? WHERE transaction_id = ?', [$invoiceId, $charge['transaction_id']]);
        }
        if ($total <= 0) {
            billingMarkInvoicePaid($invoiceId, 'other');
        }
        logAuditTrail('invoice_created', 'invoice', $invoiceId, ['user_id' => $userId, 'total' => $total, 'items' => count($charges)]);
        return billingInvoice($invoiceId);
    });
}

function billingMarkInvoicePaid(int $invoiceId, string $method): void
{
    Database::execute("UPDATE invoices SET status = 'paid', paid_at = NOW() WHERE invoice_id = ?", [$invoiceId]);
    Database::execute(
        "UPDATE transactions SET status = 'completed', payment_method = COALESCE(payment_method, ?) WHERE invoice_id = ? AND status = 'pending'",
        [$method, $invoiceId]
    );
}

function billingRecordPayment(int $invoiceId, float $amount, string $method, ?string $reference = null): array
{
    if (!in_array($method, BILLING_PAYMENT_METHODS, true)) {
        throw new InvalidArgumentException('Unsupported payment method.');
    }
    return billingAtomic(function () use ($invoiceId, $amount, $method, $reference) {
        $invoice = Database::fetchOne('SELECT * FROM invoices WHERE invoice_id = ? FOR UPDATE', [$invoiceId]);
        if (!$invoice) {
            throw new InvalidArgumentException('Invoice not found.');
        }
        if (!in_array($invoice['status'], ['issued', 'partially_paid'], true)) {
            throw new InvalidArgumentException("Invoice {$invoice['invoice_number']} is {$invoice['status']} and cannot take payments.");
        }
        $due = billingMoney((float) $invoice['total_amount'] - (float) $invoice['amount_paid']);
        $amount = billingMoney($amount);
        if ($amount <= 0) {
            throw new InvalidArgumentException('Payment amount must be greater than zero.');
        }
        if ($amount > $due + 0.001) {
            throw new InvalidArgumentException('Payment exceeds the amount due (' . number_format($due, 2) . ').');
        }

        if ($method === 'wallet') {
            billingChargeWallet((int) $invoice['user_id'], $amount, "Payment for invoice {$invoice['invoice_number']}", null, $invoiceId);
        }

        Database::insert(
            'INSERT INTO invoice_payments (invoice_id, amount, payment_method, reference, received_by) VALUES (?, ?, ?, ?, ?)',
            [$invoiceId, $amount, $method, $reference, billingStaffId()]
        );
        $paid = billingMoney((float) $invoice['amount_paid'] + $amount);
        Database::execute('UPDATE invoices SET amount_paid = ?, status = ? WHERE invoice_id = ?', [
            $paid, $paid + 0.001 >= (float) $invoice['total_amount'] ? 'paid' : 'partially_paid', $invoiceId,
        ]);
        if ($paid + 0.001 >= (float) $invoice['total_amount']) {
            billingMarkInvoicePaid($invoiceId, $method);
        }
        Database::execute(
            'UPDATE users SET total_spent = total_spent + ? WHERE user_id = ?',
            [$amount, $invoice['user_id']]
        );
        logAuditTrail('invoice_payment', 'invoice', $invoiceId, ['amount' => $amount, 'method' => $method]);
        return billingInvoice($invoiceId);
    });
}

function billingVoidInvoice(int $invoiceId, string $reason): array
{
    return billingAtomic(function () use ($invoiceId, $reason) {
        $invoice = Database::fetchOne('SELECT * FROM invoices WHERE invoice_id = ? FOR UPDATE', [$invoiceId]);
        if (!$invoice) {
            throw new InvalidArgumentException('Invoice not found.');
        }
        if ((float) $invoice['amount_paid'] > 0) {
            throw new InvalidArgumentException('Invoices with payments cannot be voided. Refund the payment instead.');
        }
        if ($invoice['status'] === 'void') {
            throw new InvalidArgumentException('This invoice is already void.');
        }
        Database::execute(
            "UPDATE invoices SET status = 'void', notes = CONCAT(COALESCE(notes, ''), ?) WHERE invoice_id = ?",
            ["\nVoided: " . trim($reason), $invoiceId]
        );
        // Charges return to the unbilled list so they can be re-invoiced or written off.
        Database::execute("UPDATE transactions SET invoice_id = NULL WHERE invoice_id = ? AND status = 'pending'", [$invoiceId]);
        logAuditTrail('invoice_voided', 'invoice', $invoiceId, ['reason' => $reason]);
        return billingInvoice($invoiceId);
    });
}

/**
 * Use the wallet balance to pay unbilled charges and open invoices, oldest first.
 */
function billingSettleFromWallet(int $userId): array
{
    return billingAtomic(function () use ($userId) {
        billingEnsureAccount($userId);
        $paid = 0.0;
        $balance = (float) Database::fetchColumn('SELECT balance FROM billing_accounts WHERE user_id = ? FOR UPDATE', [$userId]);
        foreach (billingUnbilledCharges($userId) as $charge) {
            $amount = (float) $charge['total_amount'];
            if ($amount > $balance + 0.001) {
                break;
            }
            billingChargeWallet($userId, $amount, 'Paid: ' . mb_substr((string) $charge['description'], 0, 200), (int) $charge['transaction_id']);
            Database::execute(
                "UPDATE transactions SET status = 'completed', payment_method = 'wallet' WHERE transaction_id = ?",
                [$charge['transaction_id']]
            );
            $balance -= $amount;
            $paid += $amount;
        }
        $openInvoices = Database::fetchAll(
            "SELECT invoice_id, total_amount - amount_paid AS due FROM invoices
             WHERE user_id = ? AND status IN ('issued','partially_paid') ORDER BY due_date ASC",
            [$userId]
        );
        foreach ($openInvoices as $invoice) {
            $amount = billingMoney(min((float) $invoice['due'], $balance));
            if ($amount <= 0) {
                break;
            }
            billingRecordPayment((int) $invoice['invoice_id'], $amount, 'wallet');
            $balance -= $amount;
            $paid += $amount;
        }
        return ['paid' => billingMoney($paid), 'balance' => billingMoney($balance), 'outstanding' => billingOutstanding($userId)];
    });
}

/**
 * Called when a station session ends with an unpaid charge: pay it from the wallet if enabled.
 */
function billingAutoPayTransaction(int $userId, int $transactionId, float $amount): bool
{
    if (!billingEnabled() || $amount <= 0) {
        return false;
    }
    $account = Database::fetchOne('SELECT balance, auto_pay_sessions, status FROM billing_accounts WHERE user_id = ?', [$userId]);
    if (!$account || !$account['auto_pay_sessions'] || $account['status'] !== 'active' || (float) $account['balance'] + 0.001 < $amount) {
        return false;
    }
    billingChargeWallet($userId, $amount, 'Station session (auto-pay)', $transactionId);
    Database::execute("UPDATE transactions SET status = 'completed', payment_method = 'wallet' WHERE transaction_id = ?", [$transactionId]);
    return true;
}

function billingInvoice(int $invoiceId): array
{
    $invoice = Database::fetchOne(
        'SELECT i.*, ' . billingInvoiceStatusSql() . " AS display_status, (i.total_amount - i.amount_paid) AS balance_due,
                u.full_name AS customer_name, u.email AS customer_email, u.phone AS customer_phone,
                ba.account_number, s.full_name AS created_by_name
         FROM invoices i
         JOIN users u ON u.user_id = i.user_id
         LEFT JOIN billing_accounts ba ON ba.user_id = i.user_id
         LEFT JOIN staff s ON s.staff_id = i.created_by
         WHERE i.invoice_id = ?",
        [$invoiceId]
    );
    if (!$invoice) {
        throw new InvalidArgumentException('Invoice not found.');
    }
    $invoice['items'] = Database::fetchAll('SELECT * FROM invoice_items WHERE invoice_id = ? ORDER BY invoice_item_id', [$invoiceId]);
    $invoice['payments'] = Database::fetchAll(
        'SELECT p.*, s.full_name AS received_by_name FROM invoice_payments p LEFT JOIN staff s ON s.staff_id = p.received_by
         WHERE p.invoice_id = ? ORDER BY p.payment_id',
        [$invoiceId]
    );
    return $invoice;
}

function billingAccountOverview(int $userId): array
{
    $account = billingEnsureAccount($userId);
    $customer = Database::fetchOne(
        'SELECT user_id, full_name, email, phone, membership_tier, total_visits, total_spent, created_at FROM users WHERE user_id = ?',
        [$userId]
    );
    return [
        'customer' => $customer,
        'account' => $account,
        'outstanding' => billingOutstanding($userId),
        'unbilled' => billingUnbilledCharges($userId),
        'invoices' => Database::fetchAll(
            'SELECT invoice_id, invoice_number, issue_date, due_date, total_amount, amount_paid, (total_amount - amount_paid) AS balance_due, '
            . billingInvoiceStatusSql('invoices') . ' AS display_status
             FROM invoices WHERE user_id = ? ORDER BY invoice_id DESC LIMIT 50',
            [$userId]
        ),
        'ledger' => Database::fetchAll(
            'SELECT l.*, s.full_name AS staff_name FROM billing_ledger l LEFT JOIN staff s ON s.staff_id = l.staff_id
             WHERE l.user_id = ? ORDER BY l.entry_id DESC LIMIT 50',
            [$userId]
        ),
    ];
}
