<?php
/**
 * CTECHLIT & SYSTEMS — Payments API
 *
 * POST action=topup        {user_id, amount, payment_method, reference?, settle?}   counter top-up (cash/MoMo/card/bank)
 * POST action=online_link  {user_id, amount, purpose: wallet_topup|invoice, invoice_id?, email?}  Paystack payment link
 * POST action=verify       {reference}   confirm an online payment with Paystack
 * GET  ?intents=1[&user_id=]             recent online payments
 * GET  ?config=1                         which payment channels are available
 * GET  ?callback=1&reference=…           browser return from Paystack (redirects to Customer Billing)
 */

require_once __DIR__ . '/common.php';
require_once __DIR__ . '/../../includes/paystack.php';

$method = $_SERVER['REQUEST_METHOD'];

function paymentsCanCollect(): bool
{
    return hasPermission('billing.manage') || hasPermission('pos.manage');
}

try {
    if ($method === 'GET') {
        if (isset($_GET['callback'])) {
            $reference = (string) ($_GET['reference'] ?? $_GET['trxref'] ?? '');
            $note = 'pending';
            $userId = 0;
            try {
                $result = paystackVerify($reference);
                $note = $result['status'];
                $userId = (int) $result['intent']['user_id'];
            } catch (Throwable $error) {
                $note = 'error';
                error_log('Paystack callback verify failed: ' . $error->getMessage());
            }
            redirect(base_path('billing.php') . '?' . http_build_query(['user_id' => $userId ?: null, 'payment' => $note, 'reference' => $reference]));
        }
        if (isset($_GET['config'])) {
            successResponse([
                'online_enabled' => paystackConfigured(),
                'can_collect' => paymentsCanCollect(),
                'counter_methods' => ['cash', 'mobile_money', 'card', 'bank_transfer'],
            ]);
        }
        requirePermission('billing.view');
        $params = [];
        $filter = '';
        if (!empty($_GET['user_id'])) {
            $filter = 'WHERE pi.user_id = ?';
            $params[] = (int) $_GET['user_id'];
        }
        successResponse(Database::tableExists('payment_intents') ? Database::fetchAll(
            "SELECT pi.reference, pi.purpose, pi.amount, pi.currency, pi.status, pi.channel, pi.authorization_url, pi.provider_message,
                    pi.created_at, pi.completed_at, u.full_name AS customer_name, i.invoice_number
             FROM payment_intents pi JOIN users u ON u.user_id = pi.user_id LEFT JOIN invoices i ON i.invoice_id = pi.invoice_id
             $filter ORDER BY pi.intent_id DESC LIMIT 50",
            $params
        ) : []);
    }

    if ($method !== 'POST') {
        errorResponse('Method not allowed', 405);
    }
    if (!paymentsCanCollect()) {
        errorResponse('You need the Manage Billing or Manage POS Sales permission to take payments.', 403);
    }

    $action = (string) ($input['action'] ?? '');
    $userId = (int) ($input['user_id'] ?? 0);
    $reference = isset($input['reference']) && trim((string) $input['reference']) !== '' ? mb_substr(trim((string) $input['reference']), 0, 100) : null;

    if ($action === 'topup') {
        if ($userId <= 0) {
            throw new InvalidArgumentException('Choose a customer to top up.');
        }
        $result = billingTopUp($userId, (float) ($input['amount'] ?? 0), (string) ($input['payment_method'] ?? 'cash'), $reference);
        if (!empty($input['settle'])) {
            $result['settlement'] = billingSettleFromWallet($userId);
        }
        successResponse($result, 'Wallet topped up. New balance: ' . number_format((float) ($result['settlement']['balance'] ?? $result['balance']), 2));
    }

    if ($action === 'online_link') {
        if ($userId <= 0) {
            throw new InvalidArgumentException('Choose a customer.');
        }
        $https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
        $host = preg_replace('/[^A-Za-z0-9.:\-\[\]]/', '', (string) ($_SERVER['HTTP_HOST'] ?? 'localhost'));
        $callback = ($https ? 'https' : 'http') . '://' . $host . base_path('api/payments.php') . '?callback=1';
        $link = paystackCreatePaymentLink(
            $userId, (float) ($input['amount'] ?? 0), (string) ($input['purpose'] ?? 'wallet_topup'),
            !empty($input['invoice_id']) ? (int) $input['invoice_id'] : null, $input['email'] ?? null, $callback
        );
        successResponse($link, 'Payment link created. The customer can pay with Mobile Money or card.');
    }

    if ($action === 'verify') {
        $result = paystackVerify((string) ($input['reference'] ?? ''));
        $messages = [
            'succeeded' => $result['already_applied'] ?? false ? 'This payment was already credited.' : 'Payment confirmed and credited.',
            'pending' => 'The customer has not completed this payment yet.',
            'failed' => 'This payment failed.',
            'abandoned' => 'The customer abandoned this payment.',
        ];
        successResponse(['status' => $result['status']], $messages[$result['status']] ?? 'Payment status updated.');
    }

    errorResponse('Unknown payment action', 400);
} catch (InvalidArgumentException $e) {
    errorResponse($e->getMessage(), 422);
} catch (RuntimeException $e) {
    errorResponse($e->getMessage(), 502);
} catch (Throwable $e) {
    error_log('Payments API error: ' . $e->getMessage());
    errorResponse('The payment could not be processed.', 500);
}
