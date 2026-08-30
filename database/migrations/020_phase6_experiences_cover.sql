-- =====================================================
-- Phase 6.x: Cover image for experiences
-- =====================================================

SET @col_exists := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME   = 'experiences'
      AND COLUMN_NAME  = 'cover_image'
);
SET @sql := IF(@col_exists = 0,
    'ALTER TABLE experiences ADD COLUMN cover_image VARCHAR(255) DEFAULT NULL AFTER description',
    'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
