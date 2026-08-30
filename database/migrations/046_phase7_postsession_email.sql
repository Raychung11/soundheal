-- =====================================================
-- Phase 7.x: Post-session thank-you tracking
--
--   Adds event_bookings.postsession_sent_at — stamped only on
--   successful send by /api/send_reminders.php's new sweep. The
--   sweep runs after the existing T-24h and T-2h passes; it
--   picks up any paid/attended booking whose event started
--   between 2 and 6 hours ago and hasn't been thanked yet.
-- =====================================================

SET @c = (SELECT COUNT(*) FROM information_schema.COLUMNS
          WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'event_bookings' AND COLUMN_NAME = 'postsession_sent_at');
SET @s = IF(@c = 0,
  "ALTER TABLE event_bookings ADD COLUMN postsession_sent_at DATETIME DEFAULT NULL",
  "DO 0");
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;
