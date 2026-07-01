-- =====================================================
-- Phase 8: Custom-dates recurrence mode.
--
-- Admins with irregular schedules (e.g. sound baths on 7 Jul, 14 Jul,
-- 21 Jul but SKIP 29 Jul, then 4 Aug, 18 Aug) don't fit any of the
-- daily / weekly / monthly patterns cleanly. This mode lets them
-- pick specific dates from a picker; each becomes an occurrence.
--
-- Storage:
--   events.recurrence gains a 'custom' value.
--   events.custom_dates (TEXT) holds a CSV of YYYY-MM-DD dates.
--
-- The recurrence_days column (VARCHAR(20)) is too small for a list
-- of full dates, so a new column is added rather than reusing it.
-- =====================================================

ALTER TABLE events
    MODIFY COLUMN recurrence ENUM('none','daily','weekly','monthly','custom') NOT NULL DEFAULT 'none';

SET @c = (SELECT COUNT(*) FROM information_schema.COLUMNS
          WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'events' AND COLUMN_NAME = 'custom_dates');
SET @s = IF(@c = 0,
  "ALTER TABLE events ADD COLUMN custom_dates TEXT DEFAULT NULL AFTER recurrence_days",
  "DO 0");
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;
