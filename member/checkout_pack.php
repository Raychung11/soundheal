<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_login();
$pageTitle = 'Class packs';
$user = current_user();

$packs   = active_packs();
$balance = credit_balance_for((int) $user['id']);

// POST = start a Billplz purchase for a chosen pack.
if (is_post()) {
    csrf_verify();
    $packId = (int) input('pack_id', 0);
    $pack = null;
    foreach ($packs as $p) {
        if ((int) $p['id'] === $packId) { $pack = $p; break; }
    }
    if (!$pack) {
        flash('packs', 'Pack not available.', 'error');
        redirect('/member/checkout_pack.php');
    }

    $totalCredits = (int) $pack['paid_credits'] + (int) $pack['bonus_credits'];

    $snap = [
        'name'          => $pack['name'],
        'price'         => (float) $pack['price'],
        'paid_credits'  => (int) $pack['paid_credits'],
        'bonus_credits' => (int) $pack['bonus_credits'],
        'validity_days' => (int) $pack['validity_days'],
    ];

    $ins = db()->prepare(
        "INSERT INTO pack_purchases (user_id, pack_id, pack_snapshot, credits_granted, credits_remaining, status)
         VALUES (:u, :pid, :snap, :c, 0, 'pending')"
    );
    $ins->execute([
        ':u'    => (int) $user['id'],
        ':pid'  => (int) $pack['id'],
        ':snap' => json_encode($snap, JSON_UNESCAPED_UNICODE),
        ':c'    => $totalCredits,
    ]);
    $purchaseId = (int) db()->lastInsertId();

    // Issue the invoice now so the customer can see what they owe.
    issue_invoice(
        (int) $user['id'],
        'class_pack',
        $purchaseId,
        build_pack_line_items($pack, $totalCredits)
    );

    // Hand off to Billplz with purpose=class_pack.
    redirect('/api/billplz_create.php?purpose=class_pack&ref=' . $purchaseId);
}

require __DIR__ . '/../includes/header.php';
?>
<section class="max-w-5xl mx-auto px-6 py-16">
  <p class="text-gold-400/80 tracking-[0.3em] uppercase text-xs">Class packs</p>
  <h1 class="font-serif text-4xl text-beige-100 mt-3">Pay less, gather more</h1>
  <p class="mt-3 text-beige-100/70 max-w-2xl leading-relaxed">Bundle several offline classes into a single pack. Each credit holds one seat — redeem them at any published session within the pack's validity window.</p>

  <?php if ($balance > 0): ?>
    <div class="mt-6 inline-flex items-center gap-3 px-5 py-3 rounded-full border border-gold-500/30 bg-gold-500/5 text-sm">
      <span class="text-beige-100/70">You currently hold</span>
      <span class="font-serif text-gold-400 text-lg"><?= (int)$balance ?> credit<?= $balance === 1 ? '' : 's' ?></span>
      <a href="<?= url('/member/my_credits.php') ?>" class="text-gold-400 hover:text-gold-300 underline-offset-4 hover:underline">View balance</a>
    </div>
  <?php endif; ?>

  <?php if (!$packs): ?>
    <p class="mt-10 text-beige-100/60">No packs are available right now. Please check back soon.</p>
  <?php else: ?>
    <div class="mt-10 grid sm:grid-cols-2 lg:grid-cols-3 gap-6">
      <?php foreach ($packs as $pack): $total = (int)$pack['paid_credits'] + (int)$pack['bonus_credits']; ?>
        <article class="border border-white/5 rounded-3xl p-7 bg-navy-900/40 hover:border-gold-500/30 transition">
          <p class="text-[11px] uppercase tracking-[0.3em] text-gold-400/70"><?= e($pack['tagline'] ?? 'Class pack') ?></p>
          <h3 class="font-serif text-3xl text-beige-100 mt-3"><?= e($pack['name']) ?></h3>
          <p class="font-serif text-4xl text-gold-400 mt-5"><?= e(format_money((float)$pack['price'])) ?></p>

          <div class="mt-5 space-y-2 text-sm text-beige-100/75">
            <div class="flex items-start gap-2">
              <span class="text-gold-400 mt-0.5">✓</span>
              <span><?= (int)$pack['paid_credits'] ?> paid class<?= (int)$pack['paid_credits'] === 1 ? '' : 'es' ?></span>
            </div>
            <?php if ((int)$pack['bonus_credits'] > 0): ?>
              <div class="flex items-start gap-2">
                <span class="text-gold-400 mt-0.5">✓</span>
                <span><strong class="text-gold-400/90"><?= (int)$pack['bonus_credits'] ?> bonus</strong> — on us</span>
              </div>
            <?php endif; ?>
            <div class="flex items-start gap-2">
              <span class="text-gold-400 mt-0.5">✓</span>
              <span><?= $total ?> credits total · 1 credit per session</span>
            </div>
            <?php if ((int)$pack['validity_days'] > 0): ?>
              <div class="flex items-start gap-2">
                <span class="text-gold-400 mt-0.5">✓</span>
                <span><?= (int)$pack['validity_days'] ?>-day validity from purchase</span>
              </div>
            <?php else: ?>
              <div class="flex items-start gap-2">
                <span class="text-gold-400 mt-0.5">✓</span>
                <span>No expiry — use whenever you're ready</span>
              </div>
            <?php endif; ?>
          </div>

          <?php if (!empty($pack['description'])): ?>
            <p class="mt-5 text-sm text-beige-100/65 leading-relaxed"><?= e($pack['description']) ?></p>
          <?php endif; ?>

          <form method="post" class="mt-7">
            <?= csrf_field() ?>
            <input type="hidden" name="pack_id" value="<?= (int)$pack['id'] ?>">
            <button class="w-full px-6 py-3 rounded-full bg-gold-500 text-navy-950 font-medium hover:bg-gold-400 transition">Buy pack</button>
          </form>
        </article>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <p class="mt-10 text-xs text-beige-100/45 text-center">Pay securely with Billplz · MYR · all major Malaysian banks · FPX, e-wallets, and cards.</p>
</section>
<?php require __DIR__ . '/../includes/footer.php'; ?>
