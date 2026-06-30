-- =====================================================
-- Phase 7.x: Re-format experience descriptions
--
--   The Pet Co-Resonance and EZON descriptions were entered as a
--   single run-on paragraph, which the public page rendered as
--   one wall of text. With render_rich_text() now in place, this
--   migration re-stores them with proper paragraph breaks and
--   emoji / em-dash bullets so the renderer can paragraph-split
--   them into a clean layout.
--
--   Idempotent — re-applies the same canonical text every time.
-- =====================================================

UPDATE experiences
   SET description =
'Because the deepest connection doesn''t always need words. Our pets may not speak our language, but they can feel our emotions, energy, and presence.

Join us for a heartwarming experience where you and your furry companion can slow down, reconnect, and relax through the soothing vibrations of a Gong Bath.

✨ Experience love · connect through frequency · heal together with your furry companion.

🌿 Workshop Highlights

💛 Relax your body & mind
🐾 Strengthen your bond
✨ Balance your energy
🤍 Heal together'
 WHERE title LIKE '%Human%Pet%Resonance%'
    OR title LIKE '%人宠%';

UPDATE experiences
   SET description =
'A 90-minute experience.

— Welcome and intention setting (10 min)
— Light Energy Activation (30–40 min)
— Gong Sound Journey (30–40 min)
— Tea and sharing circle (10–15 min)'
 WHERE title LIKE '%EZON%';
