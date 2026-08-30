-- =====================================================
-- Gallery photos: tag by experience (in addition to event link).
--
-- Admins have been asking for the public gallery to group / filter
-- by Experience (Sound Bath, Human & Pet, etc.) instead of just the
-- free-text category tag. Adding a proper foreign key so the filter
-- stays typo-proof and cascades cleanly when an experience is
-- renamed.
--
-- Backfill copies each photo's experience_id from its linked event
-- (when one exists), so nothing on the current gallery gets untagged.
-- =====================================================

SET @c = (SELECT COUNT(*) FROM information_schema.COLUMNS
          WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'gallery_photos' AND COLUMN_NAME = 'experience_id');
SET @s = IF(@c = 0,
  "ALTER TABLE gallery_photos ADD COLUMN experience_id INT UNSIGNED DEFAULT NULL AFTER event_id",
  "DO 0");
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @i = (SELECT COUNT(*) FROM information_schema.STATISTICS
          WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'gallery_photos' AND INDEX_NAME = 'idx_gallery_experience');
SET @s = IF(@i = 0,
  "ALTER TABLE gallery_photos ADD INDEX idx_gallery_experience (experience_id, status, sort_order)",
  "DO 0");
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @c = (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
          WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'gallery_photos' AND CONSTRAINT_NAME = 'fk_gallery_experience');
SET @s = IF(@c = 0,
  "ALTER TABLE gallery_photos ADD CONSTRAINT fk_gallery_experience FOREIGN KEY (experience_id) REFERENCES experiences(id) ON DELETE SET NULL",
  "DO 0");
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- One-shot backfill: for every gallery photo linked to an event
-- that has an experience_id, copy that experience across. Skips
-- photos that already have one set (idempotent re-run).
UPDATE gallery_photos g
   JOIN events e ON e.id = g.event_id
    SET g.experience_id = e.experience_id
  WHERE g.experience_id IS NULL
    AND e.experience_id IS NOT NULL;
