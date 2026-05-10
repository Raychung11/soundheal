-- =====================================================
-- Phase 2 migration
-- Password reset tokens, email verification tokens,
-- listening history, audio plays.
-- =====================================================

CREATE TABLE IF NOT EXISTS user_tokens (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    purpose ENUM('password_reset','email_verify') NOT NULL,
    token_hash CHAR(64) NOT NULL,
    expires_at DATETIME NOT NULL,
    used_at DATETIME DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_tokens_user (user_id),
    INDEX idx_tokens_hash (token_hash),
    CONSTRAINT fk_tokens_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS content_plays (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED DEFAULT NULL,
    content_id INT UNSIGNED NOT NULL,
    played_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    seconds_played INT UNSIGNED DEFAULT 0,
    INDEX idx_plays_user (user_id),
    INDEX idx_plays_content (content_id),
    CONSTRAINT fk_plays_content FOREIGN KEY (content_id) REFERENCES wellness_content(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE event_bookings
    ADD COLUMN cancelled_at DATETIME DEFAULT NULL AFTER updated_at,
    ADD COLUMN refunded_at  DATETIME DEFAULT NULL AFTER cancelled_at;
