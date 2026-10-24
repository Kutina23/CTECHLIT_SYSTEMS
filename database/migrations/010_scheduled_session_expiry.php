<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; } // command-line only
/**
 * Add scheduled expiry timestamps to station sessions.
 *
 * Run with: php database/migrations/010_scheduled_session_expiry.php
 */

require_once __DIR__ . '/../../config/functions.php';

if (!Database::tableExists('sessions')) {
    throw new RuntimeException('The sessions table does not exist. Apply the database schema first.');
}

$columnExists = (int) Database::fetchColumn(
    "SELECT COUNT(*) FROM information_schema.columns
     WHERE table_schema = DATABASE()
       AND table_name = 'sessions'
       AND column_name = 'scheduled_end_at'"
);

if (!$columnExists) {
    Database::execute('ALTER TABLE sessions ADD COLUMN scheduled_end_at DATETIME NULL AFTER ended_at');
    echo "Added sessions.scheduled_end_at.\n";
} else {
    echo "sessions.scheduled_end_at already exists.\n";
}

$indexExists = (int) Database::fetchColumn(
    "SELECT COUNT(*) FROM information_schema.statistics
     WHERE table_schema = DATABASE()
       AND table_name = 'sessions'
       AND index_name = 'idx_scheduled_expiry'"
);

if (!$indexExists) {
    Database::execute('ALTER TABLE sessions ADD INDEX idx_scheduled_expiry (status, scheduled_end_at)');
    echo "Added idx_scheduled_expiry.\n";
} else {
    echo "idx_scheduled_expiry already exists.\n";
}

if (Database::tableExists('pos_transactions')) {
    $backfilledSessions = Database::execute(
        "UPDATE sessions s
         SET s.scheduled_end_at = (
             SELECT MAX(DATE_ADD(p.created_at, INTERVAL p.usage_minutes MINUTE))
             FROM pos_transactions p
             WHERE p.station_id = s.station_id
               AND p.created_at >= s.started_at
               AND p.status = 'paid'
               AND p.usage_minutes > 0
         )
         WHERE s.status = 'active'
           AND s.scheduled_end_at IS NULL
           AND EXISTS (
               SELECT 1 FROM pos_transactions p
               WHERE p.station_id = s.station_id
                 AND p.created_at >= s.started_at
                 AND p.status = 'paid'
                 AND p.usage_minutes > 0
           )"
    );
    echo "Backfilled scheduled end times for $backfilledSessions active POS session(s).\n";
}

echo "Scheduled session expiry migration complete.\n";
