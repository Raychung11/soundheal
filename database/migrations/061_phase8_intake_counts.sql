-- =====================================================
-- Phase 8: Per-package intake composition (humans + pets).
--
-- The pet workshop currently hard-codes "Comfort = 2 pets, BYO = 1 pet"
-- in reserve.php. Real bookings vary: 2 humans + 1 pet, all humans,
-- 1 human + 3 pets, etc. Admins should set the intake shape per event
-- + per package rather than having it baked into the booking flow.
--
-- Storage — 4 new nullable TINYINTs on events. Defaults chosen to
-- keep the existing behaviour when the admin hasn't touched the
-- fields yet:
--   package_a_humans = 1   package_a_pets = 2
--   package_b_humans = 1   package_b_pets = 1
--
-- These only apply when intake_type = 'pet'; other events ignore
-- them entirely.
-- =====================================================

SET @c = (SELECT COUNT(*) FROM information_schema.COLUMNS
          WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'events' AND COLUMN_NAME = 'package_a_humans');
SET @s = IF(@c = 0,
  "ALTER TABLE events ADD COLUMN package_a_humans TINYINT UNSIGNED NOT NULL DEFAULT 1 AFTER package_b_enabled",
  "DO 0");
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @c = (SELECT COUNT(*) FROM information_schema.COLUMNS
          WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'events' AND COLUMN_NAME = 'package_a_pets');
SET @s = IF(@c = 0,
  "ALTER TABLE events ADD COLUMN package_a_pets TINYINT UNSIGNED NOT NULL DEFAULT 2 AFTER package_a_humans",
  "DO 0");
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @c = (SELECT COUNT(*) FROM information_schema.COLUMNS
          WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'events' AND COLUMN_NAME = 'package_b_humans');
SET @s = IF(@c = 0,
  "ALTER TABLE events ADD COLUMN package_b_humans TINYINT UNSIGNED NOT NULL DEFAULT 1 AFTER package_a_pets",
  "DO 0");
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @c = (SELECT COUNT(*) FROM information_schema.COLUMNS
          WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'events' AND COLUMN_NAME = 'package_b_pets');
SET @s = IF(@c = 0,
  "ALTER TABLE events ADD COLUMN package_b_pets TINYINT UNSIGNED NOT NULL DEFAULT 1 AFTER package_b_humans",
  "DO 0");
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;
