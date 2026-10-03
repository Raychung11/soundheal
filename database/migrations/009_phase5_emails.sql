-- =====================================================
-- Phase 5 follow-up: contact emails
-- Sets the public contact addresses to the jaemiesoundbath.com domain.
-- Idempotent — re-running is harmless.
-- =====================================================

INSERT INTO site_settings (`key`, `value`, `value_type`) VALUES
    ('company_email',         'hello@jaemiesoundbath.com',   'string'),
    ('company_billing_email', 'billing@jaemiesoundbath.com', 'string'),
    ('company_privacy_email', 'privacy@jaemiesoundbath.com', 'string')
ON DUPLICATE KEY UPDATE
    `value` = VALUES(`value`),
    `value_type` = VALUES(`value_type`);
