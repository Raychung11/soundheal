-- =====================================================
-- Phase 7.x: Waitlist for sold-out sessions
--
--   When a session is fully held, visitors can leave their name,
--   email and (optional) mobile so we can invite them if a seat
--   opens later. When admin cancels/refunds a booking, the
--   oldest 'waiting' entry gets an invite email automatically.
--
--   Storage:
--     event_id          — top-level event (template for recurring)
--     occurrence_date   — YYYY-MM-DD for recurring instances,
--                          NULL for one-off events
--     user_id           — nullable, links to logged-in member
--     UNIQUE (event_id, occurrence_date, email) keeps duplicates
--                          out and lets the same email queue on
--                          different dates of a recurring session.
-- =====================================================

CREATE TABLE IF NOT EXISTS event_waitlist (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    event_id        INT UNSIGNED NOT NULL,
    occurrence_date DATE DEFAULT NULL,
    user_id         INT UNSIGNED DEFAULT NULL,
    email           VARCHAR(190) NOT NULL,
    full_name       VARCHAR(190) DEFAULT NULL,
    mobile          VARCHAR(60)  DEFAULT NULL,
    status          ENUM('waiting','notified','converted','removed') NOT NULL DEFAULT 'waiting',
    notified_at     DATETIME DEFAULT NULL,
    created_at      DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_waitlist_event_email (event_id, occurrence_date, email),
    INDEX idx_waitlist_event_status (event_id, status),
    INDEX idx_waitlist_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
