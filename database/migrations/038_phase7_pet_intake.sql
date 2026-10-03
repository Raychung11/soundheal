-- =====================================================
-- Phase 7.x: Per-event intake form (starting with pet workshops)
--
--   Adds:
--     events.intake_type           — 'none' | 'pet' (extensible)
--     event_bookings.intake_data   — JSON blob of the answers
--
--   When an event has intake_type != 'none', the booking page
--   renders the matching extra fields inline (pawrent details +
--   pet profile for the pet workshop). Answers land in
--   intake_data alongside the rest of the booking.
--
--   Flips the 人宠共频 workshop (slug:
--   gong-bath-pet-wellness-2026-07-12) to intake_type='pet' so the
--   form picks it up the moment migration 038 is applied.
-- =====================================================

SET @c = (SELECT COUNT(*) FROM information_schema.COLUMNS
          WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'events' AND COLUMN_NAME = 'intake_type');
SET @s = IF(@c = 0,
  "ALTER TABLE events ADD COLUMN intake_type ENUM('none','pet') NOT NULL DEFAULT 'none' AFTER package_b_perks",
  "DO 0");
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @c = (SELECT COUNT(*) FROM information_schema.COLUMNS
          WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'event_bookings' AND COLUMN_NAME = 'intake_data');
SET @s = IF(@c = 0,
  "ALTER TABLE event_bookings ADD COLUMN intake_data TEXT DEFAULT NULL",
  "DO 0");
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;

UPDATE events SET intake_type = 'pet'
 WHERE slug = 'gong-bath-pet-wellness-2026-07-12';
