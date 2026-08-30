-- =====================================================
-- Phase 10: Google OAuth login.
--
-- users.google_sub — Google's stable "subject" identifier per user
-- (an opaque string, guaranteed unique across Google accounts, safe
-- to store long-term). We key logins on this because emails on
-- Google accounts CAN change (via workspace admin move). The sub
-- never changes.
--
-- google_avatar_url — snapshotted from Google's profile at each
-- successful login so admins can render a friendly member avatar in
-- the sanctuary. Doesn't hit Google on every page load.
--
-- Unique index on google_sub means a given Google identity can bind
-- to exactly one account here — no duplicate accounts silently spun
-- up if a user clicks "continue with Google" twice.
-- =====================================================

SET @c = (SELECT COUNT(*) FROM information_schema.COLUMNS
          WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'google_sub');
SET @s = IF(@c = 0,
  "ALTER TABLE users ADD COLUMN google_sub VARCHAR(64) DEFAULT NULL AFTER phone",
  "DO 0");
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @c = (SELECT COUNT(*) FROM information_schema.COLUMNS
          WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'google_avatar_url');
SET @s = IF(@c = 0,
  "ALTER TABLE users ADD COLUMN google_avatar_url VARCHAR(500) DEFAULT NULL AFTER google_sub",
  "DO 0");
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @i = (SELECT COUNT(*) FROM information_schema.STATISTICS
          WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND INDEX_NAME = 'uq_users_google_sub');
SET @s = IF(@i = 0,
  "ALTER TABLE users ADD UNIQUE KEY uq_users_google_sub (google_sub)",
  "DO 0");
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Placeholder rows so admin/oauth_settings.php can render the empty
-- form on first load and set_setting() can UPSERT them. Actual values
-- populate via the admin form.
INSERT IGNORE INTO site_settings (`key`, `value`, `value_type`) VALUES
    ('oauth_google_enabled',       '0', 'bool'),
    ('oauth_google_client_id',     '',  'text'),
    ('oauth_google_client_secret', '',  'text');
