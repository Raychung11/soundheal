<?php
declare(strict_types=1);

/**
 * Partner (cafe / business) referral helpers.
 *
 *   find_partner_by_slug()       — lookup active partners by their QR slug.
 *   set_partner_cookie()         — drop the attribution cookie on scan.
 *   get_partner_cookie()         — current cookie value (validated).
 *   clear_partner_cookie()       — remove after successful attribution.
 *   attribute_partner_booking()  — write a pending referral row for a new
 *                                  booking + cache partner_id on the booking.
 *   earn_partner_referral()      — flip 'pending' → 'earned' on attendance.
 *   reverse_partner_referral()   — flip on refund.
 *   settle_partner_referral_payout() — batch unpaid earned rows for one partner.
 *   partner_summary()            — dashboard totals (all or one partner).
 *
 * All accounting mirrors event_referral_rewards so both live under the
 * same admin flow. Idempotency comes from UNIQUE(booking_id) + WHERE
 * status guards on every UPDATE.
 */

const PARTNER_COOKIE = 'sh_partner';

function find_partner_by_slug(?string $slug): ?array
{
    if (!$slug) return null;
    $slug = strtolower(trim((string) $slug));
    if ($slug === '' || !preg_match('/^[a-z0-9\-]{2,80}$/', $slug)) return null;
    $stmt = db()->prepare("SELECT * FROM partners WHERE slug = :s AND status = 'active' LIMIT 1");
    $stmt->execute([':s' => $slug]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function find_partner_by_id(int $id): ?array
{
    if ($id <= 0) return null;
    $stmt = db()->prepare("SELECT * FROM partners WHERE id = :id LIMIT 1");
    $stmt->execute([':id' => $id]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function set_partner_cookie(string $slug): void
{
    $slug = strtolower(trim($slug));
    if (!preg_match('/^[a-z0-9\-]{2,80}$/', $slug)) return;
    $days = max(1, (int) setting('partner_cookie_days', 45));
    setcookie(PARTNER_COOKIE, $slug, [
        'expires'  => time() + 86400 * $days,
        'path'     => '/',
        'secure'   => (bool) config('app.security.cookie_secure', true),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    $_COOKIE[PARTNER_COOKIE] = $slug;
}

function get_partner_cookie(): ?string
{
    $slug = $_COOKIE[PARTNER_COOKIE] ?? null;
    if (!$slug) return null;
    $slug = strtolower(trim((string) $slug));
    return preg_match('/^[a-z0-9\-]{2,80}$/', $slug) ? $slug : null;
}

function clear_partner_cookie(): void
{
    setcookie(PARTNER_COOKIE, '', [
        'expires'  => time() - 3600,
        'path'     => '/',
        'samesite' => 'Lax',
    ]);
    unset($_COOKIE[PARTNER_COOKIE]);
}

/**
 * Convert commission_type / commission_rate into a MYR amount for a
 * booking of the given total. Percent is calculated on the booking's
 * paid total (not the list price) so refunds/discounts flow through.
 */
function partner_commission_amount(array $partner, float $bookingTotal): float
{
    $rate = (float) ($partner['commission_rate'] ?? 0);
    if ($rate <= 0) return 0.0;
    if (($partner['commission_type'] ?? 'fixed') === 'percent') {
        return round(max(0.0, $bookingTotal) * ($rate / 100), 2);
    }
    return round($rate, 2);
}

/**
 * Called from book_event.php right after commit. If the visitor arrived
 * via a partner QR (cookie set), attribute the booking and record a
 * pending referral row. Self-attribution to inactive partners is a
 * no-op. UNIQUE(booking_id) makes this safe to call twice.
 */
function attribute_partner_booking(int $bookingId, ?string $slug = null): void
{
    if ($bookingId <= 0) return;
    $slug = $slug ?: get_partner_cookie();
    if (!$slug) return;

    $partner = find_partner_by_slug($slug);
    if (!$partner) return;

    $b = db()->prepare(
        "SELECT id, user_id, total_amount FROM event_bookings WHERE id = :id LIMIT 1"
    );
    $b->execute([':id' => $bookingId]);
    $booking = $b->fetch();
    if (!$booking) return;

    $amount = partner_commission_amount($partner, (float) $booking['total_amount']);
    if ($amount <= 0) return;

    db()->prepare(
        "INSERT IGNORE INTO partner_referrals
            (partner_id, booking_id, user_id, amount, status)
         VALUES (:p, :b, :u, :a, 'pending')"
    )->execute([
        ':p' => (int) $partner['id'],
        ':b' => $bookingId,
        ':u' => (int) $booking['user_id'],
        ':a' => $amount,
    ]);

    db()->prepare(
        "UPDATE event_bookings SET partner_id = :p
          WHERE id = :b AND partner_id IS NULL"
    )->execute([':p' => (int) $partner['id'], ':b' => $bookingId]);

    audit_log('partner.referral.pending', 'partner_referrals', $bookingId, [
        'partner_id' => (int) $partner['id'],
        'amount'     => $amount,
    ]);

    // Cookie has done its job — clear it so a re-scan by the same
    // visitor doesn't accidentally re-attribute a later booking they
    // made independently. If we ever want multi-booking attribution we
    // can widen the cookie TTL and skip this line.
    clear_partner_cookie();
}

function earn_partner_referral(int $bookingId): void
{
    if ($bookingId <= 0) return;
    db()->prepare(
        "UPDATE partner_referrals
            SET status = 'earned', earned_at = COALESCE(earned_at, NOW())
          WHERE booking_id = :b AND status = 'pending'"
    )->execute([':b' => $bookingId]);
}

function reverse_partner_referral(int $bookingId, string $reason = 'Refunded'): void
{
    if ($bookingId <= 0) return;
    db()->prepare(
        "UPDATE partner_referrals
            SET status = 'reversed', reversed_at = NOW(), note = :n
          WHERE booking_id = :b AND status IN ('pending','earned')"
    )->execute([':n' => substr($reason, 0, 255), ':b' => $bookingId]);
}

/**
 * Bump the scan counter on the partner row. Cheap increment so the
 * admin list can show "seen 34 times · last on 3 Jul" without a
 * separate visits table.
 */
function record_partner_scan(int $partnerId): void
{
    if ($partnerId <= 0) return;
    db()->prepare(
        "UPDATE partners
            SET scan_count = scan_count + 1, last_scan_at = NOW()
          WHERE id = :id"
    )->execute([':id' => $partnerId]);
}

function partner_summary(?int $partnerId = null): array
{
    $where  = $partnerId ? 'WHERE partner_id = :p' : '';
    $params = $partnerId ? [':p' => $partnerId] : [];
    $stmt = db()->prepare(
        "SELECT
            COALESCE(SUM(CASE WHEN status='pending' THEN amount END),0)                                       AS pending_total,
            COALESCE(SUM(CASE WHEN status='earned'  AND payout_status='unpaid' THEN amount END),0)            AS unpaid_earned,
            COALESCE(SUM(CASE WHEN payout_status='paid'                        THEN amount END),0)            AS paid_total,
            COALESCE(SUM(CASE WHEN status='reversed'                           THEN amount END),0)            AS reversed_total,
            COUNT(CASE WHEN status='earned' AND payout_status='unpaid'         THEN 1 END)                    AS unpaid_count
          FROM partner_referrals $where"
    );
    $stmt->execute($params);
    return $stmt->fetch() ?: [
        'pending_total' => 0, 'unpaid_earned' => 0, 'paid_total' => 0,
        'reversed_total' => 0, 'unpaid_count' => 0,
    ];
}

/**
 * Distinct name from revenue.php's settle_partner_payout() (which
 * batches IT-partner revenue-split rows into the partner_payouts
 * table). This one writes to partner_referral_payouts and is scoped
 * to cafe/business referrers.
 */
function settle_partner_referral_payout(int $partnerId, string $reference, ?int $byUserId): array
{
    if ($partnerId <= 0) {
        return ['ok' => false, 'message' => 'Missing partner.'];
    }

    // Cheap read outside the txn just to decide whether to open one.
    // Real amount is computed after the claiming UPDATE below so a
    // concurrent check-in can't fatten a payout we've already promised
    // to pay a fixed amount for.
    $preview = db()->prepare(
        "SELECT COUNT(*) FROM partner_referrals
          WHERE partner_id = :p AND status = 'earned' AND payout_status = 'unpaid'"
    );
    $preview->execute([':p' => $partnerId]);
    if ((int) $preview->fetchColumn() === 0) {
        return ['ok' => false, 'message' => 'There is nothing owed to this partner yet.'];
    }

    db()->beginTransaction();
    try {
        // 1. Reserve a payout row up-front with placeholder totals so
        //    we have an id to stamp on the referrals.
        db()->prepare(
            "INSERT INTO partner_referral_payouts (partner_id, amount, currency, reward_count, reference, paid_by)
             VALUES (:p, 0, 'MYR', 0, :ref, :u)"
        )->execute([
            ':p'   => $partnerId,
            ':ref' => $reference !== '' ? substr($reference, 0, 160) : null,
            ':u'   => $byUserId,
        ]);
        $payoutId = (int) db()->lastInsertId();

        // 2. Claim every currently-eligible referral atomically. Rows
        //    that flip to 'earned' AFTER this UPDATE will land in the
        //    next payout, not this one.
        db()->prepare(
            "UPDATE partner_referrals
                SET payout_status = 'paid', payout_id = :po
              WHERE partner_id = :p AND status = 'earned' AND payout_status = 'unpaid'"
        )->execute([':po' => $payoutId, ':p' => $partnerId]);

        // 3. Compute the real totals from the rows we actually claimed
        //    and write them onto the payout row.
        $sumStmt = db()->prepare(
            "SELECT COALESCE(SUM(amount),0) AS amt, COUNT(*) AS c
               FROM partner_referrals WHERE payout_id = :po"
        );
        $sumStmt->execute([':po' => $payoutId]);
        $s = $sumStmt->fetch();
        $amount = (float) ($s['amt'] ?? 0);
        $count  = (int)   ($s['c']   ?? 0);

        db()->prepare(
            "UPDATE partner_referral_payouts SET amount = :a, reward_count = :c WHERE id = :id"
        )->execute([':a' => $amount, ':c' => $count, ':id' => $payoutId]);

        db()->commit();
        return ['ok' => true, 'amount' => $amount, 'count' => $count, 'payout_id' => $payoutId];
    } catch (Throwable $e) {
        db()->rollBack();
        error_log('[partners] settle failed: ' . $e->getMessage());
        return ['ok' => false, 'message' => 'Could not record the payout. Please try again.'];
    }
}

/**
 * Slug generator — lowercased, dashes, ASCII only. Falls back to a
 * random suffix on collision so admins never need to hand-edit.
 */
function generate_partner_slug(string $name): string
{
    $s = strtolower(trim($name));
    $s = (string) preg_replace('/[^a-z0-9]+/', '-', $s);
    $s = trim($s, '-');
    if ($s === '') $s = 'partner';
    if (strlen($s) > 60) $s = substr($s, 0, 60);
    $base = $s;
    $i = 0;
    while (true) {
        $check = db()->prepare('SELECT 1 FROM partners WHERE slug = :s LIMIT 1');
        $check->execute([':s' => $s]);
        if (!$check->fetchColumn()) return $s;
        $i++;
        $s = $base . '-' . strtolower(substr(bin2hex(random_bytes(2)), 0, 4));
        if ($i > 4) return $s;
    }
}

/**
 * URL a partner puts on their poster / sticker. Short and memorable so
 * it prints legibly under the QR ("scan me · /p/cafemocha").
 */
function partner_share_url(array $partner): string
{
    return rtrim((string) config('app.url'), '/') . '/public/p.php?s=' . rawurlencode((string) $partner['slug']);
}

/**
 * QR image URL. Uses the same qrserver.com service already in use for
 * ticket QR codes so we don't add a new external dependency.
 */
function partner_qr_image_url(array $partner, int $size = 480): string
{
    $size = max(120, min(1000, $size));
    return 'https://api.qrserver.com/v1/create-qr-code/?size=' . $size . 'x' . $size
        . '&margin=10&data=' . urlencode(partner_share_url($partner));
}
