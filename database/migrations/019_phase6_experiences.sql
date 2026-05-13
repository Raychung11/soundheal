-- =====================================================
-- Phase 6.x: Admin-managed experiences (session types)
--
--   Replaces the hard-coded list on /public/experiences.php with a
--   table that the admin can edit at /admin/experiences.php.
--
--   The six legacy entries are seeded so the page keeps its shape;
--   only Sound Bath is active out of the gate.
-- =====================================================

CREATE TABLE IF NOT EXISTS experiences (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    slug VARCHAR(64) NOT NULL UNIQUE,
    title VARCHAR(190) NOT NULL,
    duration VARCHAR(40) DEFAULT NULL,           -- "75 min", "90 min", etc.
    description TEXT,
    status ENUM('active','inactive') NOT NULL DEFAULT 'active',
    sort_order INT NOT NULL DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_experiences_status (status, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO experiences (slug, title, duration, description, status, sort_order) VALUES
    ('sound-bath',         'Sound Bath',         '75 min', 'A 75-minute immersion in crystal bowls and gongs. Lie down. Let the frequencies do the work.', 'active',   10),
    ('breathwork-journey', 'Breathwork Journey', '60 min', 'Guided conscious breathing to release stored tension and arrive in the body.',                  'inactive', 20),
    ('moon-circle',        'Moon Circle',        '90 min', 'Monthly women''s circle held in candlelight — sound, journaling, and gentle ceremony.',         'inactive', 30),
    ('couples-tuning',     'Couples Tuning',     '60 min', 'A private session for two — synchronised sound and breath to soften connection.',               'inactive', 40),
    ('corporate-reset',    'Corporate Reset',    '45 min', 'On-site sound healing for teams. 45 minutes to lower the room and lift the focus.',             'inactive', 50),
    ('one-on-one',         '1:1 Concierge',      '90 min', 'A bespoke private session crafted around your current emotional landscape.',                    'inactive', 60)
ON DUPLICATE KEY UPDATE slug = slug;
