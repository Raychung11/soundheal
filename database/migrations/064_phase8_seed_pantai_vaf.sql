-- =====================================================
-- Seed: Pantai Hospital Ampang + VAF as public partners.
--
-- Rolled off the Gong Bath Wellness Morning collaboration flyer.
-- Both go straight onto /public/partners.php with a short blurb and
-- a Healthcare / Wellness-collaborators grouping. Commission is left
-- at the fixed-0 default because this is a public-listing seed, not
-- a QR-referral relationship; the admin can flip that on later from
-- /admin/partners.php if the collaboration grows into a paid one.
--
-- INSERT IGNORE keys off the UNIQUE slug so re-running is a no-op.
-- Fine-tuning copy / logo URLs / sort order can be done through the
-- admin UI without another migration.
-- =====================================================

INSERT IGNORE INTO partners (
    name,
    slug,
    commission_type, commission_rate,
    landing_path,
    status,
    show_on_public_page,
    category,
    description,
    website_url,
    sort_order
) VALUES
(
    'Pantai Hospital Ampang',
    'pantai-hospital-ampang',
    'fixed', 0.00,
    '/public/events.php',
    'active',
    1,
    'Healthcare partners',
    'Part of the IHH Healthcare network. We collaborate on wellness mornings that pair health screenings and doctor sharing with restorative sound sessions.',
    'https://www.pantai.com.my/ampang',
    10
),
(
    'VAF · Value. Abundance. Freedom.',
    'vaf',
    'fixed', 0.00,
    '/public/events.php',
    'active',
    1,
    'Wellness collaborators',
    'A community brand centred on holistic wellbeing. We co-host sharing sessions that thread abundance mindset with restorative sound work.',
    NULL,
    20
);
