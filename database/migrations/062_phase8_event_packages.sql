-- =====================================================
-- Phase 8: Move from hardcoded A/B packages to N packages per event.
--
-- The events table has been carrying six fixed columns per package
-- (label, perks, humans, pets, price via price_public/price_member,
-- enabled flag). That works for the two-tier Comfort/BYO shape but
-- can't express a workshop with, say, "Adult · RM 88", "Adult + Pet
-- · RM 129", "Two Adults · RM 149".
--
-- New shape:
--   event_packages — one row per bookable tier. Each has its own
--   label, perks, price, and intake composition (humans + pets).
--   Zero or more per event, ordered by sort_order.
--
--   event_bookings.package_id — points at the specific package a
--   booking was made against. Nullable so historical bookings that
--   used the legacy comfort/byo enum stay valid; new bookings all
--   carry the id.
--
-- Backfill runs once per event: creates a Package A row from the
-- price_public / package_a_* columns and a Package B row from the
-- price_member / package_b_* columns (skipped if the event has
-- package_b_enabled = 0). Everything old keeps working; the admin
-- form now just edits these package rows instead of the fixed
-- column set.
-- =====================================================

CREATE TABLE IF NOT EXISTS event_packages (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    event_id   INT UNSIGNED NOT NULL,
    label      VARCHAR(120) NOT NULL,
    price      DECIMAL(10,2) NOT NULL DEFAULT 0,
    perks      TEXT DEFAULT NULL,
    humans     TINYINT UNSIGNED NOT NULL DEFAULT 1,
    pets       TINYINT UNSIGNED NOT NULL DEFAULT 0,
    sort_order INT NOT NULL DEFAULT 10,
    status     ENUM('active','disabled') NOT NULL DEFAULT 'active',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_event_pkg_event (event_id, status, sort_order),
    CONSTRAINT fk_event_pkg_event FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Backfill Package A (Comfort tier) — only if the event has no
-- packages yet, so re-running the migration is safe.
INSERT INTO event_packages (event_id, label, price, perks, humans, pets, sort_order, status)
SELECT e.id,
       COALESCE(NULLIF(e.package_a_label, ''), 'Comfort'),
       e.price_public,
       NULLIF(e.package_a_perks, ''),
       COALESCE(e.package_a_humans, 1),
       COALESCE(e.package_a_pets, 2),
       10,
       'active'
FROM events e
WHERE NOT EXISTS (SELECT 1 FROM event_packages WHERE event_id = e.id);

-- Backfill Package B (BYO tier) — only when the event had B enabled
-- AND doesn't already have a second package. Sort order 20 keeps it
-- second in the list.
INSERT INTO event_packages (event_id, label, price, perks, humans, pets, sort_order, status)
SELECT e.id,
       COALESCE(NULLIF(e.package_b_label, ''), 'Bring-Your-Own-Zen'),
       e.price_member,
       NULLIF(e.package_b_perks, ''),
       COALESCE(e.package_b_humans, 1),
       COALESCE(e.package_b_pets, 1),
       20,
       IF(COALESCE(e.package_b_enabled, 1) = 1, 'active', 'disabled')
FROM events e
WHERE (SELECT COUNT(*) FROM event_packages WHERE event_id = e.id) = 1;

-- event_bookings.package_id — points at the specific package row.
SET @c = (SELECT COUNT(*) FROM information_schema.COLUMNS
          WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'event_bookings' AND COLUMN_NAME = 'package_id');
SET @s = IF(@c = 0,
  "ALTER TABLE event_bookings ADD COLUMN package_id INT UNSIGNED DEFAULT NULL AFTER package",
  "DO 0");
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @i = (SELECT COUNT(*) FROM information_schema.STATISTICS
          WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'event_bookings' AND INDEX_NAME = 'idx_bookings_package');
SET @s = IF(@i = 0,
  "ALTER TABLE event_bookings ADD INDEX idx_bookings_package (package_id)",
  "DO 0");
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;
