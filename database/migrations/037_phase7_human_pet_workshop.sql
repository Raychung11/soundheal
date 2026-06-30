-- =====================================================
-- Phase 7.x: Seed the Gong Bath × Pet Wellness workshop
--
--   A one-off 12 July 2026 workshop with two custom package tiers
--   (RM169 for 1 human + 1 pet, RM229 for 2 humans + 2 pets · 3
--   pax). Uses the per-event package_* override columns from
--   migration 036 so the booking page shows the correct labels +
--   perks instead of the default Comfort / BYO copy.
--
--   Idempotent via ON DUPLICATE KEY UPDATE on the unique slug.
--   Cover image left blank — upload it from Admin → Events later.
-- =====================================================

INSERT INTO events
    (slug, title, subtitle, description, cover_image, location,
     starts_at, ends_at, capacity, price_public, price_member,
     facilitator, category, status, recurrence,
     package_a_label, package_a_perks,
     package_b_label, package_b_perks)
VALUES (
    'gong-bath-pet-wellness-2026-07-12',
    '人宠共频工作坊 · Gong Bath × Pet Wellness',
    'A gentle co-frequency journey with you and your fur companion',
    'A unique co-frequency workshop weaving Jaemie''s gong sound healing with Crystal''s pet wellness practice. Lie down with your pet and feel the healing vibration of the gong — soft frequencies that help both of you release, reconnect and rest deeply.

Hosted by Jaemie (gong sound healer) and Crystal Ralph Lim (pet wellness practitioner).

两位导师联手打造的人宠共频体验 · 与你的毛孩一起感受爱，链接频率的力量。',
    '',
    'Kuala Lumpur (location confirmed on registration)',
    '2026-07-12 16:00:00',
    '2026-07-12 19:00:00',
    12,
    229.00,
    169.00,
    'Jaemie & Crystal Ralph Lim',
    'workshop',
    'published',
    'none',
    '2 humans + 2 pets · 3 pax',
    'For 2 humans + 2 pets (3 pax total)
Relax body and mind together (放松身心)
Deepen the bond with your pets (增进链接)
Balance your energies (平衡能量)
Heal one another (疗愈彼此)',
    '1 human + 1 pet',
    'For 1 human + 1 pet
Relax body and mind together (放松身心)
Deepen the bond with your pet (增进链接)
Balance your energies (平衡能量)
Heal one another (疗愈彼此)'
)
ON DUPLICATE KEY UPDATE
    title           = VALUES(title),
    subtitle        = VALUES(subtitle),
    description     = VALUES(description),
    location        = VALUES(location),
    starts_at       = VALUES(starts_at),
    ends_at         = VALUES(ends_at),
    capacity        = VALUES(capacity),
    price_public    = VALUES(price_public),
    price_member    = VALUES(price_member),
    facilitator     = VALUES(facilitator),
    category        = VALUES(category),
    status          = VALUES(status),
    recurrence      = VALUES(recurrence),
    package_a_label = VALUES(package_a_label),
    package_a_perks = VALUES(package_a_perks),
    package_b_label = VALUES(package_b_label),
    package_b_perks = VALUES(package_b_perks);
