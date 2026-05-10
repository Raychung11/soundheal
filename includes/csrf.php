<?php
declare(strict_types=1);

/**
 * CSRF token helpers.
 * Use csrf_field() inside any state-changing form.
 * Use csrf_verify() at the top of any POST handler.
 */

function csrf_token(): string
{
    if (empty($_SESSION['_csrf_token'])) {
        $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['_csrf_token'];
}

function csrf_field(): string
{
    $name = config('app.security.csrf_token_name', '_csrf');
    return '<input type="hidden" name="' . e($name) . '" value="' . e(csrf_token()) . '">';
}

function csrf_verify(): void
{
    $name = config('app.security.csrf_token_name', '_csrf');
    $sent = $_POST[$name] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!is_string($sent) || !hash_equals($_SESSION['_csrf_token'] ?? '', $sent)) {
        http_response_code(419);
        exit('Invalid CSRF token. Please refresh and try again.');
    }
}
