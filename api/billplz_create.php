<?php
/**
 * Create a Billplz bill for a booking or membership and redirect to the gateway.
 * Replace the cURL stub with a real Billplz API call once credentials are in place.
 */
require_once __DIR__ . '/../includes/bootstrap.php';
require_login();

$user = current_user();
$purpose = input('purpose', 'booking');
$ref     = (int) input('ref', input('booking_id', 0));

if (is_post()) {
    csrf_verify();
}

if ($purpose === 'booking') {
    $stmt = db()->prepare("SELECT b.*, e.title FROM event_bookings b JOIN events e ON e.id = b.event_id WHERE b.id = :id AND b.user_id = :u LIMIT 1");
    $stmt->execute([':id' => $ref, ':u' => $user['id']]);
    $entity = $stmt->fetch();
    if (!$entity) { http_response_code(404); exit('Booking not found.'); }
    $amount = (float) $entity['total_amount'];
    $description = 'Booking ' . $entity['booking_ref'] . ' · ' . $entity['title'];
} elseif ($purpose === 'membership') {
    $stmt = db()->prepare("SELECT m.*, p.name, p.price FROM memberships m JOIN membership_plans p ON p.id = m.plan_id WHERE m.id = :id AND m.user_id = :u LIMIT 1");
    $stmt->execute([':id' => $ref, ':u' => $user['id']]);
    $entity = $stmt->fetch();
    if (!$entity) { http_response_code(404); exit('Membership not found.'); }
    $amount = (float) $entity['price'];
    $description = 'Membership · ' . $entity['name'];
} else {
    http_response_code(400); exit('Unknown purpose.');
}

$cfg = payment_config();

$ins = db()->prepare(
    "INSERT INTO payments (user_id, purpose, reference_id, gateway, amount, currency, status)
     VALUES (:u, :p, :ref, :g, :amt, :cur, 'pending')"
);
$ins->execute([
    ':u' => $user['id'], ':p' => $purpose, ':ref' => $ref,
    ':g' => 'billplz', ':amt' => $amount, ':cur' => $cfg['currency'],
]);
$paymentId = (int) db()->lastInsertId();

if (!$cfg['api_key'] || !$cfg['collection_id']) {
    flash('payment', 'Payment gateway not yet configured. (Demo mode — booking marked as paid.)', 'info');
    db()->prepare("UPDATE payments SET status='paid', paid_at=NOW(), gateway_bill_id=:b WHERE id=:id")
        ->execute([':b' => 'DEMO-' . $paymentId, ':id' => $paymentId]);
    settle_payment($paymentId);
    redirect($purpose === 'membership' ? '/member/my_membership.php' : '/member/my_bookings.php');
}

$payload = http_build_query([
    'collection_id' => $cfg['collection_id'],
    'email'         => $user['email'],
    'name'          => $user['full_name'],
    'amount'        => (int) round($amount * 100),
    'callback_url'  => $cfg['callback_url'],
    'redirect_url'  => $cfg['redirect_url'],
    'description'   => substr($description, 0, 200),
    'reference_1_label' => 'PaymentID',
    'reference_1'   => (string) $paymentId,
]);

$ch = curl_init($cfg['api_base'] . '/bills');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => $payload,
    CURLOPT_USERPWD        => $cfg['api_key'] . ':',
    CURLOPT_TIMEOUT        => 15,
]);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode >= 200 && $httpCode < 300 && $response) {
    $bill = json_decode($response, true);
    if (!empty($bill['id']) && !empty($bill['url'])) {
        db()->prepare("UPDATE payments SET gateway_bill_id = :b, raw_payload = :r WHERE id = :id")
            ->execute([':b' => $bill['id'], ':r' => json_encode($bill), ':id' => $paymentId]);
        redirect($bill['url']);
    }
}

flash('payment', 'We could not start your payment. Please try again or contact support.', 'error');
redirect('/member/my_bookings.php');

// settle_payment() now lives in includes/payments.php and is auto-loaded
// via bootstrap, so admin + member-side callers can reuse it without
// triggering this endpoint's HTTP flow.
