-- =====================================================
-- Phase 7.x: New session pricing — RM69 Comfort / RM49 BYO
--
--   Updates every active event row to the new package pricing:
--     price_public = 69.00  (Comfort: welcome drink, mat, blanket,
--                            full sound healing)
--     price_member = 49.00  (Bring-Your-Own-Zen: full sound healing,
--                            bring your own mat & blanket)
--
--   Archived / cancelled events are left untouched so historical
--   prices remain accurate.
--
--   NOTE: in the current booking flow these two fields are
--   applied by membership status (members pay price_member,
--   non-members pay price_public). The packages above are amenity
--   choices, not membership tiers — if a proper "package picker"
--   at booking time is wanted, that's a UI follow-up.
-- =====================================================

UPDATE events
   SET price_public = 69.00,
       price_member = 49.00
 WHERE status IN ('draft','published');
