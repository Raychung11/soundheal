-- =====================================================
-- Phase 8: Tighten partner FKs so deleting a partner cannot silently
-- wipe accounting history.
--
--   Migration 054 shipped partner_referrals.partner_id and
--   partner_referral_payouts.partner_id with ON DELETE CASCADE, which
--   means a `DELETE FROM partners WHERE id = X` erases every
--   attributed booking record and payout receipt for that partner —
--   including rewards that were already paid. This migration re-adds
--   both constraints as ON DELETE RESTRICT so any attempt to delete
--   a partner errors out until the ledger is manually reconciled.
--
--   Also adds the previously-missing FK on event_bookings.partner_id
--   so a booking cannot end up pointing at a nonexistent partner id.
--
--   Idempotent: constraint drops guarded by information_schema, and
--   the CREATEs skip if a constraint with the same name is already
--   present.
-- =====================================================

-- partner_referrals.partner_id: drop CASCADE, add RESTRICT.
SET @c = (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
          WHERE TABLE_SCHEMA = DATABASE()
            AND TABLE_NAME   = 'partner_referrals'
            AND CONSTRAINT_NAME = 'fk_partner_ref_partner');
SET @s = IF(@c = 1,
  "ALTER TABLE partner_referrals DROP FOREIGN KEY fk_partner_ref_partner",
  "DO 0");
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @c = (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
          WHERE TABLE_SCHEMA = DATABASE()
            AND TABLE_NAME   = 'partner_referrals'
            AND CONSTRAINT_NAME = 'fk_partner_ref_partner');
SET @s = IF(@c = 0,
  "ALTER TABLE partner_referrals
     ADD CONSTRAINT fk_partner_ref_partner
     FOREIGN KEY (partner_id) REFERENCES partners(id) ON DELETE RESTRICT",
  "DO 0");
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- partner_referral_payouts.partner_id: drop CASCADE, add RESTRICT.
-- Guarded because the parent table may not exist yet on deployments
-- that skipped the renamed 054.
SET @t = (SELECT COUNT(*) FROM information_schema.TABLES
          WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'partner_referral_payouts');
SET @c = IF(@t = 1,
  (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME   = 'partner_referral_payouts'
       AND CONSTRAINT_NAME = 'fk_partner_ref_payouts_partner'),
  0);
SET @s = IF(@c = 1,
  "ALTER TABLE partner_referral_payouts DROP FOREIGN KEY fk_partner_ref_payouts_partner",
  "DO 0");
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @c = IF(@t = 1,
  (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME   = 'partner_referral_payouts'
       AND CONSTRAINT_NAME = 'fk_partner_ref_payouts_partner'),
  1);
SET @s = IF(@t = 1 AND @c = 0,
  "ALTER TABLE partner_referral_payouts
     ADD CONSTRAINT fk_partner_ref_payouts_partner
     FOREIGN KEY (partner_id) REFERENCES partners(id) ON DELETE RESTRICT",
  "DO 0");
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- event_bookings.partner_id: add FK if not already present. Uses
-- RESTRICT for the same reason — a booking must not orphan itself.
-- Any pre-existing rows with a stale partner_id would block this
-- ALTER, so we NULL them out first (they're already unreachable
-- since attribute_partner_booking wouldn't have written a bad id).
UPDATE event_bookings b
   LEFT JOIN partners p ON p.id = b.partner_id
   SET b.partner_id = NULL
 WHERE b.partner_id IS NOT NULL AND p.id IS NULL;

SET @c = (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
          WHERE TABLE_SCHEMA = DATABASE()
            AND TABLE_NAME   = 'event_bookings'
            AND CONSTRAINT_NAME = 'fk_bookings_partner');
SET @s = IF(@c = 0,
  "ALTER TABLE event_bookings
     ADD CONSTRAINT fk_bookings_partner
     FOREIGN KEY (partner_id) REFERENCES partners(id) ON DELETE RESTRICT",
  "DO 0");
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;
