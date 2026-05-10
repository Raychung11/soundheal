<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_login();
$pageTitle = 'My Tickets';
$user = current_user();

$bookingId = (int) input('booking', 0);
$tickets = [];
if ($bookingId) {
    $stmt = db()->prepare(
        "SELECT t.*, e.title, e.starts_at, e.location, b.booking_ref
         FROM tickets t
         JOIN event_bookings b ON b.id = t.booking_id
         JOIN events e ON e.id = b.event_id
         WHERE t.booking_id = :bid AND b.user_id = :u"
    );
    $stmt->execute([':bid' => $bookingId, ':u' => $user['id']]);
    $tickets = $stmt->fetchAll();
}

require __DIR__ . '/../includes/header.php';
?>
<section class="max-w-3xl mx-auto px-6 py-16">
  <h1 class="font-serif text-4xl text-beige-100">My tickets</h1>
  <p class="mt-2 text-beige-100/60">Show this QR code at the door.</p>

  <?php if (!$tickets): ?>
    <p class="mt-12 text-beige-100/60">No tickets to show. <a class="text-gold-400" href="<?= url('/member/my_bookings.php') ?>">Back to bookings</a></p>
  <?php else: ?>
    <div class="mt-10 space-y-6">
      <?php foreach ($tickets as $t):
        $qrUrl = 'https://api.qrserver.com/v1/create-qr-code/?size=240x240&margin=10&data=' . urlencode(url('/api/qr_validate.php?token=' . $t['qr_token']));
      ?>
        <div class="border border-white/5 rounded-3xl p-8 bg-navy-900/40 grid md:grid-cols-[1fr_auto] gap-8 items-center">
          <div>
            <p class="text-xs uppercase tracking-widest text-gold-400/80"><?= e(format_datetime($t['starts_at'], 'D, d M Y · g:i A')) ?></p>
            <h2 class="font-serif text-2xl text-beige-100 mt-1"><?= e($t['title']) ?></h2>
            <p class="text-sm text-beige-100/50 mt-2"><?= e($t['location'] ?? 'Location TBA') ?></p>
            <p class="text-xs text-beige-100/40 mt-4">Ticket: <?= e($t['ticket_code']) ?></p>
            <p class="text-xs text-beige-100/40">Status: <span class="text-gold-400"><?= e($t['status']) ?></span></p>
          </div>
          <div class="bg-beige-100 p-3 rounded-2xl">
            <img src="<?= e($qrUrl) ?>" alt="Ticket QR code" width="200" height="200">
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</section>
<?php require __DIR__ . '/../includes/footer.php'; ?>
