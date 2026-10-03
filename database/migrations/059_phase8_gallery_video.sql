-- =====================================================
-- Phase 8: Gallery items can now be videos too.
--
--   video_url — YouTube or Vimeo URL. When set, the tile shows a play
--               overlay and the lightbox renders the platform embed
--               instead of a zoomed image.
--   image     — becomes optional. For YouTube URLs the public page
--               falls back to img.youtube.com/vi/<id>/hqdefault.jpg
--               so admins don't have to upload a separate thumbnail.
--
-- MP4 uploads are intentionally out of scope for this migration —
-- shared hosting storage / bandwidth make external embeds the
-- safer default. Can add a video/* mime bucket later if needed.
-- =====================================================

ALTER TABLE gallery_photos
    MODIFY COLUMN image VARCHAR(255) DEFAULT NULL;

SET @c = (SELECT COUNT(*) FROM information_schema.COLUMNS
          WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'gallery_photos' AND COLUMN_NAME = 'video_url');
SET @s = IF(@c = 0,
  "ALTER TABLE gallery_photos ADD COLUMN video_url VARCHAR(500) DEFAULT NULL AFTER image",
  "DO 0");
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;
