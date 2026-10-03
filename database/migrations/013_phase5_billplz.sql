-- =====================================================
-- Phase 5 follow-up: Billplz live credentials (admin-managed)
-- These are blank by default — fill them in via /admin/payment_settings.php
-- or pre-seed via this migration if you're scripting deployment.
-- =====================================================

INSERT INTO site_settings (`key`, `value`, `value_type`) VALUES
    ('billplz_sandbox',         '1',  'bool'),   -- 1 = sandbox, 0 = live
    ('billplz_api_key',         '',   'string'),
    ('billplz_collection_id',   '',   'string'),
    ('billplz_x_signature',     '',   'string'),
    ('billplz_redirect_url',    '',   'string')  -- optional, defaults to APP_URL/member/my_bookings.php
ON DUPLICATE KEY UPDATE `key` = `key`;
