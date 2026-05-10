<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_login();
$pageTitle = 'Checkout';
$user = current_user();

$bookingId = (int) input('booking', 0);
$stmt = db()->prepare(
    "SELECT b.*, e.title, e.starts_at
     FROM event_bookings b
     JOIN events e ON e.id = b.event_id
     WHERE b.id = :id AND b.user_id = :u LIMIT 1"
);
$stmt->execute([':id' => $bookingId, ':u' => $user['id']]);
$booking = $stmt->fetch();
if (!$booking) {
    flash('checkout', 'We could not find that booking.', 'error');
    redirect('/member/my_bookings.php');
}

require __DIR__ . '/../includes/header.php';
?>
<section class="max-w-xl mx-auto px-6 py-16">
  <p class="text-gold-400/80 tracking-[0.3em] uppercase text-xs">Checkout</p>
  <h1 class="font-serif text-4xl text-beige-100 mt-4">A gentle confirmation</h1>

  <div class="mt-8 border border-white/5 rounded-3xl p-6 bg-navy-900/40">
    <p class="text-sm text-beige-100/60">Booking <?= e($booking['booking_ref']) ?></p>
    <p class="font-serif text-2xl text-beige-100 mt-1"><?= e($booking['title']) ?></p>
    <p class="text-sm text-beige-100/60 mt-1"><?= e(format_datetime($booking['starts_at'])) ?></p>
    <div class="mt-4 flex justify-between text-sm border-t border-white/5 pt-4">
      <span class="text-beige-100/60"><?= (int)$booking['quantity'] ?> × <?= e(format_money((float)$booking['unit_price'])) ?></span>
      <span class="text-gold-400 font-serif text-xl"><?= e(format_money((float)$booking['total_amount'])) ?></span>
    </div>
  </div>

  <form method="post" action="<?= url('/api/billplz_create.php') ?>" class="mt-8">
    <?= csrf_field() ?>
    <input type="hidden" name="booking_id" value="<?= (int)$booking['id'] ?>">
    <button class="w-full px-6 py-4 rounded-full bg-gold-500 text-navy-950 font-medium hover:bg-gold-400 transition">Pay securely with Billplz</button>
    <p class="text-xs text-center text-beige-100/40 mt-4">You will be redirected to Billplz to complete payment. Your seat is held for 30 minutes.</p>
  </form>
</section>
<?php require __DIR__ . '/../includes/footer.php'; ?>
