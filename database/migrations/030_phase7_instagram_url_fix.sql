-- =====================================================
-- Phase 7.x: Correct the Instagram handle to jaemie.soundbath
--
--   Supersedes migration 029 (which used the earlier
--   "jaemie.southbath" spelling). Append-only follow-up; 029 is
--   left intact. Idempotent: ON DUPLICATE KEY UPDATE overwrites.
-- =====================================================

INSERT INTO site_settings (`key`, `value`, `value_type`) VALUES
  ('company_social_instagram',
   'https://www.instagram.com/jaemie.soundbath?igsh=MTF2bTBxZGhiMjJrYw==',
   'string')
ON DUPLICATE KEY UPDATE
  `value`      = VALUES(`value`),
  `value_type` = VALUES(`value_type`);
