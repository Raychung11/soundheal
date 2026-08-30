-- =====================================================
-- Phase 7.x: Event audience (public/private) + credit eligibility
--
--   Adds two per-event flags:
--
--   events.audience ENUM('public','private') — 'private' hides
--     the event from the public /events.php calendar and from
--     the "Next session" line on experience cards. The event
--     stays reachable via its direct URL (/public/event.php?id=)
--     so admins can share invite links with specific customers.
--
--   events.credit_eligible TINYINT(1) — when 0, the "Use 1
--     credit instead of paying" option is hidden on the booking
--     form for that event, so class-pack credits can't cover it.
--     Defaults to 1 (all existing sessions remain eligible).
--
--   Both are idempotent; existing rows keep their current
--   behaviour (public + credit-eligible) unless the admin edits
--   the event.
-- =====================================================

SET @c = (SELECT COUNT(*) FROM information_schema.COLUMNS
          WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'events' AND COLUMN_NAME = 'audience');
SET @s = IF(@c = 0,
  "ALTER TABLE events ADD COLUMN audience ENUM('public','private') NOT NULL DEFAULT 'public' AFTER status",
  "DO 0");
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @c = (SELECT COUNT(*) FROM information_schema.COLUMNS
          WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'events' AND COLUMN_NAME = 'credit_eligible');
SET @s = IF(@c = 0,
  "ALTER TABLE events ADD COLUMN credit_eligible TINYINT(1) NOT NULL DEFAULT 1 AFTER audience",
  "DO 0");
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @i = (SELECT COUNT(*) FROM information_schema.STATISTICS
          WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'events' AND INDEX_NAME = 'idx_events_audience');
SET @s = IF(@i = 0,
  "ALTER TABLE events ADD INDEX idx_events_audience (audience, status)",
  "DO 0");
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;
