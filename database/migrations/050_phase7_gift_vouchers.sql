-- =====================================================
-- Phase 7.x: Gift vouchers
--
--   Fixed-value MYR vouchers with a unique code, single-use,
--   redeemable on any event booking. MVP:
--     - Admin issues them manually (list, revoke, resend email).
--     - Recipient enters the code in the booking form's promo /
--       gift-code field; validate_gift_voucher() takes precedence
--       over the promo_codes table.
--     - Full voucher amount applies as a discount (capped at the
--       booking subtotal — any leftover is not rolled over in MVP).
--     - On successful use, status flips to 'redeemed' with the
--       booking_id / redeemed_at stamped for audit.
--
--   Public purchase flow with Billplz is a later phase — for now
--   admins issue vouchers and share the code with recipients.
-- =====================================================

CREATE TABLE IF NOT EXISTS gift_vouchers (
    id                    INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code                  VARCHAR(40) NOT NULL UNIQUE,
    amount                DECIMAL(10,2) NOT NULL,
    currency              VARCHAR(8) NOT NULL DEFAULT 'MYR',
    purchaser_id          INT UNSIGNED DEFAULT NULL,
    recipient_name        VARCHAR(150) DEFAULT NULL,
    recipient_email       VARCHAR(190) DEFAULT NULL,
    message               TEXT DEFAULT NULL,
    status                ENUM('issued','redeemed','revoked','expired') NOT NULL DEFAULT 'issued',
    redeemed_by_booking_id INT UNSIGNED DEFAULT NULL,
    redeemed_at           DATETIME DEFAULT NULL,
    expires_at            DATETIME DEFAULT NULL,
    notes                 VARCHAR(255) DEFAULT NULL,
    created_by            INT UNSIGNED DEFAULT NULL,
    created_at            DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at            DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_gv_status (status),
    INDEX idx_gv_recipient (recipient_email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Link the applied voucher on a booking (parallel to promo_code).
SET @c = (SELECT COUNT(*) FROM information_schema.COLUMNS
          WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'event_bookings' AND COLUMN_NAME = 'gift_voucher_id');
SET @s = IF(@c = 0,
  "ALTER TABLE event_bookings ADD COLUMN gift_voucher_id INT UNSIGNED DEFAULT NULL",
  "DO 0");
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;
