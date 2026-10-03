-- =====================================================
-- Phase 8: Multiple time slots per event day.
--
-- Some sessions run more than once on the same date — a 3pm and a
-- 6pm gong bath on Saturday, for example. Rather than force admins
-- to create two separate events with duplicated metadata, we let a
-- single event carry a list of *additional* start times. Each entry
-- generates its own occurrence per candidate date, alongside the
-- primary starts_at time.
--
-- Storage:
--   events.time_slots (VARCHAR(255)) — CSV of HH:MM values, e.g. "18:00,20:30".
--   Empty / NULL means the event runs once per date at starts_at
--   (existing behaviour).
--
-- Bookings materialise per (parent, date, time) so each slot has its
-- own capacity, its own seat count, its own tickets.
-- =====================================================

SET @c = (SELECT COUNT(*) FROM information_schema.COLUMNS
          WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'events' AND COLUMN_NAME = 'time_slots');
SET @s = IF(@c = 0,
  "ALTER TABLE events ADD COLUMN time_slots VARCHAR(255) DEFAULT NULL AFTER custom_dates",
  "DO 0");
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;
