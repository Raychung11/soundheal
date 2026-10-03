-- =====================================================
-- Phase 5 follow-up: admin-managed mail (SMTP) settings
-- Lets admin configure outgoing email from /admin/mail_settings.php
-- without touching .env. Stores SMTP creds in site_settings.
-- =====================================================

INSERT INTO site_settings (`key`, `value`, `value_type`) VALUES
    ('mail_driver',       'smtp',                                  'string'),
    ('mail_host',         'smtp.hostinger.com',                    'string'),
    ('mail_port',         '587',                                   'int'),
    ('mail_username',     '',                                      'string'),
    ('mail_password',     '',                                      'string'),
    ('mail_encryption',   'tls',                                   'string'),  -- tls | ssl | none
    ('mail_from_address', 'hello@jaemiesoundbath.com',             'string'),
    ('mail_from_name',    'jaemie sound bath',                     'string')
ON DUPLICATE KEY UPDATE `key` = `key`;

-- mail_log: every outbound send attempt, success or failure.
CREATE TABLE IF NOT EXISTS mail_log (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    to_email VARCHAR(190) NOT NULL,
    to_name  VARCHAR(190) DEFAULT NULL,
    subject  VARCHAR(255) NOT NULL,
    template VARCHAR(64)  DEFAULT NULL,
    status ENUM('sent','failed') NOT NULL,
    driver VARCHAR(40) DEFAULT NULL,             -- 'phpmailer-smtp' | 'mail()' | other
    error  TEXT DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_mail_log_to (to_email),
    INDEX idx_mail_log_status (status, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
