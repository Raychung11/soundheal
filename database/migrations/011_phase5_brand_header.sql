-- =====================================================
-- Phase 5 follow-up: brand display string
-- Updates the navbar / footer / email header brand to read
-- "jaemie sound bath" (with spaces) and a tagline of
-- "Sound healing sanctuary".
-- Idempotent — re-running rewrites with the latest version.
-- =====================================================

INSERT INTO site_settings (`key`, `value`, `value_type`) VALUES
    ('company_name',    'jaemie sound bath',         'string'),
    ('company_tagline', 'Sound healing sanctuary',   'string')
ON DUPLICATE KEY UPDATE
    `value` = VALUES(`value`),
    `value_type` = VALUES(`value_type`);
