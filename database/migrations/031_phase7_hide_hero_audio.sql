-- =====================================================
-- Phase 7.x: Hide the hero ambient-audio pill
--
--   Clears hero_audio_path so the "Wear headphones if you can"
--   ambient player on the home hero disappears (its block is
--   gated on $heroAudio being non-empty). Re-uploading audio
--   from Admin → Home page brings it back.
--
--   Idempotent: ON DUPLICATE KEY UPDATE overwrites.
-- =====================================================

INSERT INTO site_settings (`key`, `value`, `value_type`) VALUES
  ('hero_audio_path',  '', 'string'),
  ('hero_audio_label', '', 'string')
ON DUPLICATE KEY UPDATE
  `value`      = VALUES(`value`),
  `value_type` = VALUES(`value_type`);
