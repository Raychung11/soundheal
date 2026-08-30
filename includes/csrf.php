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
    $stored = $_SESSION['_csrf_token'] ?? '';
    // Reject when either side is empty — otherwise hash_equals('','') is true
    // and a fresh session (no form rendered yet) bypasses CSRF entirely.
    if ($stored === '' || !is_string($sent) || $sent === '' || !hash_equals($stored, $sent)) {
        http_response_code(419);
        exit('Invalid CSRF token. Please refresh and try again.');
    }
}
