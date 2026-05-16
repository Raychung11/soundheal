<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_login();
$pageTitle = 'Payment received';
$user = current_user();

// Billplz appends these on its redirect: ?billplz[id]=...&billplz[paid]=true&billplz[paid_at]=...
$billplz   = $_GET['billplz'] ?? [];
$billId    = is_array($billplz) ? (string)($billplz['id']   ?? '') : '';
$paidParam = is_array($billplz) ? (string)($billplz['paid'] ?? '') : '';
$paidAt    = is_array($billplz) ? (string)($billplz['paid_at'] ?? '') : '';

$payment = null;
if ($billId !== '') {
    $stmt = db()->prepare(
        "SELECT p.*, u.full_name, u.email
         FROM payments p
         LEFT JOIN users u ON u.id = p.user_id
         WHERE p.gateway_bill_id = :b LIMIT 1"
    );
    $stmt->execute([':b' => $billId]);
    $payment = $stmt->fetch() ?: null;
}

// NEVER trust Billplz's redirect param — anyone can hit this URL with
// ?billplz[paid]=true. If the local record is still pending (webhook hasn't
// fired yet) we re-confirm the bill server-side, straight from Billplz with
// the secret key, verifying both the paid state and the amount before we
// settle. The webhook will also fire; settle_payment() is idempotent.
if ($payment && $payment['status'] === 'pending' && $paidParam === 'true'
    && function_exists('billplz_verify_paid')
    && billplz_verify_paid((string) $payment['gateway_bill_id'], (float) $payment['amount'])
) {
    db()->prepare("UPDATE payments SET status = 'paid', paid_at = COALESCE(:p, NOW()) WHERE id = :id")
        ->execute([':p' => $paidAt ?: null, ':id' => $payment['id']]);

    // settle_payment() is loaded via bootstrap (includes/payments.php).
    if (function_exists('settle_payment')) {
        settle_payment((int) $payment['id']);
    }

    // Re-fetch with the new state + linked record details.
    $stmt = db()->prepare(
        "SELECT p.*, u.full_name, u.email
         FROM payments p
         LEFT JOIN users u ON u.id = p.user_id
         WHERE p.id = :id LIMIT 1"
    );
    $stmt->execute([':id' => $payment['id']]);
    $payment = $stmt->fetch() ?: $payment;
}

// Pull reference details for the booking or membership.
$ref = null;
if ($payment) {
    if ($payment['purpose'] === 'booking') {
        $r = db()->prepare(
            "SELECT b.booking_ref, b.quantity, b.total_amount,
                    e.title, e.starts_at, e.location,
                    (SELECT COUNT(*) FROM tickets t WHERE t.booking_id = b.id) AS ticket_count
             FROM event_bookings b
             JOIN events e ON e.id = b.event_id
             WHERE b.id = :id LIMIT 1"
        );
        $r->execute([':id' => $payment['reference_id']]);
        $ref = $r->fetch() ?: null;
    } elseif ($payment['purpose'] === 'membership') {
        $r = db()->prepare(
            "SELECT m.status AS m_status, m.expires_at, p.name AS plan_name, p.billing_cycle
             FROM memberships m
             JOIN membership_plans p ON p.id = m.plan_id
             WHERE m.id = :id LIMIT 1"
        );
        $r->execute([':id' => $payment['reference_id']]);
        $ref = $r->fetch() ?: null;
    }
}

$state = 'unknown';
if ($payment) {
    $state = $payment['status']; // pending | paid | failed | refunded | cancelled
} elseif ($billId !== '') {
    $state = 'unrecognised';
}

require __DIR__ . '/../includes/header.php';
?>

