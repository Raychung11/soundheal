-- =====================================================
-- Phase 6.x: "Meet your guide" section on the About page
--
--   A dedicated facilitator block (portrait + name + role + bio)
--   editable from /admin/about_settings.php. The public section
--   only renders when at least a name, bio, or image is set.
-- =====================================================

INSERT INTO site_settings (`key`, `value`, `value_type`) VALUES
    ('about_guide_eyebrow', 'Your guide',                   'string'),
    ('about_guide_name',    '',                             'string'),
    ('about_guide_role',    'Sound practitioner & founder', 'string'),
    ('about_guide_bio',     '',                             'string'),
    ('about_guide_image_path', '',                          'string')
ON DUPLICATE KEY UPDATE `key` = `key`;
