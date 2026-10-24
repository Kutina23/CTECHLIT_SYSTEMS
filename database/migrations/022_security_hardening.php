<?php
/**
 * Security hardening: shared rate-limit / lockout counters.
 * Idempotent: safe to run more than once.
 *
 * Run with: php database/migrations/022_security_hardening.php
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../../config/database.php';

Database::execute(
    "CREATE TABLE IF NOT EXISTS security_throttle (
        throttle_key VARCHAR(191) NOT NULL PRIMARY KEY,
        hits INT UNSIGNED NOT NULL DEFAULT 0,
        window_start DATETIME NOT NULL,
        updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_updated (updated_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
);
echo "security_throttle ready\n";
