<?php
/**
 * CTECHLIT & SYSTEMS — Paystack online payments (Mobile Money and card)
 *
 * Flow: staff creates a payment link → customer pays on Paystack → the signed
 * webhook (or a manual "verify") confirms it → the wallet or invoice is credited once.
 */

require_once __DIR__ . '/billing.php';

function paystackSecretKey(): string
{
    $key = (string) ($_ENV['PAYSTACK_SECRET_KEY'] ?? '');
    return preg_match('/\Ask_(test|live)_[A-Za-z0-9]+\z/', $key) ? $key : '';
}

function paystackConfigured(): bool
{
    return paystackSecretKey() !== '' && Database::tableExists('payment_intents');
}

function paystackRequest(string $method, string $path, ?array $body = null): array
{
    $handle = curl_init('https://api.paystack.co' . $path);
    curl_setopt_array($handle, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . paystackSecretKey(), 'Content-Type: application/json', 'Accept: application/json'],
        CURLOPT_POSTFIELDS => $body !== null ? json_encode($body) : null,
        CURLOPT_CONNECTTIMEOUT => 8,
        CURLOPT_TIMEOUT => 25,
    ]);
    $response = curl_exec($handle);
    $status = (int) curl_getinfo($handle, CURLINFO_HTTP_CODE);
    $error = curl_error($handle);
    curl_close($handle);
    if ($response === false) {
        throw new RuntimeException('Could not reach Paystack: ' . $error);
    }
    $decoded = json_decode((string) $response, true);
    if (!is_array($decoded) || $status >= 400 || empty($decoded['status'])) {
        throw new RuntimeException('Paystack: ' . (is_array($decoded) ? ($decoded['message'] ?? 'request failed') : 'unreadable response') . " (HTTP $status)");
    }
    return $decoded['data'] ?? [];
}

/**
 * Create a hosted payment link for a wallet top-up or an invoice balance.
 */
function paystackCreatePaymentLink(int $userId, float $amount, string $purpose, ?int $invoiceId, ?string $email, string $callbackUrl): array
{
    if (!paystackConfigured()) {
        throw new InvalidArgumentException('Online payments are not set up. Add your Paystack secret key (sk_live_… or sk_test_…) to PAYSTACK_SECRET_KEY in config/.env.');
    }
    $amount = billingMoney($amount);
    if ($amount < 1 || $amount > 100000) {
        throw new InvalidArgumentException('Online payments must be between 1.00 and 100,000.00.');
    }
    $customer = Database::fetchOne('SELECT user_id, full_name, email, phone FROM users WHERE user_id = ?', [$userId]);
    if (!$customer) {
        throw new InvalidArgumentException('Customer not found.');
    }
    if ($purpose === 'invoice') {
        $invoice = Database::fetchOne("SELECT invoice_id, user_id, total_amount - amount_paid AS due, status FROM invoices WHERE invoice_id = ?", [$invoiceId]);
        if (!$invoice || (int) $invoice['user_id'] !== $userId || !in_array($invoice['status'], ['issued', 'partially_paid'], true)) {
            throw new InvalidArgumentException('That invoice cannot take online payments.');
        }
        if ($amount > (float) $invoice['due'] + 0.001) {
            throw new InvalidArgumentException('Amount exceeds the invoice balance (' . number_format((float) $invoice['due'], 2) . ').');
        }
    } else {
        $purpose = 'wallet_topup';
        $invoiceId = null;
    }
    // Paystack requires an email; customers without one get a stable placeholder on our domain.
    $email = trim((string) ($email ?: $customer['email']));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $email = 'customer' . $userId . '@' . (parse_url((string) ($_ENV['APP_URL'] ?? ''), PHP_URL_HOST) ?: 'ctechlitsystems.com');
    }

    billingEnsureAccount($userId);
    $reference = 'CTL-' . date('ymd') . '-' . strtoupper(bin2hex(random_bytes(5)));
    $currency = strtoupper(siteSetting('currency', 'GHS'));
    $data = paystackRequest('POST', '/transaction/initialize', [
        'email' => $email,
        'amount' => (int) round($amount * 100),
        'currency' => $currency,
        'reference' => $reference,
        'callback_url' => $callbackUrl,
        'channels' => ['mobile_money', 'card', 'bank_transfer'],
        'metadata' => ['user_id' => $userId, 'purpose' => $purpose, 'invoice_id' => $invoiceId, 'customer' => $customer['full_name']],
    ]);
    Database::insert(
        'INSERT INTO payment_intents (reference, user_id, purpose, invoice_id, amount, currency, email, authorization_url, created_by)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
        [$reference, $userId, $purpose, $invoiceId, $amount, $currency, $email, $data['authorization_url'] ?? null, billingStaffId()]
    );
    logAuditTrail('payment_link_created', 'payment_intent', null, ['reference' => $reference, 'user_id' => $userId, 'amount' => $amount, 'purpose' => $purpose]);
    return ['reference' => $reference, 'authorization_url' => $data['authorization_url'] ?? null, 'amount' => $amount, 'purpose' => $purpose];
}

