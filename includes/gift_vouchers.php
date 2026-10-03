<?php
declare(strict_types=1);

/**
 * Gift voucher validation + redemption.
 *
 *   generate_gift_voucher_code() — SH-XXXX-XXXX random, uppercase.
 *
 *   validate_gift_voucher($code, $subtotal) — returns
 *     ['ok'=>true, 'voucher'=>row, 'discount'=>float] on success
 *     or ['ok'=>false, 'error'=>string] with a friendly reason.
 *
 *   redeem_gift_voucher($voucherId, $bookingId, $discount) — flips
 *     the voucher to 'redeemed' with a WHERE guard so a race
 *     between validate + use is caught cleanly. Also stamps
 *     event_bookings.gift_voucher_id + discount_amount.
 */

if (!function_exists('validate_gift_voucher')) {

    function generate_gift_voucher_code(): string
    {
        do {
            $body = strtoupper(bin2hex(random_bytes(4))); // 8 hex chars
            $code = 'SH-' . substr($body, 0, 4) . '-' . substr($body, 4, 4);
            $chk = db()->prepare("SELECT 1 FROM gift_vouchers WHERE code = :c LIMIT 1");
            $chk->execute([':c' => $code]);
        } while ($chk->fetch());
        return $code;
    }

    function validate_gift_voucher(string $rawCode, float $subtotal): array
    {
        $code = strtoupper(trim($rawCode));
        if ($code === '') {
            return ['ok' => false, 'error' => 'Enter a code first.'];
        }
        if ($subtotal <= 0) {
            return ['ok' => false, 'error' => 'This booking is already free.'];
        }

        $stmt = db()->prepare("SELECT * FROM gift_vouchers WHERE code = :c LIMIT 1");
        $stmt->execute([':c' => $code]);
        $row = $stmt->fetch();
        if (!$row) {
            return ['ok' => false, 'error' => "That code isn't recognised."];
        }
        if ($row['status'] === 'redeemed') {
            return ['ok' => false, 'error' => 'This gift has already been used.'];
        }
        if ($row['status'] === 'revoked') {
            return ['ok' => false, 'error' => 'This gift has been revoked.'];
        }
        if (!empty($row['expires_at']) && $row['expires_at'] < date('Y-m-d H:i:s')) {
            return ['ok' => false, 'error' => 'This gift has expired.'];
        }

        $discount = min((float) $row['amount'], $subtotal);
        return ['ok' => true, 'voucher' => $row, 'discount' => round($discount, 2)];
    }

    /**
     * Marks the voucher redeemed and stamps the booking. Atomic
     * WHERE status='issued' guard so a concurrent second booking
     * can't double-spend the same gift.
     */
    function redeem_gift_voucher(int $voucherId, int $bookingId, float $discount): bool
    {
        if ($voucherId <= 0 || $bookingId <= 0) return false;

        $stmt = db()->prepare(
            "UPDATE gift_vouchers
                SET status = 'redeemed',
                    redeemed_by_booking_id = :b,
                    redeemed_at = NOW()
              WHERE id = :id AND status = 'issued'"
        );
        $stmt->execute([':b' => $bookingId, ':id' => $voucherId]);
        if ($stmt->rowCount() < 1) {
            return false;
        }

        db()->prepare(
            "UPDATE event_bookings
                SET gift_voucher_id = :v, discount_amount = discount_amount + :d
              WHERE id = :b"
        )->execute([':v' => $voucherId, ':d' => $discount, ':b' => $bookingId]);
        return true;
    }
}
