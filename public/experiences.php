<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$pageTitle = 'Experiences';
$packs = active_packs();

$experiences = db()->query(
    "SELECT id, slug, title, duration, description, cover_image
       FROM experiences
      WHERE status = 'active'
      ORDER BY sort_order ASC, title ASC"
)->fetchAll();

// For each experience, look up the soonest bookable session linked to
// it (one-off in the future, OR a daily-recurring template). Drives the
// "Next session" line + the Reserve button target.
$nextStmt = db()->prepare(
    "SELECT id, title, starts_at, recurrence, capacity, ends_at
       FROM events
      WHERE status = 'published'
        AND (audience IS NULL OR audience = 'public')
        AND parent_event_id IS NULL
        AND experience_id = :xid
        AND (recurrence = 'daily' OR starts_at >= NOW())
      ORDER BY starts_at ASC LIMIT 1"
);
foreach ($experiences as $idx => $exp) {
    $nextStmt->execute([':xid' => (int) $exp['id']]);
    $experiences[$idx]['next_event'] = $nextStmt->fetch() ?: null;
}

require __DIR__ . '/../includes/header.php';
?>
<section class="relative overflow-hidden">
  <div class="absolute inset-0 bg-gradient-to-b from-navy-950 to-transparent"></div>
  <div class="relative max-w-6xl mx-auto px-6 py-24 md:py-32">
    <p class="text-gold-400/80 tracking-[0.4em] uppercase text-[11px]">Experiences</p>
    <h1 class="font-serif text-5xl md:text-6xl text-beige-100 mt-6 leading-tight">Curated for stillness</h1>
    <p class="mt-6 max-w-2xl text-beige-100/70 leading-[1.85] font-light">Each experience is held with intention. Choose what your body is asking for today, or let Aria — our wellness concierge — quietly suggest where to begin.</p>
  </div>
</section>

<section class="max-w-6xl mx-auto px-6 pb-24">
  <?php if (!$experiences): ?>
    <div class="border border-white/5 rounded-3xl p-10 bg-navy-900/40 text-center">
      <p class="text-beige-100/70">New offerings are taking shape. Please check back soon, or <a href="<?= url('/public/contact.php') ?>" class="text-gold-400 hover:text-gold-300">say hello</a>.</p>
    </div>
  <?php else: ?>
    <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-6">
      <?php foreach ($experiences as $exp): ?>
        <article class="group relative border border-white/5 rounded-3xl bg-navy-900/40 hover:border-gold-500/30 hover:bg-navy-900/70 transition overflow-hidden flex flex-col">
          <?php if (!empty($exp['cover_image'])): ?>
            <div class="relative aspect-[16/10] overflow-hidden">
              <img src="<?= e(str_starts_with((string)$exp['cover_image'], '/') ? url($exp['cover_image']) : $exp['cover_image']) ?>"
                   alt="<?= e($exp['title']) ?>"
                   loading="lazy"
                   class="w-full h-full object-cover transition duration-700 group-hover:scale-[1.03]">
              <div class="absolute inset-0 bg-gradient-to-t from-navy-950/85 via-navy-950/20 to-transparent"></div>
            </div>
          <?php endif; ?>
          <div class="relative p-8 flex-1 flex flex-col">
            <?php if (empty($exp['cover_image'])): ?>
              <div class="absolute -right-16 -top-16 w-48 h-48 rounded-full border border-gold-500/10 group-hover:border-gold-500/20 transition"></div>
            <?php endif; ?>
            <?php if (!empty($exp['duration'])): ?>
              <p class="text-[11px] uppercase tracking-[0.3em] text-gold-400/70"><?= e($exp['duration']) ?></p>
            <?php endif; ?>
            <h3 class="font-serif text-3xl text-gold-400 mt-3"><?= e($exp['title']) ?></h3>
            <?php if (!empty($exp['description'])): ?>
              <div class="mt-5 text-beige-100/70 text-sm space-y-3">
                <?= render_rich_text((string) $exp['description']) ?>
              </div>
            <?php endif; ?>

            <?php
              $nxt = $exp['next_event'] ?? null;
              $reserveUrl = '/public/events.php?experience=' . urlencode((string) $exp['slug']);
              $nextLine = '';
              if ($nxt) {
                  if (($nxt['recurrence'] ?? 'none') === 'daily') {
                      $nextLine = 'Daily at ' . date('g:i A', strtotime((string) $nxt['starts_at']));
                  } else {
                      $nextLine = format_datetime($nxt['starts_at'], 'D, d M Y · g:i A');
                  }
              }
            ?>

            <div class="mt-8 pt-5 border-t border-white/5 flex items-center justify-between gap-3">
              <div class="min-w-0">
                <?php if ($nextLine !== ''): ?>
                  <p class="text-[10px] uppercase tracking-[0.25em] text-beige-100/45">Next session</p>
                  <p class="text-sm text-gold-400 mt-0.5 truncate"><?= e($nextLine) ?></p>
                <?php else: ?>
                  <p class="text-xs text-beige-100/50 italic">New dates coming soon.</p>
                <?php endif; ?>
              </div>
              <?php if ($nxt): ?>
                <a href="<?= url($reserveUrl) ?>"
                   class="shrink-0 inline-flex items-center gap-2 px-4 py-2 rounded-full bg-gold-500 text-navy-950 text-sm font-medium hover:bg-gold-400 transition">
                  Reserve →
                </a>
              <?php else: ?>
                <a href="<?= url('/public/contact.php') ?>"
                   class="shrink-0 inline-flex items-center gap-2 px-4 py-2 rounded-full border border-gold-500/40 text-gold-400 text-sm hover:bg-gold-500/10 transition">
                  Notify me
                </a>
              <?php endif; ?>
            </div>
          </div>
        </article>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</section>

