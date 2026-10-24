<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; } // command-line only
/**
 * Personal-device billing (auto-renew, server-side metering) and online payment intents (Paystack).
 * Idempotent: safe to run more than once.
 *
 * Run with: php database/migrations/019_personal_device_billing_payments.php
 */

require_once __DIR__ . '/../../config/database.php';

if (!function_exists('migrationColumnExists')) {
    function migrationColumnExists(string $table, string $column): bool
    {
        return (bool) Database::fetchColumn(
            'SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?',
            [$table, $column]
        );
    }
}

$columns = [
    'auto_renew' => 'BOOLEAN NOT NULL DEFAULT FALSE AFTER plan_id',
    'last_metered_at' => 'TIMESTAMP NULL AFTER last_seen_at',
];
foreach ($columns as $column => $definition) {
    if (!migrationColumnExists('personal_devices', $column)) {
        Database::execute("ALTER TABLE personal_devices ADD COLUMN $column $definition");
        echo "Added personal_devices.$column\n";
    }
}

Database::execute(
    "ALTER TABLE device_usage_events MODIFY event_type
     ENUM('connect','disconnect','speed_change','plan_expired','blocked','resumed','time_purchased','auto_renewed') NOT NULL"
);
echo "Extended device_usage_events.event_type\n";

if (!Database::tableExists('payment_intents')) {
    Database::execute(<<<SQL
CREATE TABLE payment_intents (
    intent_id     BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    provider      VARCHAR(20) NOT NULL DEFAULT 'paystack',
    reference     VARCHAR(100) UNIQUE NOT NULL,
    user_id       BIGINT UNSIGNED NOT NULL,
    purpose       ENUM('wallet_topup','invoice') NOT NULL DEFAULT 'wallet_topup',
    invoice_id    BIGINT UNSIGNED NULL,
    amount        DECIMAL(12,2) NOT NULL,
    currency      CHAR(3) NOT NULL DEFAULT 'GHS',
    email         VARCHAR(255) NOT NULL,
    status        ENUM('pending','succeeded','failed','abandoned') DEFAULT 'pending',
    channel       VARCHAR(30) NULL,
    authorization_url VARCHAR(500) NULL,
    provider_message  VARCHAR(255) NULL,
    created_by    BIGINT UNSIGNED NULL,
    created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    completed_at  TIMESTAMP NULL,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (invoice_id) REFERENCES invoices(invoice_id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES staff(staff_id) ON DELETE SET NULL,
    INDEX idx_user (user_id),
    INDEX idx_status (status)
) ENGINE=InnoDB
SQL);
    echo "Created table: payment_intents\n";
}
