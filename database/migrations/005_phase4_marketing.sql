-- =====================================================
-- Phase 4 migration: marketing analytics
-- - page_views: every public/member page render (admin/staff excluded)
-- =====================================================

CREATE TABLE IF NOT EXISTS page_views (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED DEFAULT NULL,
    session_token CHAR(64) DEFAULT NULL,        -- sha256(session_id) for unique-visitor counts
    path VARCHAR(255) NOT NULL,
    referrer VARCHAR(500) DEFAULT NULL,
    referrer_host VARCHAR(120) DEFAULT NULL,    -- denormalised for fast top-referrer queries
    user_agent VARCHAR(255) DEFAULT NULL,
    ip_hash CHAR(64) DEFAULT NULL,
    utm_source   VARCHAR(80) DEFAULT NULL,
    utm_medium   VARCHAR(80) DEFAULT NULL,
    utm_campaign VARCHAR(80) DEFAULT NULL,
    is_bot TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_views_path (path(64)),
    INDEX idx_views_created (created_at),
    INDEX idx_views_session (session_token),
    INDEX idx_views_referrer_host (referrer_host),
    INDEX idx_views_utm (utm_source, utm_medium, utm_campaign)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
