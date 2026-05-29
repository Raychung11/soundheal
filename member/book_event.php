<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_login();
$user = current_user();
$pageTitle = 'Reserve';

$eventId = (int) input('event_id', 0);
$stmt = db()->prepare("SELECT * FROM events WHERE id = :id AND status = 'published' LIMIT 1");
$stmt->execute([':id' => $eventId]);
$event = $stmt->fetch();
if (!$event) {
    flash('booking', 'That session is no longer available.', 'error');
    redirect('/public/events.php');
}

// Member pricing if they have an active membership.
$mStmt = db()->prepare("SELECT id FROM memberships WHERE user_id = :u AND status = 'active' LIMIT 1");
$mStmt->execute([':u' => $user['id']]);
$isMember = (bool) $mStmt->fetch();
$unitPrice = $isMember ? (float)$event['price_member'] : (float)$event['price_public'];

// Credit balance — lets the member pay with a pack credit instead of cash.
$creditBalance = credit_balance_for((int) $user['id']);

$errors = [];

if (is_post()) {
    csrf_verify();
    $useCredit = !empty($_POST['use_credit']) && $creditBalance > 0;
    $qty = $useCredit ? 1 : max(1, min(6, (int) input('quantity', 1)));

    db()->beginTransaction();
    try {
        $cap = db()->prepare(
            "SELECT capacity,
                    (SELECT COALESCE(SUM(quantity), 0)
                       FROM event_bookings
                      WHERE event_id = e.id
                        AND status IN ('paid','attended','pending')) AS taken
             FROM events e WHERE id = :id FOR UPDATE"
        );
        $cap->execute([':id' => $eventId]);
        $row = $cap->fetch();
        $remaining = (int)$row['capacity'] - (int)$row['taken'];
        if ($qty > $remaining) {
            throw new RuntimeException("Only {$remaining} seats remain for this session.");
        }

        $bookingRef = 'SH-' . strtoupper(bin2hex(random_bytes(4)));
        // When paying with a credit, the booking is effectively prepaid:
        // unit_price/total are recorded as 0 and the credit ledger is the source of truth.
        $effectiveUnit = $useCredit ? 0.0 : $unitPrice;
        $total = $effectiveUnit * $qty;

        $ins = db()->prepare(
            "INSERT INTO event_bookings (booking_ref, user_id, event_id, quantity, unit_price, total_amount, status, paid_with_credit)
             VALUES (:ref, :u, :e, :q, :up, :tot, :status, :pwc)"
        );
        $ins->execute([
            ':ref' => $bookingRef,
            ':u'   => $user['id'],
            ':e'   => $eventId,
            ':q'   => $qty,
            ':up'  => $effectiveUnit,
            ':tot' => $total,
            ':status' => ($useCredit || $effectiveUnit <= 0) ? 'paid' : 'pending',
            ':pwc' => $useCredit ? 1 : 0,
        ]);
        $bookingId = (int) db()->lastInsertId();

        // Burn the credit before issuing the ticket so we never double-spend.
        if ($useCredit) {
            if (!redeem_credit_for_booking((int) $user['id'], $bookingId)) {
                throw new RuntimeException('Could not redeem your credit. Please refresh and try again.');
            }
        }

        // Issue tickets immediately for free sessions and credit-paid sessions.
        if ($effectiveUnit <= 0 || $useCredit) {
            $tStmt = db()->prepare(
                "INSERT INTO tickets (booking_id, ticket_code, qr_token) VALUES (:b, :code, :token)"
            );
            for ($i = 0; $i < $qty; $i++) {
                $tStmt->execute([
                    ':b' => $bookingId,
                    ':code' => $bookingRef . '-' . ($i + 1),
                    ':token' => generate_token(24),
                ]);
            }
        }

        db()->commit();
        audit_log('booking.create', 'event_bookings', $bookingId, ['ref' => $bookingRef, 'total' => $total]);

        // Skip the invoice when the booking is fully paid with a credit —
        // the pack purchase already produced its own invoice/receipt.
        if (!$useCredit) {
            issue_invoice(
                (int) $user['id'],
                'booking',
                $bookingId,
                build_booking_line_items([
                    'event_title'  => $event['title'],
                    'event_id'     => $eventId,
                    'starts_at'    => $event['starts_at'],
                    'quantity'     => $qty,
                    'unit_price'   => $unitPrice,
                    'total_amount' => $total,
                ])
            );
        }

        if ($effectiveUnit <= 0 || $useCredit) {
            send_mail($user['email'], $user['full_name'], 'Your seat is held', 'booking_confirm', [
                'event_title' => $event['title'],
                'starts_at'   => format_datetime($event['starts_at']),
                'location'    => $event['location'] ?? 'Location TBA',
                'booking_ref' => $bookingRef,
            ]);
            $msg = $useCredit
                ? 'Seat held — 1 credit redeemed. We can\'t wait to welcome you.'
                : 'Your seat is held. We can\'t wait to welcome you.';
            flash('booking', $msg, 'success');
            redirect('/member/my_bookings.php');
        }

        // Otherwise hand off to payment.
        redirect('/member/checkout.php?booking=' . $bookingId);
    } catch (Throwable $e) {
        db()->rollBack();
        $errors[] = $e->getMessage();
    }
}

