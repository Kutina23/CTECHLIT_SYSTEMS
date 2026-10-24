<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; } // command-line only
/**
 * CTECHLIT & SYSTEMS — Migration Runner
 * Run database migrations in order
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/functions.php';

// Create migrations table if not exists
if (!Database::tableExists('migrations')) {
    Database::execute("
        CREATE TABLE IF NOT EXISTS migrations (
            id INT AUTO_INCREMENT PRIMARY KEY,
            migration VARCHAR(255) NOT NULL,
            batch INT NOT NULL DEFAULT 1,
            executed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY unique_migration (migration)
        ) ENGINE=InnoDB;
    ");
}

// Get executed migrations
$executed = Database::fetchAll("SELECT migration FROM migrations");
$executedMigrations = array_column($executed, 'migration');

// Find all migration files
$migrationDir = __DIR__;
$files = glob($migrationDir . '/*.sql') + glob($migrationDir . '/*.php');
sort($files);

$batch = (int) Database::fetchColumn("SELECT MAX(batch) FROM migrations") + 1;
$executedCount = 0;
$skippedCount = 0;
$errorCount = 0;

echo "CTECHLIT & SYSTEMS — Migration Runner\n";
echo "=====================================\n\n";

foreach ($files as $file) {
    $filename = basename($file);
    
    // Skip if already executed
    if (in_array($filename, $executedMigrations)) {
        echo "  ⊗ $filename (already executed)\n";
        $skippedCount++;
        continue;
    }
    
    echo "  → Executing $filename... ";
    
    try {
        if (pathinfo($file, PATHINFO_EXTENSION) === 'sql') {
            // Execute SQL file
            $sql = file_get_contents($file);
            
            // Split by delimiter and execute each statement
            $statements = array_filter(
                array_map('trim', explode(';', $sql)),
                function($stmt) { return !empty($stmt) && !preg_match('/^--/', $stmt); }
            );
            
            foreach ($statements as $statement) {
                if (!empty($statement)) {
                    Database::execute($statement);
                }
            }
        } else {
            // Execute PHP migration
            require_once $file;
        }
        
        // Record migration
        Database::execute(
            "INSERT INTO migrations (migration, batch) VALUES (?, ?)",
            [$filename, $batch]
        );
        
        echo "✓\n";
        $executedCount++;
    } catch (Exception $e) {
        echo "✗\n";
        echo "    Error: " . $e->getMessage() . "\n";
        $errorCount++;
    }
}

echo "\n=====================================\n";
echo "Migrations completed:\n";
echo "  Executed: $executedCount\n";
echo "  Skipped:  $skippedCount\n";
echo "  Errors:   $errorCount\n";
echo "=====================================\n";

if ($errorCount > 0) {
    exit(1);
}

echo "\nAll migrations completed successfully!\n";
