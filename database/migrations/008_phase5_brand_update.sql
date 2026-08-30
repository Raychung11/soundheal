-- =====================================================
-- Phase 5 follow-up: brand identity
-- Sets the live brand to jaemiesoundbath / JLC Management Sdn. Bhd.
-- Idempotent — re-running is harmless.
-- =====================================================

INSERT INTO site_settings (`key`, `value`, `value_type`) VALUES
    ('company_name',       'jaemiesoundbath',           'string'),
    ('company_legal_name', 'JLC Management Sdn. Bhd.',  'string')
ON DUPLICATE KEY UPDATE
    `value` = VALUES(`value`),
    `value_type` = VALUES(`value_type`);
