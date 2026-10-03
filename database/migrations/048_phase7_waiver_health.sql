-- =====================================================
-- Phase 7.x: Booking waiver + health disclosure
--
--   Wellness liability + PDPA basics:
--
--   users.waiver_accepted_at DATETIME NULL — stamped when the
--     member ticks "I've read and agree" on the booking form. If
--     the admin edits the waiver text (which bumps
--     legal_waiver_updated_at automatically in legal_settings.php)
--     and the member's accepted_at is older, the checkbox appears
--     again so they must re-consent.
--
--   event_bookings.health_disclosure TEXT NULL — free-text field
--     where the member can flag conditions (pregnancy, cardiac,
--     epilepsy, medication) — surfaced on the session prep sheet
--     for front-of-house.
--
--   Seeds a sensible default waiver body (from the existing
--   "not medical advice" language) so the checkbox has content
--   the first time a member sees it. Editable from
--   Admin → Legal pages → Waiver.
-- =====================================================

SET @c = (SELECT COUNT(*) FROM information_schema.COLUMNS
          WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'waiver_accepted_at');
SET @s = IF(@c = 0,
  "ALTER TABLE users ADD COLUMN waiver_accepted_at DATETIME DEFAULT NULL",
  "DO 0");
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @c = (SELECT COUNT(*) FROM information_schema.COLUMNS
          WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'event_bookings' AND COLUMN_NAME = 'health_disclosure');
SET @s = IF(@c = 0,
  "ALTER TABLE event_bookings ADD COLUMN health_disclosure TEXT DEFAULT NULL",
  "DO 0");
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Seed default waiver copy. INSERT IGNORE keeps the admin's edits
-- safe across re-runs.
INSERT IGNORE INTO site_settings (`key`, `value`, `value_type`) VALUES
  ('legal_waiver_title', 'Session waiver &amp; health acknowledgement', 'string'),
  ('legal_waiver_updated_at', DATE_FORMAT(NOW(), '%Y-%m-%d'), 'string'),
  ('legal_waiver_body',
   '<p>Our sessions are a complementary wellness practice — not medical treatment. They are not intended to diagnose, treat, cure or replace professional medical or mental-health care.</p><p>By booking, you confirm that you are participating voluntarily and at your own risk, and that you have consulted a qualified healthcare provider if you have any concerns (including but not limited to: pregnancy, epilepsy or seizure history, active cardiac condition, severe mental-health condition, recent surgery, or being under medical supervision).</p><p>Please share any relevant conditions in the "anything we should know" field so we can hold space for you safely. Individual experiences vary.</p>',
   'text');
