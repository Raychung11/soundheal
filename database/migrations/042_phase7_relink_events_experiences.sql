-- =====================================================
-- Phase 7.x: Smarter experience → event backfill
--
--   Migration 040's backfill only matched when the *entire*
--   experience title appeared as a substring of the event title
--   — so an experience like "🐾 Human & Pet Co-Resonance
--   Workshop" (emoji prefix) missed events titled just
--   "Human & Pet Co-Resonance Workshop".
--
--   This migration re-runs the backfill with:
--     - alpha-numeric-only normalisation (strips emojis and
--       punctuation from both sides before comparing)
--     - case-insensitive match
--     - matches in either direction (event title contains the
--       normalised experience title, OR the reverse)
--
--   Only fills events where experience_id IS NULL — any manual
--   link from the admin Events form is preserved.
--
--   Requires MySQL 8.0+ for REGEXP_REPLACE. Idempotent.
-- =====================================================

UPDATE events e
  JOIN experiences x
    ON x.status = 'active'
   AND (
        LOWER(REGEXP_REPLACE(e.title, '[^A-Za-z0-9]+', ''))
          LIKE CONCAT('%', LOWER(REGEXP_REPLACE(x.title, '[^A-Za-z0-9]+', '')), '%')
     OR LOWER(REGEXP_REPLACE(x.title, '[^A-Za-z0-9]+', ''))
          LIKE CONCAT('%', LOWER(REGEXP_REPLACE(e.title, '[^A-Za-z0-9]+', '')), '%')
       )
   SET e.experience_id = x.id
 WHERE e.experience_id IS NULL
   AND e.parent_event_id IS NULL;
