<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_staff_or_admin();
$pageTitle = 'Bookings';

if (is_post()) {
    csrf_verify();
    $action = (string) input('action');
    $bookingId = (int) input('booking_id', 0);
    if (!$bookingId) {
        flash('booking', 'Missing booking.', 'error');
        redirect('/admin/bookings.php');
    }
    if ($action === 'mark_paid') {
        // Manual rescue when the Billplz webhook didn't reach us. Settle
        // through the existing path so tickets are issued + confirmation
        // email goes out, exactly like a real webhook would.
        db()->beginTransaction();
        try {
            // Promote any linked pending payment row.
            $p = db()->prepare(
                "SELECT id FROM payments WHERE purpose='booking' AND reference_id=:id ORDER BY id DESC LIMIT 1"
            );
            $p->execute([':id' => $bookingId]);
            $paymentId = (int) ($p->fetchColumn() ?: 0);

            if ($paymentId > 0) {
                db()->prepare(
                    "UPDATE payments SET status='paid', paid_at = COALESCE(paid_at, NOW()) WHERE id=:id"
                )->execute([':id' => $paymentId]);
                db()->commit();
                if (function_exists('settle_payment')) settle_payment($paymentId);
            } else {
                // No payment row at all (e.g. demo booking) — flip booking
                // and issue tickets directly.
                db()->prepare("UPDATE event_bookings SET status='paid' WHERE id=:id")->execute([':id' => $bookingId]);
                $b = db()->prepare("SELECT booking_ref, quantity FROM event_bookings WHERE id=:id");
                $b->execute([':id' => $bookingId]);
                $booking = $b->fetch();
                $check = db()->prepare("SELECT COUNT(*) FROM tickets WHERE booking_id=:b");
                $check->execute([':b' => $bookingId]);
                if ($booking && (int) $check->fetchColumn() === 0) {
                    $t = db()->prepare(
                        "INSERT INTO tickets (booking_id, ticket_code, qr_token) VALUES (:b, :c, :tok)"
                    );
                    for ($i = 0; $i < (int) $booking['quantity']; $i++) {
                        $t->execute([
                            ':b'   => $bookingId,
                            ':c'   => $booking['booking_ref'] . '-' . ($i + 1),
                            ':tok' => generate_token(24),
                        ]);
                    }
                }
                db()->commit();
            }
            audit_log('booking.manual_mark_paid', 'event_bookings', $bookingId, ['payment_id' => $paymentId]);
        } catch (Throwable $e) {
            db()->rollBack();
            error_log('[admin/bookings] mark_paid failed: ' . $e->getMessage());
        }
    } elseif ($action === 'mark_attended') {
        db()->prepare("UPDATE event_bookings SET status='attended' WHERE id = :id")->execute([':id' => $bookingId]);
        audit_log('booking.mark_attended', 'event_bookings', $bookingId);
        if (function_exists('earn_referral_reward')) {
            earn_referral_reward($bookingId);
        }
    } elseif ($action === 'cancel') {
        $bk = db()->prepare("SELECT user_id, paid_with_credit FROM event_bookings WHERE id = :id LIMIT 1");
        $bk->execute([':id' => $bookingId]);
        $bk = $bk->fetch();
        db()->beginTransaction();
        try {
            db()->prepare("UPDATE event_bookings SET status='cancelled', cancelled_at = NOW() WHERE id = :id")->execute([':id' => $bookingId]);
            db()->prepare("UPDATE tickets SET status='revoked' WHERE booking_id = :b")->execute([':b' => $bookingId]);
            db()->commit();
            audit_log('booking.cancel.admin', 'event_bookings', $bookingId);
            if ($bk && !empty($bk['paid_with_credit']) && function_exists('refund_credit_for_booking')) {
                refund_credit_for_booking((int) $bk['user_id'], $bookingId);
            }
            if (function_exists('notify_next_waitlist_for_booking')) {
                notify_next_waitlist_for_booking($bookingId);
            }
        } catch (Throwable $e) { db()->rollBack(); }
    } elseif ($action === 'refund') {
        require_admin();
        $bk = db()->prepare("SELECT user_id, paid_with_credit FROM event_bookings WHERE id = :id LIMIT 1");
        $bk->execute([':id' => $bookingId]);
        $bk = $bk->fetch();
        db()->beginTransaction();
        try {
            db()->prepare("UPDATE event_bookings SET status='refunded', refunded_at = NOW() WHERE id = :id")->execute([':id' => $bookingId]);
            db()->prepare("UPDATE payments SET status='refunded' WHERE purpose='booking' AND reference_id = :id")->execute([':id' => $bookingId]);
            db()->prepare("UPDATE tickets SET status='revoked' WHERE booking_id = :b")->execute([':b' => $bookingId]);
            // Reverse the revenue split for any payment on this booking.
            if (function_exists('reverse_revenue_split')) {
                $rp = db()->prepare("SELECT id FROM payments WHERE purpose='booking' AND reference_id = :id");
                $rp->execute([':id' => $bookingId]);
                foreach ($rp->fetchAll() as $payRow) {
                    reverse_revenue_split((int) $payRow['id'], 'Booking #' . $bookingId . ' refunded');
                }
            }
            // Reverse the referral reward too so the referrer isn't paid
            // for a session that got refunded.
            if (function_exists('reverse_referral_reward')) {
                reverse_referral_reward($bookingId, 'Booking #' . $bookingId . ' refunded');
            }
            db()->commit();
            audit_log('booking.refund', 'event_bookings', $bookingId);
            if (function_exists('notify_next_waitlist_for_booking')) {
                notify_next_waitlist_for_booking($bookingId);
            }
            if ($bk && !empty($bk['paid_with_credit']) && function_exists('refund_credit_for_booking')) {
                refund_credit_for_booking((int) $bk['user_id'], $bookingId);
            }
        } catch (Throwable $e) { db()->rollBack(); }
    }
    flash('booking', 'Updated.', 'success');
    redirect('/admin/bookings.php');
}

