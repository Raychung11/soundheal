-- =====================================================
-- Phase 11: Frictionless enrollment.
--
-- 1. login_tokens — one-time links emailed to a visitor so they can
--    sign in or claim a fresh account without ever setting a password.
--    Stored as SHA-256 hashes; the raw token only ever lives in the
--    emailed URL. 30-minute lifetime, one-use.
--
-- 2. users.onboarded_at — timestamp of the first time a member saw
--    their dashboard and dismissed the welcome strip. NULL means
--    "still to be onboarded" so the strip renders exactly once.
--
-- 3. users.phone_prompt_dismissed_at — timestamp of the last time
--    the member said "not now" to the add-your-phone banner. Combined
--    with an IS NULL check on phone, this lets us nudge Google-first
--    members without pestering them forever.
-- =====================================================

CREATE TABLE IF NOT EXISTS login_tokens (
    id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id        INT UNSIGNED DEFAULT NULL,       -- filled when the token binds to an existing user
    email          VARCHAR(255) NOT NULL,           -- so we can create a fresh account on verify if user_id is NULL
    purpose        ENUM('magic_link') NOT NULL DEFAULT 'magic_link',
    token_hash     VARCHAR(64) NOT NULL,            -- sha256 hex of the raw token
    expires_at     DATETIME NOT NULL,
    used_at        DATETIME DEFAULT NULL,
    requested_ip   VARCHAR(45) DEFAULT NULL,
    requested_ua   VARCHAR(255) DEFAULT NULL,
    created_at     DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_login_tokens_hash (token_hash),
    INDEX idx_login_tokens_email (email, purpose, used_at),
    INDEX idx_login_tokens_expires (expires_at),
    CONSTRAINT fk_login_tokens_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Timestamps on users. Guarded so re-runs are safe.
SET @c = (SELECT COUNT(*) FROM information_schema.COLUMNS
          WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'onboarded_at');
SET @s = IF(@c = 0,
  "ALTER TABLE users ADD COLUMN onboarded_at DATETIME DEFAULT NULL AFTER last_login_at",
  "DO 0");
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @c = (SELECT COUNT(*) FROM information_schema.COLUMNS
          WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'phone_prompt_dismissed_at');
SET @s = IF(@c = 0,
  "ALTER TABLE users ADD COLUMN phone_prompt_dismissed_at DATETIME DEFAULT NULL AFTER onboarded_at",
  "DO 0");
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Any member who has already logged in before this migration lands
-- shouldn't see the welcome strip retroactively — treat them as
-- already-onboarded by backdating onboarded_at to their last login.
UPDATE users
   SET onboarded_at = COALESCE(last_login_at, created_at)
 WHERE onboarded_at IS NULL
   AND last_login_at IS NOT NULL;
