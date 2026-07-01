<?php
declare(strict_types=1);

/**
 * Promo code validation + application.
 *
 *   validate_promo_code($code, $subtotal) — returns
 *     ['ok' => true,  'code' => row, 'discount' => float]
 *   or
 *     ['ok' => false, 'error' => string]
 *
 *   record_promo_use($bookingId, $code, $discount) — stamps the
 *   booking with the code + discount and increments used_count.
 *   Safe to call inside the booking transaction.
 */

if (!function_exists('validate_promo_code')) {

    function validate_promo_code(string $rawCode, float $subtotal): array
    {
        $code = strtoupper(trim($rawCode));
        if ($code === '') {
            return ['ok' => false, 'error' => 'Enter a code first.'];
        }
        if ($subtotal <= 0) {
            return ['ok' => false, 'error' => 'This booking is already free.'];
        }

        $stmt = db()->prepare(
            "SELECT * FROM promo_codes WHERE code = :c LIMIT 1"
        );
        $stmt->execute([':c' => $code]);
        $row = $stmt->fetch();
        if (!$row) {
            return ['ok' => false, 'error' => "That code isn't recognised."];
        }
        if ($row['status'] !== 'active') {
            return ['ok' => false, 'error' => 'That code is no longer active.'];
        }
        $now = date('Y-m-d H:i:s');
        if (!empty($row['valid_from']) && $row['valid_from'] > $now) {
            return ['ok' => false, 'error' => 'That code is not valid yet.'];
        }
        if (!empty($row['valid_until']) && $row['valid_until'] < $now) {
            return ['ok' => false, 'error' => 'That code has expired.'];
        }
        if ($row['max_uses'] !== null && (int) $row['used_count'] >= (int) $row['max_uses']) {
            return ['ok' => false, 'error' => 'That code has reached its usage limit.'];
        }

        $type  = (string) $row['discount_type'];
        $value = (float) $row['discount_value'];
        $discount = $type === 'percent'
            ? round($subtotal * min(100.0, max(0.0, $value)) / 100, 2)
            : round(min($subtotal, max(0.0, $value)), 2);

        if ($discount <= 0) {
            return ['ok' => false, 'error' => 'This code doesn\'t offer a discount on this booking.'];
        }

        return ['ok' => true, 'code' => $row, 'discount' => $discount];
    }

    /**
     * Called after a booking is successfully created with a valid
     * code applied. Uses an atomic UPDATE with a WHERE guard so a
     * code that hit its cap between validation and use rejects the
     * increment safely (caller can then error out cleanly).
     */
    function record_promo_use(int $bookingId, string $code, float $discount): bool
    {
        $code = strtoupper(trim($code));
        if ($code === '' || $bookingId <= 0) return false;

        $stmt = db()->prepare(
            "UPDATE promo_codes
                SET used_count = used_count + 1
              WHERE code = :c
                AND status = 'active'
                AND (max_uses IS NULL OR used_count < max_uses)"
        );
        $stmt->execute([':c' => $code]);
        if ($stmt->rowCount() < 1) {
            return false;
        }

        db()->prepare(
            "UPDATE event_bookings
                SET promo_code = :c, discount_amount = :d
              WHERE id = :id"
        )->execute([':c' => $code, ':d' => $discount, ':id' => $bookingId]);
        return true;
    }
}
