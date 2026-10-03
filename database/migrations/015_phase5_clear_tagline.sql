-- =====================================================
-- Phase 5 follow-up: clear the "Wellness Operating System" tagline.
-- The header was still rendering this fallback because earlier rows in
-- site_settings (or the env default) carried it. Clearing it makes the
-- navbar render brand-only; admin can re-add a tagline at any time from
-- /admin/footer_settings.php.
-- =====================================================

INSERT INTO site_settings (`key`, `value`, `value_type`) VALUES
    ('company_tagline', '', 'string')
ON DUPLICATE KEY UPDATE
    `value` = '',
    `value_type` = 'string';
