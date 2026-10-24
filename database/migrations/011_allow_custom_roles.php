<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; } // command-line only
/**
 * Allow arbitrary custom role titles on staff accounts.
 *
 * Run with: php database/migrations/011_allow_custom_roles.php
 */

require_once __DIR__ . '/../../config/database.php';

if (!Database::tableExists('staff')) {
    throw new RuntimeException('The staff table does not exist. Apply the database schema first.');
}

$columnInfo = Database::fetchOne(
    "SELECT COLUMN_TYPE, DATA_TYPE, CHARACTER_MAXIMUM_LENGTH
     FROM information_schema.columns
     WHERE table_schema = DATABASE()
       AND table_name = 'staff'
       AND column_name = 'role'"
);

if (!$columnInfo) {
    throw new RuntimeException('The staff.role column does not exist.');
}

$isEnum = stripos($columnInfo['COLUMN_TYPE'] ?? '', 'enum') !== false;

if (!$isEnum) {
    echo "staff.role is already {$columnInfo['DATA_TYPE']}. No change needed.\n";
    exit;
}

// Convert ENUM to VARCHAR while preserving existing values
Database::execute("ALTER TABLE staff MODIFY COLUMN role VARCHAR(100) DEFAULT 'attendant'");

echo "Changed staff.role from ENUM to VARCHAR(100).\n";
