-- =====================================================
-- Site-wide theme default. Individual visitors can still override
-- via the navbar toggle (persisted in the sh-theme cookie); this
-- setting is the fallback when no cookie is set.
-- =====================================================

INSERT INTO site_settings (`key`, `value`, `value_type`) VALUES
    ('site_theme', 'dark', 'string')
ON DUPLICATE KEY UPDATE `key` = `key`;