require __DIR__ . '/../includes/header.php';
?>
<div x-data="{ useCredit: false, qty: 1 }">
<section class="max-w-2xl mx-auto px-6 py-16">
  <p class="text-gold-400/80 tracking-[0.3em] uppercase text-xs">Reserve</p>
  <h1 class="font-serif text-4xl text-beige-100 mt-4"><?= e($event['title']) ?></h1>
  <p class="mt-2 text-beige-100/60"><?= e(format_datetime($event['starts_at'], 'l, d M Y · g:i A')) ?> · <?= e($event['location'] ?? 'Location TBA') ?></p>

  <?php foreach ($errors as $err): ?>
    <p class="mt-4 text-red-300/80"><?= e($err) ?></p>
  <?php endforeach; ?>

  <form id="bookForm" method="post" class="mt-10 space-y-6">
    <?= csrf_field() ?>

    <?php if ($creditBalance > 0 && $unitPrice > 0): ?>
      <label class="flex items-start gap-3 border border-gold-500/30 rounded-2xl p-5 bg-gold-500/5 cursor-pointer">
        <input type="checkbox" name="use_credit" value="1" x-model="useCredit" class="mt-1 accent-gold-500">
        <span>
          <span class="text-beige-100">Use 1 credit instead of paying</span>
          <span class="block text-xs text-beige-100/60 mt-1">You currently hold <strong class="text-gold-400"><?= (int)$creditBalance ?> credit<?= $creditBalance === 1 ? '' : 's' ?></strong>. One credit = one seat. <a href="<?= url('/member/my_credits.php') ?>" class="text-gold-400 hover:text-gold-300 underline-offset-4 hover:underline">View balance</a></span>
        </span>
      </label>
    <?php endif; ?>

    <div class="border border-white/5 rounded-3xl p-6 bg-navy-900/40 flex justify-between items-center">
      <div>
        <p class="text-beige-100">Per seat <?= $isMember ? '(member)' : '(public)' ?></p>
        <p class="font-serif text-2xl text-gold-400 mt-1" :class="useCredit ? 'line-through opacity-50' : ''"><?= e(format_money($unitPrice)) ?></p>
        <p class="text-xs text-gold-400 mt-1" x-show="useCredit" x-cloak>Paid with 1 credit</p>
      </div>
      <label class="flex items-center gap-3 text-sm" :class="useCredit ? 'opacity-50 pointer-events-none' : ''">
        <span>Seats</span>
        <select name="quantity" x-model.number="qty" class="rounded-full bg-navy-950 border border-white/5 px-4 py-2 focus:border-gold-500/50 focus:outline-none" :disabled="useCredit">
          <?php for ($i = 1; $i <= 6; $i++): ?><option><?= $i ?></option><?php endfor; ?>
        </select>
      </label>
    </div>

    <div class="hidden md:block">
      <button class="w-full px-6 py-4 rounded-full bg-gold-500 text-navy-950 font-medium hover:bg-gold-400 transition">
        <span x-show="!useCredit"><?= $unitPrice > 0 ? 'Continue to payment' : 'Confirm reservation' ?></span>
        <span x-show="useCredit" x-cloak>Redeem 1 credit · confirm reservation</span>
      </button>
    </div>

    <?php if ($creditBalance === 0 && $unitPrice > 0): ?>
      <p class="text-center text-xs text-beige-100/45">Want to save? <a href="<?= url('/member/checkout_pack.php') ?>" class="text-gold-400 hover:text-gold-300 underline-offset-4 hover:underline">Buy a class pack</a> and pay with credits next time.</p>
    <?php endif; ?>
  </form>
</section>

<!-- Mobile-only sticky reserve bar (inline so the qty/use-credit Alpine state stays in scope). -->
<div class="md:hidden fixed inset-x-0 z-40 bg-navy-950/95 backdrop-blur border-t border-white/10 px-4 py-3"
     style="bottom: calc(64px + env(safe-area-inset-bottom));">
  <p class="text-[11px] text-beige-100/55 text-center mb-2">
    <span x-show="useCredit" x-cloak>1 credit · 1 seat</span>
    <span x-show="!useCredit">
      <?= e(format_money($unitPrice)) ?> × <span x-text="qty"></span> seat<span x-show="qty > 1" x-cloak>s</span>
    </span>
  </p>
  <button type="submit" form="bookForm"
          class="w-full py-3.5 rounded-full bg-gold-500 text-navy-950 font-medium hover:bg-gold-400 transition">
    <span x-show="!useCredit"><?= $unitPrice > 0 ? 'Continue to payment' : 'Confirm reservation' ?></span>
    <span x-show="useCredit" x-cloak>Redeem 1 credit · Confirm</span>
  </button>
</div>
<div class="md:hidden h-24" aria-hidden="true"></div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
