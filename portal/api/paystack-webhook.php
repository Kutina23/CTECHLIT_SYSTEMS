<?php
/**
 * Paystack webhook — public endpoint, authenticated by the HMAC-SHA512 signature.
 * Configure in the Paystack dashboard: https://<your-domain>/systems/portal/api/paystack-webhook.php
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/paystack.php';

header('Content-Type: application/json');

$payload = (string) file_get_contents('php://input');
$signature = (string) ($_SERVER['HTTP_X_PAYSTACK_SIGNATURE'] ?? '');
$secret = paystackSecretKey();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || $secret === '' || $signature === ''
    || !hash_equals(hash_hmac('sha512', $payload, $secret), $signature)) {
    http_response_code(401);
    echo json_encode(['success' => false]);
    exit;
}

$event = json_decode($payload, true);
try {
    if (is_array($event) && ($event['event'] ?? '') === 'charge.success' && is_array($event['data'] ?? null)) {
        // Re-verify with the API rather than trusting the payload amounts alone.
        paystackVerify((string) ($event['data']['reference'] ?? ''));
    }
} catch (Throwable $error) {
    error_log('Paystack webhook processing failed: ' . $error->getMessage());
}

http_response_code(200);
echo json_encode(['success' => true]);
