<?php
/**
 * Grant full access to any existing CEO staff account.
 *
 * This ensures legacy or manually created CEO accounts receive
 * every known permission in staff_permissions, matching the
 * implicit wildcard used at runtime.
 *
 * Run with: php database/migrations/012_grant_ceo_full_access.php
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';

if (!Database::tableExists('staff')) {
    die("The staff table does not exist.\n");
}

$ceoStaff = Database::fetchAll(
    "SELECT staff_id, full_name, role FROM staff WHERE role = 'ceo' AND is_active = 1"
);

if (!$ceoStaff) {
    echo "No active CEO staff accounts found. Nothing to update.\n";
    exit;
}

$allPerms = getAllPermissions();
$updated = 0;

foreach ($ceoStaff as $staff) {
    $staffId = (int) $staff['staff_id'];

    Database::beginTransaction();
    try {
        // Grant wildcard
        Database::execute(
            "INSERT INTO staff_permissions (staff_id, permission, granted) VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE granted = VALUES(granted)",
            [$staffId, '*', 1]
        );

        // Grant every known permission
        foreach ($allPerms as $permKey => $_label) {
            Database::execute(
                "INSERT INTO staff_permissions (staff_id, permission, granted) VALUES (?, ?, ?)
                 ON DUPLICATE KEY UPDATE granted = VALUES(granted)",
                [$staffId, $permKey, 1]
            );
        }

        Database::commit();
        $updated++;
        echo "Updated CEO staff_id={$staffId} name=" . $staff['full_name'] . "\n";
    } catch (Throwable $e) {
        Database::rollback();
        echo "Failed to update staff_id={$staffId}: " . $e->getMessage() . "\n";
    }
}

echo "\nGranted full access to {$updated} CEO account(s).\n";
