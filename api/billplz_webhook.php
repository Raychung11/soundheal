<?php
/**
 * Billplz webhook receiver.
 *
 * Billplz POSTs:
 *   id, collection_id, paid, paid_at, amount, state, x_signature, ...
 * We validate x_signature, mark the payment as paid, and call settle_payment().
 */
require_once __DIR__ . '/../includes/bootstrap.php';
// settle_payment() is loaded via bootstrap (includes/payments.php).

if (!is_post()) {
    http_response_code(405); exit('Method not allowed.');
}

$cfg     = payment_config();
$payload = $_POST;
file_put_contents(SH_ROOT . '/logs/billplz-webhook.log',
    '[' . date('c') . '] ' . json_encode($payload) . PHP_EOL, FILE_APPEND);

if (!empty($cfg['x_signature'])) {
    $signature = $payload['x_signature'] ?? '';
    $copy = $payload;
    unset($copy['x_signature']);
    ksort($copy);
    $compact = '';
    foreach ($copy as $k => $v) {
        $compact .= $k . $v;
    }
    $expected = hash_hmac('sha256', $compact, $cfg['x_signature']);
    if (!hash_equals($expected, $signature)) {
        http_response_code(403); exit('Invalid signature.');
    }
}

$billId = $payload['id'] ?? null;
$paid   = (string)($payload['paid'] ?? 'false') === 'true' || ($payload['state'] ?? '') === 'paid';

if (!$billId) {
    http_response_code(400); exit('Missing bill id.');
}

$stmt = db()->prepare("SELECT * FROM payments WHERE gateway_bill_id = :b LIMIT 1");
$stmt->execute([':b' => $billId]);
$payment = $stmt->fetch();
if (!$payment) {
    http_response_code(404); exit('Unknown bill.');
}

if ($paid && $payment['status'] !== 'paid') {
    db()->prepare(
        "UPDATE payments SET status = 'paid', paid_at = NOW(), raw_payload = :r WHERE id = :id"
    )->execute([':r' => json_encode($payload), ':id' => $payment['id']]);
    settle_payment((int) $payment['id']);
    audit_log('payment.paid', 'payments', (int) $payment['id'], ['bill_id' => $billId]);
} elseif (!$paid) {
    db()->prepare("UPDATE payments SET status = 'failed', raw_payload = :r WHERE id = :id")
        ->execute([':r' => json_encode($payload), ':id' => $payment['id']]);
    audit_log('payment.failed', 'payments', (int) $payment['id'], ['bill_id' => $billId]);
}

http_response_code(200);
echo 'OK';
