<?php
/**
 * Dynamic XML sitemap of public, indexable pages.
 * Referenced from /robots.txt. Member/admin/api paths are excluded.
 */
require_once __DIR__ . '/includes/bootstrap.php';

header('Content-Type: application/xml; charset=utf-8');

$base = rtrim((string) config('app.url'), '/');

// path => [changefreq, priority]
$pages = [
    '/public/index.php'       => ['daily',   '1.0'],
    '/public/events.php'      => ['daily',   '0.9'],
    '/public/experiences.php' => ['weekly',  '0.8'],
    '/public/membership.php'  => ['weekly',  '0.8'],
    '/public/about.php'       => ['monthly', '0.6'],
    '/public/contact.php'     => ['monthly', '0.5'],
    '/public/share_experience.php' => ['monthly', '0.4'],
    '/public/terms.php'       => ['yearly',  '0.3'],
    '/public/privacy.php'     => ['yearly',  '0.3'],
    '/public/refund.php'      => ['yearly',  '0.3'],
];

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
foreach ($pages as $path => [$freq, $priority]) {
    echo '  <url>'
       . '<loc>' . htmlspecialchars($base . $path, ENT_QUOTES) . '</loc>'
       . '<changefreq>' . $freq . '</changefreq>'
       . '<priority>' . $priority . '</priority>'
       . '</url>' . "\n";
}
echo '</urlset>';
