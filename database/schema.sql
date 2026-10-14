-- ============================================================
-- CTECHLIT & SYSTEMS — Internet Café & Digital Services
-- Optimized MySQL Schema for High-Load Production
-- ============================================================
-- Engine: InnoDB (ACID compliant, row-level locking)
-- Charset: utf8mb4 (full Unicode support)
-- Collation: utf8mb4_unicode_ci
-- ============================================================

CREATE DATABASE IF NOT EXISTS ctechlit_systems
  DEFAULT CHARACTER SET utf8mb4
  DEFAULT COLLATE utf8mb4_unicode_ci;

USE ctechlit_systems;

-- ============================================================
-- 1. USERS & AUTHENTICATION
-- ============================================================

-- Customers / Walk-in users
CREATE TABLE users (
    user_id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    email            VARCHAR(255) UNIQUE,
    phone            VARCHAR(20) UNIQUE,
    full_name        VARCHAR(100) NOT NULL,
    password_hash    VARCHAR(255),
    membership_tier  ENUM('standard', 'premium', 'vip') DEFAULT 'standard',
    total_visits     INT UNSIGNED DEFAULT 0,
    total_spent      DECIMAL(10,2) DEFAULT 0.00,
    created_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    last_login_at    TIMESTAMP NULL,
    is_active        BOOLEAN DEFAULT TRUE,
    INDEX idx_phone (phone),
    INDEX idx_email (email),
    INDEX idx_membership (membership_tier),
    INDEX idx_created (created_at)
) ENGINE=InnoDB;

-- Staff / Attendants
CREATE TABLE staff (
    staff_id         BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    email            VARCHAR(255) UNIQUE NOT NULL,
    phone            VARCHAR(20) UNIQUE NOT NULL,
    full_name        VARCHAR(100) NOT NULL,
    password_hash    VARCHAR(255) NOT NULL,
    role             VARCHAR(100) DEFAULT 'attendant',
    station_id       BIGINT UNSIGNED NULL,
    is_on_duty       BOOLEAN DEFAULT FALSE,
    hired_at         DATE NOT NULL,
    created_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    last_login_at    TIMESTAMP NULL,
    is_active        BOOLEAN DEFAULT TRUE,
    INDEX idx_role (role),
    INDEX idx_station (station_id),
    INDEX idx_on_duty (is_on_duty)
) ENGINE=InnoDB;

-- ============================================================
-- 1.1 PERMISSIONS & RBAC
-- ============================================================

-- Granular permissions catalog
CREATE TABLE permissions (
    permission_id    BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    permission_key   VARCHAR(100) UNIQUE NOT NULL,
    description      VARCHAR(255) NOT NULL,
    category         VARCHAR(50) NOT NULL,
    created_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_category (category),
    INDEX idx_key (permission_key)
) ENGINE=InnoDB;

-- Staff custom permissions (overrides role defaults)
CREATE TABLE staff_permissions (
    staff_permission_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    staff_id         BIGINT UNSIGNED NOT NULL,
    permission       VARCHAR(100) NOT NULL,
    granted          BOOLEAN DEFAULT TRUE,
    created_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (staff_id) REFERENCES staff(staff_id) ON DELETE CASCADE,
    UNIQUE KEY uk_staff_permission (staff_id, permission),
    INDEX idx_staff (staff_id)
) ENGINE=InnoDB;

-- Insert default permissions
INSERT INTO permissions (permission_key, description, category) VALUES
-- Dashboard
('dashboard.view', 'View Dashboard', 'Dashboard'),
('dashboard.stats', 'View Statistics', 'Dashboard'),
-- Users
('users.view', 'View Users', 'Users'),
('users.create', 'Create Users', 'Users'),
('users.edit', 'Edit Users', 'Users'),
('users.delete', 'Delete Users', 'Users'),
-- Stations
('stations.view', 'View Stations', 'Stations'),
('stations.manage', 'Manage Stations', 'Stations'),
('stations.control', 'Control Stations', 'Stations'),
-- Sessions
('sessions.view', 'View Sessions', 'Sessions'),
('sessions.manage', 'Manage Sessions', 'Sessions'),
('sessions.end', 'End Sessions', 'Sessions'),
-- Services
('services.view', 'View Services', 'Services'),
('services.create', 'Create Services', 'Services'),
('services.edit', 'Edit Services', 'Services'),
('services.delete', 'Delete Services', 'Services'),
-- Print Jobs
('print_jobs.view', 'View Print Jobs', 'Print Jobs'),
('print_jobs.manage', 'Manage Print Jobs', 'Print Jobs'),
('print_jobs.cancel', 'Cancel Print Jobs', 'Print Jobs'),
-- Transactions
('transactions.view', 'View Transactions', 'Transactions'),
('transactions.refund', 'Process Refunds', 'Transactions'),
('transactions.reports', 'View Financial Reports', 'Transactions'),
-- Inventory
('inventory.view', 'View Inventory', 'Inventory'),
('inventory.manage', 'Manage Inventory', 'Inventory'),
('inventory.adjust', 'Adjust Stock', 'Inventory'),
-- Staff
('staff.view', 'View Staff', 'Staff'),
('staff.create', 'Create Staff', 'Staff'),
('staff.edit', 'Edit Staff', 'Staff'),
('staff.delete', 'Delete Staff', 'Staff'),
('staff.assign_permissions', 'Assign Permissions', 'Staff'),
-- Branded Assets
('branded_assets.view', 'View Branded Assets', 'Branded Assets'),
('branded_assets.create', 'Generate Branded Assets', 'Branded Assets'),
('branded_assets.edit', 'Edit Branded Asset Templates', 'Branded Assets'),
('branded_assets.delete', 'Delete Branded Asset Templates', 'Branded Assets'),
-- Settings
('settings.view', 'View Settings', 'Settings'),
('settings.edit', 'Edit Settings', 'Settings'),
-- Reports
('reports.view', 'View Reports', 'Reports'),
('reports.export', 'Export Reports', 'Reports'),
-- Feedback
('feedback.view', 'View Feedback', 'Feedback'),
('feedback.respond', 'Respond to Feedback', 'Feedback'),
-- Support
('support.view', 'View Support Tickets', 'Support'),
('support.manage', 'Manage Support Tickets', 'Support');

-- ============================================================
-- 2. STATIONS & HARDWARE
-- ============================================================