<section class="max-w-2xl mx-auto px-6 py-20 text-center">
  <?php if ($state === 'paid'): ?>
    <p class="text-gold-400/80 tracking-[0.4em] uppercase text-[11px]">Payment received</p>
    <h1 class="font-serif text-5xl text-beige-100 mt-4 leading-tight">Held with thanks.</h1>
    <p class="mt-5 text-beige-100/75 leading-relaxed">Your payment has been confirmed. Take a slow breath in… and out.</p>

    <?php
    $rcStmt = db()->prepare(
        "SELECT id, doc_number, access_token FROM invoices
         WHERE doc_type='receipt' AND payment_id = :pid LIMIT 1"
    );
    $rcStmt->execute([':pid' => (int) $payment['id']]);
    $receipt = $rcStmt->fetch();
    ?>
    <?php if ($receipt): ?>
      <a href="<?= url('/member/document.php?id=' . (int) $receipt['id'] . '&t=' . urlencode((string) $receipt['access_token'])) ?>"
         class="mt-6 inline-flex items-center gap-2 px-5 py-2 rounded-full border border-gold-500/40 text-gold-400 text-sm hover:bg-gold-500/10 transition">
        View receipt · <?= e($receipt['doc_number']) ?>
      </a>
    <?php endif; ?>

    <div class="mt-10 border border-gold-500/30 rounded-3xl p-6 bg-navy-900/60 text-left">
      <?php if ($ref && $payment['purpose'] === 'booking'): ?>
        <p class="text-[11px] uppercase tracking-[0.3em] text-gold-400/80">Your booking</p>
        <h2 class="font-serif text-2xl text-beige-100 mt-2"><?= e($ref['title']) ?></h2>
        <p class="text-sm text-beige-100/70 mt-1"><?= e(format_datetime($ref['starts_at'])) ?></p>
        <?php if (!empty($ref['location'])): ?>
          <p class="text-sm text-beige-100/55 mt-1"><?= e($ref['location']) ?></p>
        <?php endif; ?>
        <div class="mt-4 flex flex-wrap justify-between gap-3 text-sm">
          <span class="text-beige-100/60">Ref · <span class="font-mono text-beige-100"><?= e($ref['booking_ref']) ?></span></span>
          <span class="text-beige-100/60"><?= (int) $ref['quantity'] ?> × <?= e(format_money((float)$payment['amount'], (string)$payment['currency'])) ?></span>
        </div>
        <?php if ((int) $ref['ticket_count'] > 0): ?>
          <a href="<?= url('/member/my_tickets.php?booking=' . (int) $payment['reference_id']) ?>"
             class="mt-6 inline-block px-5 py-3 rounded-full bg-gold-500 text-navy-950 text-sm font-medium hover:bg-gold-400 transition">View your ticket →</a>
        <?php endif; ?>
      <?php elseif ($ref && $payment['purpose'] === 'membership'): ?>
        <p class="text-[11px] uppercase tracking-[0.3em] text-gold-400/80">Your membership</p>
        <h2 class="font-serif text-2xl text-beige-100 mt-2"><?= e($ref['plan_name']) ?></h2>
        <p class="text-sm text-beige-100/70 mt-1 capitalize"><?= e($ref['billing_cycle']) ?> · renews <?= e(format_datetime($ref['expires_at'], 'd M Y')) ?></p>
        <a href="<?= url('/member/my_membership.php') ?>"
           class="mt-6 inline-block px-5 py-3 rounded-full bg-gold-500 text-navy-950 text-sm font-medium hover:bg-gold-400 transition">Manage membership →</a>
      <?php else: ?>
        <p class="text-sm text-beige-100/70">Reference: <span class="font-mono"><?= e($billId) ?></span></p>
      <?php endif; ?>
    </div>

  <?php elseif ($state === 'pending'): ?>
    <p class="text-gold-400/80 tracking-[0.4em] uppercase text-[11px]">Almost there</p>
    <h1 class="font-serif text-5xl text-beige-100 mt-4">Confirming your payment.</h1>
    <p class="mt-5 text-beige-100/75 leading-relaxed">We've handed the payment to Billplz and are waiting for the final confirmation. This usually takes a few seconds — refresh this page in a moment, or check <a class="text-gold-400 underline-offset-2 hover:text-gold-300" href="<?= url('/member/my_bookings.php') ?>">My Bookings</a>.</p>
    <button onclick="location.reload()" class="mt-8 px-5 py-3 rounded-full border border-gold-500/40 text-gold-400 hover:bg-gold-500/10 transition">Refresh</button>

  <?php elseif ($state === 'failed' || $state === 'cancelled'): ?>
    <p class="text-red-300/80 tracking-[0.4em] uppercase text-[11px]">Not completed</p>
    <h1 class="font-serif text-5xl text-beige-100 mt-4">Payment didn't complete.</h1>
    <p class="mt-5 text-beige-100/75 leading-relaxed">No charge was made. You can try again from your bookings or membership page.</p>
    <div class="mt-8 flex flex-wrap justify-center gap-3">
      <a href="<?= url('/member/my_bookings.php') ?>"   class="px-5 py-3 rounded-full bg-gold-500 text-navy-950 hover:bg-gold-400 transition text-sm">Try again from bookings</a>
      <a href="<?= url('/public/contact.php') ?>"        class="px-5 py-3 rounded-full border border-white/10 text-beige-100/80 hover:border-gold-500/40 hover:text-gold-400 transition text-sm">Contact us</a>
    </div>

  <?php elseif ($state === 'refunded'): ?>
    <p class="text-gold-400/80 tracking-[0.4em] uppercase text-[11px]">Refunded</p>
    <h1 class="font-serif text-5xl text-beige-100 mt-4">This payment has been refunded.</h1>
    <p class="mt-5 text-beige-100/75">Allow up to 7 business days for the amount to reach your account.</p>

  <?php else: ?>
    <p class="text-gold-400/80 tracking-[0.4em] uppercase text-[11px]">Thank you</p>
    <h1 class="font-serif text-5xl text-beige-100 mt-4">We've received your visit.</h1>
    <p class="mt-5 text-beige-100/75 leading-relaxed">We couldn't match your session to a specific payment. If you've just completed a transaction, head over to <a class="text-gold-400 hover:text-gold-300" href="<?= url('/member/my_bookings.php') ?>">My Bookings</a> or <a class="text-gold-400 hover:text-gold-300" href="<?= url('/member/my_membership.php') ?>">My Membership</a> to check the latest status.</p>
  <?php endif; ?>

  <p class="mt-12 text-xs text-beige-100/40 italic">A receipt will be emailed to <?= e($user['email']) ?>.</p>
</section>

<?php require __DIR__ . '/../includes/footer.php'; ?>
