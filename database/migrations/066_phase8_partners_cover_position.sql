-- =====================================================
-- Partners: draggable focal point on the cover photo.
--
-- Stores CSS object-position values like "35% 62%". Applied on the
-- public card so admins can pick which part of a tall photo shows
-- inside the aspect-16/10 crop without editing the source file.
--
-- Defaults to "50% 50%" (dead centre) so existing rows behave
-- identically until the admin drags the marker.
-- =====================================================

SET @c = (SELECT COUNT(*) FROM information_schema.COLUMNS
          WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'partners' AND COLUMN_NAME = 'cover_position');
SET @s = IF(@c = 0,
  "ALTER TABLE partners ADD COLUMN cover_position VARCHAR(16) NOT NULL DEFAULT '50% 50%' AFTER cover_image",
  "DO 0");
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;
