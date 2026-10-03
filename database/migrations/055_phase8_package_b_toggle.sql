-- =====================================================
-- Phase 8: Per-event toggle for the second package (Package B / BYO).
--
-- Some events (workshops with a single-tier ticket, private
-- corporate sessions, retreats) don't offer the Bring-Your-Own-Zen
-- option. Admins set this on the event form; the booking page
-- and public event page hide the second card when it's off.
--
-- Defaults to 1 so every existing event keeps both packages on.
-- Idempotent ALTER guarded by information_schema.
-- =====================================================

SET @c = (SELECT COUNT(*) FROM information_schema.COLUMNS
          WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'events' AND COLUMN_NAME = 'package_b_enabled');
SET @s = IF(@c = 0,
  "ALTER TABLE events ADD COLUMN package_b_enabled TINYINT(1) NOT NULL DEFAULT 1 AFTER package_b_perks",
  "DO 0");
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;
