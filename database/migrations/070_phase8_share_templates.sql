-- =====================================================
-- Editable share-message templates.
--
-- The gift voucher + promo share panels each render a "ready-to-
-- paste" message. Copy was hardcoded in PHP until now — admins
-- wanted to tweak the tone (formal vs. warm, English vs. Chinese,
-- add a signoff, etc.) without touching code.
--
-- Templates support these placeholders (documented in the admin
-- textarea's helper text too):
--   {NAME}     recipient name (voucher) or "friend" fallback
--   {BRAND}    site brand name
--   {CODE}     the voucher / promo code
--   {AMOUNT}   MYR amount (voucher) / percent-or-fixed summary (promo)
--   {URL}      the reserve share URL
--   {EXPIRY}   formatted expiry date (or blank if none)
--
-- CONCAT + CHAR(10) is used for the multi-line default so the
-- linebreaks are stored as real newlines rather than a literal
-- "\n" pair (MySQL doesn't interpret \n in single-quoted strings
-- by default and this behaviour varies by SQL_MODE).
-- =====================================================

INSERT IGNORE INTO site_settings (`key`, `value`, `value_type`) VALUES
    ('voucher_share_message',
     CONCAT(
        'Hi {NAME} — a small gift from {BRAND}.', CHAR(10), CHAR(10),
        'Your voucher code is {CODE} (worth {AMOUNT}).', CHAR(10), CHAR(10),
        'Redeem here: {URL}', CHAR(10), CHAR(10),
        'Warm regards,', CHAR(10),
        '{BRAND}'
     ),
     'text'),
    ('promo_share_message',
     CONCAT(
        'Hi there — sending a little something from {BRAND}.', CHAR(10), CHAR(10),
        'Use code {CODE} at checkout for {AMOUNT} your booking.', CHAR(10), CHAR(10),
        'Reserve here: {URL}', CHAR(10), CHAR(10),
        'Warm regards,', CHAR(10),
        '{BRAND}'
     ),
     'text');
