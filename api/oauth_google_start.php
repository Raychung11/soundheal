<?php
/**
 * Google Sign-In · step 1 — build the authorize URL and redirect.
 *
 * Optional ?next=/path so the caller can hand the visitor back to the
 * page they were on (e.g. from a reserve form). The `next` value is
 * stored in the session alongside a random state — the URL param
 * itself is untrusted (an attacker could rewrite it), so the callback
 * reads the session copy, not the URL.
 */
require_once __DIR__ . '/../includes/bootstrap.php';

if (!oauth_google_ready()) {
    flash('auth', 'Google sign-in is not configured yet. Please use email + password.', 'error');
    redirect('/public/login.php');
}

// Only accept relative /path URLs for `next`, so an attacker can't
// swap the redirect for an off-site link on the return trip.
$next = trim((string) input('next', ''));
if ($next !== '' && (str_starts_with($next, '//') || !str_starts_with($next, '/'))) {
    $next = '';
}

try {
    $url = oauth_google_start_url($next !== '' ? $next : null);
    redirect($url);
} catch (RuntimeException $e) {
    error_log('[oauth_google_start] ' . $e->getMessage());
    flash('auth', 'Could not start Google sign-in. Please try again.', 'error');
    redirect('/public/login.php');
}
