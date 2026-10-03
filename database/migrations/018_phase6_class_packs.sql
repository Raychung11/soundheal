-- =====================================================
-- Phase 6: Class packs (multi-class bundles)
--
--   Example use-case:
--     - RM 99  → single offline class (uses event price, no pack needed)
--     - RM 400 → 4-class pack + 1 free (5 credits total, 90-day validity)
--
--   Schema:
--     class_packs        — admin-defined bundles (name, price, credits, validity)
--     pack_purchases     — one row per paid purchase, links payment → credits
--     member_credits     — ledger: +credits on purchase, -1 on redemption,
--                          +1 on cancellation. Balance = SUM(credits_change).
-- =====================================================

CREATE TABLE IF NOT EXISTS class_packs (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    slug VARCHAR(64) NOT NULL UNIQUE,
    name VARCHAR(190) NOT NULL,
    tagline VARCHAR(255) DEFAULT NULL,
    description TEXT,
    paid_credits INT UNSIGNED NOT NULL DEFAULT 1,         -- credits the customer pays for
    bonus_credits INT UNSIGNED NOT NULL DEFAULT 0,        -- "free" credits on top
    price DECIMAL(10,2) NOT NULL DEFAULT 0,
    validity_days INT UNSIGNED NOT NULL DEFAULT 90,       -- 0 = no expiry
    status ENUM('active','inactive') NOT NULL DEFAULT 'active',
    sort_order INT NOT NULL DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_class_packs_status (status, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS pack_purchases (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    pack_id INT UNSIGNED NOT NULL,
    payment_id INT UNSIGNED DEFAULT NULL,
    pack_snapshot JSON DEFAULT NULL,                      -- name/price/credits at purchase
    credits_granted INT UNSIGNED NOT NULL,                -- paid + bonus
    credits_remaining INT NOT NULL DEFAULT 0,             -- denormalised cache
    expires_at DATETIME DEFAULT NULL,
    status ENUM('pending','active','expired','cancelled') NOT NULL DEFAULT 'pending',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_pack_purchases_user (user_id, status),
    INDEX idx_pack_purchases_payment (payment_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS member_credits (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    purchase_id INT UNSIGNED DEFAULT NULL,                -- pack_purchases.id
    booking_id INT UNSIGNED DEFAULT NULL,                 -- event_bookings.id (on redemption)
    credits_change INT NOT NULL,                          -- +N on grant, -1 on use, +1 on refund
    reason VARCHAR(80) NOT NULL,                          -- 'pack_purchase','booking_redeem','booking_cancel','admin_adjust','expiry'
    note VARCHAR(255) DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_member_credits_user (user_id, created_at),
    INDEX idx_member_credits_purchase (purchase_id),
    INDEX idx_member_credits_booking (booking_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Allow event_bookings to be paid by credit (no payment row needed).
SET @col_exists := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME   = 'event_bookings'
      AND COLUMN_NAME  = 'paid_with_credit'
);
SET @sql := IF(@col_exists = 0,
    'ALTER TABLE event_bookings ADD COLUMN paid_with_credit TINYINT(1) NOT NULL DEFAULT 0 AFTER payment_id',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Add 'class_pack' to the payments.purpose enum.
ALTER TABLE payments MODIFY COLUMN purpose
    ENUM('membership','booking','class_pack','other') NOT NULL DEFAULT 'booking';

-- Seed the launch offer.
INSERT INTO class_packs (slug, name, tagline, description, paid_credits, bonus_credits, price, validity_days, status, sort_order)
VALUES
    ('starter-5',
     '5-Class Pack',
     'Pay for 4, gather for 5',
     'Save with a small bundle. Pay for four offline classes and we''ll gift you a fifth — yours to enjoy within 90 days.',
     4, 1, 400.00, 90, 'active', 10)
ON DUPLICATE KEY UPDATE slug = slug;
