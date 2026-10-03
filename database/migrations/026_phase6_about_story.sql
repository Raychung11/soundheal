-- =====================================================
-- Phase 6.x: Refreshed About-page "Story" statement
--
--   Updates the about_story_paragraphs setting (the intro
--   story block, editable from /admin/about_settings.php).
--   Paragraphs are separated by a blank line; the public
--   page splits on blank lines and renders each as a <p>.
--
--   Idempotent: ON DUPLICATE KEY UPDATE overwrites, so
--   re-running always re-applies the copy below.
-- =====================================================

INSERT INTO site_settings (`key`, `value`, `value_type`) VALUES
  ('about_story_paragraphs',
'SoundHeal began as a quiet practice between friends — gathering once a week with bowls and breath, holding space for the noise of the city to settle.

Today we carry that same intention into a wider community: in-person sessions, a curated audio library, and an AI concierge designed to soften the path back to yourself.

We are not a clinic. We are not a fad. We are a sanctuary — small enough to know your name, intentional enough to honour your stillness.',
   'text')
ON DUPLICATE KEY UPDATE
  `value`      = VALUES(`value`),
  `value_type` = VALUES(`value_type`);
