-- =====================================================
-- Phase 6.x: About-page "Story" — brand-neutral wording
--
--   Supersedes migration 026: replaces the brand name in the
--   opening line with neutral phrasing ("Our practice began…")
--   so the statement never drifts if the brand name changes.
--   Append-only follow-up; 026 is left intact.
--
--   Idempotent: ON DUPLICATE KEY UPDATE overwrites.
-- =====================================================

INSERT INTO site_settings (`key`, `value`, `value_type`) VALUES
  ('about_story_paragraphs',
'Our practice began as a quiet gathering between friends — meeting once a week with bowls and breath, holding space for the noise of the city to settle.

Today we carry that same intention into a wider community: in-person sessions, a curated audio library, and an AI concierge designed to soften the path back to yourself.

We are not a clinic. We are not a fad. We are a sanctuary — small enough to know your name, intentional enough to honour your stillness.',
   'text')
ON DUPLICATE KEY UPDATE
  `value`      = VALUES(`value`),
  `value_type` = VALUES(`value_type`);
