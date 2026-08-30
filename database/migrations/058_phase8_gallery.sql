-- =====================================================
-- Phase 8: Public activities photo gallery.
--
--   gallery_photos — one row per uploaded image, with an optional
--   caption, category tag (e.g. "Gong Bath", "Human & Pet"), and
--   an optional link back to the event the photo came from. Ordered
--   by sort_order for a hand-arranged grid.
-- =====================================================

CREATE TABLE IF NOT EXISTS gallery_photos (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    image        VARCHAR(255) NOT NULL,
    caption      VARCHAR(255) DEFAULT NULL,
    category     VARCHAR(80)  DEFAULT NULL,
    event_id     INT UNSIGNED DEFAULT NULL,
    sort_order   INT NOT NULL DEFAULT 100,
    status       ENUM('visible','hidden') NOT NULL DEFAULT 'visible',
    created_by   INT UNSIGNED DEFAULT NULL,
    created_at   DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_gallery_status_sort (status, sort_order, id),
    INDEX idx_gallery_category    (category)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
