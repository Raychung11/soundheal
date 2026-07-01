<?php
require_once __DIR__ . '/../includes/bootstrap.php';

/**
 * Partner QR landing.
 *
 *   /public/p.php?s=<slug>
 *
 * Drops the sh_partner cookie so any booking / signup within the
 * cookie window gets attributed to this partner, bumps the scan
 * counter for the admin dashboard, then 302s to the partner's
 * configured landing_path (defaults to the events calendar).
 *
 * Kept intentionally tiny — the URL prints under the QR sticker so
 * the shorter the redirect chain the better on flaky cafe Wi-Fi.
 */

$slug = strtolower(trim((string) input('s', '')));
$partner = find_partner_by_slug($slug);

if (!$partner) {
    // Silently redirect to the calendar rather than 404 — the QR is
    // physical and might outlive the partner record.
    redirect('/public/events.php');
}

set_partner_cookie((string) $partner['slug']);
record_partner_scan((int) $partner['id']);

$dest = trim((string) ($partner['landing_path'] ?? ''));
if ($dest === '' || $dest[0] !== '/') $dest = '/public/events.php';

// Attach ?partner=<slug> to the destination so pages can surface a
// small "welcome from <cafe>" banner if they want, without needing to
// read the cookie.
$sep = str_contains($dest, '?') ? '&' : '?';
redirect($dest . $sep . 'partner=' . rawurlencode((string) $partner['slug']));
