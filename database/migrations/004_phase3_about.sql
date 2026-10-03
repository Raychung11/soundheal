-- =====================================================
-- Phase 3 follow-up: About page settings
-- Admin-controlled hero, story, principles and closing
-- with seeded calm Unsplash sample imagery.
-- =====================================================

INSERT INTO site_settings (`key`, `value`, `value_type`) VALUES
    -- Hero
    ('about_hero_eyebrow',  'Our story',                                              'string'),
    ('about_hero_headline', 'A sanctuary built on listening.',                        'string'),
    ('about_hero_image_path',
        'https://images.unsplash.com/photo-1545389336-cf090694435e?auto=format&fit=crop&w=1800&q=80',
        'string'),

    -- Story
    ('about_story_image_path',
        'https://images.unsplash.com/photo-1591291621060-89265cf72bd4?auto=format&fit=crop&w=1200&q=80',
        'string'),
    ('about_story_paragraphs',
        "SoundHeal began as a quiet practice between friends — gathering once a week with bowls and breath, holding space for the noise of the city to settle.\n\nToday we carry that same intention into a wider community: in-person sessions, a curated audio library, and an AI concierge designed to soften the path back to yourself.\n\nWe are not a clinic. We are not a fad. We are a sanctuary — small enough to know your name, intentional enough to honour your stillness.",
        'text'),

    -- Three principles (label + body + image each)
    ('about_principle_1_label', 'Listen',
        'string'),
    ('about_principle_1_body',
        'We begin every session by listening — to your breath, your body, the room.',
        'text'),
    ('about_principle_1_image_path',
        'https://images.unsplash.com/photo-1528319725582-ddc096101511?auto=format&fit=crop&w=900&q=80',
        'string'),

    ('about_principle_2_label', 'Hold',
        'string'),
    ('about_principle_2_body',
        'Held space is the work. The container is more important than the technique.',
        'text'),
    ('about_principle_2_image_path',
        'https://images.unsplash.com/photo-1518604666860-9ed391f76460?auto=format&fit=crop&w=900&q=80',
        'string'),

    ('about_principle_3_label', 'Return',
        'string'),
    ('about_principle_3_body',
        'Wellness is not a destination. We help you return to yourself, gently, often.',
        'text'),
    ('about_principle_3_image_path',
        'https://images.unsplash.com/photo-1499209974431-9dddcece7f88?auto=format&fit=crop&w=900&q=80',
        'string'),

    -- Closing card
    ('about_closing_image_path',
        'https://images.unsplash.com/photo-1511671782779-c97d3d27a1d4?auto=format&fit=crop&w=1800&q=80',
        'string'),
    ('about_closing_eyebrow',  'Quietly, with care',                                  'string'),
    ('about_closing_headline', 'Founded in Kuala Lumpur, 2024',                       'string'),
    ('about_closing_body',
        'By a small circle of practitioners and operators who believe wellness should be calm, premium, and within reach.',
        'text')
ON DUPLICATE KEY UPDATE `key` = `key`;
