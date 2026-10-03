<?php
/**
 * Google Sign-In · step 2 — receive ?code=… & ?state=… from Google.
 *
 * Verifies the CSRF state (session-bound), swaps the code for tokens,
 * fetches the userinfo profile, and hands the profile to
 * oauth_google_login_from_profile() which finds-or-creates the user
 * and logs them in. On any error a friendly flash lands the visitor
 * back on /public/login.php.
 */
require_once __DIR__ . '/../includes/bootstrap.php';

// Google will bounce back with ?error=access_denied if the user
// closed the consent screen. Handle that quietly — no big red bar.
if (($err = trim((string) input('error', ''))) !== '') {
    if ($err !== 'access_denied') {
        error_log('[oauth_google_callback] provider error: ' . $err);
    }
    flash('auth', 'Google sign-in was cancelled.', 'info');
    redirect('/public/login.php');
}

$code  = trim((string) input('code', ''));
$state = trim((string) input('state', ''));
if ($code === '' || $state === '') {
    flash('auth', 'Google sign-in did not return the expected data. Please try again.', 'error');
    redirect('/public/login.php');
}

try {
    $result  = oauth_google_handle_callback($code, $state);
    $profile = $result['profile'];
    $userId  = oauth_google_login_from_profile($profile);

    $stored  = $result['next'];
    $next    = is_string($stored) && $stored !== '' ? $stored : '/member/dashboard.php';
    flash('welcome', 'Welcome. Your sanctuary is open.', 'success');
    redirect($next);
} catch (RuntimeException $e) {
    error_log('[oauth_google_callback] ' . $e->getMessage());
    flash('auth', $e->getMessage(), 'error');
    redirect('/public/login.php');
}
