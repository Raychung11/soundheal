<?php
declare(strict_types=1);

/**
 * Event referral rewards — cash to the referrer when a friend they
 * invited actually attends a session.
 *
 *   record_event_referral($bookingId, $referrerId)
 *     — Booking just created via a ref link → write a 'pending' row.
 *   earn_referral_reward($bookingId)
 *     — Booking flipped to 'attended' (check-in) → 'earned'.
 *   reverse_referral_reward($bookingId, $reason)
 *     — Booking refunded → 'reversed'.
 *   settle_referral_payout($referrerId, $ref, $byUserId)
 *     — Admin batches all 'earned' + 'unpaid' rewards for one
 *       referrer into a payout row.
 *
 * All operations idempotent — UNIQUE(booking_id) plus WHERE
 * status guards mean the hooks are safe to call from multiple
 * paths (webhook, thanks page, admin manual settle).
 */

if (!function_exists('record_event_referral')) {

    function referral_reward_amount_for(int $eventId): float
    {
        $stmt = db()->prepare("SELECT referral_reward_amount FROM events WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $eventId]);
        $override = $stmt->fetchColumn();
        if ($override !== false && $override !== null && (float) $override > 0) {
            return (float) $override;
        }
        return (float) setting('referral_event_reward_default', 50.00);
    }

    function record_event_referral(int $bookingId, int $referrerId): void
    {
        if ($bookingId <= 0 || $referrerId <= 0) return;

        $b = db()->prepare(
            "SELECT id, user_id, event_id FROM event_bookings WHERE id = :id LIMIT 1"
        );
        $b->execute([':id' => $bookingId]);
        $booking = $b->fetch();
        if (!$booking) return;

        // Self-referral guard.
        if ((int) $booking['user_id'] === $referrerId) return;

        $amount = referral_reward_amount_for((int) $booking['event_id']);
        if ($amount <= 0) return;

        // UNIQUE(booking_id) → INSERT IGNORE keeps this idempotent if
        // the hook fires twice.
        db()->prepare(
            "INSERT IGNORE INTO event_referral_rewards
                (booking_id, referrer_id, amount, status)
             VALUES (:b, :r, :a, 'pending')"
        )->execute([':b' => $bookingId, ':r' => $referrerId, ':a' => $amount]);

        // Stamp the referrer on the booking too — makes admin lookups easy.
        db()->prepare(
            "UPDATE event_bookings SET referred_by_user_id = :r
              WHERE id = :b AND referred_by_user_id IS NULL"
        )->execute([':r' => $referrerId, ':b' => $bookingId]);
    }

    function earn_referral_reward(int $bookingId): void
    {
        if ($bookingId <= 0) return;
        db()->prepare(
            "UPDATE event_referral_rewards
                SET status = 'earned', earned_at = COALESCE(earned_at, NOW())
              WHERE booking_id = :b AND status = 'pending'"
        )->execute([':b' => $bookingId]);
    }

    function reverse_referral_reward(int $bookingId, string $reason = 'Refunded'): void
    {
        if ($bookingId <= 0) return;
        db()->prepare(
            "UPDATE event_referral_rewards
                SET status = 'reversed', reversed_at = NOW(), note = :n
              WHERE booking_id = :b AND status IN ('pending','earned')"
        )->execute([':n' => substr($reason, 0, 255), ':b' => $bookingId]);
    }

    /**
     * Roll-up totals for the admin dashboard. Pass a referrer id to
     * scope to one member, or null for platform-wide totals.
     */
    function referral_rewards_summary(?int $referrerId = null): array
    {
        $where = $referrerId ? 'WHERE referrer_id = :r' : '';
        $stmt = db()->prepare(
            "SELECT
                COALESCE(SUM(CASE WHEN status='pending' THEN amount END),0)                                       AS pending_total,
                COALESCE(SUM(CASE WHEN status='earned'  AND payout_status='unpaid' THEN amount END),0)            AS unpaid_earned,
                COALESCE(SUM(CASE WHEN payout_status='paid'                        THEN amount END),0)            AS paid_total,
                COALESCE(SUM(CASE WHEN status='reversed'                           THEN amount END),0)            AS reversed_total,
                COUNT(CASE WHEN status='earned' AND payout_status='unpaid'         THEN 1 END)                    AS unpaid_count
              FROM event_referral_rewards $where"
        );
        $referrerId ? $stmt->execute([':r' => $referrerId]) : $stmt->execute();
        return $stmt->fetch() ?: [
            'pending_total' => 0, 'unpaid_earned' => 0, 'paid_total' => 0,
            'reversed_total' => 0, 'unpaid_count' => 0,
        ];
    }

    /**
     * Batch every unpaid+earned reward for a referrer into a single
     * payout row. Same pattern as settle_partner_payout().
     */
    function settle_referral_payout(int $referrerId, string $reference, ?int $byUserId): array
    {
        // Cheap read outside the txn just to decide whether to open one
        // and produce the "nothing owed" UX message. The real amount is
        // computed after the claiming UPDATE below so a concurrent
        // check-in can't inflate a payout we've already promised to
        // pay a fixed amount for.
        $preview = db()->prepare(
            "SELECT COUNT(*) FROM event_referral_rewards
              WHERE referrer_id = :r AND status = 'earned' AND payout_status = 'unpaid'"
        );
        $preview->execute([':r' => $referrerId]);
        if ((int) $preview->fetchColumn() === 0) {
            return ['ok' => false, 'message' => 'There is nothing owed to this referrer yet.'];
        }

        db()->beginTransaction();
        try {
            // 1. Reserve a payout row up-front with placeholder totals so
            //    we have an id to stamp on the rewards.
            db()->prepare(
                "INSERT INTO referral_payouts (referrer_id, amount, currency, reward_count, reference, paid_by)
                 VALUES (:r, 0, 'MYR', 0, :ref, :u)"
            )->execute([
                ':r'   => $referrerId,
                ':ref' => $reference !== '' ? substr($reference, 0, 160) : null,
                ':u'   => $byUserId,
            ]);
            $payoutId = (int) db()->lastInsertId();

            // 2. Claim every currently-eligible reward atomically. Rows
            //    that flip to 'earned' AFTER this UPDATE will land in
            //    the next payout, not this one.
            db()->prepare(
                "UPDATE event_referral_rewards
                    SET payout_status = 'paid', payout_id = :p
                  WHERE referrer_id = :r AND status = 'earned' AND payout_status = 'unpaid'"
            )->execute([':p' => $payoutId, ':r' => $referrerId]);

            // 3. Compute the real totals from the rows we actually
            //    claimed, then write them onto the payout row.
            $sumStmt = db()->prepare(
                "SELECT COALESCE(SUM(amount),0) AS amt, COUNT(*) AS c
                   FROM event_referral_rewards WHERE payout_id = :p"
            );
            $sumStmt->execute([':p' => $payoutId]);
            $s = $sumStmt->fetch();
            $amount = (float) ($s['amt'] ?? 0);
            $count  = (int)   ($s['c']   ?? 0);

            db()->prepare(
                "UPDATE referral_payouts SET amount = :a, reward_count = :c WHERE id = :id"
            )->execute([':a' => $amount, ':c' => $count, ':id' => $payoutId]);

            db()->commit();
            return ['ok' => true, 'amount' => $amount, 'count' => $count, 'payout_id' => $payoutId];
        } catch (Throwable $e) {
            db()->rollBack();
            error_log('[referral_rewards] settle failed: ' . $e->getMessage());
            return ['ok' => false, 'message' => 'Could not record the payout. Please try again.'];
        }
    }
}
