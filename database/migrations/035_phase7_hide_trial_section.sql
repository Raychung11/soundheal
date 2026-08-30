-- =====================================================
-- Phase 7.x: Hide the home-page free-trial section
--
--   Flips trial_enabled to '0' so the "A gift on the threshold /
--   Try a 2-minute sound bath, on us" block no longer renders.
--   The toggle is also exposed in Admin → Home page → "Free trial
--   section · Show on home page" — flip it back any time without
--   another migration.
-- =====================================================

INSERT INTO site_settings (`key`, `value`, `value_type`) VALUES
  ('trial_enabled', '0', 'bool')
ON DUPLICATE KEY UPDATE
  `value`      = VALUES(`value`),
  `value_type` = VALUES(`value_type`);
