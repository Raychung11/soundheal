<?php
declare(strict_types=1);

/**
 * Hardened session bootstrap.
 */

if (session_status() === PHP_SESSION_NONE) {
    $sec = config('app.security');
    session_name($sec['session_name']);
    session_set_cookie_params([
        'lifetime' => $sec['session_lifetime'],
        'path'     => '/',
        'domain'   => '',
        'secure'   => $sec['cookie_secure'],
        'httponly' => $sec['cookie_httponly'],
        'samesite' => $sec['cookie_samesite'],
    ]);
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    session_start();

    // Rotate ID periodically to prevent fixation.
    if (!isset($_SESSION['_created_at'])) {
        $_SESSION['_created_at'] = time();
    } elseif (time() - $_SESSION['_created_at'] > 1800) {
        session_regenerate_id(true);
        $_SESSION['_created_at'] = time();
    }
}
