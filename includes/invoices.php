<?php
declare(strict_types=1);

/**
 * Invoice + receipt issuance.
 *
 *   issue_invoice() — called when a booking or membership is created.
 *                     Produces a doc_type='invoice', status='due'.
 *   issue_receipt() — called when a payment is settled. Produces a
 *                     doc_type='receipt', status='paid', linked to the
 *                     originating invoice. Also flips the invoice's
 *                     status to 'paid' for clarity.
 *
 * Document numbers are issued atomically once the row exists:
 *   INV-2026-000123 / RCP-2026-000123.
 *
 * Snapshots of the customer + company are stored as JSON, so reprinting
 * an old document yields the same content even after rebranding.
 */

if (!function_exists('issue_invoice')) {

    function _company_snapshot_for_invoice(): array
    {
        return [
            'brand'        => brand_name(),
            'legal_name'   => (string) setting('company_legal_name', ''),
            'registration' => (string) setting('company_registration_no', ''),
            'address'      => array_values(array_filter([
                (string) setting('company_address_line1', ''),
                (string) setting('company_address_line2', ''),
                (string) setting('company_city', ''),
                (string) setting('company_country', ''),
            ], fn ($v) => trim($v) !== '')),
            'phone'         => (string) setting('company_phone', ''),
            'email'         => (string) setting('company_email', ''),
            'billing_email' => (string) setting('company_billing_email', ''),
        ];
    }

    function _customer_snapshot_for_invoice(int $userId): array
    {
        $stmt = db()->prepare('SELECT full_name, email, phone FROM users WHERE id = :id');
        $stmt->execute([':id' => $userId]);
        $row = $stmt->fetch() ?: [];
        return [
            'name'  => (string) ($row['full_name'] ?? ''),
            'email' => (string) ($row['email'] ?? ''),
            'phone' => (string) ($row['phone'] ?? ''),
        ];
    }

    function _assign_doc_number(int $invoiceId, string $docType): void
    {
        $prefix = $docType === 'receipt' ? 'RCP' : 'INV';
        $year   = date('Y');
        $number = sprintf('%s-%s-%06d', $prefix, $year, $invoiceId);
        db()->prepare('UPDATE invoices SET doc_number = :n WHERE id = :id AND doc_number IS NULL')
            ->execute([':n' => $number, ':id' => $invoiceId]);
    }

    /**
     * Build a line-items array for a booking (1 line, multiplied by quantity).
     * Callers can pass their own array if the shape needs to be different.
     */
    function build_booking_line_items(array $booking): array
    {
        return [[
            'description' => 'Session · ' . ($booking['event_title'] ?? ('Event #' . (int)($booking['event_id'] ?? 0))),
            'subtext'     => isset($booking['starts_at']) ? format_datetime((string) $booking['starts_at']) : '',
            'quantity'    => (int) ($booking['quantity'] ?? 1),
            'unit_price'  => (float) ($booking['unit_price'] ?? 0),
            'amount'      => (float) ($booking['total_amount'] ?? ((int)($booking['quantity'] ?? 1) * (float)($booking['unit_price'] ?? 0))),
        ]];
    }

    function build_membership_line_items(array $membership): array
    {
        return [[
            'description' => 'Membership · ' . ($membership['plan_name'] ?? ''),
            'subtext'     => isset($membership['billing_cycle']) ? ucfirst((string) $membership['billing_cycle']) . ' cycle' : '',
            'quantity'    => 1,
            'unit_price'  => (float) ($membership['price'] ?? 0),
            'amount'      => (float) ($membership['price'] ?? 0),
        ]];
    }

    /**
     * Idempotent — if an open invoice already exists for the same
     * (purpose, reference_id), it's returned instead of duplicated.
     */
    function issue_invoice(int $userId, string $purpose, int $referenceId, array $lineItems, float $tax = 0.0, string $currency = 'MYR'): int
    {
        $existing = db()->prepare(
            "SELECT id FROM invoices
             WHERE doc_type = 'invoice'
               AND purpose = :p AND reference_id = :r
               AND status <> 'void'
             LIMIT 1"
        );
        $existing->execute([':p' => $purpose, ':r' => $referenceId]);
        if ($id = (int) ($existing->fetchColumn() ?: 0)) {
            return $id;
        }

        $subtotal = 0.0;
        foreach ($lineItems as $li) {
            $subtotal += (float) ($li['amount'] ?? 0);
        }
        $total = $subtotal + $tax;

        $stmt = db()->prepare(
            "INSERT INTO invoices
                (doc_type, access_token, user_id, purpose, reference_id,
                 subtotal, tax, total, currency, status,
                 line_items, customer_snapshot, company_snapshot, issued_at)
             VALUES
                ('invoice', :tok, :u, :p, :r,
                 :sub, :tax, :tot, :cur, 'due',
                 :li, :cust, :co, NOW())"
        );
        $stmt->execute([
            ':tok'  => bin2hex(random_bytes(20)),
            ':u'    => $userId,
            ':p'    => $purpose,
            ':r'    => $referenceId,
            ':sub'  => $subtotal,
            ':tax'  => $tax,
            ':tot'  => $total,
            ':cur'  => $currency,
            ':li'   => json_encode($lineItems, JSON_UNESCAPED_UNICODE),
            ':cust' => json_encode(_customer_snapshot_for_invoice($userId), JSON_UNESCAPED_UNICODE),
            ':co'   => json_encode(_company_snapshot_for_invoice(), JSON_UNESCAPED_UNICODE),
        ]);
        $id = (int) db()->lastInsertId();
        _assign_doc_number($id, 'invoice');
        if (function_exists('audit_log')) {
            audit_log('invoice.issue', 'invoices', $id, ['purpose' => $purpose, 'ref' => $referenceId]);
        }
        return $id;
    }

    /**
     * Issue a B2B / speaker-fee / consulting invoice to an arbitrary
     * billing entity that isn't a registered member — e.g. a hospital
     * paying for a wellness session or a corporate sponsor.
     *
     * $billTo is an assoc array — name (required), attention, address,
     * email, phone, tax_id, notes. Snapshot stored verbatim into
     * customer_snapshot so the printed invoice always matches what
     * the admin typed at issue time, even if the company later
     * changes address.
     *
     * $lineItems is the same shape issue_invoice() takes:
     *   [ ['description' => 'Speaker fee — Gong Bath', 'quantity' => 1, 'unit_price' => 2000, 'amount' => 2000] ]
     *
     * $createdBy is the admin user_id (invoices.user_id is NOT NULL
     * so we still need someone — the admin is the sensible audit
     * anchor).
     *
     * Returns the new invoice id.
     */
    function issue_manual_invoice(array $billTo, array $lineItems, int $createdBy, float $tax = 0.0, string $currency = 'MYR', ?string $notes = null): int
    {
        if (trim((string) ($billTo['name'] ?? '')) === '') {
            throw new InvalidArgumentException('Bill-to name is required.');
        }

        $subtotal = 0.0;
        foreach ($lineItems as $li) {
            $subtotal += (float) ($li['amount'] ?? 0);
        }
        $total = $subtotal + $tax;

        db()->prepare(
            "INSERT INTO invoices
                (doc_type, access_token, user_id, bill_to_type, purpose, reference_id,
                 subtotal, tax, total, currency, status,
                 line_items, customer_snapshot, company_snapshot, notes, issued_at)
             VALUES
                ('invoice', :tok, :u, 'company', 'manual', NULL,
                 :sub, :tax, :tot, :cur, 'due',
                 :li, :bill, :co, :notes, NOW())"
        )->execute([
            ':tok'   => bin2hex(random_bytes(20)),
            ':u'     => $createdBy,
            ':sub'   => $subtotal,
            ':tax'   => $tax,
            ':tot'   => $total,
            ':cur'   => $currency,
            ':li'    => json_encode($lineItems, JSON_UNESCAPED_UNICODE),
            ':bill'  => json_encode($billTo, JSON_UNESCAPED_UNICODE),
            ':co'    => json_encode(_company_snapshot_for_invoice(), JSON_UNESCAPED_UNICODE),
            ':notes' => $notes,
        ]);
        $id = (int) db()->lastInsertId();
        _assign_doc_number($id, 'invoice');
        if (function_exists('audit_log')) {
            audit_log('invoice.manual', 'invoices', $id, [
                'bill_to' => (string) $billTo['name'],
                'total'   => $total,
                'by'      => $createdBy,
            ]);
        }
        return $id;
    }

    /**
     * Mark a manual invoice as paid without going through Billplz.
     * Used when the company pays offline (bank transfer, cheque).
     */
    function mark_invoice_paid_manually(int $invoiceId, ?string $note = null): bool
    {
        $stmt = db()->prepare(
            "UPDATE invoices
                SET status = 'paid', paid_at = NOW(),
                    notes = TRIM(CONCAT(COALESCE(notes,''), :sep, :add))
              WHERE id = :id AND status IN ('due','refunded')"
        );
        $stmt->execute([
            ':sep' => $note !== null ? "\n" : '',
            ':add' => $note !== null ? '[paid ' . date('Y-m-d') . '] ' . $note : '',
            ':id'  => $invoiceId,
        ]);
        if ($stmt->rowCount() > 0 && function_exists('audit_log')) {
            audit_log('invoice.mark_paid', 'invoices', $invoiceId, ['note' => $note]);
        }
        return $stmt->rowCount() > 0;
    }

    /**
     * Settles the open invoice for this payment and emits a paired receipt.
     * Idempotent — returns the existing receipt id if already issued.
     */
    function issue_receipt(int $paymentId): ?int
    {
        $p = db()->prepare("SELECT * FROM payments WHERE id = :id");
        $p->execute([':id' => $paymentId]);
        $payment = $p->fetch();
        if (!$payment || $payment['status'] !== 'paid') {
            return null;
        }

        // Reuse the invoice's snapshots so the receipt stays consistent with it.
        $i = db()->prepare(
            "SELECT * FROM invoices
              WHERE doc_type='invoice' AND purpose=:p AND reference_id=:r AND status<>'void'
              ORDER BY id DESC LIMIT 1"
        );
        $i->execute([':p' => $payment['purpose'], ':r' => $payment['reference_id']]);
        $invoice = $i->fetch();

        // Already have a receipt linked to this payment?
        if ($invoice) {
            $rc = db()->prepare("SELECT id FROM invoices WHERE doc_type='receipt' AND payment_id = :pid LIMIT 1");
            $rc->execute([':pid' => $paymentId]);
            if ($rid = (int) ($rc->fetchColumn() ?: 0)) {
                // Make sure the invoice is marked paid.
                db()->prepare("UPDATE invoices SET status='paid', paid_at=COALESCE(paid_at, NOW()), payment_id=:pid WHERE id=:id AND status<>'paid'")
                    ->execute([':pid' => $paymentId, ':id' => $invoice['id']]);
                return $rid;
            }
        }

        // Build line items + snapshots. If no invoice exists (very rare —
        // e.g. demo-mode), still emit a stand-alone receipt.
        if ($invoice) {
            $lineItems  = json_decode((string) $invoice['line_items'], true) ?: [];
            $customer   = json_decode((string) $invoice['customer_snapshot'], true) ?: _customer_snapshot_for_invoice((int) $payment['user_id']);
            $company    = json_decode((string) $invoice['company_snapshot'], true) ?: _company_snapshot_for_invoice();
            $subtotal   = (float) $invoice['subtotal'];
            $tax        = (float) $invoice['tax'];
            $total      = (float) $invoice['total'];
            $currency   = (string) $invoice['currency'];
            $invoiceId  = (int) $invoice['id'];
        } else {
            $lineItems  = [[
                'description' => ucfirst((string) $payment['purpose']) . ' payment',
                'subtext'     => '',
                'quantity'    => 1,
                'unit_price'  => (float) $payment['amount'],
                'amount'      => (float) $payment['amount'],
            ]];
            $customer   = _customer_snapshot_for_invoice((int) $payment['user_id']);
            $company    = _company_snapshot_for_invoice();
            $subtotal   = (float) $payment['amount'];
            $tax        = 0.0;
            $total      = (float) $payment['amount'];
            $currency   = (string) $payment['currency'];
            $invoiceId  = null;
        }

        db()->beginTransaction();
        try {
            $stmt = db()->prepare(
                "INSERT INTO invoices
                    (doc_type, access_token, user_id, purpose, reference_id, payment_id, invoice_id,
                     subtotal, tax, total, currency, status,
                     line_items, customer_snapshot, company_snapshot,
                     issued_at, paid_at)
                 VALUES
                    ('receipt', :tok, :u, :p, :r, :pid, :iid,
                     :sub, :tax, :tot, :cur, 'paid',
                     :li, :cust, :co,
                     NOW(), COALESCE(:paid_at, NOW()))"
            );
            $stmt->execute([
                ':tok'      => bin2hex(random_bytes(20)),
                ':u'        => (int) $payment['user_id'],
                ':p'        => (string) $payment['purpose'],
                ':r'        => (int) $payment['reference_id'],
                ':pid'      => $paymentId,
                ':iid'      => $invoiceId,
                ':sub'      => $subtotal,
                ':tax'      => $tax,
                ':tot'      => $total,
                ':cur'      => $currency,
                ':li'       => json_encode($lineItems, JSON_UNESCAPED_UNICODE),
                ':cust'     => json_encode($customer, JSON_UNESCAPED_UNICODE),
                ':co'       => json_encode($company, JSON_UNESCAPED_UNICODE),
                ':paid_at'  => $payment['paid_at'] ?? null,
            ]);
            $receiptId = (int) db()->lastInsertId();
            _assign_doc_number($receiptId, 'receipt');

            if ($invoiceId) {
                db()->prepare(
                    "UPDATE invoices SET status='paid', paid_at=COALESCE(paid_at, NOW()), payment_id=:pid
                     WHERE id=:id"
                )->execute([':pid' => $paymentId, ':id' => $invoiceId]);
            }
            db()->commit();
        } catch (Throwable $e) {
            db()->rollBack();
            error_log('[invoices] receipt issue failed: ' . $e->getMessage());
            return null;
        }

        if (function_exists('audit_log')) {
            audit_log('receipt.issue', 'invoices', $receiptId, ['payment' => $paymentId]);
        }
        return $receiptId;
    }

    function document_url(array $invoice): string
    {
        return url('/member/document.php?id=' . (int) $invoice['id']
                   . '&t=' . urlencode((string) $invoice['access_token']));
    }
}
