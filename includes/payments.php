<?php
declare(strict_types=1);

/**
 * Payment-settlement logic shared by:
 *   - api/billplz_create.php       (demo / inline settle when no key set)
 *   - api/billplz_webhook.php      (Billplz callback)
 *   - member/payment_thanks.php    (redirect rescue when webhook hasn't fired)
 *   - admin/payments.php           (manual Settle button)
 *
 * Kept here so the callers can `require_once` without re-running an
 * HTTP-endpoint file (which previously leaked "Booking not found." from
 * billplz_create.php into /admin/payments.php).
 */

if (!function_exists('settle_payment')) {

    /**
     * Marks a payment as settled and either:
     *   - For bookings: flips the booking to paid, issues QR tickets (one per
     *     seat), and emails the member their confirmation.
     *   - For memberships: activates the membership.
     *
     * Idempotent — calling it twice on the same payment is safe.
     */
    function settle_payment(int $paymentId): void
    {
        $p = db()->prepare("SELECT * FROM payments WHERE id = :id");
        $p->execute([':id' => $paymentId]);
        $payment = $p->fetch();
        if (!$payment || $payment['status'] !== 'paid') {
            return;
        }

        if ($payment['purpose'] === 'booking') {
            db()->prepare("UPDATE event_bookings SET status='paid', payment_id=:p WHERE id=:id")
                ->execute([':p' => $paymentId, ':id' => $payment['reference_id']]);

            $b = db()->prepare(
                "SELECT b.booking_ref, b.quantity, e.title, e.starts_at, e.location, u.email, u.full_name
                 FROM event_bookings b
                 JOIN events e ON e.id = b.event_id
                 JOIN users u ON u.id = b.user_id
                 WHERE b.id = :id"
            );
            $b->execute([':id' => $payment['reference_id']]);
            $booking = $b->fetch();

            $check = db()->prepare("SELECT COUNT(*) FROM tickets WHERE booking_id = :b");
            $check->execute([':b' => $payment['reference_id']]);

            if ((int) $check->fetchColumn() === 0 && $booking) {
                $t = db()->prepare("INSERT INTO tickets (booking_id, ticket_code, qr_token) VALUES (:b, :c, :tok)");
                for ($i = 0; $i < (int) $booking['quantity']; $i++) {
                    $t->execute([
                        ':b'   => $payment['reference_id'],
                        ':c'   => $booking['booking_ref'] . '-' . ($i + 1),
                        ':tok' => generate_token(24),
                    ]);
                }
                if (function_exists('send_mail')) {
                    send_mail($booking['email'], $booking['full_name'],
                        'Your seat is held',
                        'booking_confirm', [
                            'event_title' => $booking['title'],
                            'starts_at'   => format_datetime($booking['starts_at']),
                            'location'    => $booking['location'] ?? 'Location TBA',
                            'booking_ref' => $booking['booking_ref'],
                        ]);
                }
            }
        } elseif ($payment['purpose'] === 'membership') {
            db()->prepare("UPDATE memberships SET status='active', last_payment_id=:p WHERE id=:id")
                ->execute([':p' => $paymentId, ':id' => $payment['reference_id']]);
        } elseif ($payment['purpose'] === 'class_pack') {
            // Bind the payment to the pack purchase and grant the credits.
            db()->prepare("UPDATE pack_purchases SET payment_id = :p WHERE id = :id")
                ->execute([':p' => $paymentId, ':id' => $payment['reference_id']]);
            if (function_exists('grant_pack_credits')) {
                grant_pack_credits((int) $payment['reference_id']);
            }
        }

        // Emit the receipt (and mark the matching invoice paid). Idempotent.
        if (function_exists('issue_receipt')) {
            issue_receipt($paymentId);
        }
    }
}
