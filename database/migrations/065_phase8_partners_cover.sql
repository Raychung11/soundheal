-- =====================================================
-- Partners get a proper cover photo alongside their logo. The
-- logo is what appears on the printed QR poster and any small
-- badge; the cover fills the top of the public partner card.
-- Both are file uploads now — the existing URL-only logo_url
-- input stays as a fallback (paste a hosted URL if the admin
-- prefers).
-- =====================================================

SET @c = (SELECT COUNT(*) FROM information_schema.COLUMNS
          WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'partners' AND COLUMN_NAME = 'cover_image');
SET @s = IF(@c = 0,
  "ALTER TABLE partners ADD COLUMN cover_image VARCHAR(255) DEFAULT NULL AFTER logo_url",
  "DO 0");
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;
