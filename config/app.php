<?php
declare(strict_types=1);

/**
 * Application-wide configuration.
 * Override any value through environment variables when deploying.
 */

// Best-effort auto-detection of the public URL when APP_URL is not set.
// This avoids handing Billplz a "soundheal.local" callback / redirect URL
// on hosts where the env var was never configured.
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

return [
    'name'        => getenv('APP_NAME') ?: 'SoundHeal',
    'tagline'     => 'Wellness Operating System',
    'env'         => getenv('APP_ENV') ?: 'production',
    'debug'       => filter_var(getenv('APP_DEBUG') ?: 'false', FILTER_VALIDATE_BOOLEAN),
    'url'         => rtrim(getenv('APP_URL') ?: $detectedUrl, '/'),
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
