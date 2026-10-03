-- =====================================================
-- Phase 7.x: Event referral rewards (cash)
--
--   When a friend books via /public/event.php?id=X&ref=<code>,
--   the referrer earns a fixed cash amount — a default admin
--   setting with per-event overrides for special workshops.
--
--   Flow:
--     1. Booking created with ?ref cookie set → row inserted in
--        event_referral_rewards (status='pending').
--     2. Friend attends (all tickets scanned → booking 'attended',
--        or admin marks attended) → status flips to 'earned'.
--     3. Booking refunded → status flips to 'reversed'.
--     4. Admin settles a payout batch → payout_status='paid',
--        payout_id points at a row in referral_payouts.
--
--   Same accounting pattern as the IT-partner revenue split.
--   Idempotent ALTERs / CREATE IF NOT EXISTS.
-- =====================================================

-- events.referral_reward_amount — per-event override (NULL = use default)
SET @c = (SELECT COUNT(*) FROM information_schema.COLUMNS
          WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'events' AND COLUMN_NAME = 'referral_reward_amount');
SET @s = IF(@c = 0,
  "ALTER TABLE events ADD COLUMN referral_reward_amount DECIMAL(10,2) DEFAULT NULL AFTER experience_id",
  "DO 0");
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- event_bookings.referred_by_user_id — who referred this booking
SET @c = (SELECT COUNT(*) FROM information_schema.COLUMNS
          WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'event_bookings' AND COLUMN_NAME = 'referred_by_user_id');
SET @s = IF(@c = 0,
  "ALTER TABLE event_bookings ADD COLUMN referred_by_user_id INT UNSIGNED DEFAULT NULL",
  "DO 0");
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @i = (SELECT COUNT(*) FROM information_schema.STATISTICS
          WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'event_bookings' AND INDEX_NAME = 'idx_bookings_referrer');
SET @s = IF(@i = 0,
  "ALTER TABLE event_bookings ADD INDEX idx_bookings_referrer (referred_by_user_id)",
  "DO 0");
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;

CREATE TABLE IF NOT EXISTS event_referral_rewards (
    id                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    booking_id        INT UNSIGNED NOT NULL,
    referrer_id       INT UNSIGNED NOT NULL,
    amount            DECIMAL(10,2) NOT NULL,
    currency          VARCHAR(8) NOT NULL DEFAULT 'MYR',
    status            ENUM('pending','earned','reversed') NOT NULL DEFAULT 'pending',
    payout_status     ENUM('unpaid','paid') NOT NULL DEFAULT 'unpaid',
    payout_id         INT UNSIGNED DEFAULT NULL,
    earned_at         DATETIME DEFAULT NULL,
    reversed_at       DATETIME DEFAULT NULL,
    note              VARCHAR(255) DEFAULT NULL,
    created_at        DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_rrewards_booking (booking_id),
    INDEX idx_rrewards_referrer (referrer_id, payout_status),
    INDEX idx_rrewards_status (status, payout_status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS referral_payouts (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    referrer_id  INT UNSIGNED NOT NULL,
    amount       DECIMAL(10,2) NOT NULL,
    currency     VARCHAR(8) NOT NULL DEFAULT 'MYR',
    reward_count INT UNSIGNED NOT NULL DEFAULT 0,
    reference    VARCHAR(160) DEFAULT NULL,
    paid_by      INT UNSIGNED DEFAULT NULL,
    created_at   DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_rpayouts_referrer (referrer_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Default reward amount (RM per successful referral). Editable in
-- Admin → Referral program.
INSERT INTO site_settings (`key`, `value`, `value_type`) VALUES
    ('referral_event_reward_default', '50.00', 'string')
ON DUPLICATE KEY UPDATE `key` = `key`;
