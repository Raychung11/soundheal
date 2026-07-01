<?php
/**
 * Booking reminder cron endpoint.
 *
 *   Hit this URL every 30–60 minutes from Hostinger cron with the
 *   token from site_settings.reminder_cron_token (set by
 *   migration 044). Example (30-min cadence):
 *
 *     */30 * * * * /usr/bin/curl -fsS "https://jaemiesoundbath.com/api/send_reminders.php?token=<TOKEN>" >/dev/null
 *
 *   The endpoint scans for paid / attended bookings whose event
 *   starts_at falls into a ~2h window around 24h-from-now and
 *   2h-from-now, sends the matching reminder email once, and
 *   stamps event_bookings.reminder_*_sent_at so a re-run never
 *   sends the same reminder twice.
 *
 *   Also runnable from CLI without a token — Hostinger's cron
 *   supports both PHP-CLI and curl-URL. When called from CLI
 *   (php ... send_reminders.php) the token check is skipped and
 *   auth relies on filesystem access.
 */
require_once __DIR__ . '/../includes/bootstrap.php';

$isCli = (php_sapi_name() === 'cli');

if (!$isCli) {
    $expected = (string) setting('reminder_cron_token', '');
    if ($expected === '') {
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => 'Reminder token not configured. Apply migration 044 or set reminder_cron_token in site_settings.']);
        exit;
    }
    $sent = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    if (stripos($sent, 'Bearer ') === 0) {
        $sent = substr($sent, 7);
    } else {
        $sent = (string) input('token', '');
    }
    if (!hash_equals($expected, (string) $sent)) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'error' => 'Invalid token']);
        exit;
    }
    header('Content-Type: application/json');
}

/**
 * Sweeps one reminder window. Returns [attempted, sent, failed].
 */
$runSweep = function (string $col, string $template, string $subject, string $windowStart, string $windowEnd) {
    $stmt = db()->prepare(
        "SELECT b.id, b.booking_ref, e.title AS event_title, e.starts_at, e.location,
                u.email, u.full_name
           FROM event_bookings b
           JOIN events e ON e.id = b.event_id
           JOIN users  u ON u.id = b.user_id
          WHERE b.status IN ('paid','attended')
            AND b.$col IS NULL
            AND e.starts_at BETWEEN :ws AND :we
            AND u.email IS NOT NULL AND u.email <> ''"
    );
    $stmt->execute([':ws' => $windowStart, ':we' => $windowEnd]);
    $rows = $stmt->fetchAll();

    $attempted = 0; $sent = 0; $failed = 0;
    foreach ($rows as $r) {
        $attempted++;
        $firstName = trim(explode(' ', (string) $r['full_name'])[0]) ?: 'friend';
        $ok = send_mail(
            (string) $r['email'],
            (string) $r['full_name'],
            $subject . ' · ' . $r['event_title'],
            $template,
            [
                'name'        => $firstName,
                'event_title' => $r['event_title'],
                'starts_at'   => format_datetime($r['starts_at']),
                'location'    => $r['location'] ?: 'Location TBA',
                'booking_ref' => $r['booking_ref'],
            ]
        );
        if ($ok) {
            $sent++;
            // Stamp only on success so a transient SMTP failure retries next tick.
            db()->prepare("UPDATE event_bookings SET $col = NOW() WHERE id = :id")
                ->execute([':id' => (int) $r['id']]);
        } else {
            $failed++;
        }
    }
    return [$attempted, $sent, $failed];
};

// Window: T-26h .. T-22h  →  24-hour-ahead reminder
[$a24, $s24, $f24] = $runSweep(
    'reminder_24h_sent_at',
    'booking_reminder_24h',
    'A gentle reminder — tomorrow',
    date('Y-m-d H:i:s', strtotime('+22 hours')),
    date('Y-m-d H:i:s', strtotime('+26 hours'))
);

// Window: T-2.5h .. T-1.5h  →  2-hour-ahead reminder
[$a2, $s2, $f2] = $runSweep(
    'reminder_2h_sent_at',
    'booking_reminder_2h',
    'See you soon — starting in 2 hours',
    date('Y-m-d H:i:s', strtotime('+90 minutes')),
    date('Y-m-d H:i:s', strtotime('+150 minutes'))
);

audit_log('reminders.sweep', 'event_bookings', null, [
    'sent_24h' => $s24, 'failed_24h' => $f24,
    'sent_2h'  => $s2,  'failed_2h'  => $f2,
]);

$result = [
    'ok'      => true,
    'ran_at'  => date('c'),
    '24h'     => ['attempted' => $a24, 'sent' => $s24, 'failed' => $f24],
    '2h'      => ['attempted' => $a2,  'sent' => $s2,  'failed' => $f2],
];

if ($isCli) {
    echo json_encode($result, JSON_PRETTY_PRINT) . PHP_EOL;
} else {
    echo json_encode($result);
}
