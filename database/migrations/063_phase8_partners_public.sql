-- =====================================================
-- Phase 8: Public partners listing on top of the existing referral
-- partners table.
--
-- The QR-referral flow already has a partners table (id, name, slug,
-- commission_rate, etc.). The user wants a public "Our partners"
-- page too — reuses those rows so a cafe that both hands out a QR
-- and appears on the public page doesn't need a duplicate record.
--
-- New columns:
--   show_on_public_page — surface this partner on /public/partners.php
--   category            — soft grouping ("Cafés", "Wellness studios")
--   description         — short blurb shown on the card
--   website_url         — outbound link on the card
--   sort_order          — ordering within a category
--
-- All nullable / default-safe so existing QR-only rows aren't
-- forced to fill anything in.
-- =====================================================

SET @c = (SELECT COUNT(*) FROM information_schema.COLUMNS
          WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'partners' AND COLUMN_NAME = 'show_on_public_page');
SET @s = IF(@c = 0,
  "ALTER TABLE partners ADD COLUMN show_on_public_page TINYINT(1) NOT NULL DEFAULT 0 AFTER notes",
  "DO 0");
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @c = (SELECT COUNT(*) FROM information_schema.COLUMNS
          WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'partners' AND COLUMN_NAME = 'category');
SET @s = IF(@c = 0,
  "ALTER TABLE partners ADD COLUMN category VARCHAR(80) DEFAULT NULL AFTER show_on_public_page",
  "DO 0");
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @c = (SELECT COUNT(*) FROM information_schema.COLUMNS
          WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'partners' AND COLUMN_NAME = 'description');
SET @s = IF(@c = 0,
  "ALTER TABLE partners ADD COLUMN description TEXT DEFAULT NULL AFTER category",
  "DO 0");
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @c = (SELECT COUNT(*) FROM information_schema.COLUMNS
          WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'partners' AND COLUMN_NAME = 'website_url');
SET @s = IF(@c = 0,
  "ALTER TABLE partners ADD COLUMN website_url VARCHAR(255) DEFAULT NULL AFTER description",
  "DO 0");
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @c = (SELECT COUNT(*) FROM information_schema.COLUMNS
          WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'partners' AND COLUMN_NAME = 'sort_order');
SET @s = IF(@c = 0,
  "ALTER TABLE partners ADD COLUMN sort_order INT NOT NULL DEFAULT 100 AFTER website_url",
  "DO 0");
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @i = (SELECT COUNT(*) FROM information_schema.STATISTICS
          WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'partners' AND INDEX_NAME = 'idx_partners_public');
SET @s = IF(@i = 0,
  "ALTER TABLE partners ADD INDEX idx_partners_public (show_on_public_page, sort_order, id)",
  "DO 0");
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Copy hero / seo defaults so admins can theme the public page from
-- site_settings later without a schema change.
INSERT INTO site_settings (`key`, `value`, `value_type`) VALUES
    ('partners_page_eyebrow',    'Partners',                                                         'string'),
    ('partners_page_headline',   'The circle around the sound',                                      'string'),
    ('partners_page_intro',      'Friends and neighbours we hold space with — cafés, studios, retreat centres and pet-loving businesses that keep the community grounded.', 'text')
ON DUPLICATE KEY UPDATE `key` = `key`;
