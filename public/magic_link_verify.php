<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$pageTitle = 'Opening your sanctuary';

$rawToken = (string) input('token', '');
$result   = magic_link_verify_and_login($rawToken);

if ($result['ok']) {
    flash('welcome', 'Welcome. Your sanctuary is open.', 'success');

    // If the visitor started the flow from a specific page (rare —
    // magic links typically come from a plain "sign in" click), we
    // could honour a next=… param. Left simple for now: land on the
    // dashboard, or the sanctuary if they don't have member access.
    redirect(has_role('admin','staff') ? '/admin/dashboard.php' : '/member/dashboard.php');
}

flash('auth', (string) ($result['error'] ?? 'That sanctuary link could not be opened.'), 'error');
redirect('/public/magic_link_request.php');
