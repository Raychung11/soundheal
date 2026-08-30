-- =====================================================
-- Phase 7.x: Recurring events (daily)
--
--   Adds:
--     events.recurrence       'none' | 'daily'
--     events.recurrence_until DATE — optional cutoff
--     events.parent_event_id  INT   — links auto-created instance
--                                     rows back to their template
--
--   A "template" event (recurrence='daily', parent_event_id NULL)
--   is expanded into virtual cards for the next N days on the
--   public sessions page. When a member books a specific date,
--   member/book_event.php creates a concrete child event for that
--   day (recurrence='none', parent_event_id=template) and the
--   booking attaches to the child. Capacity / seats / refunds all
--   work against the concrete child row exactly like today.
--
--   Idempotent: each ALTER is gated on information_schema.
-- =====================================================

-- recurrence
SET @c = (SELECT COUNT(*) FROM information_schema.COLUMNS
          WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'events' AND COLUMN_NAME = 'recurrence');
SET @s = IF(@c = 0,
  "ALTER TABLE events ADD COLUMN recurrence ENUM('none','daily') NOT NULL DEFAULT 'none' AFTER status",
  "DO 0");
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- recurrence_until
SET @c = (SELECT COUNT(*) FROM information_schema.COLUMNS
          WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'events' AND COLUMN_NAME = 'recurrence_until');
SET @s = IF(@c = 0,
  "ALTER TABLE events ADD COLUMN recurrence_until DATE DEFAULT NULL AFTER recurrence",
  "DO 0");
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- parent_event_id
SET @c = (SELECT COUNT(*) FROM information_schema.COLUMNS
          WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'events' AND COLUMN_NAME = 'parent_event_id');
SET @s = IF(@c = 0,
  "ALTER TABLE events ADD COLUMN parent_event_id INT UNSIGNED DEFAULT NULL AFTER recurrence_until",
  "DO 0");
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Index on parent_event_id (idempotent)
SET @i = (SELECT COUNT(*) FROM information_schema.STATISTICS
          WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'events' AND INDEX_NAME = 'idx_events_parent');
SET @s = IF(@i = 0,
  "ALTER TABLE events ADD INDEX idx_events_parent (parent_event_id)",
  "DO 0");
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Index on recurrence (idempotent)
SET @i = (SELECT COUNT(*) FROM information_schema.STATISTICS
          WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'events' AND INDEX_NAME = 'idx_events_recurrence');
SET @s = IF(@i = 0,
  "ALTER TABLE events ADD INDEX idx_events_recurrence (recurrence)",
  "DO 0");
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;
