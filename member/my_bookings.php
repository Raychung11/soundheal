<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_login();
$pageTitle = 'My Bookings';
$user = current_user();

if (is_post()) {
    csrf_verify();
    if (input('action') === 'cancel') {
        $bookingId = (int) input('booking_id', 0);
        $row = db()->prepare(
            "SELECT b.id, b.status, b.user_id, b.paid_with_credit, e.starts_at
             FROM event_bookings b
             JOIN events e ON e.id = b.event_id
             WHERE b.id = :id LIMIT 1"
        );
        $row->execute([':id' => $bookingId]);
        $booking = $row->fetch();
        if (!$booking || (int) $booking['user_id'] !== $user['id']) {
            flash('booking', 'Booking not found.', 'error');
        } elseif (!in_array($booking['status'], ['pending','paid'], true)) {
            flash('booking', 'This booking can no longer be cancelled.', 'error');
        } elseif (strtotime($booking['starts_at']) - time() < 12 * 3600) {
            flash('booking', 'Cancellations close 12 hours before the session begins.', 'error');
        } else {
            db()->prepare("UPDATE event_bookings SET status = 'cancelled', cancelled_at = NOW() WHERE id = :id")
                ->execute([':id' => $bookingId]);
            db()->prepare("UPDATE tickets SET status = 'revoked' WHERE booking_id = :b")
                ->execute([':b' => $bookingId]);
            audit_log('booking.cancel', 'event_bookings', $bookingId);
            if (!empty($booking['paid_with_credit']) && function_exists('refund_credit_for_booking')) {
                refund_credit_for_booking($user['id'], $bookingId);
                flash('booking', 'Your booking has been cancelled and your credit has been returned.', 'success');
            } else {
                flash('booking', 'Your booking has been cancelled. If you had paid, refund will follow within 7 days.', 'success');
            }
            if (function_exists('notify_next_waitlist_for_booking')) {
                notify_next_waitlist_for_booking($bookingId);
            }
        }
        redirect('/member/my_bookings.php');
    }
}

$stmt = db()->prepare(
    "SELECT b.*, e.title, e.starts_at, e.location,
            (SELECT COUNT(*) FROM tickets t WHERE t.booking_id = b.id AND t.status = 'valid') AS ticket_count,
            (SELECT id FROM invoices WHERE doc_type='receipt' AND purpose='booking' AND reference_id = b.id ORDER BY id DESC LIMIT 1) AS receipt_id,
            (SELECT access_token FROM invoices WHERE doc_type='receipt' AND purpose='booking' AND reference_id = b.id ORDER BY id DESC LIMIT 1) AS receipt_token,
            (SELECT id FROM invoices WHERE doc_type='invoice' AND purpose='booking' AND reference_id = b.id ORDER BY id DESC LIMIT 1) AS invoice_id,
            (SELECT access_token FROM invoices WHERE doc_type='invoice' AND purpose='booking' AND reference_id = b.id ORDER BY id DESC LIMIT 1) AS invoice_token
     FROM event_bookings b
     JOIN events e ON e.id = b.event_id
     WHERE b.user_id = :u
     ORDER BY e.starts_at DESC"
);
$stmt->execute([':u' => $user['id']]);
$bookings = $stmt->fetchAll();

require __DIR__ . '/../includes/header.php';
?>
<section class="max-w-5xl mx-auto px-6 py-16">
  <h1 class="font-serif text-4xl text-beige-100">My bookings</h1>
  <p class="mt-2 text-beige-100/60">Your sessions, tickets and history.</p>

  <?php if (!$bookings): ?>
    <div class="mt-12 border border-white/5 rounded-3xl p-12 text-center bg-navy-900/40">
      <p class="text-beige-100/70">You haven't reserved a session yet.</p>
      <a href="<?= url('/public/events.php') ?>" class="inline-block mt-6 px-6 py-3 rounded-full bg-gold-500 text-navy-950 hover:bg-gold-400 transition">Browse sessions</a>
    </div>
  <?php else: ?>
    <div class="mt-10 space-y-4">
      <?php foreach ($bookings as $b): ?>
        <div class="border border-white/5 rounded-3xl p-6 bg-navy-900/40 flex flex-col md:flex-row md:items-center gap-4 md:justify-between">
          <div>
            <p class="text-xs uppercase tracking-widest text-gold-400/80"><?= e(format_datetime($b['starts_at'], 'D, d M Y · g:i A')) ?></p>
            <p class="font-serif text-2xl text-beige-100 mt-1"><?= e($b['title']) ?></p>
            <p class="text-sm text-beige-100/50 mt-1">Ref: <?= e($b['booking_ref']) ?> · <?= e($b['location'] ?? 'Location TBA') ?></p>
          </div>
          <div class="flex items-center gap-4 flex-wrap">
            <span class="text-xs px-3 py-1 rounded-full <?= $b['status'] === 'paid' ? 'bg-gold-500/20 text-gold-400' : 'bg-white/5 text-beige-100/60' ?>"><?= e($b['status']) ?></span>
            <?php if ((int)$b['ticket_count'] > 0): ?>
              <a href="<?= url('/member/my_tickets.php?booking=' . (int)$b['id']) ?>" class="text-sm text-gold-400 hover:text-gold-300">View ticket →</a>
            <?php endif; ?>
            <?php if (!empty($b['receipt_id'])): ?>
              <a href="<?= url('/member/document.php?id=' . (int) $b['receipt_id'] . '&t=' . urlencode((string) $b['receipt_token'])) ?>" class="text-xs text-gold-400/80 hover:text-gold-300">Receipt</a>
            <?php elseif (!empty($b['invoice_id'])): ?>
              <a href="<?= url('/member/document.php?id=' . (int) $b['invoice_id'] . '&t=' . urlencode((string) $b['invoice_token'])) ?>" class="text-xs text-gold-400/80 hover:text-gold-300">Invoice</a>
            <?php endif; ?>
            <?php if (in_array($b['status'], ['pending','paid'], true) && strtotime($b['starts_at']) - time() > 12 * 3600): ?>
              <form method="post" class="inline" onsubmit="return confirm('Cancel this booking?');">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="cancel">
                <input type="hidden" name="booking_id" value="<?= (int)$b['id'] ?>">
                <button class="text-xs text-beige-100/50 hover:text-red-300/80">Cancel</button>
              </form>
            <?php endif; ?>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</section>
<?php require __DIR__ . '/../includes/footer.php'; ?>
