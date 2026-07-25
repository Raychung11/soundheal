-- =====================================================
-- Manual invoices — B2B billing for speaker fees, corporate
-- workshops, consulting, sponsorships. The existing invoices
-- table already snapshots line items + billing party JSON, so
-- the only schema changes needed are:
--
--   purpose ENUM gains 'manual'.
--   Existing customer_snapshot JSON reused to carry the company
--   bill-to (name, address, contact_name, contact_email, tax_id).
--   New bill_to_type column disambiguates so downstream code
--   knows whether to look up a real user_id or trust the JSON.
--
-- user_id stays required (points at the admin who created the
-- invoice, for audit) since making it nullable would ripple
-- through every existing query.
-- =====================================================

ALTER TABLE invoices
    MODIFY COLUMN purpose ENUM('booking','membership','other','manual') NOT NULL DEFAULT 'booking';

SET @c = (SELECT COUNT(*) FROM information_schema.COLUMNS
          WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'invoices' AND COLUMN_NAME = 'bill_to_type');
SET @s = IF(@c = 0,
  "ALTER TABLE invoices ADD COLUMN bill_to_type ENUM('user','company') NOT NULL DEFAULT 'user' AFTER user_id",
  "DO 0");
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;
