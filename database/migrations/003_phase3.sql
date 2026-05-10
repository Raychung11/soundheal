-- =====================================================
-- Phase 3 migration
-- - site_settings: admin-controlled homepage content
-- - users.trial_ends_at + seed columns for free trial
-- - Seed: one demo event + one demo audio + default homepage settings
-- =====================================================

CREATE TABLE IF NOT EXISTS site_settings (
    `key`        VARCHAR(80)  NOT NULL PRIMARY KEY,
    `value`      MEDIUMTEXT   DEFAULT NULL,
    `value_type` ENUM('string','text','int','bool','json') NOT NULL DEFAULT 'string',
    `updated_by` INT UNSIGNED DEFAULT NULL,
    `updated_at` DATETIME     DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE users
    ADD COLUMN trial_ends_at DATETIME DEFAULT NULL AFTER status;

-- Seed homepage defaults.
INSERT INTO site_settings (`key`, `value`, `value_type`) VALUES
    ('hero_eyebrow',       'A sanctuary for stillness',                                                          'string'),
    ('hero_headline',      'Return to the sound of yourself.',                                                   'string'),
    ('hero_subheadline',   'Curated sound healing sessions, breathwork journeys and a quiet audio sanctuary — held with intention, designed for the modern soul.', 'text'),
    ('hero_cta_primary_label', 'Reserve a session',                                                              'string'),
    ('hero_cta_primary_url',   '/public/events.php',                                                             'string'),
    ('hero_cta_secondary_label','Become a member',                                                               'string'),
    ('hero_cta_secondary_url',  '/public/membership.php',                                                        'string'),
    ('hero_image_path',    '',                                                                                   'string'),
    ('hero_audio_path',    '',                                                                                   'string'),
    ('hero_audio_label',   'Press play. Begin softly.',                                                          'string'),

    ('trial_enabled',      '1',                                                                                  'bool'),
    ('trial_duration_days','7',                                                                                  'int'),
    ('trial_eyebrow',      'A gift on the threshold',                                                            'string'),
    ('trial_headline',     'Try a 5-minute sound bath, on us.',                                                  'string'),
    ('trial_subheadline',  'Press play, dim the lights, and feel what we mean. No payment, no commitment — just a quiet first step.', 'text'),
    ('trial_audio_path',   '',                                                                                   'string'),
    ('trial_cta_label',    'Start your 7-day free trial',                                                        'string')
ON DUPLICATE KEY UPDATE `key` = `key`;

-- Seed one demo event (next Saturday at 7pm) if no events exist.
INSERT INTO events (slug, title, subtitle, description, location, starts_at, ends_at,
                    capacity, price_public, price_member, facilitator, category, status)
SELECT 'demo-sound-bath', 'Crystal Bowl Sound Bath',
       'A gentle 75-minute immersion in tuned crystal bowls and gongs.',
       'Lie down on a mat. Cover your eyes. Let the frequencies do the work — no experience required, only your breath.',
       'SoundHeal Sanctuary, Bangsar, Kuala Lumpur',
       DATE_FORMAT(DATE_ADD(NOW(), INTERVAL (7 - WEEKDAY(NOW()) + 5) % 7 DAY), '%Y-%m-%d 19:00:00'),
       DATE_FORMAT(DATE_ADD(NOW(), INTERVAL (7 - WEEKDAY(NOW()) + 5) % 7 DAY), '%Y-%m-%d 20:15:00'),
       30, 88.00, 58.00, 'Aria · Lead Facilitator', 'sound', 'published'
WHERE NOT EXISTS (SELECT 1 FROM events WHERE slug = 'demo-sound-bath');

-- Seed one demo audio (public access) if none exist with this slug.
INSERT INTO wellness_content (slug, title, description, type, file_path, duration_seconds, access, is_published)
SELECT 'demo-five-minute-sound-bath',
       'Five-Minute Sound Bath',
       'A short crystal bowl meditation. Press play, breathe slowly, and let your shoulders fall.',
       'audio',
       'https://cdn.pixabay.com/audio/2022/03/15/audio_1eb9d5f6dc.mp3',
       300, 'public', 1
WHERE NOT EXISTS (SELECT 1 FROM wellness_content WHERE slug = 'demo-five-minute-sound-bath');

-- Seed one ambient hero loop (public access) if none exist with this slug.
INSERT INTO wellness_content (slug, title, description, type, file_path, duration_seconds, access, is_published)
SELECT 'hero-ambient-loop',
       'Hero Ambient Loop',
       'Soft ambient drone for the homepage hero.',
       'audio',
       'https://cdn.pixabay.com/audio/2022/10/30/audio_347c9ab4d6.mp3',
       60, 'public', 1
WHERE NOT EXISTS (SELECT 1 FROM wellness_content WHERE slug = 'hero-ambient-loop');

-- Point trial + hero audio settings at the seeded URLs.
UPDATE site_settings SET `value` = (SELECT file_path FROM wellness_content WHERE slug = 'demo-five-minute-sound-bath' LIMIT 1)
  WHERE `key` = 'trial_audio_path' AND (`value` IS NULL OR `value` = '');
UPDATE site_settings SET `value` = (SELECT file_path FROM wellness_content WHERE slug = 'hero-ambient-loop' LIMIT 1)
  WHERE `key` = 'hero_audio_path' AND (`value` IS NULL OR `value` = '');

-- Seed three calm testimonials so the home page feels populated.
INSERT INTO testimonials (author_name, author_title, quote, rating, is_published, sort_order)
SELECT * FROM (
  SELECT 'Aisha R.'   AS author_name, 'Founder, Studio Lila'        AS author_title, 'I came in tense and left feeling like I'd had a long swim. The container is special.'                              AS quote, 5 AS rating, 1 AS is_published, 1 AS sort_order UNION ALL
  SELECT 'Daniel T.', 'Software engineer',                                          'It''s the only hour of the week my mind genuinely quiets. Worth the membership on its own.',                              5, 1, 2 UNION ALL
  SELECT 'Mei L.',    'New mother',                                                  'A soft, held space. I cried twice and slept eleven hours that night. Thank you.',                                          5, 1, 3
) AS seed
WHERE NOT EXISTS (SELECT 1 FROM testimonials WHERE author_name IN ('Aisha R.','Daniel T.','Mei L.'));
