-- ============================================================
-- CTECHLIT & SYSTEMS — Migration 004
-- Transaction refunds (full, partial and line-item refunds)
-- Run after schema.sql
-- ============================================================

USE ctechlit_systems;

-- ============================================================
-- 1. REFUND RECORDS (audit trail for money returned to customers)
-- ============================================================

CREATE TABLE IF NOT EXISTS transaction_refunds (
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

-- ============================================================
-- 2. LINE-ITEM REFUND ALLOCATION
-- Links a refund to the specific transaction_items it covers,
-- so repeated partial refunds of the same line item are tracked.
-- ============================================================

CREATE TABLE IF NOT EXISTS transaction_item_refunds (
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
-- 3. VIEW: net revenue per transaction
-- ============================================================

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

-- ============================================================
-- 4. VIEW: refundable transactions at the counter
-- ============================================================

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
