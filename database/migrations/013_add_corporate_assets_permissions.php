<?php
/**
 * Add branded assets permissions to the permissions table.
 *
 * Run with: php database/migrations/013_add_corporate_assets_permissions.php
 */

require_once __DIR__ . '/../../config/database.php';

if (!Database::tableExists('permissions')) {
    die("The permissions table does not exist.\n");
}

$permissions = [
    ['branded_assets.view', 'View Branded Assets', 'Branded Assets'],
    ['branded_assets.create', 'Generate Branded Assets', 'Branded Assets'],
    ['branded_assets.edit', 'Edit Branded Asset Templates', 'Branded Assets'],
    ['branded_assets.delete', 'Delete Branded Asset Templates', 'Branded Assets'],
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
