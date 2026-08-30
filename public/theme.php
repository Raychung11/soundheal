<?php
require_once __DIR__ . '/../includes/bootstrap.php';

/**
 * Theme toggle endpoint. POST-only + CSRF so the navbar button can
 * flip between dark and light without a JS dependency and without
 * being exploitable via a GET-crafted link.
 *
 *   POST /public/theme.php   theme=dark|light  next=<path>
 */

if (!is_post()) {
    redirect('/');
}
csrf_verify();

$theme = (string) input('theme', 'dark');
if (!in_array($theme, ['dark','light'], true)) $theme = 'dark';

setcookie('sh-theme', $theme, [
    'expires'  => time() + 60 * 60 * 24 * 365, // one year
    'path'     => '/',
    'secure'   => (bool) config('app.security.cookie_secure', true),
    'httponly' => false, // read by no server code beyond the resolver;
                         // safe to keep client-visible too if we ever
                         // add a client-side flip animation.
    'samesite' => 'Lax',
]);

// Redirect back to wherever we came from. Guard against off-site
// redirects — only accept a path that begins with a single slash.
$next = (string) input('next', '/');
if ($next === '' || $next[0] !== '/' || (isset($next[1]) && ($next[1] === '/' || $next[1] === '\\'))) {
    $next = '/';
}
redirect($next);
