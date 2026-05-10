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
    if ($action === 'mark_attended') {
        db()->prepare("UPDATE event_bookings SET status='attended' WHERE id = :id")->execute([':id' => $bookingId]);
        audit_log('booking.mark_attended', 'event_bookings', $bookingId);
    } elseif ($action === 'cancel') {
        db()->beginTransaction();
        try {
            db()->prepare("UPDATE event_bookings SET status='cancelled', cancelled_at = NOW() WHERE id = :id")->execute([':id' => $bookingId]);
            db()->prepare("UPDATE tickets SET status='revoked' WHERE booking_id = :b")->execute([':b' => $bookingId]);
            db()->commit();
            audit_log('booking.cancel.admin', 'event_bookings', $bookingId);
        } catch (Throwable $e) { db()->rollBack(); }
    } elseif ($action === 'refund') {
        require_admin();
        db()->beginTransaction();
        try {
            db()->prepare("UPDATE event_bookings SET status='refunded', refunded_at = NOW() WHERE id = :id")->execute([':id' => $bookingId]);
            db()->prepare("UPDATE payments SET status='refunded' WHERE purpose='booking' AND reference_id = :id")->execute([':id' => $bookingId]);
            db()->prepare("UPDATE tickets SET status='revoked' WHERE booking_id = :b")->execute([':b' => $bookingId]);
            db()->commit();
            audit_log('booking.refund', 'event_bookings', $bookingId);
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
    "SELECT b.*, e.title AS event_title, e.starts_at, u.full_name, u.email
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
      <?php endforeach; ?>
      <?php if (!$bookings): ?>
        <tr><td colspan="8" class="px-4 py-6 text-beige-100/60">No bookings.</td></tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>
<?php require __DIR__ . '/../includes/admin_layout_end.php'; ?>