-- Physical workstations / client computers
CREATE TABLE stations (
    station_id       BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    station_code     VARCHAR(20) UNIQUE NOT NULL,  -- e.g., "ST-01"
    name             VARCHAR(50) NOT NULL,
    location         VARCHAR(100),                 -- e.g., "Zone A", "Gaming Corner"
    status           ENUM('available', 'occupied', 'maintenance', 'offline') DEFAULT 'available',
    specs            JSON,                         -- CPU, RAM, GPU, OS
    ip_address       VARCHAR(45),
    mac_address      VARCHAR(17) UNIQUE,
    last_heartbeat   TIMESTAMP NULL,
    created_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_status (status),
    INDEX idx_location (location),
    INDEX idx_heartbeat (last_heartbeat)
) ENGINE=InnoDB;

-- Peripheral devices (printers, scanners, etc.)
CREATE TABLE peripherals (
    peripheral_id    BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    peripheral_type  ENUM('printer', 'scanner', 'game_console', 'router', 'other') NOT NULL,
    name             VARCHAR(100) NOT NULL,
    model            VARCHAR(100),
    serial_number    VARCHAR(100) UNIQUE,
    station_id       BIGINT UNSIGNED NULL,
    status           ENUM('online', 'offline', 'error', 'maintenance') DEFAULT 'online',
    config           JSON,
    installed_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_maintenance TIMESTAMP NULL,
    created_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (station_id) REFERENCES stations(station_id) ON DELETE SET NULL,
    INDEX idx_type (peripheral_type),
    INDEX idx_station (station_id),
    INDEX idx_status (status)
) ENGINE=InnoDB;

-- ============================================================
-- 2.1 POS, FINANCE & AUDIT TRACKING
-- ============================================================

-- Staff POS terminals assigned to specific staff and devices
CREATE TABLE pos_terminals (
    pos_terminal_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    staff_id        BIGINT UNSIGNED NOT NULL,
    station_id     BIGINT UNSIGNED NULL,
    terminal_name   VARCHAR(100) NOT NULL,
    service_focus   ENUM('internet', 'gaming', 'printing', 'mixed') DEFAULT 'mixed',
    is_active      BOOLEAN DEFAULT TRUE,
    assigned_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (staff_id) REFERENCES staff(staff_id) ON DELETE CASCADE,
    FOREIGN KEY (station_id) REFERENCES stations(station_id) ON DELETE SET NULL,
    INDEX idx_staff (staff_id),
    INDEX idx_station (station_id),
    INDEX idx_service_focus (service_focus)
) ENGINE=InnoDB;

-- Finance records for internet, gaming, printing and related services
CREATE TABLE pos_transactions (
    pos_transaction_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    staff_id           BIGINT UNSIGNED NOT NULL,
    station_id         BIGINT UNSIGNED NULL,
    customer_name      VARCHAR(100) NOT NULL,
    service_type       ENUM('internet', 'gaming', 'printing', 'other') NOT NULL,
    amount             DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    payment_method     ENUM('cash', 'mobile_money', 'card', 'bank_transfer', 'other') DEFAULT 'cash',
    status             ENUM('pending', 'paid', 'cancelled', 'refunded') DEFAULT 'paid',
    usage_minutes      INT UNSIGNED DEFAULT 0,
    notes              TEXT,
    created_at         TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at         TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (staff_id) REFERENCES staff(staff_id) ON DELETE CASCADE,
    FOREIGN KEY (station_id) REFERENCES stations(station_id) ON DELETE SET NULL,
    INDEX idx_staff (staff_id),
    INDEX idx_station (station_id),
    INDEX idx_service_type (service_type),
    INDEX idx_created (created_at)
) ENGINE=InnoDB;

-- Device usage hours and totals by station
CREATE TABLE device_usage_logs (
    device_usage_id    BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    station_id         BIGINT UNSIGNED NOT NULL,
    staff_id           BIGINT UNSIGNED NULL,
    customer_name      VARCHAR(100) NOT NULL,
    service_type       ENUM('internet', 'gaming', 'printing', 'other') NOT NULL,
    started_at         TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    ended_at           TIMESTAMP NULL,
    usage_minutes      INT UNSIGNED DEFAULT 0,
    total_amount       DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    status             ENUM('active', 'completed', 'cancelled') DEFAULT 'completed',
    notes              TEXT,
    created_at         TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at         TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (station_id) REFERENCES stations(station_id) ON DELETE CASCADE,
    FOREIGN KEY (staff_id) REFERENCES staff(staff_id) ON DELETE SET NULL,
    INDEX idx_station (station_id),
    INDEX idx_staff (staff_id),
    INDEX idx_service_type (service_type),
    INDEX idx_started (started_at)
) ENGINE=InnoDB;

