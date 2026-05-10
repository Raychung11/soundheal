-- =====================================================
-- SoundHeal Wellness Operating System - Database Schema
-- MySQL 8+ / MariaDB 10.4+
-- =====================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- -----------------------------------------------------
-- roles
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS roles (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL UNIQUE,
    description VARCHAR(255) DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO roles (id, name, description) VALUES
    (1, 'admin', 'Full administrative access'),
    (2, 'staff', 'Operational staff (check-in, content)'),
    (3, 'member', 'Standard member account'),
    (4, 'guest', 'Unauthenticated guest');

-- -----------------------------------------------------
-- users
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    role_id INT UNSIGNED NOT NULL DEFAULT 3,
    full_name VARCHAR(150) NOT NULL,
    email VARCHAR(190) NOT NULL UNIQUE,
    phone VARCHAR(30) DEFAULT NULL,
    password_hash VARCHAR(255) NOT NULL,
    avatar VARCHAR(255) DEFAULT NULL,
    status ENUM('active','inactive','suspended') DEFAULT 'active',
    email_verified_at DATETIME DEFAULT NULL,
    last_login_at DATETIME DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_users_role (role_id),
    CONSTRAINT fk_users_role FOREIGN KEY (role_id) REFERENCES roles(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- -----------------------------------------------------
-- membership_plans
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS membership_plans (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(50) NOT NULL UNIQUE,
    name VARCHAR(150) NOT NULL,
    description TEXT,
    price DECIMAL(10,2) NOT NULL DEFAULT 0,
    currency VARCHAR(8) NOT NULL DEFAULT 'MYR',
    billing_cycle ENUM('monthly','quarterly','yearly','lifetime') NOT NULL DEFAULT 'monthly',
    duration_days INT UNSIGNED NOT NULL DEFAULT 30,
    perks_json JSON DEFAULT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    sort_order INT DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- -----------------------------------------------------
-- memberships  (active subscription per user)
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS memberships (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    plan_id INT UNSIGNED NOT NULL,
    started_at DATETIME NOT NULL,
    expires_at DATETIME DEFAULT NULL,
    status ENUM('pending','active','expired','cancelled') NOT NULL DEFAULT 'pending',
    auto_renew TINYINT(1) DEFAULT 0,
    last_payment_id INT UNSIGNED DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_memberships_user (user_id),
    INDEX idx_memberships_status (status),
    CONSTRAINT fk_memberships_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_memberships_plan FOREIGN KEY (plan_id) REFERENCES membership_plans(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- -----------------------------------------------------
-- events
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS events (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    slug VARCHAR(190) NOT NULL UNIQUE,
    title VARCHAR(200) NOT NULL,
    subtitle VARCHAR(255) DEFAULT NULL,
    description TEXT,
    cover_image VARCHAR(255) DEFAULT NULL,
    location VARCHAR(255) DEFAULT NULL,
    starts_at DATETIME NOT NULL,
    ends_at DATETIME NOT NULL,
    capacity INT UNSIGNED NOT NULL DEFAULT 30,
    price_public DECIMAL(10,2) NOT NULL DEFAULT 0,
    price_member DECIMAL(10,2) NOT NULL DEFAULT 0,
    facilitator VARCHAR(150) DEFAULT NULL,
    category VARCHAR(80) DEFAULT NULL,
    status ENUM('draft','published','archived','cancelled') NOT NULL DEFAULT 'draft',
    created_by INT UNSIGNED DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_events_status (status),
    INDEX idx_events_starts_at (starts_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- -----------------------------------------------------
-- event_bookings
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS event_bookings (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    booking_ref VARCHAR(40) NOT NULL UNIQUE,
    user_id INT UNSIGNED NOT NULL,
    event_id INT UNSIGNED NOT NULL,
    quantity INT UNSIGNED NOT NULL DEFAULT 1,
    unit_price DECIMAL(10,2) NOT NULL DEFAULT 0,
    total_amount DECIMAL(10,2) NOT NULL DEFAULT 0,
    status ENUM('pending','paid','cancelled','refunded','attended','no_show') NOT NULL DEFAULT 'pending',
    payment_id INT UNSIGNED DEFAULT NULL,
    notes TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_bookings_user (user_id),
    INDEX idx_bookings_event (event_id),
    INDEX idx_bookings_status (status),
    CONSTRAINT fk_bookings_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_bookings_event FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- -----------------------------------------------------
-- tickets
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS tickets (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    booking_id INT UNSIGNED NOT NULL,
    ticket_code VARCHAR(64) NOT NULL UNIQUE,
    qr_token VARCHAR(128) NOT NULL UNIQUE,
    qr_image_path VARCHAR(255) DEFAULT NULL,
    issued_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    status ENUM('valid','used','revoked','expired') NOT NULL DEFAULT 'valid',
    INDEX idx_tickets_booking (booking_id),
    CONSTRAINT fk_tickets_booking FOREIGN KEY (booking_id) REFERENCES event_bookings(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- -----------------------------------------------------
-- payments
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS payments (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED DEFAULT NULL,
    purpose ENUM('membership','booking','other') NOT NULL DEFAULT 'booking',
    reference_id INT UNSIGNED DEFAULT NULL,
    gateway VARCHAR(50) NOT NULL DEFAULT 'billplz',
    gateway_bill_id VARCHAR(120) DEFAULT NULL,
    amount DECIMAL(10,2) NOT NULL,
    currency VARCHAR(8) NOT NULL DEFAULT 'MYR',
    status ENUM('pending','paid','failed','refunded','cancelled') NOT NULL DEFAULT 'pending',
    paid_at DATETIME DEFAULT NULL,
    raw_payload JSON DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_payments_user (user_id),
    INDEX idx_payments_status (status),
    INDEX idx_payments_purpose (purpose, reference_id),
    INDEX idx_payments_bill (gateway_bill_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- -----------------------------------------------------
-- checkins
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS checkins (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ticket_id INT UNSIGNED NOT NULL,
    event_id INT UNSIGNED NOT NULL,
    checked_in_by INT UNSIGNED DEFAULT NULL,
    checked_in_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    method ENUM('qr','manual') DEFAULT 'qr',
    notes VARCHAR(255) DEFAULT NULL,
    UNIQUE KEY uniq_checkin_ticket (ticket_id),
    INDEX idx_checkins_event (event_id),
    CONSTRAINT fk_checkins_ticket FOREIGN KEY (ticket_id) REFERENCES tickets(id) ON DELETE CASCADE,
    CONSTRAINT fk_checkins_event FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- -----------------------------------------------------
-- ai_conversations
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS ai_conversations (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED DEFAULT NULL,
    session_token VARCHAR(64) NOT NULL UNIQUE,
    channel ENUM('web','whatsapp') DEFAULT 'web',
    mood VARCHAR(64) DEFAULT NULL,
    summary TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_ai_conv_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- -----------------------------------------------------
-- ai_messages
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS ai_messages (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    conversation_id INT UNSIGNED NOT NULL,
    role ENUM('user','assistant','system') NOT NULL,
    content MEDIUMTEXT NOT NULL,
    tokens INT UNSIGNED DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_ai_msg_conv (conversation_id),
    CONSTRAINT fk_ai_msg_conv FOREIGN KEY (conversation_id) REFERENCES ai_conversations(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- -----------------------------------------------------
-- wellness_content
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS wellness_content (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    slug VARCHAR(190) NOT NULL UNIQUE,
    title VARCHAR(200) NOT NULL,
    description TEXT,
    type ENUM('audio','meditation','sleep','breathing','article') NOT NULL DEFAULT 'audio',
    file_path VARCHAR(255) DEFAULT NULL,
    cover_image VARCHAR(255) DEFAULT NULL,
    duration_seconds INT UNSIGNED DEFAULT 0,
    access ENUM('public','member','premium') NOT NULL DEFAULT 'member',
    is_published TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_content_type (type),
    INDEX idx_content_access (access)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- -----------------------------------------------------
-- testimonials
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS testimonials (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    author_name VARCHAR(150) NOT NULL,
    author_title VARCHAR(150) DEFAULT NULL,
    quote TEXT NOT NULL,
    avatar VARCHAR(255) DEFAULT NULL,
    rating TINYINT UNSIGNED DEFAULT 5,
    is_published TINYINT(1) NOT NULL DEFAULT 0,
    sort_order INT DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- -----------------------------------------------------
-- corporate_inquiries
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS corporate_inquiries (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    company_name VARCHAR(200) NOT NULL,
    contact_name VARCHAR(150) NOT NULL,
    contact_email VARCHAR(190) NOT NULL,
    contact_phone VARCHAR(30) DEFAULT NULL,
    team_size VARCHAR(50) DEFAULT NULL,
    interest VARCHAR(255) DEFAULT NULL,
    message TEXT,
    status ENUM('new','contacted','proposal_sent','won','lost') DEFAULT 'new',
    assigned_to INT UNSIGNED DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- -----------------------------------------------------
-- contact_leads
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS contact_leads (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    email VARCHAR(190) NOT NULL,
    phone VARCHAR(30) DEFAULT NULL,
    subject VARCHAR(200) DEFAULT NULL,
    message TEXT,
    source VARCHAR(80) DEFAULT 'website',
    handled TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- -----------------------------------------------------
-- audit_logs
-- -----------------------------------------------------
CREATE TABLE IF NOT EXISTS audit_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED DEFAULT NULL,
    action VARCHAR(100) NOT NULL,
    entity VARCHAR(100) DEFAULT NULL,
    entity_id INT UNSIGNED DEFAULT NULL,
    ip_address VARCHAR(45) DEFAULT NULL,
    user_agent VARCHAR(255) DEFAULT NULL,
    metadata JSON DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_audit_user (user_id),
    INDEX idx_audit_action (action)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

SET FOREIGN_KEY_CHECKS = 1;

-- =====================================================
-- Seed data
-- =====================================================

-- Default admin: change password immediately after first login.
-- Password below = "ChangeMe!2026" hashed with PASSWORD_DEFAULT (bcrypt).
INSERT IGNORE INTO users (id, role_id, full_name, email, password_hash, status, email_verified_at)
VALUES (1, 1, 'SoundHeal Admin', 'admin@soundheal.local',
        '$2y$10$wH5oQ1Q7jHv1xL5x9V6m9eZ9bH8nK4oS0Wq2V4xM6tH3JpZ9Ck1Ku', 'active', NOW());

INSERT IGNORE INTO membership_plans (id, code, name, description, price, billing_cycle, duration_days, sort_order, is_active)
VALUES
    (1, 'serenity_monthly', 'Serenity', 'Begin your wellness journey. Member pricing on all sessions plus access to the calm content library.', 79.00, 'monthly', 30, 1, 1),
    (2, 'harmony_quarterly', 'Harmony', 'Three months of guided sessions, priority booking, and curated audio journeys.', 219.00, 'quarterly', 90, 2, 1),
    (3, 'sanctuary_yearly', 'Sanctuary', 'A full year of unlimited access, complimentary guest passes and concierge wellness support.', 799.00, 'yearly', 365, 3, 1);
