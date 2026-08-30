-- =====================================================
-- Phase 11: Blog / Journal.
--
-- Rich-text posts with inline social embeds. The body is stored as
-- plain text with human-readable embed markers:
--
--   [instagram: https://www.instagram.com/p/XXX/]
--   [youtube: https://www.youtube.com/watch?v=XXX]
--   [vimeo: https://vimeo.com/12345678]
--
-- blog_render_body() (in includes/blog.php) splits on the markers,
-- renders each text chunk through render_rich_text(), and interleaves
-- responsive embeds where each marker sat. Storing the marker as plain
-- text (rather than a positional JSON array) keeps the "edit in a
-- textarea" experience one step: paste the URL where you want it to
-- appear, save.
--
-- Cover image is optional and drives the OG share card + list-card
-- thumbnail. Tags is a comma-separated string — kept flat because a
-- proper many-to-many pivot is overkill for a house journal.
-- =====================================================

CREATE TABLE IF NOT EXISTS blog_posts (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    slug          VARCHAR(120) NOT NULL,
    title         VARCHAR(200) NOT NULL,
    subtitle      VARCHAR(255) DEFAULT NULL,
    excerpt       TEXT DEFAULT NULL,           -- shown on list cards + used as OG description
    body          MEDIUMTEXT DEFAULT NULL,     -- rich text with [instagram: ...] / [youtube: ...] markers
    cover_image   VARCHAR(255) DEFAULT NULL,
    tags          VARCHAR(255) DEFAULT NULL,   -- comma-separated, e.g. "reflection, gong bath"
    status        ENUM('draft','published','archived') NOT NULL DEFAULT 'draft',
    published_at  DATETIME DEFAULT NULL,
    author_id     INT UNSIGNED DEFAULT NULL,
    created_at    DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at    DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_blog_posts_slug (slug),
    INDEX idx_blog_posts_status_pub (status, published_at DESC),
    CONSTRAINT fk_blog_posts_author FOREIGN KEY (author_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
