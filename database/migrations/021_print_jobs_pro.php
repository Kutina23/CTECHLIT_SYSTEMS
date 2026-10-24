<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; } // command-line only
/**
 * Print jobs: no fake sessions, real pricing & payment, IPP dispatch tracking, protected uploads.
 * Idempotent: safe to run more than once.
 *
 * Run with: php database/migrations/021_print_jobs_pro.php
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

// A print job no longer needs a station session or service-usage row.
Database::execute('ALTER TABLE print_jobs MODIFY usage_id BIGINT UNSIGNED NULL, MODIFY station_id BIGINT UNSIGNED NULL');
echo "print_jobs.usage_id / station_id are now optional\n";

$columns = [
    'staff_id' => 'BIGINT UNSIGNED NULL AFTER peripheral_id',
    'transaction_id' => 'BIGINT UNSIGNED NULL AFTER staff_id',
    'unit_price' => 'DECIMAL(8,2) NOT NULL DEFAULT 0.00 AFTER duplex',
    'amount' => 'DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER unit_price',
    'payment_status' => "ENUM('unpaid','paid','on_account','refunded','waived') NOT NULL DEFAULT 'unpaid' AFTER amount",
    'dispatch' => "ENUM('manual','ipp') NOT NULL DEFAULT 'manual' AFTER payment_status",
    'printer_job_id' => 'INT NULL AFTER dispatch',
    'file_size' => 'INT UNSIGNED NULL AFTER file_path',
    'mime_type' => 'VARCHAR(100) NULL AFTER file_size',
    'notes' => 'VARCHAR(255) NULL AFTER error_message',
];
foreach ($columns as $column => $definition) {
    if (!migrationColumnExists('print_jobs', $column)) {
        Database::execute("ALTER TABLE print_jobs ADD COLUMN $column $definition");
        echo "Added print_jobs.$column\n";
    }
}
$hasIndex = Database::fetchColumn("SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'print_jobs' AND INDEX_NAME = 'idx_printer_queue'");
if (!$hasIndex) {
    Database::execute('ALTER TABLE print_jobs ADD INDEX idx_printer_queue (peripheral_id, status, priority, created_at)');
    echo "Added queue index\n";
}

// Fake "Print queue session" rows created by the old code held stations open; close any that exist.
$closed = Database::execute(
    "UPDATE sessions SET status = 'cancelled', ended_at = COALESCE(ended_at, NOW()), notes = CONCAT(notes, ' (closed: print jobs no longer use sessions)')
     WHERE status = 'active' AND notes = 'Print queue session'"
);
echo "Closed $closed leftover print-queue session(s)\n";

$dir = __DIR__ . '/../../uploads/print-jobs';
if (!is_dir($dir)) {
    mkdir($dir, 0750, true);
}
// Customer documents are only served through the authenticated download endpoint.
file_put_contents($dir . '/.htaccess', "Require all denied\n");
echo "Protected uploads/print-jobs\n";
