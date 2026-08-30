-- =====================================================
-- Phase 7.x: Corporate lead → auto-spawn a private event
--
--   When a corporate_inquiries row transitions to status='won',
--   admin/corporate_leads.php now materialises a linked private
--   event (audience='private', status='draft', credits disabled)
--   pre-populated from the corporate package the lead requested.
--
--   This column records which event was spawned so we don't
--   double-create on subsequent saves of the same 'won' status,
--   and lets the leads view surface a "→ Draft event" link.
-- =====================================================

SET @c = (SELECT COUNT(*) FROM information_schema.COLUMNS
          WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'corporate_inquiries' AND COLUMN_NAME = 'spawned_event_id');
SET @s = IF(@c = 0,
  "ALTER TABLE corporate_inquiries ADD COLUMN spawned_event_id INT UNSIGNED DEFAULT NULL",
  "DO 0");
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @i = (SELECT COUNT(*) FROM information_schema.STATISTICS
          WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'corporate_inquiries' AND INDEX_NAME = 'idx_corp_spawn');
SET @s = IF(@i = 0,
  "ALTER TABLE corporate_inquiries ADD INDEX idx_corp_spawn (spawned_event_id)",
  "DO 0");
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;
