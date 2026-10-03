-- =====================================================
-- Phase 7.x: Corporate wellness packages
--
--   Catalog of ready-made corporate offerings that display on
--   /public/corporate.php. A visitor picks one and submits a
--   package-tagged inquiry, which lands in the existing
--   corporate_inquiries pipeline with the package_id attached so
--   the operator knows exactly what they asked for.
--
--   Fields:
--     name           — public display name
--     slug           — URL-safe id
--     tagline        — one-liner shown under the name
--     description    — rich text (supports the render_rich_text
--                       helper: blank-line paragraphs, emoji or
--                       -/* bullets)
--     seat_count     — headcount included (nullable = "flexible")
--     session_count  — sessions included (nullable = one-off)
--     price          — RM value; NULL means "on request"
--     image          — hero cover
--     status         — active / inactive
--     sort_order     — display order (asc)
-- =====================================================

CREATE TABLE IF NOT EXISTS corporate_packages (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    slug          VARCHAR(120) NOT NULL UNIQUE,
    name          VARCHAR(200) NOT NULL,
    tagline       VARCHAR(255) DEFAULT NULL,
    description   TEXT DEFAULT NULL,
    seat_count    INT UNSIGNED DEFAULT NULL,
    session_count INT UNSIGNED DEFAULT NULL,
    price         DECIMAL(10,2) DEFAULT NULL,
    image         VARCHAR(255) DEFAULT NULL,
    status        ENUM('active','inactive') NOT NULL DEFAULT 'active',
    sort_order    INT NOT NULL DEFAULT 10,
    created_at    DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at    DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_corp_status (status, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Link inquiries to the package requested (optional).
SET @c = (SELECT COUNT(*) FROM information_schema.COLUMNS
          WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'corporate_inquiries' AND COLUMN_NAME = 'package_id');
SET @s = IF(@c = 0,
  "ALTER TABLE corporate_inquiries ADD COLUMN package_id INT UNSIGNED DEFAULT NULL",
  "DO 0");
PREPARE stmt FROM @s; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Seed two starter packages so the page has content on first
-- deploy. Admins can edit or add via Admin → Corporate packages.
INSERT IGNORE INTO corporate_packages
  (slug, name, tagline, description, seat_count, session_count, price, status, sort_order)
VALUES
  ('team-reset-half-day',
   'Team Reset · Half-day',
   'A 3-hour on-site sound bath + breathwork for one team.',
   'A private, on-site session for one team. We bring the gongs, bowls and mats to your office (or a chosen venue). Includes a facilitated arrival ritual, 60 minutes of sound immersion, a guided breathwork close, and welcome tea.

Ideal for: leadership offsites · team quarter starts · post-launch decompression.',
   20, 1, 3500.00, 'active', 10),

  ('wellness-wednesday-monthly',
   'Wellness Wednesday · Monthly',
   'A recurring monthly session held for your team.',
   'A monthly on-site or in-studio wellness ritual for your team. One 90-minute session each month, same facilitator each time, so your people build a small monthly rhythm together.

Ideal for: HR/People teams building a year-long wellbeing programme.',
   25, 12, NULL, 'active', 20);