$status = input('status');
$where = '';
$params = [];
if (in_array($status, ['pending','paid','cancelled','refunded','attended','no_show'], true)) {
    $where = 'WHERE b.status = :status';
    $params[':status'] = $status;
}

$stmt = db()->prepare(
    "SELECT b.*, e.title AS event_title, e.starts_at, e.intake_type, u.full_name, u.email
     FROM event_bookings b
     JOIN events e ON e.id = b.event_id
     JOIN users u ON u.id = b.user_id
     {$where}
     ORDER BY b.created_at DESC LIMIT 200"
);
$stmt->execute($params);
$bookings = $stmt->fetchAll();

require __DIR__ . '/../includes/admin_layout.php';
?>
<h1 class="font-serif text-3xl text-beige-100">Bookings</h1>

<form class="mt-4 flex gap-3 items-center text-sm">
  <span class="text-beige-100/60">Filter:</span>
  <?php foreach (['', 'pending','paid','attended','cancelled','refunded'] as $opt): ?>
    <a href="<?= url('/admin/bookings.php' . ($opt ? '?status=' . $opt : '')) ?>"
       class="px-3 py-1 rounded-full <?= ($status ?? '') === $opt ? 'bg-gold-500 text-navy-950' : 'border border-white/10 text-beige-100/70' ?>">
      <?= $opt === '' ? 'All' : e($opt) ?>
    </a>
  <?php endforeach; ?>
</form>

