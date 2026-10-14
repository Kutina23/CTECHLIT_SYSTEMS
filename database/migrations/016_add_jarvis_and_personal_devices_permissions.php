<?php
/**
 * Add jarvis and personal devices permissions to the permissions table.
 *
 * Run with: php database/migrations/016_add_jarvis_and_personal_devices_permissions.php
 */

require_once __DIR__ . '/../../config/database.php';

if (!Database::tableExists('permissions')) {
    die("The permissions table does not exist.\n");
}

$permissions = [
    ['jarvis.view', 'View Jarvis Assistant', 'Jarvis'],
    ['jarvis.use', 'Use Jarvis Assistant', 'Jarvis'],
    ['personal_devices.view', 'View Personal Devices', 'Personal Devices'],
    ['personal_devices.manage', 'Manage Personal Devices', 'Personal Devices'],
];

$added = 0;

foreach ($permissions as $perm) {
    $exists = Database::fetchOne(
        "SELECT permission_key FROM permissions WHERE permission_key = ?",
        [$perm[0]]
    );

    if (!$exists) {
        Database::execute(
            "INSERT INTO permissions (permission_key, description, category) VALUES (?, ?, ?)",
            $perm
        );
        echo "Added permission: {$perm[0]}\n";
        $added++;
    } else {
        echo "Permission already exists: {$perm[0]}\n";
    }
}

echo "\nAdded {$added} new permission(s).\n";
