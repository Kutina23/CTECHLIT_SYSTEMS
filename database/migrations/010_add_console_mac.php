<?php
/**
 * CTECHLIT & SYSTEMS — Migration: Add MAC addresses to peripherals and game consoles
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/functions.php';

if (!Database::tableExists('peripherals')) {
    die("peripherals table does not exist\n");
}

// Add mac_address column if not exists
$columnExists = Database::fetchColumn(
    "SELECT COUNT(*) FROM information_schema.columns 
     WHERE table_schema = DATABASE() 
     AND table_name = 'peripherals' 
     AND column_name = 'mac_address'"
);

if (!$columnExists) {
    Database::execute("ALTER TABLE peripherals ADD COLUMN mac_address VARCHAR(17) DEFAULT NULL AFTER serial_number");
    echo "Added mac_address column to peripherals table\n";
}

// Update game console MAC addresses
$consoles = Database::fetchAll(
    "SELECT peripheral_id, name, model, station_id
     FROM peripherals
     WHERE peripheral_type = 'game_console'"
);

$macByModel = [
    'CFI-1215A' => 'AA:BB:CC:DD:EE:11', // PS5
    'RRT-00015' => 'AA:BB:CC:DD:EE:12', // Xbox Series X
];

$updatedCount = 0;
foreach ($consoles as $console) {
    $mac = $macByModel[$console['model']] ?? null;
    if ($mac) {
        Database::execute(
            "UPDATE peripherals SET mac_address = ?, updated_at = NOW() WHERE peripheral_id = ?",
            [$mac, $console['peripheral_id']]
        );
        echo "Updated MAC for {$console['name']} ({$console['model']}): $mac\n";
        $updatedCount++;
    } else {
        echo "No MAC mapping for model: {$console['model']}\n";
    }
}

echo "\nMigration completed. Updated $updatedCount console(s).\n";
echo "NOTE: Replace sample MACs with actual console MAC addresses.\n";
echo "On PS5: Settings > System > System Software > Console Information\n";
echo "On Xbox: Settings > Network > Advanced Settings > MAC Address\n";
