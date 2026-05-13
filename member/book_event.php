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

$errors = [];

if (is_post()) {
    csrf_verify();
    $qty = max(1, min(6, (int) input('quantity', 1)));

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
        $total = $unitPrice * $qty;

        $ins = db()->prepare(
            "INSERT INTO event_bookings (booking_ref, user_id, event_id, quantity, unit_price, total_amount, status)
             VALUES (:ref, :u, :e, :q, :up, :tot, :status)"
        );
        $ins->execute([
            ':ref' => $bookingRef,
            ':u'   => $user['id'],
            ':e'   => $eventId,
            ':q'   => $qty,
            ':up'  => $unitPrice,
            ':tot' => $total,
            ':status' => $unitPrice > 0 ? 'pending' : 'paid',
        ]);
        $bookingId = (int) db()->lastInsertId();

        // For free sessions, immediately issue tickets.
        if ($unitPrice <= 0) {
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

        // Issue an invoice immediately so the customer can see what's owed
        // (or, for free bookings, an immediate receipt is issued below).
        $invoiceId = issue_invoice(
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

        if ($unitPrice <= 0) {
            send_mail($user['email'], $user['full_name'], 'Your SoundHeal seat is held', 'booking_confirm', [
                'event_title' => $event['title'],
                'starts_at'   => format_datetime($event['starts_at']),
                'location'    => $event['location'] ?? 'Location TBA',
                'booking_ref' => $bookingRef,
            ]);
            flash('booking', 'Your seat is held. We can\'t wait to welcome you.', 'success');
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
<section class="max-w-2xl mx-auto px-6 py-16">
  <p class="text-gold-400/80 tracking-[0.3em] uppercase text-xs">Reserve</p>
  <h1 class="font-serif text-4xl text-beige-100 mt-4"><?= e($event['title']) ?></h1>
  <p class="mt-2 text-beige-100/60"><?= e(format_datetime($event['starts_at'], 'l, d M Y · g:i A')) ?> · <?= e($event['location'] ?? 'Location TBA') ?></p>

  <?php foreach ($errors as $err): ?>
    <p class="mt-4 text-red-300/80"><?= e($err) ?></p>
  <?php endforeach; ?>

  <form method="post" class="mt-10 space-y-6">
    <?= csrf_field() ?>
    <div class="border border-white/5 rounded-3xl p-6 bg-navy-900/40 flex justify-between items-center">
      <div>
        <p class="text-beige-100">Per seat <?= $isMember ? '(member)' : '(public)' ?></p>
        <p class="font-serif text-2xl text-gold-400 mt-1"><?= e(format_money($unitPrice)) ?></p>
      </div>
      <label class="flex items-center gap-3 text-sm">
        <span>Seats</span>
        <select name="quantity" class="rounded-full bg-navy-950 border border-white/5 px-4 py-2 focus:border-gold-500/50 focus:outline-none">
          <?php for ($i = 1; $i <= 6; $i++): ?><option><?= $i ?></option><?php endfor; ?>
        </select>
      </label>
    </div>

    <button class="w-full px-6 py-4 rounded-full bg-gold-500 text-navy-950 font-medium hover:bg-gold-400 transition">
      <?= $unitPrice > 0 ? 'Continue to payment' : 'Confirm reservation' ?>
    </button>
  </form>
</section>
<?php require __DIR__ . '/../includes/footer.php'; ?>
