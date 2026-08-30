-- =====================================================
-- Phase 7.x: Link events to experiences
--
--   Each event can be tagged with the experience it belongs to.
--   The public Experiences page uses this link to point its
--   "Reserve" button at the matching sessions, and the public
--   events page filters by ?experience=<slug> when arriving from
--   that link.
--
--   NULL = unlinked (existing events default to this — set the
--   link in Admin → Events as you edit each one).
--
--   Idempotent ALTER + index.
-- =====================================================

SET @c = (SELECT COUNT(*) FROM information_schema.COLUMNS
          WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'events' AND COLUMN_NAME = 'experience_id');
SET @s = IF(@c = 0,
  "ALTER TABLE events ADD COLUMN experience_id INT UNSIGNED DEFAULT NULL AFTER category",
  "DO 0");
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @i = (SELECT COUNT(*) FROM information_schema.STATISTICS
          WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'events' AND INDEX_NAME = 'idx_events_experience');
SET @s = IF(@i = 0,
  "ALTER TABLE events ADD INDEX idx_events_experience (experience_id)",
  "DO 0");
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Best-effort backfill: link existing events to existing experiences
-- by title prefix (e.g. a "Crystal Bowl Sound Bath" event lands under
-- the "Sound Bath" experience). Safe to re-run — UPDATE WHERE
-- experience_id IS NULL so manual links aren't overwritten.
UPDATE events e
   JOIN experiences x ON x.status = 'active'
                     AND (
                          e.title LIKE CONCAT('%', x.title, '%')
                       OR (x.slug = 'sound-bath' AND e.title LIKE '%Sound Bath%')
                     )
    SET e.experience_id = x.id
  WHERE e.experience_id IS NULL;
