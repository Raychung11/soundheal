-- =====================================================
-- Phase 6.x: Founder's story + the science of sound
--
--   Persists the founder's narrative, the "science of sound
--   resonance" section and the home-page story teaser into
--   site_settings so the content lives in the database (and is
--   editable from /admin/about_settings.php and /admin/home_settings.php)
--   rather than only existing as code defaults.
--
--   Idempotent: ON DUPLICATE KEY UPDATE overwrites, so re-running
--   this migration always re-applies the copy below.
-- =====================================================

INSERT INTO site_settings (`key`, `value`, `value_type`) VALUES

  -- ---------- About page · Founder's story ----------
  ('about_founder_eyebrow',  'Our founder''s story',                                  'string'),
  ('about_founder_headline', 'My personal experience with sound bath healing',        'string'),
  ('about_founder_quote',
   'Sound doesn''t heal the body for you — it may help create the condition where the body can heal itself better.',
   'text'),
  ('about_founder_body',
'In December 2025, I went through one of the most exhausting periods of my life. I had been struggling with a persistent cough for almost three weeks. Nights were the hardest — excessive phlegm, constant coughing, interrupted sleep, and a body that simply couldn''t fully recover. I tried different ways to manage it, yet healing felt frustratingly slow.

By early January, during a company retreat, I was invited to join a gong bath session. To be honest, I joined with curiosity rather than expectation. Something surprising happened.

That night, for the first time in weeks, I slept deeply. My breathing felt calmer, my body felt less tense, and over the next days, the coughing significantly eased. It wasn''t an overnight "miracle," but it felt as though my body had finally shifted into recovery mode.

That experience changed my perspective completely — and eventually became one of the reasons I founded Jaemie Sound Bath.',
   'text'),

  -- ---------- About page · The science of sound ----------
  ('about_science_eyebrow',  'Not magic — resonance',                                 'string'),
  ('about_science_headline', 'The science of sound resonance',                        'string'),
  ('about_science_body',
'At Jaemie Sound Bath, we do not position sound healing as superstition or a replacement for medical treatment. Instead, we understand the sound bath through the lens of nervous system regulation and deep relaxation.

When the body is under prolonged stress, poor sleep, or inflammation, it can remain in a heightened "fight-or-flight" state. Research increasingly suggests that immersive sound experiences — especially low-frequency instruments such as gongs and singing bowls — may help guide the body into a more relaxed parasympathetic state, often called the "rest and restore" mode.

One of the most fascinating aspects of a gong bath is sound resonance. Everything in our environment — including the human body — naturally vibrates. Our body is made up of water, tissues, muscles, bones and organs that can respond to vibration and frequency.

During a sound bath, instruments such as gongs and singing bowls produce rich layers of sound waves and low-frequency vibrations. These sounds are not only heard through the ears — they are often felt physically throughout the body.

Resonance refers to the way one vibrating system can influence another. A simple example is how music vibrations can make windows gently shake, or how a tuning fork can activate another tuning fork of a similar frequency. In wellness settings, we believe immersive sound vibration may help the body shift from tension into relaxation.',
   'text'),
  ('about_science_points',
'Encouraging slower breathing patterns
Helping muscles release physical tightness
Supporting nervous system regulation
Promoting a meditative, restorative state
Creating an environment for deeper rest and recovery',
   'text'),
  ('about_science_disclaimer',
   'Sound bath is a complementary wellness practice and is not intended to diagnose, treat, cure, or replace medical care. Individual experiences may vary.',
   'text'),

  -- ---------- Home page · Founder's story teaser ----------
  ('home_story_enabled',   '1',          'bool'),
  ('home_story_eyebrow',   'Our story',  'string'),
  ('home_story_quote',
   'Sound doesn''t heal the body for you — it may help create the condition where the body can heal itself better.',
   'text'),
  ('home_story_body',
   'Jaemie Sound Bath was born from a personal experience — weeks of exhaustion and restless nights that eased after a single gong bath. Not magic, but resonance: sound gently guiding the body back into rest and recovery.',
   'text'),
  ('home_story_cta_label', 'Read our story', 'string')

ON DUPLICATE KEY UPDATE
  `value`      = VALUES(`value`),
  `value_type` = VALUES(`value_type`);
