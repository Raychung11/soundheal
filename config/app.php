<?php
declare(strict_types=1);

/**
 * Application-wide configuration.
 * Override any value through environment variables when deploying.
 */

// Best-effort auto-detection of the public URL.
// We use this whenever APP_URL is unset or still holds the placeholder
// "soundheal.local". Real production overrides via env continue to win.
$detectedUrl = (static function (): string {
    if (php_sapi_name() === 'cli' || empty($_SERVER['HTTP_HOST'])) {
        return 'https://soundheal.local';
    }
    $proto = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
          || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https')
          || ((int) ($_SERVER['SERVER_PORT'] ?? 0) === 443)
        ? 'https' : 'http';
    return $proto . '://' . $_SERVER['HTTP_HOST'];
})();

$envUrl = trim((string) getenv('APP_URL'));
// Treat the development placeholder (or anything pointing at .local /
// localhost) as "not set" so a stale Hostinger env var can't poison
// the redirect / callback URLs we hand to Billplz.
if ($envUrl === ''
    || stripos($envUrl, 'soundheal.local') !== false
    || stripos($envUrl, 'localhost') !== false
    || stripos($envUrl, '127.0.0.1') !== false) {
    $envUrl = '';
}

return [
    'name'        => getenv('APP_NAME') ?: 'SoundHeal',
    'tagline'     => 'Wellness Operating System',
    'env'         => getenv('APP_ENV') ?: 'production',
    'debug'       => filter_var(getenv('APP_DEBUG') ?: 'false', FILTER_VALIDATE_BOOLEAN),
    'url'         => rtrim($envUrl !== '' ? $envUrl : $detectedUrl, '/'),
    'timezone'    => getenv('APP_TIMEZONE') ?: 'Asia/Kuala_Lumpur',
    'currency'    => 'MYR',
    'locale'      => 'en',

    'mail' => [
        'driver'   => getenv('MAIL_DRIVER') ?: 'smtp',
        'host'     => getenv('MAIL_HOST') ?: 'smtp.hostinger.com',
        'port'     => (int) (getenv('MAIL_PORT') ?: 587),
        'username' => getenv('MAIL_USERNAME') ?: '',
        'password' => getenv('MAIL_PASSWORD') ?: '',
        'encryption' => getenv('MAIL_ENCRYPTION') ?: 'tls',
        'from_address' => getenv('MAIL_FROM_ADDRESS') ?: 'no-reply@soundheal.local',
        'from_name'    => getenv('MAIL_FROM_NAME') ?: 'SoundHeal',
    ],

    'paths' => [
        'root'      => dirname(__DIR__),
        'uploads'   => dirname(__DIR__) . '/uploads',
        'qr'        => dirname(__DIR__) . '/qr',
        'logs'      => dirname(__DIR__) . '/logs',
    ],

    'security' => [
        'session_name'     => 'soundheal_session',
        'session_lifetime' => 60 * 60 * 4,
        'cookie_secure'    => filter_var(getenv('COOKIE_SECURE') ?: 'true', FILTER_VALIDATE_BOOLEAN),
        'cookie_httponly'  => true,
        'cookie_samesite'  => 'Lax',
        'csrf_token_name'  => '_csrf',
    ],
];