<div class="mt-6 border border-white/5 rounded-2xl bg-navy-900/40 overflow-x-auto">
  <table class="w-full text-sm">
    <thead class="text-left text-beige-100/50 text-xs uppercase tracking-wider bg-navy-950/40">
      <tr><th class="px-4 py-3">Ref</th><th>Member</th><th>Session</th><th>When</th><th>Qty</th><th>Total</th><th>Status</th><th></th></tr>
    </thead>
    <tbody class="divide-y divide-white/5">
      <?php foreach ($bookings as $b): ?>
        <tr>
          <td class="px-4 py-3"><?= e($b['booking_ref']) ?></td>
          <td><?= e($b['full_name']) ?><br><span class="text-xs text-beige-100/40"><?= e($b['email']) ?></span></td>
          <td><?= e($b['event_title']) ?></td>
          <td><?= e(format_datetime($b['starts_at'])) ?></td>
          <td><?= (int)$b['quantity'] ?></td>
          <td><?= e(format_money((float)$b['total_amount'])) ?></td>
          <td><span class="text-xs px-2 py-1 rounded-full <?= $b['status'] === 'paid' ? 'bg-gold-500/20 text-gold-400' : 'bg-white/5 text-beige-100/60' ?>"><?= e($b['status']) ?></span></td>
          <td class="text-right pr-4 space-x-2 whitespace-nowrap">
            <?php if ($b['status'] === 'pending'): ?>
              <form method="post" class="inline" onsubmit="return confirm('Mark this booking as paid? Use only if the Billplz webhook didn\'t reach us — tickets will be issued and a confirmation email sent.');">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="mark_paid">
                <input type="hidden" name="booking_id" value="<?= (int)$b['id'] ?>">
                <button class="text-xs text-gold-400 hover:text-gold-300">Mark paid</button>
              </form>
            <?php endif; ?>
            <?php if (in_array($b['status'], ['pending','paid'], true)): ?>
              <form method="post" class="inline">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="mark_attended">
                <input type="hidden" name="booking_id" value="<?= (int)$b['id'] ?>">
                <button class="text-xs text-gold-400">Mark attended</button>
              </form>
              <form method="post" class="inline" onsubmit="return confirm('Cancel?');">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="cancel">
                <input type="hidden" name="booking_id" value="<?= (int)$b['id'] ?>">
                <button class="text-xs text-beige-100/50 hover:text-red-300/80">Cancel</button>
              </form>
            <?php endif; ?>
            <?php if ($b['status'] === 'paid' && has_role('admin')): ?>
              <form method="post" class="inline" onsubmit="return confirm('Mark as refunded?');">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="refund">
                <input type="hidden" name="booking_id" value="<?= (int)$b['id'] ?>">
                <button class="text-xs text-beige-100/50 hover:text-red-300/80">Refund</button>
              </form>
            <?php endif; ?>
          </td>
        </tr>
        <?php
          $intake = $b['intake_data'] ? (json_decode((string) $b['intake_data'], true) ?: null) : null;
          if ($intake && (!empty($intake['pawrent']) || !empty($intake['pets']))):
        ?>
          <tr class="bg-navy-950/30">
            <td colspan="8" class="px-4 py-3">
              <details class="text-xs">
                <summary class="cursor-pointer text-gold-400/90 hover:text-gold-300 inline-flex items-center gap-2">
                  <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M11 5a3 3 0 1 1 0 6 3 3 0 0 1 0-6Z"/><path d="M17 7a2 2 0 1 1 0 4 2 2 0 0 1 0-4ZM5 8a2 2 0 1 1 0 4 2 2 0 0 1 0-4ZM4 16a2 2 0 1 1 0 4 2 2 0 0 1 0-4ZM20 17a2 2 0 1 1 0 4 2 2 0 0 1 0-4Z"/><path d="M14 14c2 0 4 2 4 4l-2 2H8l-2-2c0-2 2-4 4-4Z"/></svg>
                  Pet intake · <?= count($intake['pets'] ?? []) ?> pet<?= count($intake['pets'] ?? []) === 1 ? '' : 's' ?>
                  <?php if ($b['package']): ?><span class="text-beige-100/40">· <?= e((string) $b['package']) ?></span><?php endif; ?>
                </summary>
                <div class="mt-3 grid gap-3 sm:grid-cols-2 text-beige-100/80">
                  <?php if (!empty($intake['pawrent'])): ?>
                    <div class="border border-white/5 rounded-lg p-3 bg-navy-900/40">
                      <p class="text-[10px] uppercase tracking-widest text-gold-400/70 mb-1.5">Pawrent</p>
                      <p><?= e((string) ($intake['pawrent']['name'] ?? '—')) ?></p>
                      <p class="text-beige-100/70 mt-0.5"><?= e((string) ($intake['pawrent']['mobile'] ?? '—')) ?></p>
                      <p class="text-beige-100/70"><?= e((string) ($intake['pawrent']['email'] ?? '—')) ?></p>
                    </div>
                  <?php endif; ?>
                  <?php foreach ($intake['pets'] ?? [] as $pi => $pet): ?>
                    <div class="border border-white/5 rounded-lg p-3 bg-navy-900/40">
                      <p class="text-[10px] uppercase tracking-widest text-gold-400/70 mb-1.5">Pet <?= $pi + 1 ?></p>
                      <p class="font-medium"><?= e((string) ($pet['name'] ?? '—')) ?>
                        <?php if (!empty($pet['breed'])): ?><span class="text-beige-100/60"> · <?= e((string) $pet['breed']) ?></span><?php endif; ?>
                        <?php if (!empty($pet['age'])): ?><span class="text-beige-100/60"> · <?= e((string) $pet['age']) ?></span><?php endif; ?>
                      </p>
                      <?php $n = (string) ($pet['neutered'] ?? '');
                        $nLabel = $n === 'yes' ? 'Neutered/Spayed' : ($n === 'no' ? 'Not neutered' : ($n === 'na' ? 'Not disclosed' : ''));
                      ?>
                      <?php if ($nLabel !== ''): ?><p class="text-beige-100/70 mt-0.5"><?= e($nLabel) ?></p><?php endif; ?>
                      <?php if (!empty($pet['character'])): ?>
                        <p class="text-beige-100/70 mt-0.5">Character: <?= e(implode(', ', (array) $pet['character'])) ?></p>
                      <?php endif; ?>
                      <?php if (!empty($pet['medical'])): ?>
                        <p class="text-beige-100/70 mt-1.5"><span class="text-beige-100/50">Medical:</span> <?= e((string) $pet['medical']) ?></p>
                      <?php endif; ?>
                    </div>
                  <?php endforeach; ?>
                </div>
              </details>
            </td>
          </tr>
        <?php endif; ?>
      <?php endforeach; ?>
      <?php if (!$bookings): ?>
        <tr><td colspan="8" class="px-4 py-6 text-beige-100/60">No bookings.</td></tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>
<?php require __DIR__ . '/../includes/admin_layout_end.php'; ?>
