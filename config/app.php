<?php
declare(strict_types=1);

/**
 * Application-wide configuration.
 * Override any value through environment variables when deploying.
 */

return [
    'name'        => getenv('APP_NAME') ?: 'SoundHeal',
    'tagline'     => 'Wellness Operating System',
    'env'         => getenv('APP_ENV') ?: 'production',
    'debug'       => filter_var(getenv('APP_DEBUG') ?: 'false', FILTER_VALIDATE_BOOLEAN),
    'url'         => rtrim(getenv('APP_URL') ?: 'https://soundheal.local', '/'),
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