/**
 * Apply a successful Paystack transaction exactly once.
 */
function paystackApplyTransaction(array $transaction): array
{
    $reference = (string) ($transaction['reference'] ?? '');
    return billingAtomic(function () use ($reference, $transaction) {
        $intent = Database::fetchOne('SELECT * FROM payment_intents WHERE reference = ? FOR UPDATE', [$reference]);
        if (!$intent) {
            throw new InvalidArgumentException('Unknown payment reference.');
        }
        if ($intent['status'] === 'succeeded') {
            return ['status' => 'succeeded', 'already_applied' => true, 'intent' => $intent];
        }
        $paidStatus = (string) ($transaction['status'] ?? '');
        if ($paidStatus !== 'success') {
            $final = in_array($paidStatus, ['failed', 'abandoned'], true) ? $paidStatus : 'pending';
            Database::execute('UPDATE payment_intents SET status = ?, provider_message = ? WHERE intent_id = ?', [$final, mb_substr((string) ($transaction['gateway_response'] ?? $paidStatus), 0, 255), $intent['intent_id']]);
            return ['status' => $final, 'intent' => $intent];
        }
        $paid = billingMoney(((int) ($transaction['amount'] ?? 0)) / 100);
        if ($paid + 0.001 < (float) $intent['amount'] || strtoupper((string) ($transaction['currency'] ?? '')) !== $intent['currency']) {
            Database::execute("UPDATE payment_intents SET status = 'failed', provider_message = 'Amount or currency mismatch' WHERE intent_id = ?", [$intent['intent_id']]);
            throw new InvalidArgumentException('Paystack amount or currency does not match this payment.');
        }
        $channel = (string) ($transaction['channel'] ?? '');
        $method = ['mobile_money' => 'mobile_money', 'card' => 'card', 'bank' => 'bank_transfer', 'bank_transfer' => 'bank_transfer'][$channel] ?? 'other';

        if ($intent['purpose'] === 'invoice' && $intent['invoice_id']) {
            billingRecordPayment((int) $intent['invoice_id'], (float) $intent['amount'], $method, $reference);
        } else {
            billingTopUp((int) $intent['user_id'], (float) $intent['amount'], $method, $reference);
        }
        Database::execute(
            "UPDATE payment_intents SET status = 'succeeded', channel = ?, provider_message = ?, completed_at = NOW() WHERE intent_id = ?",
            [$channel, mb_substr((string) ($transaction['gateway_response'] ?? 'Approved'), 0, 255), $intent['intent_id']]
        );
        return ['status' => 'succeeded', 'already_applied' => false, 'intent' => $intent];
    });
}

function paystackVerify(string $reference): array
{
    if (!paystackConfigured()) {
        throw new InvalidArgumentException('Online payments are not set up (PAYSTACK_SECRET_KEY).');
    }
    if (!preg_match('/\A[A-Za-z0-9-]{6,100}\z/', $reference)) {
        throw new InvalidArgumentException('Invalid payment reference.');
    }
    return paystackApplyTransaction(paystackRequest('GET', '/transaction/verify/' . rawurlencode($reference)));
}
