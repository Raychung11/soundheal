-- =====================================================
-- Phase 7.x: Promo / discount codes
--
--   MVP: percent or fixed-amount code, optional max uses,
--   optional valid_from / valid_until window, active/disabled
--   toggle. Applies to any booking (event scoping is a future
--   layer). Codes are case-insensitive at validate-time — stored
--   uppercased for consistency.
--
--   Adds two columns to event_bookings so we know which code
--   applied to which booking and how much came off:
--     event_bookings.promo_code       — the code text (upper)
--     event_bookings.discount_amount  — RM taken off the total
-- =====================================================

CREATE TABLE IF NOT EXISTS promo_codes (
    id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code           VARCHAR(60)  NOT NULL UNIQUE,
    description    VARCHAR(255) DEFAULT NULL,
    discount_type  ENUM('percent','fixed') NOT NULL DEFAULT 'percent',
    discount_value DECIMAL(10,2) NOT NULL,
    max_uses       INT UNSIGNED DEFAULT NULL,
    used_count     INT UNSIGNED NOT NULL DEFAULT 0,
    status         ENUM('active','disabled') NOT NULL DEFAULT 'active',
    valid_from     DATETIME DEFAULT NULL,
    valid_until    DATETIME DEFAULT NULL,
    created_by     INT UNSIGNED DEFAULT NULL,
    created_at     DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at     DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_promo_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

SET @c = (SELECT COUNT(*) FROM information_schema.COLUMNS
          WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'event_bookings' AND COLUMN_NAME = 'promo_code');
SET @s = IF(@c = 0,
  "ALTER TABLE event_bookings ADD COLUMN promo_code VARCHAR(60) DEFAULT NULL",
  "DO 0");
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @c = (SELECT COUNT(*) FROM information_schema.COLUMNS
          WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'event_bookings' AND COLUMN_NAME = 'discount_amount');
SET @s = IF(@c = 0,
  "ALTER TABLE event_bookings ADD COLUMN discount_amount DECIMAL(10,2) NOT NULL DEFAULT 0",
  "DO 0");
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;
