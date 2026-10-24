<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; } // command-line only
/**
 * Device command queue, customer billing (accounts, ledger, invoices) and POS upgrades.
 * Idempotent: safe to run more than once.
 *
 * Run with: php database/migrations/018_device_commands_billing_pos.php
 */

require_once __DIR__ . '/../../config/database.php';

if (!function_exists('migrationColumnExists')) {
    function migrationColumnExists(string $table, string $column): bool
    {
        return (bool) Database::fetchColumn(
            'SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?',
            [$table, $column]
        );
    }
}

$tables = [
    'device_commands' => <<<SQL
CREATE TABLE device_commands (
    command_id     BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    station_id     BIGINT UNSIGNED NOT NULL,
    command        VARCHAR(30) NOT NULL,
    params         JSON,
    status         ENUM('queued','sent','succeeded','failed','expired') DEFAULT 'queued',
    source         ENUM('portal','jarvis','pos','system') DEFAULT 'portal',
    requested_by   BIGINT UNSIGNED NULL,
    result_message VARCHAR(500) NULL,
    created_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    delivered_at   TIMESTAMP NULL,
    completed_at   TIMESTAMP NULL,
    expires_at     DATETIME NOT NULL,
    FOREIGN KEY (station_id) REFERENCES stations(station_id) ON DELETE CASCADE,
    FOREIGN KEY (requested_by) REFERENCES staff(staff_id) ON DELETE SET NULL,
    INDEX idx_station_status (station_id, status),
    INDEX idx_created (created_at)
) ENGINE=InnoDB
SQL,
    'billing_accounts' => <<<SQL
CREATE TABLE billing_accounts (
    user_id            BIGINT UNSIGNED PRIMARY KEY,
    account_number     VARCHAR(20) UNIQUE NOT NULL,
    balance            DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    credit_limit       DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    payment_terms_days INT UNSIGNED NOT NULL DEFAULT 14,
    auto_pay_sessions  BOOLEAN DEFAULT TRUE,
    status             ENUM('active','suspended','closed') DEFAULT 'active',
    notes              TEXT,
    created_at         TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at         TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    INDEX idx_status (status)
) ENGINE=InnoDB
SQL,
    'invoices' => <<<SQL
CREATE TABLE invoices (
    invoice_id      BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    invoice_number  VARCHAR(30) UNIQUE NOT NULL,
    user_id         BIGINT UNSIGNED NOT NULL,
    status          ENUM('draft','issued','partially_paid','paid','void') DEFAULT 'issued',
    issue_date      DATE NOT NULL,
    due_date        DATE NOT NULL,
    subtotal        DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    discount_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    tax_amount      DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    total_amount    DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    amount_paid     DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    notes           TEXT,
    created_by      BIGINT UNSIGNED NULL,
    paid_at         TIMESTAMP NULL,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES staff(staff_id) ON DELETE SET NULL,
    INDEX idx_user (user_id),
    INDEX idx_status_due (status, due_date)
) ENGINE=InnoDB
SQL,
    'invoice_items' => <<<SQL
CREATE TABLE invoice_items (
    invoice_item_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    invoice_id      BIGINT UNSIGNED NOT NULL,
    transaction_id  BIGINT UNSIGNED NULL,
    description     VARCHAR(255) NOT NULL,
    quantity        DECIMAL(10,2) NOT NULL DEFAULT 1.00,
    unit_price      DECIMAL(10,2) NOT NULL,
    total_price     DECIMAL(12,2) NOT NULL,
    FOREIGN KEY (invoice_id) REFERENCES invoices(invoice_id) ON DELETE CASCADE,
    FOREIGN KEY (transaction_id) REFERENCES transactions(transaction_id) ON DELETE SET NULL,
    INDEX idx_invoice (invoice_id)
) ENGINE=InnoDB
SQL,
    'invoice_payments' => <<<SQL
CREATE TABLE invoice_payments (
    payment_id     BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    invoice_id     BIGINT UNSIGNED NOT NULL,
    amount         DECIMAL(12,2) NOT NULL,
    payment_method ENUM('cash','mobile_money','card','bank_transfer','wallet','other') NOT NULL,
    reference      VARCHAR(100) NULL,
    received_by    BIGINT UNSIGNED NULL,
    created_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (invoice_id) REFERENCES invoices(invoice_id) ON DELETE CASCADE,
    FOREIGN KEY (received_by) REFERENCES staff(staff_id) ON DELETE SET NULL,
    INDEX idx_invoice (invoice_id)
) ENGINE=InnoDB
SQL,
    'billing_ledger' => <<<SQL
CREATE TABLE billing_ledger (
    entry_id       BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id        BIGINT UNSIGNED NOT NULL,
    entry_type     ENUM('topup','charge','payment','refund','adjustment') NOT NULL,
    amount         DECIMAL(12,2) NOT NULL,
    balance_after  DECIMAL(12,2) NOT NULL,
    payment_method VARCHAR(30) NULL,
    reference      VARCHAR(100) NULL,
    description    VARCHAR(255) NOT NULL,
    invoice_id     BIGINT UNSIGNED NULL,
    transaction_id BIGINT UNSIGNED NULL,
    staff_id       BIGINT UNSIGNED NULL,
    created_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (invoice_id) REFERENCES invoices(invoice_id) ON DELETE SET NULL,
    FOREIGN KEY (transaction_id) REFERENCES transactions(transaction_id) ON DELETE SET NULL,
    FOREIGN KEY (staff_id) REFERENCES staff(staff_id) ON DELETE SET NULL,
    INDEX idx_user_created (user_id, created_at)
) ENGINE=InnoDB
SQL,
];

