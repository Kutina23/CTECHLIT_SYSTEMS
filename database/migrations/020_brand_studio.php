<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; } // command-line only
/**
 * Brand Studio: business-card designs, shared brand kit setting, protected logo folder.
 * Idempotent: safe to run more than once.
 *
 * Run with: php database/migrations/020_brand_studio.php
 */

require_once __DIR__ . '/../../config/database.php';

Database::execute(
    "ALTER TABLE branded_assets MODIFY asset_type ENUM('flyer','powerpoint','letterhead','business_card') NOT NULL"
);
echo "branded_assets.asset_type now includes business_card\n";

$exists = Database::fetchColumn("SELECT COUNT(*) FROM system_settings WHERE setting_key = 'brand_kit'");
if (!$exists) {
    Database::execute(
        "INSERT INTO system_settings (setting_key, setting_value, data_type, description, is_public)
         VALUES ('brand_kit', ?, 'json', 'Brand Studio colours and logo shared by all staff', 0)",
        [json_encode(['primary' => '#7C3AED', 'secondary' => '#06B6D4', 'dark' => '#0F172A', 'logo' => null, 'website' => ''])]
    );
    echo "Seeded brand_kit setting\n";
}

$dir = __DIR__ . '/../../uploads/branding';
if (!is_dir($dir)) {
    mkdir($dir, 0755, true);
}
// Logos are images only; never let anything in this folder execute.
file_put_contents($dir . '/.htaccess', "Options -Indexes\n<FilesMatch \"\\.(php|phtml|phar|pl|py|cgi|sh|html?|svg)$\">\n    Require all denied\n</FilesMatch>\nphp_flag engine off\n");
echo "Prepared uploads/branding\n";
