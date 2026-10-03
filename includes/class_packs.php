<?php
declare(strict_types=1);

/**
 * Class-pack helpers.
 *
 *   Balance is derived from the member_credits ledger so it is always
 *   self-consistent — even if pack_purchases.credits_remaining drifts.
 *
 *   Pack purchase flow:
 *     1. member/checkout_pack.php inserts a pack_purchases row (status=pending)
 *        and a payments row, then redirects to Billplz.
 *     2. settle_payment() (includes/payments.php) sees purpose='class_pack',
 *        calls grant_pack_credits() which flips the purchase to 'active',
 *        sets the expiry, and writes a +N ledger entry.
 *
 *   Redemption:
 *     - book_event.php deducts one credit via redeem_credit_for_booking().
 *     - The earliest-expiring active purchase is debited first (FIFO by
 *       expires_at NULLS LAST).
 *
 *   No drift, no race: ledger writes happen inside a single transaction
 *   with a SELECT ... FOR UPDATE on the purchase row.
 */

if (!function_exists('credit_balance_for')) {

    function credit_balance_for(int $userId): int
    {
        // Drop ledger rows whose pack has already expired before summing.
        expire_credits_for($userId);
        $stmt = db()->prepare(
            "SELECT COALESCE(SUM(credits_change), 0)
               FROM member_credits
              WHERE user_id = :u"
        );
        $stmt->execute([':u' => $userId]);
        return max(0, (int) $stmt->fetchColumn());
    }

    /**
     * Lazily mark any active pack_purchases whose expiry has passed as
     * 'expired' and zero out their remaining credits via a ledger entry.
     * Cheap; safe to call on every page load that touches credits.
     */
    function expire_credits_for(int $userId): void
    {
        $expired = db()->prepare(
            "SELECT id, credits_remaining FROM pack_purchases
              WHERE user_id = :u
                AND status = 'active'
                AND expires_at IS NOT NULL
                AND expires_at < NOW()
                AND credits_remaining > 0"
        );
        $expired->execute([':u' => $userId]);
        $rows = $expired->fetchAll();
        if (!$rows) return;

        foreach ($rows as $row) {
            db()->beginTransaction();
            try {
                db()->prepare(
                    "INSERT INTO member_credits (user_id, purchase_id, credits_change, reason, note)
                     VALUES (:u, :pid, :c, 'expiry', 'Pack expired')"
                )->execute([
                    ':u'   => $userId,
                    ':pid' => $row['id'],
                    ':c'   => -1 * (int) $row['credits_remaining'],
                ]);
                db()->prepare(
                    "UPDATE pack_purchases
                        SET credits_remaining = 0, status = 'expired'
                      WHERE id = :id"
                )->execute([':id' => $row['id']]);
                db()->commit();
            } catch (Throwable $e) {
                db()->rollBack();
                error_log('[class_packs] expire failed: ' . $e->getMessage());
            }
        }
    }

    function active_packs(): array
    {
        $stmt = db()->query(
            "SELECT * FROM class_packs
              WHERE status = 'active'
              ORDER BY sort_order ASC, price ASC"
        );
        return $stmt->fetchAll();
    }

    /**
     * Build the line-items array for an invoice / receipt.
     */
    function build_pack_line_items(array $pack, int $totalCredits): array
    {
        $name = (string) ($pack['name'] ?? 'Class pack');
        $paid = (int) ($pack['paid_credits'] ?? $totalCredits);
        $bonus = (int) ($pack['bonus_credits'] ?? 0);
        $subtext = $bonus > 0
            ? sprintf('%d paid + %d bonus = %d credits', $paid, $bonus, $totalCredits)
            : sprintf('%d credits', $totalCredits);
        return [[
            'description' => 'Class pack · ' . $name,
            'subtext'     => $subtext,
            'quantity'    => 1,
            'unit_price'  => (float) ($pack['price'] ?? 0),
            'amount'      => (float) ($pack['price'] ?? 0),
        ]];
    }

    /**
     * Idempotent: granting credits twice for the same purchase row is a no-op.
     * Called by settle_payment() when the Billplz webhook flips the payment to paid.
     */
    function grant_pack_credits(int $purchaseId): void
    {
        db()->beginTransaction();
        try {
            $stmt = db()->prepare("SELECT * FROM pack_purchases WHERE id = :id FOR UPDATE");
            $stmt->execute([':id' => $purchaseId]);
            $purchase = $stmt->fetch();
            if (!$purchase || $purchase['status'] !== 'pending') {
                db()->commit();
                return;
            }

            $snap = json_decode((string) ($purchase['pack_snapshot'] ?? '{}'), true) ?: [];
            $validityDays = (int) ($snap['validity_days'] ?? 0);
            $expiresAt = $validityDays > 0
                ? (new DateTimeImmutable('+' . $validityDays . ' days'))->format('Y-m-d H:i:s')
                : null;

            $credits = (int) $purchase['credits_granted'];

            db()->prepare(
                "UPDATE pack_purchases
                    SET status = 'active',
                        credits_remaining = :c,
                        expires_at = :e
                  WHERE id = :id"
            )->execute([
                ':c'  => $credits,
                ':e'  => $expiresAt,
                ':id' => $purchaseId,
            ]);

            db()->prepare(
                "INSERT INTO member_credits (user_id, purchase_id, credits_change, reason, note)
                 VALUES (:u, :pid, :c, 'pack_purchase', :n)"
            )->execute([
                ':u'   => (int) $purchase['user_id'],
                ':pid' => $purchaseId,
                ':c'   => $credits,
                ':n'   => (string) ($snap['name'] ?? 'Pack purchase'),
            ]);

            db()->commit();
        } catch (Throwable $e) {
            db()->rollBack();
            error_log('[class_packs] grant_pack_credits failed: ' . $e->getMessage());
        }
    }

    /**
     * Atomically deduct one credit from the user's earliest-expiring active
     * pack and write a ledger entry tied to the booking. Returns true on
     * success, false if no credit was available.
     */
    function redeem_credit_for_booking(int $userId, int $bookingId): bool
    {
        db()->beginTransaction();
        try {
            $stmt = db()->prepare(
                "SELECT id, credits_remaining
                   FROM pack_purchases
                  WHERE user_id = :u
                    AND status = 'active'
                    AND credits_remaining > 0
                    AND (expires_at IS NULL OR expires_at > NOW())
                  ORDER BY (expires_at IS NULL) ASC, expires_at ASC, id ASC
                  LIMIT 1
                  FOR UPDATE"
            );
            $stmt->execute([':u' => $userId]);
            $purchase = $stmt->fetch();
            if (!$purchase) {
                db()->rollBack();
                return false;
            }

            db()->prepare(
                "UPDATE pack_purchases
                    SET credits_remaining = credits_remaining - 1
                  WHERE id = :id"
            )->execute([':id' => $purchase['id']]);

            db()->prepare(
                "INSERT INTO member_credits (user_id, purchase_id, booking_id, credits_change, reason)
                 VALUES (:u, :pid, :b, -1, 'booking_redeem')"
            )->execute([
                ':u'   => $userId,
                ':pid' => $purchase['id'],
                ':b'   => $bookingId,
            ]);

            db()->commit();
            return true;
        } catch (Throwable $e) {
            db()->rollBack();
            error_log('[class_packs] redeem failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Return a credit to the user (e.g. on booking cancellation). Picks the
     * same purchase the original redemption was tied to so expiry stays
     * consistent.
     */
    function refund_credit_for_booking(int $userId, int $bookingId): void
    {
        $stmt = db()->prepare(
            "SELECT purchase_id FROM member_credits
              WHERE user_id = :u AND booking_id = :b AND reason = 'booking_redeem'
              LIMIT 1"
        );
        $stmt->execute([':u' => $userId, ':b' => $bookingId]);
        $purchaseId = (int) ($stmt->fetchColumn() ?: 0);
        if (!$purchaseId) return;

        db()->beginTransaction();
        try {
            db()->prepare(
                "UPDATE pack_purchases
                    SET credits_remaining = credits_remaining + 1
                  WHERE id = :id"
            )->execute([':id' => $purchaseId]);

            db()->prepare(
                "INSERT INTO member_credits (user_id, purchase_id, booking_id, credits_change, reason)
                 VALUES (:u, :pid, :b, 1, 'booking_cancel')"
            )->execute([
                ':u'   => $userId,
                ':pid' => $purchaseId,
                ':b'   => $bookingId,
            ]);
            db()->commit();
        } catch (Throwable $e) {
            db()->rollBack();
            error_log('[class_packs] refund failed: ' . $e->getMessage());
        }
    }
}