<?php if ($packs): ?>
<section class="border-t border-white/5 bg-navy-950/40">
  <div class="max-w-5xl mx-auto px-6 py-24">
    <div class="text-center">
      <p class="text-gold-400/80 tracking-[0.3em] uppercase text-[11px]">Pricing</p>
      <h2 class="font-serif text-4xl text-beige-100 mt-4">Single class or a gentle bundle</h2>
      <p class="mt-4 text-beige-100/65 max-w-2xl mx-auto">Drop in for a single session, or pre-purchase a pack and save. Credits are redeemable across all our published offline sessions.</p>
    </div>

    <div class="mt-12 grid md:grid-cols-<?= count($packs) >= 2 ? '2' : '1' ?> gap-6 max-w-4xl mx-auto">
      <?php foreach ($packs as $pack): $total = (int)$pack['paid_credits'] + (int)$pack['bonus_credits']; ?>
        <article class="border border-gold-500/30 rounded-3xl p-8 bg-navy-900/50">
          <p class="text-[11px] uppercase tracking-[0.3em] text-gold-400/70"><?= e($pack['tagline'] ?? 'Class pack') ?></p>
          <h3 class="font-serif text-3xl text-beige-100 mt-3"><?= e($pack['name']) ?></h3>
          <p class="font-serif text-5xl text-gold-400 mt-5"><?= e(format_money((float)$pack['price'])) ?></p>
          <ul class="mt-6 space-y-2 text-sm text-beige-100/75">
            <li class="flex items-start gap-2"><span class="text-gold-400 mt-0.5">✓</span> <?= (int)$pack['paid_credits'] ?> paid class<?= (int)$pack['paid_credits'] === 1 ? '' : 'es' ?></li>
            <?php if ((int)$pack['bonus_credits'] > 0): ?>
              <li class="flex items-start gap-2"><span class="text-gold-400 mt-0.5">✓</span> <strong class="text-gold-400/90"><?= (int)$pack['bonus_credits'] ?> bonus class<?= (int)$pack['bonus_credits'] === 1 ? '' : 'es' ?></strong> — on us</li>
            <?php endif; ?>
            <li class="flex items-start gap-2"><span class="text-gold-400 mt-0.5">✓</span> <?= $total ?> credits total · 1 per session</li>
            <?php if ((int)$pack['validity_days'] > 0): ?>
              <li class="flex items-start gap-2"><span class="text-gold-400 mt-0.5">✓</span> <?= (int)$pack['validity_days'] ?>-day validity</li>
            <?php endif; ?>
          </ul>
          <a href="<?= url(is_logged_in() ? '/member/checkout_pack.php' : '/public/register.php') ?>"
             class="mt-8 inline-block px-6 py-3 rounded-full bg-gold-500 text-navy-950 font-medium hover:bg-gold-400 transition">
            <?= is_logged_in() ? 'Buy pack' : 'Sign up to buy' ?>
          </a>
        </article>
      <?php endforeach; ?>
    </div>
    <p class="mt-8 text-xs text-beige-100/45 text-center">Single classes can also be booked at the per-class rate from any event page.</p>
  </div>
</section>
<?php endif; ?>

<section class="border-t border-white/5">
  <div class="max-w-3xl mx-auto px-6 py-24 text-center">
    <p class="text-gold-400/80 tracking-[0.3em] uppercase text-[11px]">Not sure where to begin?</p>
    <h2 class="font-serif text-4xl text-beige-100 mt-4">Let Aria suggest gently</h2>
    <p class="mt-6 text-beige-100/70 leading-relaxed">Tell our concierge how you're arriving today. She'll quietly recommend the experience that meets you where you are.</p>
    <a href="<?= url('/public/register.php') ?>" class="inline-block mt-10 px-8 py-4 rounded-full bg-gold-500 text-navy-950 font-medium hover:bg-gold-400 transition">Talk with Aria</a>
  </div>
</section>

<?php require __DIR__ . '/../includes/footer.php'; ?>
