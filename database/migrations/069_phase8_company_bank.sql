-- =====================================================
-- Company bank account for invoice payment instructions.
--
-- When a B2B invoice is issued (Hospital Pantai Indah, sponsors,
-- corporate wellness), the printed invoice needs to tell the payer
-- WHERE to bank the money to. These five site_settings rows carry
-- the details; the invoice viewer picks them up automatically and
-- renders a "Payment details" block at the bottom when the doc is
-- still due.
--
-- INSERT IGNORE seeds empty rows so the admin form has something to
-- edit into; existing values (if any) are left untouched.
-- =====================================================

INSERT IGNORE INTO site_settings (`key`, `value`, `value_type`) VALUES
    ('company_bank_name',           '', 'string'),   -- e.g. Maybank / CIMB
    ('company_bank_account_name',   '', 'string'),   -- exact registered name on the account
    ('company_bank_account_no',     '', 'string'),   -- account number
    ('company_bank_swift',          '', 'string'),   -- SWIFT / BIC (optional, for foreign transfers)
    ('company_payment_notes',       '', 'text');     -- free text: DuitNow ID, reference format, etc.
