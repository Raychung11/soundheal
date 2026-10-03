-- =====================================================
-- Phase 7.x: Booking reminder tracking
--
--   Adds two DATETIME columns to event_bookings that stamp when
--   a reminder email was successfully sent. The cron endpoint
--   /api/send_reminders.php reads / writes these to keep every
--   reminder exactly-once, even if the cron double-fires.
--
--   Also seeds a random reminder_cron_token that the endpoint
--   requires — call the endpoint with ?token=<this-value>.
-- =====================================================

SET @c = (SELECT COUNT(*) FROM information_schema.COLUMNS
          WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'event_bookings' AND COLUMN_NAME = 'reminder_24h_sent_at');
SET @s = IF(@c = 0,
  "ALTER TABLE event_bookings ADD COLUMN reminder_24h_sent_at DATETIME DEFAULT NULL",
  "DO 0");
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @c = (SELECT COUNT(*) FROM information_schema.COLUMNS
          WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'event_bookings' AND COLUMN_NAME = 'reminder_2h_sent_at');
SET @s = IF(@c = 0,
  "ALTER TABLE event_bookings ADD COLUMN reminder_2h_sent_at DATETIME DEFAULT NULL",
  "DO 0");
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Random token generated once. INSERT IGNORE keeps a re-run of
-- this migration from rotating the token unexpectedly.
INSERT INTO site_settings (`key`, `value`, `value_type`)
SELECT 'reminder_cron_token', SHA2(CONCAT(UUID(), RAND()), 256), 'string'
  FROM DUAL
 WHERE NOT EXISTS (SELECT 1 FROM site_settings WHERE `key` = 'reminder_cron_token');
