-- =====================================================
-- Phase 7.x: Capture which package a booking belongs to
--
--   Adds event_bookings.package so each booking records the
--   amenity tier the member picked at reserve time:
--     'comfort'  — welcome drink + yoga mat + blanket
--     'byo'      — bring your own mat / blanket
--     'standard' — legacy / pre-package rows (default)
--
--   Idempotent: skipped if the column already exists.
-- =====================================================

SET @c = (SELECT COUNT(*) FROM information_schema.COLUMNS
          WHERE TABLE_SCHEMA = DATABASE()
            AND TABLE_NAME = 'event_bookings'
            AND COLUMN_NAME = 'package');
SET @s = IF(@c = 0,
  "ALTER TABLE event_bookings ADD COLUMN package ENUM('standard','comfort','byo') NOT NULL DEFAULT 'standard'",
  "DO 0");
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;
