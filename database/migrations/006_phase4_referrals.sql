-- =====================================================
-- Phase 4 follow-up: Member-refer-member program
-- - users.referral_code (unique short code) + referred_by_user_id
-- - referrals table tracking each referral and the reward
-- - site_settings entries for the reward defaults (admin-tweakable)
-- =====================================================

-- Add users.referral_code if missing.
SET @c = (SELECT COUNT(*) FROM information_schema.COLUMNS
          WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'referral_code');
SET @sql = IF(@c = 0,
  'ALTER TABLE users ADD COLUMN referral_code VARCHAR(16) DEFAULT NULL UNIQUE AFTER status',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Add users.referred_by_user_id if missing.
SET @c = (SELECT COUNT(*) FROM information_schema.COLUMNS
          WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'referred_by_user_id');
SET @sql = IF(@c = 0,
  'ALTER TABLE users ADD COLUMN referred_by_user_id INT UNSIGNED DEFAULT NULL AFTER referral_code,
   ADD INDEX idx_users_referred_by (referred_by_user_id)',
  'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Referrals ledger.
CREATE TABLE IF NOT EXISTS referrals (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    referrer_user_id INT UNSIGNED NOT NULL,
    referee_user_id  INT UNSIGNED NOT NULL,
    status ENUM('signed_up','converted','rewarded','cancelled') NOT NULL DEFAULT 'signed_up',
    signed_up_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    converted_at DATETIME DEFAULT NULL,
    rewarded_at  DATETIME DEFAULT NULL,
    reward_type   VARCHAR(40)  DEFAULT NULL,    -- e.g. 'trial_extend_7d', 'session_credit'
    reward_amount DECIMAL(10,2) DEFAULT NULL,
    notes VARCHAR(255) DEFAULT NULL,
    UNIQUE KEY uniq_ref_pair (referrer_user_id, referee_user_id),
    INDEX idx_referrals_referrer (referrer_user_id),
    INDEX idx_referrals_referee  (referee_user_id),
    INDEX idx_referrals_status   (status),
    CONSTRAINT fk_referrals_referrer FOREIGN KEY (referrer_user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_referrals_referee  FOREIGN KEY (referee_user_id)  REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Reward defaults — admin-tweakable via /admin/home_settings.php (or DB).
INSERT INTO site_settings (`key`, `value`, `value_type`) VALUES
    ('referral_signup_trial_days',   '7',                                                 'int'),
    ('referral_program_eyebrow',     'Refer a friend',                                    'string'),
    ('referral_program_headline',    'Share the sanctuary, share the reward.',            'string'),
    ('referral_program_subheadline', 'For every friend who joins through your link, you each receive an extra week of audio-library access — quietly added, no payment required.', 'text')
ON DUPLICATE KEY UPDATE `key` = `key`;
