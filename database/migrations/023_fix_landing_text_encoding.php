<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; } // command-line only
/**
 * Repair homepage text that was saved double-encoded (e.g. "—" stored as "â€”").
 * Only values containing the tell-tale sequences are touched, and only when the repaired
 * text is valid UTF-8. Idempotent: safe to run more than once.
 *
 * Run with: php database/migrations/023_fix_landing_text_encoding.php
 */

require_once __DIR__ . '/../../config/database.php';

$tables = [
    'why_features' => 'feature_id', 'landing_faqs' => 'faq_id', 'about_features' => null, 'services' => 'service_id',
    'pricing_plans' => null, 'testimonials' => null, 'roadmap_items' => null, 'personas' => null,
    'spotlight_items' => null, 'how_it_works' => null, 'trust_badges' => null, 'gallery_items' => null,
    'system_settings' => null, 'help_resources' => 'resource_id',
];

function repairText(string $value): ?string
{
    if (!preg_match('/\x{00C3}|\x{00C2}|\x{00E2}\x{20AC}/u', $value)) {
        return null;
    }
    $fixed = @mb_convert_encoding($value, 'Windows-1252', 'UTF-8');
    if (!is_string($fixed) || !mb_check_encoding($fixed, 'UTF-8') || $fixed === $value) {
        return null;
    }
    return $fixed;
}

$total = 0;
foreach ($tables as $table => $pk) {
    if (!Database::tableExists($table)) {
        continue;
    }
    if ($pk === null) {
        $pk = (string) Database::fetchColumn(
            "SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_KEY = 'PRI' LIMIT 1",
            [$table]
        );
    }
    $columns = array_column(Database::fetchAll(
        "SELECT COLUMN_NAME FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND DATA_TYPE IN ('varchar','text','mediumtext','longtext','tinytext')",
        [$table]
    ), 'COLUMN_NAME');
    foreach ($columns as $column) {
        foreach (Database::fetchAll("SELECT `$pk` AS id, `$column` AS value FROM `$table` WHERE `$column` IS NOT NULL") as $row) {
            $fixed = repairText((string) $row['value']);
            if ($fixed !== null) {
                Database::execute("UPDATE `$table` SET `$column` = ? WHERE `$pk` = ?", [$fixed, $row['id']]);
                $total++;
                echo "Fixed $table.$column #{$row['id']}\n";
            }
        }
    }
}
echo "Repaired $total value(s)\n";
