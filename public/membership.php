<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$pageTitle = 'Membership';

$plans = db()->query(
    "SELECT * FROM membership_plans WHERE is_active = 1 ORDER BY sort_order ASC, price ASC"
)->fetchAll();

require __DIR__ . '/../includes/header.php';
?>
<section class="max-w-6xl mx-auto px-6 py-24">
  <p class="text-gold-400/80 tracking-[0.3em] uppercase text-xs">Membership</p>
  <h1 class="font-serif text-5xl text-beige-100 mt-4">Belong to the sanctuary</h1>
  <p class="mt-6 max-w-2xl text-beige-100/70 leading-relaxed">A membership is a quiet commitment to your wellbeing. Member pricing on every session, access to the audio library, and the soft hand of our wellness concierge.</p>

  <?php if (!$plans): ?>
    <p class="mt-12 italic text-beige-100/60">Membership plans will be available soon.</p>
  <?php else: ?>
    <div class="mt-16 grid md:grid-cols-3 gap-8">
      <?php foreach ($plans as $i => $plan):
        $perks = $plan['perks_json'] ? json_decode($plan['perks_json'], true) : [
            'Member pricing on all sessions',
            'Access to the audio sanctuary',
            'Priority booking',
        ];
        $featured = $i === 1;
      ?>
        <article class="rounded-3xl p-8 border <?= $featured ? 'border-gold-500/50 bg-navy-900/80 shadow-[0_0_60px_-20px_rgba(201,164,106,0.4)]' : 'border-white/5 bg-navy-900/40' ?>">
          <?php if ($featured): ?><p class="text-xs uppercase tracking-[0.3em] text-gold-400">Most chosen</p><?php endif; ?>
          <h3 class="font-serif text-3xl text-beige-100 mt-2"><?= e($plan['name']) ?></h3>
          <p class="text-sm text-beige-100/60 mt-2"><?= e($plan['description']) ?></p>
          <div class="mt-6 flex items-baseline gap-2">
            <span class="font-serif text-4xl text-gold-400"><?= e(format_money((float)$plan['price'])) ?></span>
            <span class="text-sm text-beige-100/50">/ <?= e($plan['billing_cycle']) ?></span>
          </div>
          <ul class="mt-6 space-y-3 text-sm text-beige-100/80">
            <?php foreach ($perks as $perk): ?>
              <li class="flex gap-3"><span class="text-gold-400">◦</span><span><?= e((string)$perk) ?></span></li>
            <?php endforeach; ?>
          </ul>
          <a href="<?= url('/member/subscribe.php?plan=' . (int)$plan['id']) ?>" class="block mt-8 text-center px-5 py-3 rounded-full <?= $featured ? 'bg-gold-500 text-navy-950 hover:bg-gold-400' : 'border border-gold-500/40 text-gold-400 hover:bg-gold-500/10' ?> transition">Choose <?= e($plan['name']) ?></a>
        </article>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</section>
<?php require __DIR__ . '/../includes/footer.php'; ?>
