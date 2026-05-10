<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$pageTitle = 'Upcoming Sessions';

$events = db()->query(
    "SELECT e.*,
            (SELECT COALESCE(SUM(quantity), 0)
               FROM event_bookings b
              WHERE b.event_id = e.id
                AND b.status IN ('paid','attended')) AS seats_taken
     FROM events e
     WHERE e.status = 'published' AND e.starts_at >= NOW()
     ORDER BY e.starts_at ASC"
)->fetchAll();

require __DIR__ . '/../includes/header.php';
?>
<section class="max-w-6xl mx-auto px-6 py-24">
  <p class="text-gold-400/80 tracking-[0.3em] uppercase text-xs">Calendar</p>
  <h1 class="font-serif text-5xl text-beige-100 mt-4">Upcoming sessions</h1>
  <p class="mt-6 max-w-2xl text-beige-100/70 leading-relaxed">Reserve your seat. Members enjoy quiet pricing and priority booking on every session.</p>

  <?php if (!$events): ?>
    <div class="mt-16 border border-white/5 rounded-3xl p-12 text-center bg-navy-900/40">
      <p class="font-serif text-2xl text-beige-100/80">New sessions are being woven into the calendar.</p>
      <p class="mt-3 text-beige-100/60">Sign up to be notified when the next one opens.</p>
      <a href="<?= url('/public/register.php') ?>" class="inline-block mt-8 px-6 py-3 rounded-full bg-gold-500 text-navy-950 hover:bg-gold-400 transition">Notify me</a>
    </div>
  <?php else: ?>
    <div class="mt-16 space-y-6">
      <?php foreach ($events as $event):
        $remaining = max(0, (int)$event['capacity'] - (int)$event['seats_taken']);
        $soldOut = $remaining <= 0;
      ?>
        <article id="event-<?= (int)$event['id'] ?>" class="grid md:grid-cols-3 gap-6 border border-white/5 rounded-3xl p-6 bg-navy-900/40">
          <div class="md:col-span-2">
            <p class="text-xs uppercase tracking-[0.3em] text-gold-400/80"><?= e(format_datetime($event['starts_at'], 'l, d M Y · g:i A')) ?></p>
            <h2 class="font-serif text-3xl text-beige-100 mt-2"><?= e($event['title']) ?></h2>
            <?php if (!empty($event['subtitle'])): ?>
              <p class="text-beige-100/70 mt-2"><?= e($event['subtitle']) ?></p>
            <?php endif; ?>
            <?php if (!empty($event['description'])): ?>
              <p class="text-sm text-beige-100/60 mt-4 leading-relaxed"><?= nl2br(e($event['description'])) ?></p>
            <?php endif; ?>
            <div class="mt-4 flex flex-wrap gap-x-6 gap-y-2 text-sm text-beige-100/60">
              <?php if (!empty($event['location'])): ?><span>📍 <?= e($event['location']) ?></span><?php endif; ?>
              <?php if (!empty($event['facilitator'])): ?><span>Facilitated by <?= e($event['facilitator']) ?></span><?php endif; ?>
              <span><?= $remaining ?> of <?= (int)$event['capacity'] ?> seats remaining</span>
            </div>
          </div>
          <div class="flex flex-col justify-between border border-white/5 rounded-2xl p-5 bg-navy-950/60">
            <div>
              <p class="text-xs uppercase tracking-widest text-beige-100/50">Public</p>
              <p class="font-serif text-2xl text-beige-100"><?= e(format_money((float)$event['price_public'])) ?></p>
              <p class="text-xs uppercase tracking-widest text-gold-400/80 mt-3">Member</p>
              <p class="font-serif text-2xl text-gold-400"><?= e(format_money((float)$event['price_member'])) ?></p>
            </div>
            <?php if ($soldOut): ?>
              <button class="mt-5 px-5 py-3 rounded-full bg-navy-800 text-beige-100/50 cursor-not-allowed" disabled>Fully held</button>
            <?php else: ?>
              <a href="<?= url('/member/book_event.php?event_id=' . (int)$event['id']) ?>" class="mt-5 text-center px-5 py-3 rounded-full bg-gold-500 text-navy-950 font-medium hover:bg-gold-400 transition">Reserve</a>
            <?php endif; ?>
          </div>
        </article>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</section>
<?php require __DIR__ . '/../includes/footer.php'; ?>
