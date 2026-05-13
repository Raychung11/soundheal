<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_login();
$pageTitle = 'My credits';
$user = current_user();
$uid = (int) $user['id'];

$balance = credit_balance_for($uid);

$purchases = db()->prepare(
    "SELECT * FROM pack_purchases
      WHERE user_id = :u
      ORDER BY id DESC"
);
$purchases->execute([':u' => $uid]);
$purchases = $purchases->fetchAll();

$history = db()->prepare(
    "SELECT mc.*, pp.pack_snapshot,
            eb.booking_ref, ev.title AS event_title, ev.starts_at AS event_starts_at
       FROM member_credits mc
       LEFT JOIN pack_purchases pp ON pp.id = mc.purchase_id
       LEFT JOIN event_bookings eb ON eb.id = mc.booking_id
       LEFT JOIN events ev ON ev.id = eb.event_id
      WHERE mc.user_id = :u
      ORDER BY mc.id DESC
      LIMIT 50"
);
$history->execute([':u' => $uid]);
$history = $history->fetchAll();

require __DIR__ . '/../includes/header.php';
?>
<section class="max-w-4xl mx-auto px-6 py-16">
  <p class="text-gold-400/80 tracking-[0.3em] uppercase text-xs">Your credits</p>
  <h1 class="font-serif text-4xl text-beige-100 mt-3">Balance &amp; history</h1>

  <div class="mt-8 border border-gold-500/30 rounded-3xl p-8 bg-gradient-to-br from-navy-900/60 to-navy-950 flex items-center justify-between gap-6 flex-wrap">
    <div>
      <p class="text-xs uppercase tracking-[0.3em] text-gold-400/80">Available</p>
      <p class="font-serif text-6xl text-gold-400 mt-2"><?= (int)$balance ?></p>
      <p class="text-beige-100/65 text-sm mt-1">credit<?= $balance === 1 ? '' : 's' ?> · each is worth one offline session seat</p>
    </div>
    <div class="flex gap-3 flex-wrap">
      <a href="<?= url('/member/checkout_pack.php') ?>" class="px-5 py-3 rounded-full border border-gold-500/40 text-gold-400 hover:bg-gold-500/10 transition text-sm">Buy more</a>
      <a href="<?= url('/public/events.php') ?>" class="px-5 py-3 rounded-full bg-gold-500 text-navy-950 hover:bg-gold-400 transition text-sm">Book a session</a>
    </div>
  </div>

  <h2 class="font-serif text-2xl text-gold-400 mt-12">Active packs</h2>
  <?php
    $activePurchases = array_values(array_filter($purchases, fn($p) => $p['status'] === 'active' && (int)$p['credits_remaining'] > 0));
  ?>
  <?php if (!$activePurchases): ?>
    <p class="mt-3 text-sm text-beige-100/55">No active packs. <a href="<?= url('/member/checkout_pack.php') ?>" class="text-gold-400 hover:text-gold-300">Buy a pack →</a></p>
  <?php else: ?>
    <div class="mt-4 grid sm:grid-cols-2 gap-4">
      <?php foreach ($activePurchases as $pp): $snap = json_decode((string)$pp['pack_snapshot'], true) ?: []; ?>
        <div class="border border-white/5 rounded-2xl p-5 bg-navy-900/40">
          <p class="text-beige-100"><?= e($snap['name'] ?? 'Class pack') ?></p>
          <p class="font-serif text-3xl text-gold-400 mt-2"><?= (int)$pp['credits_remaining'] ?> / <?= (int)$pp['credits_granted'] ?></p>
          <p class="text-[11px] text-beige-100/45 mt-2">
            <?php if ($pp['expires_at']): ?>
              Expires <?= e(format_datetime($pp['expires_at'], 'd M Y')) ?>
            <?php else: ?>
              No expiry
            <?php endif; ?>
          </p>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <?php if ($history): ?>
    <h2 class="font-serif text-2xl text-gold-400 mt-12">Recent activity</h2>
    <div class="mt-4 border border-white/5 rounded-2xl bg-navy-900/40 overflow-hidden">
      <table class="w-full text-sm">
        <thead class="text-left text-beige-100/50 text-xs uppercase tracking-wider bg-navy-950/50">
          <tr>
            <th class="py-3 px-4">When</th>
            <th>Detail</th>
            <th class="text-right pr-4">Change</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-white/5">
          <?php foreach ($history as $h):
            $snap = json_decode((string)($h['pack_snapshot'] ?? '{}'), true) ?: [];
            $label = match ($h['reason']) {
                'pack_purchase'   => 'Purchased: ' . ($snap['name'] ?? 'pack'),
                'booking_redeem'  => 'Booked: ' . ($h['event_title'] ?? ('Booking ' . ($h['booking_ref'] ?? ''))),
                'booking_cancel'  => 'Cancelled: ' . ($h['event_title'] ?? ('Booking ' . ($h['booking_ref'] ?? ''))),
                'expiry'          => 'Expired: ' . ($snap['name'] ?? 'pack'),
                'admin_adjust'    => 'Adjustment',
                default           => (string) $h['reason'],
            };
            $change = (int) $h['credits_change'];
          ?>
            <tr>
              <td class="py-3 px-4 text-beige-100/70 whitespace-nowrap"><?= e(format_datetime($h['created_at'])) ?></td>
              <td class="text-beige-100/85"><?= e($label) ?></td>
              <td class="text-right pr-4 font-serif <?= $change >= 0 ? 'text-gold-400' : 'text-beige-100/75' ?>">
                <?= $change >= 0 ? '+' . $change : $change ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</section>
<?php require __DIR__ . '/../includes/footer.php'; ?>