foreach ($tables as $table => $sql) {
    if (Database::tableExists($table)) {
        echo "Table $table already exists\n";
        continue;
    }
    Database::execute($sql);
    echo "Created table: $table\n";
}

if (!migrationColumnExists('transactions', 'invoice_id')) {
    Database::execute('ALTER TABLE transactions ADD COLUMN invoice_id BIGINT UNSIGNED NULL AFTER booking_id, ADD INDEX idx_invoice (invoice_id)');
    echo "Added transactions.invoice_id\n";
}
if (!migrationColumnExists('transactions', 'payment_method')) {
    Database::execute("ALTER TABLE transactions ADD COLUMN payment_method VARCHAR(30) NULL AFTER payment_method_id");
    echo "Added transactions.payment_method\n";
}

Database::execute(
    "ALTER TABLE pos_transactions
     MODIFY service_type ENUM('internet','gaming','printing','retail','other') NOT NULL,
     MODIFY payment_method ENUM('cash','mobile_money','card','bank_transfer','wallet','account','other') DEFAULT 'cash'"
);
$posColumns = [
    'user_id' => 'BIGINT UNSIGNED NULL AFTER station_id',
    'receipt_number' => 'VARCHAR(50) NULL AFTER status',
    'ledger_transaction_id' => 'BIGINT UNSIGNED NULL AFTER receipt_number',
    'discount_amount' => 'DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER amount',
    'amount_tendered' => 'DECIMAL(10,2) NULL AFTER discount_amount',
    'items' => 'JSON NULL AFTER notes',
];
foreach ($posColumns as $column => $definition) {
    if (!migrationColumnExists('pos_transactions', $column)) {
        Database::execute("ALTER TABLE pos_transactions ADD COLUMN $column $definition");
        echo "Added pos_transactions.$column\n";
    }
}

$permissions = [
    ['billing.view', 'View Customer Billing', 'Billing'],
    ['billing.manage', 'Manage Billing, Invoices & Payments', 'Billing'],
    ['pos.view', 'View POS', 'POS & Finance'],
    ['pos.manage', 'Manage POS Sales', 'POS & Finance'],
];
foreach ($permissions as [$key, $description, $category]) {
    Database::execute(
        'INSERT IGNORE INTO permissions (permission_key, description, category) VALUES (?, ?, ?)',
        [$key, $description, $category]
    );
}
echo "Permissions ensured\n";
