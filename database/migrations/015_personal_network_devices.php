<?php
/**
 * Create personal_devices and device_usage_events tables for BYOD / hotspot management.
 *
 * Run with: php database/migrations/015_personal_network_devices.php
 */

require_once __DIR__ . '/../../config/database.php';

if (Database::tableExists('personal_devices')) {
    die("The personal_devices table already exists.\n");
}

$sql = <<<SQL
CREATE TABLE personal_devices (
    device_id        BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id          BIGINT UNSIGNED NOT NULL,
    session_id       BIGINT UNSIGNED NULL,
    device_name      VARCHAR(100) NOT NULL,
    device_type      ENUM('phone','laptop','tablet','other') DEFAULT 'phone',
    mac_address      VARCHAR(17) UNIQUE NOT NULL,
    ip_address       VARCHAR(45) NULL,
    hostname         VARCHAR(100) NULL,
    status           ENUM('online','offline','blocked','suspended') DEFAULT 'offline',
    plan_id          BIGINT UNSIGNED NULL,
    minutes_purchased INT UNSIGNED DEFAULT 0,
    minutes_remaining INT UNSIGNED DEFAULT 0,
    data_used_mb     BIGINT UNSIGNED DEFAULT 0,
    speed_limit_kbps INT UNSIGNED NULL,
    connected_at     TIMESTAMP NULL,
    disconnected_at   TIMESTAMP NULL,
    last_seen_at     TIMESTAMP NULL,
    created_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (session_id) REFERENCES sessions(session_id) ON DELETE SET NULL,
    FOREIGN KEY (plan_id) REFERENCES pricing_plans(plan_id) ON DELETE SET NULL,
    INDEX idx_user (user_id),
    INDEX idx_mac (mac_address),
    INDEX idx_status (status),
    INDEX idx_session (session_id),
    INDEX idx_plan (plan_id)
) ENGINE=InnoDB;
SQL;

$eventsSql = <<<SQL
CREATE TABLE device_usage_events (
    event_id         BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    device_id        BIGINT UNSIGNED NOT NULL,
    user_id          BIGINT UNSIGNED NOT NULL,
    event_type       ENUM('connect','disconnect','speed_change','plan_expired','blocked','resumed') NOT NULL,
    bytes_transferred BIGINT UNSIGNED DEFAULT 0,
    minutes_consumed INT UNSIGNED DEFAULT 0,
    speed_limit_kbps INT UNSIGNED NULL,
    trigger_source   ENUM('system','staff','customer','cron') DEFAULT 'system',
    notes            TEXT,
    created_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (device_id) REFERENCES personal_devices(device_id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    INDEX idx_device (device_id),
    INDEX idx_user (user_id),
    INDEX idx_event_type (event_type),
    INDEX idx_created (created_at)
) ENGINE=InnoDB ROW_FORMAT=COMPRESSED KEY_BLOCK_SIZE=8;
SQL;

try {
    Database::execute($sql);
    echo "Created table: personal_devices\n";
} catch (Throwable $e) {
    echo "Failed to create personal_devices: " . $e->getMessage() . "\n";
}

try {
    Database::execute($eventsSql);
    echo "Created table: device_usage_events\n";
} catch (Throwable $e) {
    echo "Failed to create device_usage_events: " . $e->getMessage() . "\n";
}
