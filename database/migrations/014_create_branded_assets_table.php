<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; } // command-line only
/**
 * Create branded_assets table for saved corporate assets.
 *
 * Run with: php database/migrations/014_create_branded_assets_table.php
 */

require_once __DIR__ . '/../../config/database.php';

if (Database::tableExists('branded_assets')) {
    die("The branded_assets table already exists.\n");
}

$sql = <<<SQL
CREATE TABLE branded_assets (
    asset_id        BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    asset_type      ENUM('flyer', 'powerpoint', 'letterhead') NOT NULL,
    name            VARCHAR(150) NOT NULL,
    config          JSON NOT NULL,
    preview_data    LONGTEXT NULL,
    created_by      BIGINT UNSIGNED NOT NULL,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES staff(staff_id) ON DELETE CASCADE,
    INDEX idx_asset_type (asset_type),
    INDEX idx_created_by (created_by),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB;
SQL;

try {
    Database::execute($sql);
    echo "Created table: branded_assets\n";
} catch (Throwable $e) {
    echo "Failed to create table: " . $e->getMessage() . "\n";
}
