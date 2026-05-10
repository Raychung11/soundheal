<?php
/**
 * Validate a ticket QR token. With ?token=... it returns ticket info (GET).
 * With JSON body { token, action: 'checkin' } and a logged-in staff user, marks attendance.
 */
require_once __DIR__ . '/../includes/bootstrap.php';

header('Content-Type: application/json');

$token = '';
$action = 'lookup';

if (is_post()) {
    $raw = file_get_contents('php://input');
    $body = json_decode((string) $raw, true) ?? [];
    $token  = trim((string)($body['token'] ?? ''));
    $action = $body['action'] ?? 'lookup';

    // CSRF only for staff actions; lookup from public QR scans is read-only.
    if ($action === 'checkin') {
        if (!is_logged_in() || !has_role('admin','staff')) {
            http_response_code(401);
            echo json_encode(['ok' => false, 'message' => 'Staff sign-in required.']);
            exit;
        }
        $sent = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        if (!hash_equals($_SESSION['_csrf_token'] ?? '', (string) $sent)) {
            http_response_code(419);
            echo json_encode(['ok' => false, 'message' => 'Stale session. Please refresh.']);
            exit;
        }
    }
} else {
    $token = trim((string) input('token', ''));
}

if ($token === '') {
    echo json_encode(['ok' => false, 'message' => 'Missing token.']);
    exit;
}

$stmt = db()->prepare(
    "SELECT t.*, b.booking_ref, e.id AS event_id, e.title AS event_title, e.starts_at,
            u.full_name, u.email
     FROM tickets t
     JOIN event_bookings b ON b.id = t.booking_id
     JOIN events e ON e.id = b.event_id
     JOIN users u ON u.id = b.user_id
     WHERE t.qr_token = :tok LIMIT 1"
);
$stmt->execute([':tok' => $token]);
$ticket = $stmt->fetch();

if (!$ticket) {
    echo json_encode(['ok' => false, 'message' => 'Ticket not found.']);
    exit;
}

if ($action === 'lookup') {
    echo json_encode([
        'ok' => $ticket['status'] === 'valid',
        'message' => $ticket['status'] === 'valid' ? 'Valid ticket.' : 'Ticket no longer valid.',
        'ticket'  => [
            'ticket_code' => $ticket['ticket_code'],
            'status'      => $ticket['status'],
            'full_name'   => $ticket['full_name'],
            'event_title' => $ticket['event_title'],
            'starts_at'   => $ticket['starts_at'],
        ],
    ]);
    exit;
}

if ($action === 'checkin') {
    if ($ticket['status'] !== 'valid') {
        echo json_encode(['ok' => false, 'message' => 'Already used or invalid.', 'ticket' => $ticket]);
        exit;
    }

    db()->beginTransaction();
    try {
        $existing = db()->prepare("SELECT id FROM checkins WHERE ticket_id = :t LIMIT 1");
        $existing->execute([':t' => $ticket['id']]);
        if ($existing->fetch()) {
            db()->rollBack();
            echo json_encode(['ok' => false, 'message' => 'This ticket has already been checked in.', 'ticket' => $ticket]);
            exit;
        }
        db()->prepare("INSERT INTO checkins (ticket_id, event_id, checked_in_by, method) VALUES (:t, :e, :u, 'qr')")
            ->execute([':t' => $ticket['id'], ':e' => $ticket['event_id'], ':u' => current_user_id()]);
        db()->prepare("UPDATE tickets SET status = 'used' WHERE id = :id")->execute([':id' => $ticket['id']]);
        db()->prepare("UPDATE event_bookings SET status = 'attended' WHERE id = :id")->execute([':id' => $ticket['booking_id']]);
        db()->commit();
        audit_log('checkin.qr', 'tickets', (int) $ticket['id']);
        echo json_encode([
            'ok' => true,
            'message' => 'Welcome, ' . $ticket['full_name'] . '.',
            'ticket' => $ticket,
        ]);
    } catch (Throwable $e) {
        db()->rollBack();
        echo json_encode(['ok' => false, 'message' => 'Could not record check-in.']);
    }
    exit;
}

echo json_encode(['ok' => false, 'message' => 'Unknown action.']);
