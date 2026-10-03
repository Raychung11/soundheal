<?php
declare(strict_types=1);

/**
 * Lightweight page-view + lead-source tracking.
 * Called once per HTML page render via includes/header.php.
 */

function track_view(): void
{
    // Skip CLI / non-GET / API requests.
    if (php_sapi_name() === 'cli') return;
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') return;
    $path = (string) ($_SERVER['SCRIPT_NAME'] ?? '');
    if ($path === '' || str_contains($path, '/api/')) return;

    // Don't pollute analytics with admin/staff browsing.
    if (function_exists('has_role') && has_role('admin', 'staff')) return;

    $ua = (string) ($_SERVER['HTTP_USER_AGENT'] ?? '');
    if ($ua === '') return;
    $isBot = (int) (bool) preg_match('/bot|crawl|spider|slurp|bingpreview|facebookexternalhit|whatsapp|telegram/i', $ua);

    $referrer = (string) ($_SERVER['HTTP_REFERER'] ?? '');
    $referrerHost = '';
    if ($referrer !== '') {
        $host = parse_url($referrer, PHP_URL_HOST);
        $referrerHost = is_string($host) ? strtolower($host) : '';
        // Self-referrals don't count as a source.
        $self = parse_url((string) config('app.url'), PHP_URL_HOST);
        if ($self && $referrerHost === strtolower((string) $self)) {
            $referrerHost = '';
            $referrer = '';
        }
    }

    $sessionToken = '';
    if (session_status() === PHP_SESSION_ACTIVE) {
        $sid = session_id();
        if ($sid) {
            $sessionToken = hash('sha256', $sid);
        }
    }

    $ipHash = isset($_SERVER['REMOTE_ADDR'])
        ? hash('sha256', (string) $_SERVER['REMOTE_ADDR'])
        : null;

    $utmSource   = trim((string) ($_GET['utm_source']   ?? ''));
    $utmMedium   = trim((string) ($_GET['utm_medium']   ?? ''));
    $utmCampaign = trim((string) ($_GET['utm_campaign'] ?? ''));

    try {
        $stmt = db()->prepare(
            "INSERT INTO page_views
                (user_id, session_token, path, referrer, referrer_host,
                 user_agent, ip_hash, utm_source, utm_medium, utm_campaign, is_bot)
             VALUES
                (:u, :s, :p, :r, :rh, :ua, :ih, :us, :um, :uc, :bot)"
        );
        $stmt->execute([
            ':u'   => current_user_id(),
            ':s'   => $sessionToken !== '' ? $sessionToken : null,
            ':p'   => substr($path, 0, 255),
            ':r'   => $referrer !== '' ? substr($referrer, 0, 500) : null,
            ':rh'  => $referrerHost !== '' ? substr($referrerHost, 0, 120) : null,
            ':ua'  => substr($ua, 0, 255),
            ':ih'  => $ipHash,
            ':us'  => $utmSource   !== '' ? substr($utmSource, 0, 80)   : null,
            ':um'  => $utmMedium   !== '' ? substr($utmMedium, 0, 80)   : null,
            ':uc'  => $utmCampaign !== '' ? substr($utmCampaign, 0, 80) : null,
            ':bot' => $isBot,
        ]);
    } catch (Throwable $e) {
        // Tracking should never break a page render.
        error_log('[track_view] ' . $e->getMessage());
    }
}