-- Staff and system audit trail
CREATE TABLE audit_logs (
    audit_id         BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    staff_id         BIGINT UNSIGNED NULL,
    actor_type       ENUM('staff', 'admin', 'system') DEFAULT 'staff',
    action           VARCHAR(100) NOT NULL,
    entity_type      VARCHAR(100) NOT NULL,
    entity_id        BIGINT UNSIGNED NULL,
    details          JSON,
    ip_address       VARCHAR(45),
    created_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_staff (staff_id),
    INDEX idx_action (action),
    INDEX idx_created (created_at),
    FOREIGN KEY (staff_id) REFERENCES staff(staff_id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ============================================================
-- 3. SESSIONS & TIME TRACKING
-- ============================================================

-- User sessions at stations (core high-write table)
CREATE TABLE sessions (
    session_id       BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id          BIGINT UNSIGNED NOT NULL,
    station_id       BIGINT UNSIGNED NOT NULL,
    staff_id         BIGINT UNSIGNED NULL,         -- Attendant who started session
    started_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    ended_at         TIMESTAMP NULL,
    scheduled_end_at DATETIME NULL,
    duration_minutes INT UNSIGNED GENERATED ALWAYS AS (TIMESTAMPDIFF(MINUTE, started_at, ended_at)) VIRTUAL,
    status           ENUM('active', 'paused', 'completed', 'cancelled') DEFAULT 'active',
    notes            TEXT,
    created_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (station_id) REFERENCES stations(station_id) ON DELETE CASCADE,
    FOREIGN KEY (staff_id) REFERENCES staff(staff_id) ON DELETE SET NULL,
    INDEX idx_user (user_id),
    INDEX idx_station (station_id),
    INDEX idx_status (status),
    INDEX idx_started (started_at),
    INDEX idx_active_sessions (status, started_at),
    INDEX idx_scheduled_expiry (status, scheduled_end_at),
    -- Composite index for common queries
    INDEX idx_user_station (user_id, station_id, started_at)
) ENGINE=InnoDB
  ROW_FORMAT=COMPRESSED
  KEY_BLOCK_SIZE=8;

-- ============================================================
-- 4. SERVICES & PRICING
-- ============================================================

-- Service catalog
CREATE TABLE services (
    service_id       BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name             VARCHAR(100) NOT NULL,
    category         ENUM('internet', 'printing', 'scanning', 'gaming', 'typing', 'mobile_money', 'consultation', 'other') NOT NULL,
    description      TEXT,
    icon             VARCHAR(80),                      -- Font Awesome classes, e.g. "fa-solid fa-print"
    base_price       DECIMAL(8,2) NOT NULL,
    price_unit       ENUM('per_hour', 'per_minute', 'per_page', 'per_job', 'fixed') DEFAULT 'fixed',
    is_active        BOOLEAN DEFAULT TRUE,
    requires_station BOOLEAN DEFAULT FALSE,
    estimated_duration INT UNSIGNED,               -- in minutes
    created_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_category (category),
    INDEX idx_active (is_active)
) ENGINE=InnoDB;

-- Pricing plans / packages
CREATE TABLE pricing_plans (
    plan_id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name             VARCHAR(100) NOT NULL,
    description      TEXT,
    price            DECIMAL(8,2) NOT NULL,
    duration_minutes INT UNSIGNED NOT NULL,         -- e.g., 60 for 1 hour
    service_ids      JSON,                          -- included services
    is_active        BOOLEAN DEFAULT TRUE,
    sort_order       INT DEFAULT 0,
    created_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_active (is_active),
    INDEX idx_sort (sort_order)
) ENGINE=InnoDB;

-- ============================================================
-- 5. BOOKINGS & SERVICE USAGE
-- ============================================================

-- Service bookings (future reservations)
CREATE TABLE bookings (
    booking_id       BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id          BIGINT UNSIGNED NOT NULL,
    service_id       BIGINT UNSIGNED NOT NULL,
    station_id       BIGINT UNSIGNED NULL,
    staff_id         BIGINT UNSIGNED NULL,
    scheduled_at     TIMESTAMP NOT NULL,
    duration_minutes INT UNSIGNED NOT NULL,
    status           ENUM('pending', 'confirmed', 'in_progress', 'completed', 'cancelled', 'no_show') DEFAULT 'pending',
    notes            TEXT,
    created_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (service_id) REFERENCES services(service_id) ON DELETE RESTRICT,
    FOREIGN KEY (station_id) REFERENCES stations(station_id) ON DELETE SET NULL,
    FOREIGN KEY (staff_id) REFERENCES staff(staff_id) ON DELETE SET NULL,
    INDEX idx_user (user_id),
    INDEX idx_scheduled (scheduled_at),
    INDEX idx_status (status),
    INDEX idx_service (service_id)
) ENGINE=InnoDB;

-- Actual service usage log (high-volume, append-only)
CREATE TABLE service_usage (
    usage_id         BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    session_id       BIGINT UNSIGNED NOT NULL,
    service_id       BIGINT UNSIGNED NOT NULL,
    user_id          BIGINT UNSIGNED NOT NULL,
    station_id       BIGINT UNSIGNED NOT NULL,
    staff_id         BIGINT UNSIGNED NULL,
    quantity         DECIMAL(10,2) DEFAULT 1.00,
    unit_price       DECIMAL(8,2) NOT NULL,
    total_price      DECIMAL(10,2) NOT NULL,
    started_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    completed_at     TIMESTAMP NULL,
    status           ENUM('pending', 'in_progress', 'completed', 'cancelled', 'refunded') DEFAULT 'pending',
    metadata         JSON,                          -- e.g., pages printed, file name
    created_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (session_id) REFERENCES sessions(session_id) ON DELETE CASCADE,
    FOREIGN KEY (service_id) REFERENCES services(service_id) ON DELETE RESTRICT,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (station_id) REFERENCES stations(station_id) ON DELETE CASCADE,
    FOREIGN KEY (staff_id) REFERENCES staff(staff_id) ON DELETE SET NULL,
    INDEX idx_session (session_id),
    INDEX idx_user (user_id),
    INDEX idx_service (service_id),
    INDEX idx_status (status),
    INDEX idx_started (started_at),
    INDEX idx_completed (completed_at),
    -- Composite for reporting
    INDEX idx_user_service_date (user_id, service_id, started_at),
    INDEX idx_station_date (station_id, started_at)
) ENGINE=InnoDB
  ROW_FORMAT=COMPRESSED
  KEY_BLOCK_SIZE=8;

-- ============================================================
-- 6. PRINT JOBS (Queue Management)
-- ============================================================

CREATE TABLE print_jobs (
    print_job_id     BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    usage_id         BIGINT UNSIGNED NOT NULL,       -- linked to service_usage
    user_id          BIGINT UNSIGNED NOT NULL,
    station_id       BIGINT UNSIGNED NOT NULL,
    peripheral_id    BIGINT UNSIGNED NOT NULL,       -- which printer
    file_name        VARCHAR(255) NOT NULL,
    file_path        VARCHAR(500) NOT NULL,
    pages            INT UNSIGNED NOT NULL,
    copies           INT UNSIGNED DEFAULT 1,
    color_mode       ENUM('bw', 'color') DEFAULT 'bw',
    duplex           BOOLEAN DEFAULT FALSE,
    status           ENUM('queued', 'printing', 'completed', 'failed', 'cancelled') DEFAULT 'queued',
    priority         TINYINT DEFAULT 0,              -- higher = more urgent
    error_message    TEXT,
    started_at       TIMESTAMP NULL,
    completed_at     TIMESTAMP NULL,
    created_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (usage_id) REFERENCES service_usage(usage_id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (station_id) REFERENCES stations(station_id) ON DELETE CASCADE,
    FOREIGN KEY (peripheral_id) REFERENCES peripherals(peripheral_id) ON DELETE RESTRICT,
    INDEX idx_user (user_id),
    INDEX idx_status (status),
    INDEX idx_priority (priority, status),
    INDEX idx_created (created_at),
    INDEX idx_printer (peripheral_id, status)
) ENGINE=InnoDB;

-- ============================================================
-- 7. TRANSACTIONS & PAYMENTS
-- ============================================================

-- Payment methods
CREATE TABLE payment_methods (
    payment_method_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id           BIGINT UNSIGNED NOT NULL,
    method_type       ENUM('cash', 'card', 'mobile_money', 'wallet', 'voucher') NOT NULL,
    provider          VARCHAR(50),                   -- e.g., M-Pesa, Stripe
    last_four         CHAR(4),
    is_default        BOOLEAN DEFAULT FALSE,
    is_active         BOOLEAN DEFAULT TRUE,
    created_at        TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at        TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    INDEX idx_user (user_id),
    INDEX idx_type (method_type)
) ENGINE=InnoDB;

-- Transactions (high-volume, append-only)
CREATE TABLE transactions (
    transaction_id   BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id          BIGINT UNSIGNED NOT NULL,
    session_id       BIGINT UNSIGNED NULL,
    booking_id       BIGINT UNSIGNED NULL,
    payment_method_id BIGINT UNSIGNED NULL,
    amount           DECIMAL(10,2) NOT NULL,
    tax_amount       DECIMAL(10,2) DEFAULT 0.00,
    discount_amount  DECIMAL(10,2) DEFAULT 0.00,
    total_amount     DECIMAL(10,2) NOT NULL,
    currency         CHAR(3) DEFAULT 'USD',
    status           ENUM('pending', 'completed', 'failed', 'refunded', 'partially_refunded') DEFAULT 'pending',
    payment_reference VARCHAR(255),                  -- external transaction ID
    receipt_number   VARCHAR(50) UNIQUE,
    notes            TEXT,
    created_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (session_id) REFERENCES sessions(session_id) ON DELETE SET NULL,
    FOREIGN KEY (booking_id) REFERENCES bookings(booking_id) ON DELETE SET NULL,
    FOREIGN KEY (payment_method_id) REFERENCES payment_methods(payment_method_id) ON DELETE SET NULL,
    INDEX idx_user (user_id),
    INDEX idx_status (status),
    INDEX idx_created (created_at),
    INDEX idx_receipt (receipt_number),
    INDEX idx_payment_ref (payment_reference),
    -- Composite for daily reports
    INDEX idx_date_status (created_at, status),
    INDEX idx_user_date (user_id, created_at)
) ENGINE=InnoDB
  ROW_FORMAT=COMPRESSED
  KEY_BLOCK_SIZE=8;

-- Transaction line items (for detailed receipts)
CREATE TABLE transaction_items (
    item_id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    transaction_id   BIGINT UNSIGNED NOT NULL,
    service_id       BIGINT UNSIGNED NULL,
    usage_id         BIGINT UNSIGNED NULL,
    description      VARCHAR(255) NOT NULL,
    quantity         DECIMAL(10,2) DEFAULT 1.00,
    unit_price       DECIMAL(8,2) NOT NULL,
    total_price      DECIMAL(10,2) NOT NULL,
    created_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (transaction_id) REFERENCES transactions(transaction_id) ON DELETE CASCADE,
    FOREIGN KEY (service_id) REFERENCES services(service_id) ON DELETE SET NULL,
    FOREIGN KEY (usage_id) REFERENCES service_usage(usage_id) ON DELETE SET NULL,
    INDEX idx_transaction (transaction_id),
    INDEX idx_service (service_id)
) ENGINE=InnoDB;

-- Refunds issued against a transaction (full or partial)
CREATE TABLE transaction_refunds (
    refund_id        BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    transaction_id   BIGINT UNSIGNED NOT NULL,
    amount           DECIMAL(10,2) NOT NULL,
    reason           ENUM('customer_request', 'service_issue', 'billing_error', 'duplicate_charge', 'manager_override', 'other') NOT NULL DEFAULT 'customer_request',
    notes            TEXT,
    payment_method   ENUM('cash', 'mobile_money', 'card', 'bank_transfer', 'other') NULL,
    processed_by     BIGINT UNSIGNED NULL,           -- staff member who issued the refund
    created_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (transaction_id) REFERENCES transactions(transaction_id) ON DELETE CASCADE,
    FOREIGN KEY (processed_by) REFERENCES staff(staff_id) ON DELETE SET NULL,
    INDEX idx_transaction (transaction_id),
    INDEX idx_processed_by (processed_by),
    INDEX idx_created (created_at),
    INDEX idx_reason (reason)
) ENGINE=InnoDB
  ROW_FORMAT=COMPRESSED
  KEY_BLOCK_SIZE=8;

-- Line items covered by a refund (supports repeated partial refunds)
CREATE TABLE transaction_item_refunds (
    item_refund_id   BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    refund_id        BIGINT UNSIGNED NOT NULL,
    item_id          BIGINT UNSIGNED NOT NULL,
    quantity         DECIMAL(10,2) NOT NULL,
    amount           DECIMAL(10,2) NOT NULL,
    created_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (refund_id) REFERENCES transaction_refunds(refund_id) ON DELETE CASCADE,
    FOREIGN KEY (item_id) REFERENCES transaction_items(item_id) ON DELETE CASCADE,
    INDEX idx_refund (refund_id),
    INDEX idx_item (item_id)
) ENGINE=InnoDB
  ROW_FORMAT=COMPRESSED
  KEY_BLOCK_SIZE=8;

-- ============================================================
-- 8. INVENTORY & PRODUCTS (if selling snacks/drinks)
-- ============================================================

CREATE TABLE products (
    product_id       BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name             VARCHAR(100) NOT NULL,
    category         ENUM('beverage', 'snack', 'merchandise', 'accessory', 'other') NOT NULL,
    sku              VARCHAR(50) UNIQUE,
    barcode          VARCHAR(100) UNIQUE,
    description      TEXT,
    cost_price       DECIMAL(8,2) NOT NULL,
    selling_price    DECIMAL(8,2) NOT NULL,
    stock_quantity   INT DEFAULT 0,
    min_stock_level  INT DEFAULT 5,
    is_active        BOOLEAN DEFAULT TRUE,
    created_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_category (category),
    INDEX idx_sku (sku),
    INDEX idx_active (is_active),
    INDEX idx_stock (stock_quantity)
) ENGINE=InnoDB;

-- Inventory movements (audit trail)
CREATE TABLE inventory_movements (
    movement_id      BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    product_id       BIGINT UNSIGNED NOT NULL,
    movement_type    ENUM('purchase', 'sale', 'adjustment', 'transfer', 'waste') NOT NULL,
    quantity         INT NOT NULL,                   -- positive = in, negative = out
    reference_id     BIGINT UNSIGNED NULL,           -- transaction_id or purchase_id
    reference_type   ENUM('transaction', 'purchase', 'adjustment') NULL,
    notes            TEXT,
    created_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_by       BIGINT UNSIGNED NULL,
    FOREIGN KEY (product_id) REFERENCES products(product_id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES staff(staff_id) ON DELETE SET NULL,
    INDEX idx_product (product_id),
    INDEX idx_type (movement_type),
    INDEX idx_created (created_at),
    INDEX idx_reference (reference_id, reference_type)
) ENGINE=InnoDB;

-- ============================================================
-- 9. STAFF & OPERATIONS
-- ============================================================

-- Staff attendance
CREATE TABLE staff_attendance (
    attendance_id    BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    staff_id         BIGINT UNSIGNED NOT NULL,
    clock_in         TIMESTAMP NOT NULL,
    clock_out        TIMESTAMP NULL,
    break_minutes    INT DEFAULT 0,
    notes            TEXT,
    created_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (staff_id) REFERENCES staff(staff_id) ON DELETE CASCADE,
    INDEX idx_staff (staff_id),
    INDEX idx_clock_in (clock_in)
) ENGINE=InnoDB;

-- Station assignments history
CREATE TABLE station_assignments (
    assignment_id    BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    staff_id         BIGINT UNSIGNED NOT NULL,
    station_id       BIGINT UNSIGNED NOT NULL,
    assigned_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    unassigned_at    TIMESTAMP NULL,
    notes            TEXT,
    FOREIGN KEY (staff_id) REFERENCES staff(staff_id) ON DELETE CASCADE,
    FOREIGN KEY (station_id) REFERENCES stations(station_id) ON DELETE CASCADE,
    INDEX idx_staff (staff_id),
    INDEX idx_station (station_id),
    INDEX idx_assigned (assigned_at)
) ENGINE=InnoDB;

-- ============================================================
-- 10. FEEDBACK & SUPPORT
-- ============================================================

CREATE TABLE feedback (
    feedback_id      BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id          BIGINT UNSIGNED NULL,
    customer_name    VARCHAR(100) NULL,
    session_id       BIGINT UNSIGNED NULL,
    rating           TINYINT UNSIGNED CHECK (rating BETWEEN 1 AND 5),
    category         ENUM('service', 'station', 'staff', 'cleanliness', 'other') NOT NULL,
    comment          TEXT,
    is_resolved      BOOLEAN DEFAULT FALSE,
    resolved_by      BIGINT UNSIGNED NULL,
    resolved_at      TIMESTAMP NULL,
    testimonial_approved BOOLEAN NOT NULL DEFAULT FALSE,
    testimonial_approved_by BIGINT UNSIGNED NULL,
    testimonial_approved_at TIMESTAMP NULL,
    created_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE SET NULL,
    FOREIGN KEY (session_id) REFERENCES sessions(session_id) ON DELETE SET NULL,
    FOREIGN KEY (resolved_by) REFERENCES staff(staff_id) ON DELETE SET NULL,
    FOREIGN KEY (testimonial_approved_by) REFERENCES staff(staff_id) ON DELETE SET NULL,
    INDEX idx_user (user_id),
    INDEX idx_rating (rating),
    INDEX idx_resolved (is_resolved),
    INDEX idx_testimonial_approved (testimonial_approved),
    INDEX idx_created (created_at)
) ENGINE=InnoDB;

-- Support tickets
CREATE TABLE support_tickets (
    ticket_id        BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id          BIGINT UNSIGNED NULL,
    requester_name   VARCHAR(100) NULL,
    requester_email  VARCHAR(255) NULL,
    assigned_to      BIGINT UNSIGNED NULL,
    subject          VARCHAR(255) NOT NULL,
    description      TEXT NOT NULL,
    priority         ENUM('low', 'medium', 'high', 'urgent') DEFAULT 'medium',
    status           ENUM('open', 'in_progress', 'waiting_customer', 'resolved', 'closed') DEFAULT 'open',
    category         ENUM('technical', 'billing', 'account', 'general') DEFAULT 'general',
    resolved_at      TIMESTAMP NULL,
    created_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE SET NULL,
    FOREIGN KEY (assigned_to) REFERENCES staff(staff_id) ON DELETE SET NULL,
    INDEX idx_user (user_id),
    INDEX idx_status (status),
    INDEX idx_priority (priority),
    INDEX idx_assigned (assigned_to),
    INDEX idx_created (created_at)
) ENGINE=InnoDB;

-- Public documentation, tutorials and FAQ content managed from the Help Center.
CREATE TABLE help_resources (
    resource_id    BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    resource_type  ENUM('documentation', 'tutorial', 'faq') NOT NULL,
    title          VARCHAR(255) NOT NULL,
    content        MEDIUMTEXT NOT NULL,
    url            VARCHAR(2048) NULL,
    sort_order     INT NOT NULL DEFAULT 0,
    is_published   BOOLEAN NOT NULL DEFAULT TRUE,
    created_by     BIGINT UNSIGNED NULL,
    updated_by     BIGINT UNSIGNED NULL,
    created_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at     TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES staff(staff_id) ON DELETE SET NULL,
    FOREIGN KEY (updated_by) REFERENCES staff(staff_id) ON DELETE SET NULL,
    INDEX idx_type_published_order (resource_type, is_published, sort_order),
    INDEX idx_updated (updated_at)
) ENGINE=InnoDB;

-- ============================================================
-- 11. NOTIFICATIONS
-- ============================================================

CREATE TABLE notifications (
    notification_id  BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id          BIGINT UNSIGNED NULL,           -- NULL = broadcast to all
    staff_id         BIGINT UNSIGNED NULL,
    type             ENUM('info', 'warning', 'success', 'error', 'promotion') NOT NULL,
    title            VARCHAR(255) NOT NULL,
    message          TEXT NOT NULL,
    data             JSON,                           -- additional payload
    is_read          BOOLEAN DEFAULT FALSE,
    read_at          TIMESTAMP NULL,
    expires_at       TIMESTAMP NULL,
    created_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id) ON DELETE CASCADE,
    FOREIGN KEY (staff_id) REFERENCES staff(staff_id) ON DELETE CASCADE,
    INDEX idx_user (user_id, is_read),
    INDEX idx_staff (staff_id, is_read),
    INDEX idx_type (type),
    INDEX idx_created (created_at),
    INDEX idx_expires (expires_at)
) ENGINE=InnoDB;

-- ============================================================
-- 12. SYSTEM CONFIGURATION
-- ============================================================

CREATE TABLE system_settings (
    setting_key      VARCHAR(100) PRIMARY KEY,
    setting_value    TEXT NOT NULL,
    data_type        ENUM('string', 'number', 'boolean', 'json') DEFAULT 'string',
    description      TEXT,
    is_public        BOOLEAN DEFAULT FALSE,
    updated_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    updated_by       BIGINT UNSIGNED NULL,
    FOREIGN KEY (updated_by) REFERENCES staff(staff_id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Audit log for critical operations
CREATE TABLE audit_log (
    audit_id         BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    actor_type       ENUM('user', 'staff', 'system') NOT NULL,
    actor_id         BIGINT UNSIGNED NOT NULL,
    action           VARCHAR(100) NOT NULL,
    entity_type      VARCHAR(50) NOT NULL,
    entity_id        BIGINT UNSIGNED NOT NULL,
    old_values       JSON,
    new_values       JSON,
    ip_address       VARCHAR(45),
    user_agent       VARCHAR(500),
    created_at       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_actor (actor_type, actor_id),
    INDEX idx_entity (entity_type, entity_id),
    INDEX idx_action (action),
    INDEX idx_created (created_at)
) ENGINE=InnoDB
  ROW_FORMAT=COMPRESSED
  KEY_BLOCK_SIZE=8;

-- ============================================================
-- 13. VIEWS FOR COMMON QUERIES
-- ============================================================

-- Active sessions with user and station details
CREATE OR REPLACE VIEW v_active_sessions AS
SELECT 
    s.session_id,
    s.user_id,
    u.full_name AS user_name,
    u.phone AS user_phone,
    s.station_id,
    st.station_code,
    st.name AS station_name,
    st.location,
    s.started_at,
    TIMESTAMPDIFF(MINUTE, s.started_at, NOW()) AS elapsed_minutes,
    s.staff_id,
    sf.full_name AS staff_name
FROM sessions s
JOIN users u ON s.user_id = u.user_id
JOIN stations st ON s.station_id = st.station_id
LEFT JOIN staff sf ON s.staff_id = sf.staff_id
WHERE s.status = 'active';

-- Daily revenue summary
CREATE OR REPLACE VIEW v_daily_revenue AS
SELECT 
    DATE(created_at) AS revenue_date,
    COUNT(*) AS transaction_count,
    SUM(total_amount) AS total_revenue,
    SUM(tax_amount) AS total_tax,
    SUM(discount_amount) AS total_discounts,
    AVG(total_amount) AS avg_transaction,
    COUNT(DISTINCT user_id) AS unique_customers
FROM transactions
WHERE status = 'completed'
GROUP BY DATE(created_at);

-- Refund status per transaction
CREATE OR REPLACE VIEW v_transaction_refunds AS
SELECT
    t.transaction_id,
    t.receipt_number,
    t.user_id,
    t.total_amount,
    t.status,
    COALESCE(SUM(r.amount), 0) AS refunded_amount,
    GREATEST(t.total_amount - COALESCE(SUM(r.amount), 0), 0) AS refundable_amount,
    CASE
        WHEN t.status = 'refunded' THEN 'refunded'
        WHEN COALESCE(SUM(r.amount), 0) > 0 THEN 'partially_refunded'
        ELSE 'none'
    END AS refund_state,
    MAX(r.created_at) AS last_refund_at
FROM transactions t
LEFT JOIN transaction_refunds r ON r.transaction_id = t.transaction_id
GROUP BY t.transaction_id, t.receipt_number, t.user_id, t.total_amount, t.status;

-- Transactions with a refundable balance (counter list)
CREATE OR REPLACE VIEW v_refundable_transactions AS
SELECT
    t.transaction_id,
    t.receipt_number,
    u.full_name AS customer_name,
    t.total_amount,
    t.status,
    COALESCE(vr.refunded_amount, 0) AS refunded_amount,
    GREATEST(t.total_amount - COALESCE(vr.refunded_amount, 0), 0) AS refundable_amount,
    t.created_at
FROM transactions t
LEFT JOIN users u ON u.user_id = t.user_id
LEFT JOIN v_transaction_refunds vr ON vr.transaction_id = t.transaction_id
WHERE t.status IN ('completed', 'partially_refunded')
  AND GREATEST(t.total_amount - COALESCE(vr.refunded_amount, 0), 0) > 0;

-- Station utilization
CREATE OR REPLACE VIEW v_station_utilization AS
SELECT 
    st.station_id,
    st.station_code,
    st.name AS station_name,
    st.location,
    COUNT(s.session_id) AS total_sessions,
    SUM(TIMESTAMPDIFF(MINUTE, s.started_at, COALESCE(s.ended_at, NOW()))) AS total_minutes_used,
    COUNT(DISTINCT s.user_id) AS unique_users
FROM stations st
LEFT JOIN sessions s ON st.station_id = s.station_id 
    AND s.started_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
GROUP BY st.station_id, st.station_code, st.name, st.location;

-- ============================================================
-- 14. STORED PROCEDURES & FUNCTIONS
-- ============================================================

-- End a session and calculate charges
DELIMITER //
CREATE PROCEDURE sp_end_session(
    IN p_session_id BIGINT UNSIGNED,
    IN p_staff_id BIGINT UNSIGNED
)
BEGIN
    DECLARE v_user_id BIGINT UNSIGNED;
    DECLARE v_station_id BIGINT UNSIGNED;
    DECLARE v_started_at TIMESTAMP;
    DECLARE v_duration INT;
    DECLARE v_hourly_rate DECIMAL(8,2);
    
    -- Get session details
    SELECT user_id, station_id, started_at 
    INTO v_user_id, v_station_id, v_started_at
    FROM sessions 
    WHERE session_id = p_session_id AND status = 'active'
    FOR UPDATE;
    
    IF v_user_id IS NOT NULL THEN
        -- Calculate duration
        SET v_duration = TIMESTAMPDIFF(MINUTE, v_started_at, NOW());
        
        -- Get hourly rate (from settings or default)
        SELECT CAST(setting_value AS DECIMAL(8,2)) INTO v_hourly_rate
        FROM system_settings 
        WHERE setting_key = 'hourly_rate' 
        LIMIT 1;
        
        IF v_hourly_rate IS NULL THEN
            SET v_hourly_rate = 2.00;  -- default rate
        END IF;
        
        -- End the session
        UPDATE sessions 
        SET status = 'completed', 
            ended_at = NOW(),
            updated_at = NOW()
        WHERE session_id = p_session_id;
        
        -- Free up the station
        UPDATE stations 
        SET status = 'available', 
            updated_at = NOW()
        WHERE station_id = v_station_id;
        
        -- Create transaction for session time
        INSERT INTO transactions (user_id, session_id, amount, total_amount, status, payment_reference)
        VALUES (v_user_id, p_session_id, 
                ROUND((v_duration / 60.0) * v_hourly_rate, 2),
                ROUND((v_duration / 60.0) * v_hourly_rate, 2),
                'pending', CONCAT('SESS-', p_session_id));
        
        -- Log audit
        INSERT INTO audit_log (actor_type, actor_id, action, entity_type, entity_id)
        VALUES ('staff', p_staff_id, 'end_session', 'session', p_session_id);
    END IF;
END //
DELIMITER ;

-- Get station status summary
DELIMITER //
CREATE PROCEDURE sp_station_status_summary()
BEGIN
    SELECT 
        status,
        COUNT(*) AS count,
        GROUP_CONCAT(station_code ORDER BY station_code) AS stations
    FROM stations
    GROUP BY status;
END //
DELIMITER ;

-- ============================================================
-- 15. TRIGGERS
-- ============================================================

-- Auto-update user stats after transaction
DELIMITER //
CREATE TRIGGER trg_after_transaction_complete
AFTER UPDATE ON transactions
FOR EACH ROW
BEGIN
    IF NEW.status = 'completed' AND OLD.status != 'completed' THEN
        UPDATE users 
        SET total_visits = total_visits + 1,
            total_spent = total_spent + NEW.total_amount,
            updated_at = NOW()
        WHERE user_id = NEW.user_id;
    END IF;
END //
DELIMITER ;

-- Auto-update station status when session starts
DELIMITER //
CREATE TRIGGER trg_after_session_start
AFTER INSERT ON sessions
FOR EACH ROW
BEGIN
    IF NEW.status = 'active' THEN
        UPDATE stations 
        SET status = 'occupied', 
            updated_at = NOW()
        WHERE station_id = NEW.station_id;
    END IF;
END //
DELIMITER ;

-- ============================================================
-- 16. INITIAL DATA
-- ============================================================

-- Insert default admin staff user
-- Email: admin@ctechlitsystems.com | Password: Admin@123
INSERT INTO staff (email, phone, full_name, password_hash, role, station_id, is_on_duty, hired_at, is_active) VALUES
('admin@ctechlitsystems.com', '+233240000001', 'System Administrator', '$2y$10$4oXtogwDvxbF6B0aVIMZJOEonrSFJFXqYfBSKZfo3iwuXmb/u9XZ.', 'ceo', NULL, FALSE, CURDATE(), TRUE);

-- Insert default staff user for testing
-- Email: staff@ctechlitsystems.com | Password: Admin@123
INSERT INTO staff (email, phone, full_name, password_hash, role, station_id, is_on_duty, hired_at, is_active) VALUES
('staff@ctechlitsystems.com', '+233240000002', 'Staff User', '$2y$10$4oXtogwDvxbF6B0aVIMZJOEonrSFJFXqYfBSKZfo3iwuXmb/u9XZ.', 'attendant', NULL, FALSE, CURDATE(), TRUE);

-- Insert default system settings
INSERT INTO system_settings (setting_key, setting_value, data_type, description, is_public) VALUES
('hourly_rate', '2.00', 'number', 'Base hourly rate for station usage', TRUE),
('currency', 'USD', 'string', 'Default currency', TRUE),
('timezone', 'GMT', 'string', 'System timezone', FALSE),
('session_timeout_minutes', '480', 'number', 'Maximum session duration in minutes', FALSE),
('allow_walkins', 'true', 'boolean', 'Allow walk-in customers without accounts', TRUE),
('maintenance_mode', 'false', 'boolean', 'System maintenance mode', FALSE),
('max_concurrent_sessions', '6', 'number', 'Maximum simultaneous sessions', FALSE),
('print_cost_bw', '0.10', 'number', 'Cost per black-and-white page', TRUE),
('print_cost_color', '0.50', 'number', 'Cost per a color page', TRUE);

-- Public settings consumed by the landing page (index.php)
INSERT INTO system_settings (setting_key, setting_value, data_type, description, is_public) VALUES
('business_name', 'CTECHLIT & SYSTEMS', 'string', 'Business name shown in nav, header and footer', TRUE),
('business_tagline', 'Internet Café & Digital Services', 'string', 'Short tagline under the business name', TRUE),
('currency_symbol', 'GHS', 'string', 'Symbol prefixed to every displayed price', TRUE),
('hero_eyebrow', 'Internet Café & Digital Services', 'string', 'Hero section eyebrow text', TRUE),
('hero_title', 'A faster, friendlier place to browse, print and get things done', 'string', 'Hero headline', TRUE),
('hero_lead', 'Fast machines, a comfortable space, and staff who actually help — internet access, printing, and digital services, all under one roof.', 'string', 'Hero supporting paragraph', TRUE),
('hero_highlight', 'faster, friendlier', 'string', 'Phrase inside hero_title rendered in the accent style', TRUE),
('gap_first_card', 'Browsing, printing, typing, scanning, mobile money and more in one visit', 'string', 'Body copy of the first "why people choose us" card', TRUE),
('services_eyebrow', 'Services', 'string', 'Services section eyebrow text', TRUE),
('services_title', 'Everything one desk in Daffiama can now do', 'string', 'Services section headline', TRUE),
('services_lead', 'From an hour of browsing to a finished passport application — walk in, and walk out done.', 'string', 'Services section supporting text', TRUE),
('pricing_eyebrow', 'Pricing', 'string', 'Pricing section eyebrow text', TRUE),
('pricing_title', 'Straightforward rates', 'string', 'Pricing section headline', TRUE),
('pricing_lead', 'Indicative pricing — ask the front desk for the current full list.', 'string', 'Pricing section supporting text', TRUE),
('visit_eyebrow', 'Visit us', 'string', 'Visit section eyebrow text', TRUE),
('visit_title', 'Central Daffiama, easy to find', 'string', 'Visit section headline', TRUE),
('visit_lead', 'Near the Daffiama Senior High School & Technical Institute, along the Daffiama–Busie road.', 'string', 'Visit section supporting text', TRUE),
('contact_address', 'Central Daffiama, Daffiama-Bussie-Issa District, Upper West Region', 'string', 'Shop address', TRUE),
('contact_phone', '+233 24 123 4567', 'string', 'Display phone number', TRUE),
('contact_phone_raw', '233241234567', 'string', 'Phone number in dialable format', TRUE),
('contact_email', 'ctechlit@gmail.com', 'string', 'Public email address', TRUE),
('opening_hours', 'Monday – Saturday, 7:00 AM – 8:00 PM', 'string', 'Opening hours shown on the landing page', TRUE),
('map_url', 'https://www.google.com/maps/search/?api=1&query=Central+Daffiama+Upper+West+Region', 'string', 'Get Directions target', TRUE),
('footer_note', 'Sole Proprietorship · Daffiama-Bussie-Issa District', 'string', 'Footer ownership line', TRUE),
('footer_about', 'Daffiama''s digital access, ICT training and technology support centre.', 'string', 'Footer about text', TRUE);

-- Insert default services
INSERT INTO services (name, category, description, icon, base_price, price_unit, requires_station, estimated_duration) VALUES
('Internet Browsing', 'internet', 'High-speed internet access on wired workstations', 'fa-solid fa-globe', 2.00, 'per_hour', TRUE, 60),
('Color Printing', 'printing', 'Full-colour document printing', 'fa-solid fa-print', 0.50, 'per_page', FALSE, 5),
('Black & White Printing', 'printing', 'Monochrome document printing', 'fa-solid fa-print', 0.10, 'per_page', FALSE, 3),
('Document Scanning', 'scanning', 'Scan documents to email or USB', 'fa-solid fa-file-lines', 1.00, 'per_job', FALSE, 5),
('Gaming Session', 'gaming', 'Console gaming access', 'fa-solid fa-gamepad', 3.00, 'per_hour', TRUE, 60),
('Typing Services', 'typing', 'Professional typing and data entry', 'fa-solid fa-pen-to-square', 5.00, 'per_hour', TRUE, 60),
('Mobile Money', 'mobile_money', 'M-Pesa and other mobile money services', 'fa-solid fa-money-bill', 0.50, 'fixed', FALSE, 10),
('1-on-1 Consultation', 'consultation', 'Personalized tech assistance', 'fa-solid fa-headset', 10.00, 'per_hour', TRUE, 60);

-- Insert sample pricing plans
INSERT INTO pricing_plans (name, description, price, duration_minutes, service_ids, sort_order) VALUES
('Casual Hour', '1 hour of internet access', 2.00, 60, '[1]', 1),
('Day Pass', 'Full day access (8 hours)', 12.00, 480, '[1]', 2),
('Gaming Hour', '1 hour console gaming', 3.00, 60, '[5]', 3),
('Print Pack', '20 black & white pages', 2.00, 0, '[3]', 4),
('Premium Bundle', 'Internet + printing + consultation', 15.00, 120, '[1,3,8]', 5);

-- Insert sample stations (6 client stations as per website)
INSERT INTO stations (station_code, name, location, status, specs, ip_address) VALUES
('ST-01', 'Workstation Alpha', 'Zone A', 'available', '{"cpu":"Intel i5","ram":"16GB","gpu":"Integrated","os":"Windows 11"}', '192.168.1.101'),
('ST-02', 'Workstation Beta', 'Zone A', 'available', '{"cpu":"Intel i5","ram":"16GB","gpu":"Integrated","os":"Windows 11"}', '192.168.1.102'),
('ST-03', 'Workstation Gamma', 'Zone B', 'available', '{"cpu":"Intel i7","ram":"32GB","gpu":"RTX 3060","os":"Windows 11"}', '192.168.1.103'),
('ST-04', 'Workstation Delta', 'Zone B', 'available', '{"cpu":"Intel i7","ram":"32GB","gpu":"RTX 3060","os":"Windows 11"}', '192.168.1.104'),
('ST-05', 'Gaming Station 1', 'Gaming Corner', 'available', '{"cpu":"AMD Ryzen 5","ram":"16GB","gpu":"RTX 4070","os":"Windows 11"}', '192.168.1.105'),
('ST-06', 'Gaming Station 2', 'Gaming Corner', 'available', '{"cpu":"AMD Ryzen 5","ram":"16GB","gpu":"RTX 4070","os":"Windows 11"}', '192.168.1.106');

-- Insert peripherals
INSERT INTO peripherals (peripheral_type, name, model, station_id, status) VALUES
('printer', 'HP LaserJet Pro', 'MFP M428fdw', 1, 'online'),
('printer', 'HP LaserJet Pro', 'MFP M428fdw', 2, 'online'),
('printer', 'Canon PIXMA', 'G6020', 3, 'online'),
('scanner', 'Epson Perfection', 'V39', 1, 'online'),
('game_console', 'PlayStation 5', 'CFI-1215A', 5, 'online'),
('game_console', 'Xbox Series X', 'RRT-00015', 6, 'online');

-- ============================================================
-- 13.5 PERSONAL NETWORK DEVICES (BYOD / Hotspot)
-- ============================================================

-- Each personal device a customer registers on the network
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

-- Per-device usage events (append-only audit trail)
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

-- ============================================================
-- 14. BRANDED ASSETS
-- ============================================================

CREATE TABLE branded_assets (
    asset_id        BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    asset_type      ENUM('flyer', 'powerpoint', 'letterhead') NOT NULL,
    name            VARCHAR(150) NOT NULL,
    config          JSON NOT NULL,
    preview_data    LONGTEXT NULL,
    created_by      BIGINT UNSIGNED NOT NULL,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES staff(staff_id) ON DELETE CASCADE,
    INDEX idx_asset_type (asset_type),
    INDEX idx_created_by (created_by),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB;

-- ============================================================
-- 15. PERFORMANCE OPTIMIZATION NOTES
-- ============================================================

-- 1. INDEXING STRATEGY:
--    - Primary keys: BIGINT UNSIGNED AUTO_INCREMENT (faster than INT for high volume)
--    - Foreign keys: Always indexed
--    - Composite indexes on common query patterns
--    - Partial indexes (WHERE clauses) for filtered queries
--    - Covering indexes for frequent SELECT queries

-- 2. HIGH-LOAD CONSIDERATIONS:
--    - sessions, service_usage, transactions: ROW_FORMAT=COMPRESSED for I/O reduction
--    - Use connection pooling (e.g., ProxySQL, application-level)
--    - Consider read replicas for reporting queries (views)
--    - Partition large tables by date (sessions, transactions) if > 10M rows
--    - Use InnoDB buffer pool efficiently (set innodb_buffer_pool_size = 70-80% of RAM)

-- 3. SCALING STRATEGIES:
--    - Vertical: Increase RAM, faster SSDs, more CPU cores
--    - Horizontal: Read replicas for SELECT-heavy workloads
--    - Sharding: Consider by location or time if > 100M rows
--    - Caching: Redis for session data, frequent queries, rate limiting

-- 4. MONITORING QUERIES:
--    - Enable slow query log (long_query_time = 1s)
--    - Monitor table scans, lock waits, buffer pool hit rate
--    - Use pt-query-digest for analysis

-- 5. BACKUP STRATEGY:
--    - Daily full backups (mysqldump or Percona XtraBackup)
--    - Binary logs for point-in-time recovery
--    - Test restores regularly

-- ============================================================
-- END OF SCHEMA
-- ============================================================
