<?php
declare(strict_types=1);

/**
 * Auto revenue-split ledger.
 *
 *   record_revenue_split()  is called by settle_payment() the moment a
 *   payment is confirmed paid. It writes one immutable ledger row per
 *   payment (UNIQUE on payment_id → idempotent) splitting the GROSS
 *   amount between the company and the IT partner (default 85/15).
 *
 *   reverse_revenue_split() is called on refund and flips the matching
 *   row to 'reversed' so the partner balance stays accurate.
 *
 *   Only payments settled on/after revenue_split_start_date are split,
 *   so historical revenue is never retroactively divided. Percentages,
 *   labels, the cutover date and the on/off switch are all admin-editable
 *   from /admin/revenue_splits.php.
 */

if (!function_exists('record_revenue_split')) {

    function revenue_split_config(): array
    {
        $partner = (float) setting('revenue_split_partner_pct', 15);
        $partner = max(0.0, min(100.0, $partner));
        return [
            'enabled'       => (bool) setting('revenue_split_enabled', true),
            'partner_pct'   => $partner,
            'company_pct'   => round(100 - $partner, 2),
            'partner_label' => (string) setting('revenue_split_partner_label', 'IT partner'),
            'company_label' => (string) setting('revenue_split_company_label', 'Company'),
            'start_date'    => trim((string) setting('revenue_split_start_date', '')),
        ];
    }

    /**
     * Idempotently record the company/partner split for a paid payment.
     * No-op if disabled, unpaid, zero-value (free / credit-redeemed),
     * before the cutover date, or already recorded.
     */
    function record_revenue_split(int $paymentId): void
    {
        $cfg = revenue_split_config();
        if (!$cfg['enabled']) {
            return;
        }

        $stmt = db()->prepare("SELECT * FROM payments WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $paymentId]);
        $payment = $stmt->fetch();
        if (!$payment || $payment['status'] !== 'paid') {
            return;
        }

        $gross = (float) $payment['amount'];
        if ($gross <= 0) {
            return; // free or fully credit-paid — nothing to split
        }

        // Cutover: only split revenue settled on/after the start date.
        $paidAt = $payment['paid_at'] ?: ($payment['updated_at'] ?? null);
        if ($cfg['start_date'] !== '' && $paidAt !== null
            && strtotime((string) $paidAt) < strtotime($cfg['start_date'] . ' 00:00:00')) {
            return;
        }

        $partnerAmt = round($gross * $cfg['partner_pct'] / 100, 2);
        $companyAmt = round($gross - $partnerAmt, 2); // exact complement, no drift

        // UNIQUE(payment_id) makes this safe against the webhook +
        // redirect + manual-settle all firing for the same payment.
        $ins = db()->prepare(
            "INSERT INTO revenue_splits
                (payment_id, purpose, basis, gross_amount, basis_amount, currency,
                 company_pct, partner_pct, company_amount, partner_amount, status)
             VALUES
                (:pid, :pur, 'gross', :g, :g2, :cur,
                 :cpct, :ppct, :camt, :pamt, 'active')
             ON DUPLICATE KEY UPDATE payment_id = payment_id"
        );
        $ins->execute([
            ':pid'  => $paymentId,
            ':pur'  => (string) $payment['purpose'],
            ':g'    => $gross,
            ':g2'   => $gross,
            ':cur'  => (string) $payment['currency'],
            ':cpct' => $cfg['company_pct'],
            ':ppct' => $cfg['partner_pct'],
            ':camt' => $companyAmt,
            ':pamt' => $partnerAmt,
        ]);
    }

    /**
     * Reverse the active split for a payment (e.g. on refund). Leaves any
     * already-reversed row untouched. If the partner share was already
     * paid out, the row is still flagged reversed and surfaced in the
     * admin as an over-payment to reconcile.
     */
    function reverse_revenue_split(int $paymentId, string $reason = 'Refunded'): void
    {
        db()->prepare(
            "UPDATE revenue_splits
                SET status = 'reversed', reversed_at = NOW(), note = :n
              WHERE payment_id = :pid AND status = 'active'"
        )->execute([':n' => substr($reason, 0, 255), ':pid' => $paymentId]);
    }

    /** Headline totals for the admin dashboard. */
    function revenue_split_summary(): array
    {
        $row = db()->query(
            "SELECT
               COALESCE(SUM(CASE WHEN status='active' THEN gross_amount END),0)   AS gross_active,
               COALESCE(SUM(CASE WHEN status='active' THEN company_amount END),0)  AS company_active,
               COALESCE(SUM(CASE WHEN status='active' THEN partner_amount END),0)  AS partner_active,
               COALESCE(SUM(CASE WHEN status='active' AND partner_payout_status='unpaid' THEN partner_amount END),0) AS partner_unpaid,
               COALESCE(SUM(CASE WHEN partner_payout_status='paid' THEN partner_amount END),0) AS partner_paid,
               COALESCE(SUM(CASE WHEN status='reversed' THEN partner_amount END),0) AS partner_reversed,
               COUNT(CASE WHEN status='active' AND partner_payout_status='unpaid' THEN 1 END) AS unpaid_count
             FROM revenue_splits"
        )->fetch();
        return $row ?: [
            'gross_active' => 0, 'company_active' => 0, 'partner_active' => 0,
            'partner_unpaid' => 0, 'partner_paid' => 0, 'partner_reversed' => 0, 'unpaid_count' => 0,
        ];
    }

    /**
     * Record a partner payout: settle every unpaid, active split into a
     * single payout batch. Returns ['ok'=>bool, ...].
     */
    function settle_partner_payout(string $reference, ?int $byUserId): array
    {
        $s = db()->query(
            "SELECT COALESCE(SUM(partner_amount),0) AS amt, COUNT(*) AS c
               FROM revenue_splits
              WHERE status='active' AND partner_payout_status='unpaid'"
        )->fetch();
        $amount = (float) ($s['amt'] ?? 0);
        $count  = (int) ($s['c'] ?? 0);
        if ($count === 0 || $amount <= 0) {
            return ['ok' => false, 'message' => 'There is no unpaid partner balance to settle.'];
        }

        db()->beginTransaction();
        try {
            db()->prepare(
                "INSERT INTO partner_payouts (amount, currency, split_count, reference, paid_by)
                 VALUES (:a, 'MYR', :c, :r, :u)"
            )->execute([
                ':a' => $amount,
                ':c' => $count,
                ':r' => $reference !== '' ? substr($reference, 0, 160) : null,
                ':u' => $byUserId,
            ]);
            $payoutId = (int) db()->lastInsertId();

            db()->prepare(
                "UPDATE revenue_splits
                    SET partner_payout_status='paid', partner_payout_id=:pid
                  WHERE status='active' AND partner_payout_status='unpaid'"
            )->execute([':pid' => $payoutId]);

            db()->commit();
            return ['ok' => true, 'amount' => $amount, 'count' => $count, 'payout_id' => $payoutId];
        } catch (Throwable $e) {
            db()->rollBack();
            error_log('[revenue] settle_partner_payout failed: ' . $e->getMessage());
            return ['ok' => false, 'message' => 'Could not record the payout. Please try again.'];
        }
    }
}
