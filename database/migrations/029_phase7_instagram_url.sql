-- =====================================================
-- Phase 7.x: Seed the Instagram URL for the footer
--
--   Sets company_social_instagram so the Instagram link shows in
--   the public footer. Idempotent: ON DUPLICATE KEY UPDATE
--   overwrites the value, so re-running re-applies the URL below.
-- =====================================================

INSERT INTO site_settings (`key`, `value`, `value_type`) VALUES
  ('company_social_instagram',
   'https://www.instagram.com/jaemie.southbath?igsh=MTF2bTBxZGhiMjJrYw==',
   'string')
ON DUPLICATE KEY UPDATE
  `value`      = VALUES(`value`),
  `value_type` = VALUES(`value_type`);
