-- =====================================================
-- Phase 5 follow-up: rename "company_name" semantically
--
-- Going forward:
--   company_brand       = "jaemie sound bath"        (display)
--   company_legal_name  = "JLC Management Sdn. Bhd." (legal entity)
--
-- The deprecated key `company_name` is kept in the table for
-- backwards-compat, but the application reads `company_brand` first.
-- =====================================================

INSERT INTO site_settings (`key`, `value`, `value_type`) VALUES
    ('company_brand',      'jaemie sound bath',         'string'),
    ('company_legal_name', 'JLC Management Sdn. Bhd.',  'string')
ON DUPLICATE KEY UPDATE
    `value` = VALUES(`value`),
    `value_type` = VALUES(`value_type`);
