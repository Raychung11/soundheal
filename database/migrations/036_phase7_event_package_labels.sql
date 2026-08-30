-- =====================================================
-- Phase 7.x: Per-event package label + perks overrides
--
--   Lets each event override the default booking-page package
--   labels ("Comfort" / "Bring-Your-Own-Zen") and perk bullets so
--   special workshops (e.g. human-pet co-frequency, corporate
--   sessions) can show their own copy without changing code.
--
--     package_a_*  ← maps to price_public  (the "A" / Comfort tier)
--     package_b_*  ← maps to price_member  (the "B" / BYO tier)
--
--   NULL = fall back to the site-wide defaults. Idempotent ALTERs.
-- =====================================================

SET @c = (SELECT COUNT(*) FROM information_schema.COLUMNS
          WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'events' AND COLUMN_NAME = 'package_a_label');
SET @s = IF(@c = 0,
  "ALTER TABLE events ADD COLUMN package_a_label VARCHAR(120) DEFAULT NULL AFTER price_member",
  "DO 0");
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @c = (SELECT COUNT(*) FROM information_schema.COLUMNS
          WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'events' AND COLUMN_NAME = 'package_a_perks');
SET @s = IF(@c = 0,
  "ALTER TABLE events ADD COLUMN package_a_perks TEXT DEFAULT NULL AFTER package_a_label",
  "DO 0");
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @c = (SELECT COUNT(*) FROM information_schema.COLUMNS
          WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'events' AND COLUMN_NAME = 'package_b_label');
SET @s = IF(@c = 0,
  "ALTER TABLE events ADD COLUMN package_b_label VARCHAR(120) DEFAULT NULL AFTER package_a_perks",
  "DO 0");
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @c = (SELECT COUNT(*) FROM information_schema.COLUMNS
          WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'events' AND COLUMN_NAME = 'package_b_perks');
SET @s = IF(@c = 0,
  "ALTER TABLE events ADD COLUMN package_b_perks TEXT DEFAULT NULL AFTER package_b_label",
  "DO 0");
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;
