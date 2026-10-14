<?php
/**
 * CTECHLIT & SYSTEMS — Migration: Add MAC addresses to existing stations
 * 
 * This migration adds MAC addresses to the sample stations in the database.
 * In production, these should be updated with the actual MAC addresses of the physical machines.
 * 
 * Run this migration by accessing: /portal/admin/migrate.php
 * Or execute directly via: php database/migrations/009_add_station_mac_addresses.php
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/functions.php';

// Only run if stations table exists and mac_address column exists
if (!Database::tableExists('stations')) {
    die("Stations table does not exist\n");
}

// Check if mac_address column exists
$columnExists = Database::fetchColumn(
    "SELECT COUNT(*) FROM information_schema.columns 
     WHERE table_schema = DATABASE() 
     AND table_name = 'stations' 
     AND column_name = 'mac_address'"
);

if (!$columnExists) {
    die("mac_address column does not exist in stations table\n");
}

// Check if stations already have MAC addresses
$stationsWithoutMac = Database::fetchAll(
    "SELECT station_id, station_code, name, ip_address 
     FROM stations 
     WHERE mac_address IS NULL OR mac_address = ''"
);

if (empty($stationsWithoutMac)) {
    echo "All stations already have MAC addresses assigned.\n";
    exit(0);
}

echo "Found " . count($stationsWithoutMac) . " station(s) without MAC addresses.\n";
echo "Assigning sample MAC addresses based on IP addresses...\n\n";

// Map IP addresses to sample MAC addresses
// In production, replace these with actual MAC addresses from the physical machines
$macByIp = [
    '192.168.1.101' => 'AA:BB:CC:DD:EE:01',
    '192.168.1.102' => 'AA:BB:CC:DD:EE:02',
    '192.168.1.103' => 'AA:BB:CC:DD:EE:03',
    '192.168.1.104' => 'AA:BB:CC:DD:EE:04',
    '192.168.1.105' => 'AA:BB:CC:DD:EE:05',
    '192.168.1.106' => 'AA:BB:CC:DD:EE:06',
];

$updatedCount = 0;

foreach ($stationsWithoutMac as $station) {
    $ipAddress = $station['ip_address'] ?? '';
    $macAddress = $macByIp[$ipAddress] ?? null;
    
    if ($macAddress) {
        // Check if MAC already exists on another station
        $existing = Database::fetchOne(
            "SELECT station_id FROM stations WHERE mac_address = ? AND station_id != ?",
            [$macAddress, $station['station_id']]
        );
        
        if ($existing) {
            echo "  WARNING: MAC $macAddress already assigned to station {$existing['station_id']}, skipping {$station['station_code']}\n";
            continue;
        }
        
        Database::execute(
            "UPDATE stations SET mac_address = ?, updated_at = NOW() WHERE station_id = ?",
            [$macAddress, $station['station_id']]
        );
        
        echo "  ✓ Assigned MAC $macAddress to {$station['station_code']} ({$station['name']})\n";
        $updatedCount++;
    } else {
        echo "  ⚠ No MAC address mapping for IP {$ipAddress} ({$station['station_code']})\n";
        echo "    Please manually add the MAC address in the admin panel.\n";
    }
}

echo "\nMigration completed. Updated $updatedCount station(s).\n";

if ($updatedCount > 0) {
    echo "\nIMPORTANT: Replace sample MAC addresses with actual MAC addresses from physical machines.\n";
    echo "To find MAC addresses on Windows stations, run:\n";
    echo "  ipconfig /all\n";
    echo "  OR\n";
    echo "  Get-NetAdapter | Select-Object Name, MacAddress\n";
}
