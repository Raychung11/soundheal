<?php
declare(strict_types=1);

/**
 * Blog / Journal helpers.
 *
 * Posts are rich-text paragraphs interleaved with social embeds.
 * The admin writes the body with plain human markers:
 *
 *   Some words here.
 *
 *   [instagram: https://www.instagram.com/p/XXX/]
 *
 *   More words.
 *
 * blog_render_body() splits the text on those markers, escapes and
 * paragraph-wraps the surrounding text via render_rich_text(), and
 * inserts the appropriate `<iframe>` / `<blockquote>` block for each
 * embed. Instagram uses the standard blockquote form + embed.js.
 *
 * Supported markers (case-insensitive keyword, url inside brackets):
 *   [instagram: <url>]
 *   [youtube:   <url or 11-char id>]
 *   [vimeo:     <url or numeric id>]
 *
 * blog_page_needs_instagram_script() returns true when a rendered
 * body contained at least one Instagram embed — the public post
 * template uses this to include https://www.instagram.com/embed.js
 * once, only when needed.
 */

if (!function_exists('blog_list_published')) {

    // Set to true by blog_render_body() when it emits an Instagram
    // embed, so the page template can conditionally include embed.js.
    $GLOBALS['blog_needs_instagram_script'] = false;

    function blog_page_needs_instagram_script(): bool
    {
        return (bool) ($GLOBALS['blog_needs_instagram_script'] ?? false);
    }

    /**
     * Fetch published posts newest-first. Optional tag filter (matches
     * whole word inside the comma-separated tags string).
     */
    function blog_list_published(?string $tag = null): array
    {
        if ($tag !== null && $tag !== '') {
            $stmt = db()->prepare(
                "SELECT bp.*, u.full_name AS author_name
                   FROM blog_posts bp
                   LEFT JOIN users u ON u.id = bp.author_id
                  WHERE bp.status = 'published'
                    AND bp.published_at IS NOT NULL
                    AND bp.published_at <= NOW()
                    AND CONCAT(',', LOWER(REPLACE(bp.tags, ' ', '')), ',')
                        LIKE CONCAT('%,', LOWER(:tag), ',%')
                  ORDER BY bp.published_at DESC"
            );
            $stmt->execute([':tag' => trim($tag)]);
        } else {
            $stmt = db()->query(
                "SELECT bp.*, u.full_name AS author_name
                   FROM blog_posts bp
                   LEFT JOIN users u ON u.id = bp.author_id
                  WHERE bp.status = 'published'
                    AND bp.published_at IS NOT NULL
                    AND bp.published_at <= NOW()
                  ORDER BY bp.published_at DESC"
            );
        }
        return $stmt->fetchAll();
    }

    function blog_get_by_slug(string $slug): ?array
    {
        $stmt = db()->prepare(
            "SELECT bp.*, u.full_name AS author_name
               FROM blog_posts bp
               LEFT JOIN users u ON u.id = bp.author_id
              WHERE bp.slug = :s AND bp.status = 'published'
                AND bp.published_at IS NOT NULL
                AND bp.published_at <= NOW()
              LIMIT 1"
        );
        $stmt->execute([':s' => $slug]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * Distinct tag list across published posts, for the chip row on
     * the public listing.
     */
    function blog_active_tags(): array
    {
        $rows = db()->query(
            "SELECT tags FROM blog_posts
              WHERE status = 'published'
                AND published_at IS NOT NULL
                AND published_at <= NOW()
                AND tags IS NOT NULL AND tags <> ''"
        )->fetchAll();

        $bag = [];
        foreach ($rows as $r) {
            foreach (explode(',', (string) $r['tags']) as $t) {
                $t = trim($t);
                if ($t === '') continue;
                $key = strtolower($t);
                if (!isset($bag[$key])) $bag[$key] = ['label' => $t, 'count' => 0];
                $bag[$key]['count']++;
            }
        }
        ksort($bag);
        return array_values($bag);
    }

    /**
     * Render a post body: escape and paragraph-wrap the prose, replace
     * [instagram: url] / [youtube: url] / [vimeo: url] markers with
     * responsive embed blocks. Returns HTML.
     */
    function blog_render_body(string $body): string
    {
        $body = trim($body);
        if ($body === '') return '';

        // Split on every embed marker, keeping the delimiters so we can
        // replay them positionally.
        $pattern = '/\[(instagram|youtube|vimeo)\s*:\s*([^\]]+)\]/i';
        $parts   = preg_split($pattern, $body, -1, PREG_SPLIT_DELIM_CAPTURE);
        if (!is_array($parts) || $parts === []) {
            return render_rich_text($body);
        }

        $html = '';
        $i = 0;
        while ($i < count($parts)) {
            $chunk = (string) $parts[$i];
            // Rendered as prose.
            if ($chunk !== '') {
                $html .= '<div class="blog-prose">' . render_rich_text($chunk) . '</div>';
            }
            // If there's a captured (type, url) pair after this text
            // chunk, emit the embed.
            if (isset($parts[$i + 1], $parts[$i + 2])) {
                $type = strtolower(trim((string) $parts[$i + 1]));
                $url  = trim((string) $parts[$i + 2]);
                $html .= _blog_render_embed($type, $url);
                $i += 3;
            } else {
                $i += 1;
            }
        }
        return $html;
    }

    /**
     * One embed block. Returns '' for anything we can't recognise so
     * a typo in the body doesn't blow up the page.
     */
    function _blog_render_embed(string $type, string $url): string
    {
        $url = trim($url);
        if ($url === '') return '';

        switch ($type) {
            case 'instagram':
                // Only accept instagram.com URLs to keep the embed
                // predictable. Normalise to the /p/<code>/ form and
                // strip query strings (?igsi=…, ?utm_*).
                if (!preg_match('~^https?://(www\.)?instagram\.com/(p|reel|tv)/([A-Za-z0-9_-]+)~i', $url, $m)) {
                    return '';
                }
                $permalink = 'https://www.instagram.com/' . strtolower($m[2]) . '/' . $m[3] . '/';
                $GLOBALS['blog_needs_instagram_script'] = true;
                return <<<HTML
<div class="my-8 flex justify-center">
  <blockquote class="instagram-media" data-instgrm-permalink="{$permalink}?utm_source=ig_embed&amp;utm_campaign=loading" data-instgrm-version="14" style="background:#FFF;border:0;border-radius:12px;box-shadow:0 0 1px 0 rgba(0,0,0,0.5),0 1px 10px 0 rgba(0,0,0,0.15);max-width:540px;min-width:326px;padding:0;width:100%;margin:0;">
    <a href="{$permalink}" target="_blank" rel="noopener" style="background:#FFFFFF;line-height:0;padding:16px 32px;text-align:center;text-decoration:none;width:100%;display:block;color:#0a1027;">View this post on Instagram →</a>
  </blockquote>
</div>
HTML;

            case 'youtube':
                $id = function_exists('youtube_id') ? youtube_id($url) : '';
                if ($id === '') return '';
                $src = 'https://www.youtube.com/embed/' . rawurlencode($id) . '?rel=0';
                return '<div class="my-8"><div class="relative aspect-video rounded-2xl overflow-hidden border border-white/10">'
                     . '<iframe src="' . e($src) . '" title="YouTube video" loading="lazy" allowfullscreen '
                     . 'allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" '
                     . 'class="absolute inset-0 w-full h-full"></iframe></div></div>';

            case 'vimeo':
                $id = function_exists('vimeo_id') ? vimeo_id($url) : '';
                if ($id === '') return '';
                $src = 'https://player.vimeo.com/video/' . rawurlencode($id);
                return '<div class="my-8"><div class="relative aspect-video rounded-2xl overflow-hidden border border-white/10">'
                     . '<iframe src="' . e($src) . '" title="Vimeo video" loading="lazy" allowfullscreen '
                     . 'allow="autoplay; fullscreen; picture-in-picture" '
                     . 'class="absolute inset-0 w-full h-full"></iframe></div></div>';

            default:
                return '';
        }
    }
}
