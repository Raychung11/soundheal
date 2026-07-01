-- =====================================================
-- Phase 7.x: Monthly recurrence + skip-date exceptions
--
--   Two enhancements to the recurring-event system:
--
--   1. Monthly on the Nth weekday
--      events.recurrence adds 'monthly'. Pattern is stored in
--      recurrence_days as "<ordinal><day>" — ordinal ∈ 1..5 or L
--      (last), day ∈ SUN|MON|TUE|WED|THU|FRI|SAT. Examples:
--        '1SUN' → 1st Sunday of every month
--        'LFRI' → last Friday of every month
--      Only one ordinal+day per template in this MVP; use two
--      templates for combinations like "1st Sun AND 3rd Sat".
--
--   2. Per-event skip dates (exceptions)
--      New event_recurrence_exceptions table. When admin marks
--      a date as an exception (facilitator away, public holiday,
--      etc.), the expansion helper skips that specific date for
--      the template — no bookable card appears, no child event
--      can be created via find_or_create_recurring_instance().
--      Bookings that already exist on that date are unaffected;
--      the exception only prevents *future* materialisation.
-- =====================================================

ALTER TABLE events
    MODIFY COLUMN recurrence ENUM('none','daily','weekly','monthly') NOT NULL DEFAULT 'none';

CREATE TABLE IF NOT EXISTS event_recurrence_exceptions (
    id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    event_id       INT UNSIGNED NOT NULL,
    exception_date DATE NOT NULL,
    reason         VARCHAR(255) DEFAULT NULL,
    created_at     DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_exception (event_id, exception_date),
    INDEX idx_exception_event (event_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
