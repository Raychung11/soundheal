-- =====================================================
-- Phase 8: Partner referrals (cafes / businesses)
--
--   Cafes and other partners hand out a QR sticker or poster
--   pointing at /public/p.php?s=<slug>. Visitors who scan get
--   a partner_ref cookie; when they book, we credit the partner
--   with a commission on the same accounting rails as
--   event_referral_rewards.
--
--   partners                  — one row per business.
--   partner_referrals         — ledger of attributed bookings, mirrors
--                               event_referral_rewards structure so the
--                               admin flow is familiar.
--   partner_referral_payouts  — batches unpaid earned rows into paid runs.
--                               (NB: separate from the pre-existing
--                                partner_payouts table used by the IT
--                                revenue-split feature in migration 028.)
--   event_bookings.partner_id — quick foreign-key lookup for refunds.
--
--   Idempotent: ALTERs use information_schema guards,
--   CREATE TABLE uses IF NOT EXISTS, seeds use INSERT IGNORE.
-- =====================================================

CREATE TABLE IF NOT EXISTS partners (
    id                       INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name                     VARCHAR(180) NOT NULL,
    slug                     VARCHAR(80)  NOT NULL,
    contact_name             VARCHAR(160) DEFAULT NULL,
    contact_email            VARCHAR(180) DEFAULT NULL,
    contact_phone            VARCHAR(40)  DEFAULT NULL,
    logo_url                 VARCHAR(255) DEFAULT NULL,
    -- Commission on each attributed, attended booking. 'fixed' pays a
    -- flat MYR amount per booking; 'percent' pays a % of the booking
    -- total. Rate is either MYR or %, interpreted per commission_type.
    commission_type          ENUM('fixed','percent') NOT NULL DEFAULT 'fixed',
    commission_rate          DECIMAL(10,2) NOT NULL DEFAULT 10.00,
    -- Optional promo code auto-applied on first booking from a scan.
    first_visit_promo_code   VARCHAR(40)  DEFAULT NULL,
    -- Where the QR should land the visitor after cookie is dropped.
    -- Empty string = sessions calendar. Otherwise a relative path.
    landing_path             VARCHAR(255) NOT NULL DEFAULT '/public/events.php',
    status                   ENUM('active','inactive') NOT NULL DEFAULT 'active',
    notes                    TEXT DEFAULT NULL,
    -- Rolled-up metrics for the list view; cheaper than JOINs.
    scan_count               INT UNSIGNED NOT NULL DEFAULT 0,
    last_scan_at             DATETIME DEFAULT NULL,
    created_by               INT UNSIGNED DEFAULT NULL,
    created_at               DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at               DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_partners_slug (slug),
    INDEX idx_partners_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS partner_referrals (
    id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    partner_id     INT UNSIGNED NOT NULL,
    booking_id     INT UNSIGNED NOT NULL,
    user_id        INT UNSIGNED DEFAULT NULL,
    amount         DECIMAL(10,2) NOT NULL,
    currency       VARCHAR(8) NOT NULL DEFAULT 'MYR',
    status         ENUM('pending','earned','reversed') NOT NULL DEFAULT 'pending',
    payout_status  ENUM('unpaid','paid') NOT NULL DEFAULT 'unpaid',
    payout_id      INT UNSIGNED DEFAULT NULL,
    earned_at      DATETIME DEFAULT NULL,
    reversed_at    DATETIME DEFAULT NULL,
    note           VARCHAR(255) DEFAULT NULL,
    created_at     DATETIME DEFAULT CURRENT_TIMESTAMP,
    -- One reward per booking, same as event_referral_rewards.
    UNIQUE KEY uq_partner_ref_booking (booking_id),
    INDEX idx_partner_ref_partner (partner_id, payout_status),
    INDEX idx_partner_ref_status  (status, payout_status),
    CONSTRAINT fk_partner_ref_partner FOREIGN KEY (partner_id) REFERENCES partners(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS partner_referral_payouts (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    partner_id      INT UNSIGNED NOT NULL,
    amount          DECIMAL(12,2) NOT NULL,
    currency        VARCHAR(8) NOT NULL DEFAULT 'MYR',
    reward_count    INT UNSIGNED NOT NULL DEFAULT 0,
    reference       VARCHAR(160) DEFAULT NULL,
    paid_by         INT UNSIGNED DEFAULT NULL,
    paid_at         DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_partner_ref_payouts_partner (partner_id, paid_at),
    CONSTRAINT fk_partner_ref_payouts_partner FOREIGN KEY (partner_id) REFERENCES partners(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- event_bookings.partner_id — cache of the attributed partner so refund
-- hooks can walk booking → partner without joining the ledger.
SET @c = (SELECT COUNT(*) FROM information_schema.COLUMNS
          WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'event_bookings' AND COLUMN_NAME = 'partner_id');
SET @s = IF(@c = 0,
  "ALTER TABLE event_bookings ADD COLUMN partner_id INT UNSIGNED DEFAULT NULL",
  "DO 0");
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @i = (SELECT COUNT(*) FROM information_schema.STATISTICS
          WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'event_bookings' AND INDEX_NAME = 'idx_bookings_partner');
SET @s = IF(@i = 0,
  "ALTER TABLE event_bookings ADD INDEX idx_bookings_partner (partner_id)",
  "DO 0");
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Default cookie window for a partner scan (days).
INSERT INTO site_settings (`key`, `value`, `value_type`) VALUES
    ('partner_cookie_days',        '45', 'int'),
    ('partner_default_commission', '10.00', 'string')
ON DUPLICATE KEY UPDATE `key` = `key`;
