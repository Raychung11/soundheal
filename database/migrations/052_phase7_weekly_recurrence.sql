-- =====================================================
-- Phase 7.x: Weekly recurrence for events
--
--   Extends the recurrence system beyond one-off / daily so
--   partner sessions that repeat on specific days of the week
--   (e.g. every Tue & Thu at 7pm) can be modelled without
--   creating N one-off events.
--
--   Schema:
--     events.recurrence ENUM('none','daily','weekly') — was
--       ('none','daily'). Widening the enum is a safe change
--       for existing rows.
--     events.recurrence_days VARCHAR(20) — CSV of ISO day
--       numbers 0..6 (0=Sun, 6=Sat) for 'weekly'; NULL for
--       'none' / 'daily'.
--
--   The template's starts_at hh:mm:ss is still the session time;
--   only the pattern of *which days* changes for weekly. Bookings
--   on individual dates still materialise a concrete child event
--   via find_or_create_recurring_instance() so capacity, tickets,
--   revenue split and refunds keep working unchanged.
-- =====================================================

ALTER TABLE events
    MODIFY COLUMN recurrence ENUM('none','daily','weekly') NOT NULL DEFAULT 'none';

SET @c = (SELECT COUNT(*) FROM information_schema.COLUMNS
          WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'events' AND COLUMN_NAME = 'recurrence_days');
SET @s = IF(@c = 0,
  "ALTER TABLE events ADD COLUMN recurrence_days VARCHAR(20) DEFAULT NULL AFTER recurrence_until",
  "DO 0");
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;
