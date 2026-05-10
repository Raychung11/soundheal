<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$pageTitle = 'About';
require __DIR__ . '/../includes/header.php';
?>
<section class="max-w-3xl mx-auto px-6 py-24">
  <p class="text-gold-400/80 tracking-[0.3em] uppercase text-xs">Our story</p>
  <h1 class="font-serif text-5xl text-beige-100 mt-4 leading-tight">A sanctuary built on<br><span class="italic text-gold-400">listening</span>.</h1>
  <div class="mt-12 space-y-6 text-beige-100/80 leading-relaxed">
    <p><?= e(config('app.name')) ?> began as a quiet practice between friends — gathering once a week with bowls and breath, holding space for the noise of the city to settle.</p>
    <p>Today we carry that same intention into a wider community: in-person sessions, a curated audio library, and an AI concierge designed to soften the path back to yourself.</p>
    <p>We are not a clinic. We are not a fad. We are a sanctuary — small enough to know your name, intentional enough to honour your stillness.</p>
  </div>
</section>

<section class="border-t border-white/5">
  <div class="max-w-5xl mx-auto px-6 py-20 grid md:grid-cols-3 gap-10">
    <?php foreach ([
      ['Listen', 'We begin every session by listening — to your breath, your body, the room.'],
      ['Hold',   'Held space is the work. The container is more important than the technique.'],
      ['Return', 'Wellness is not a destination. We help you return to yourself, gently, often.'],
    ] as [$h, $b]): ?>
      <div>
        <h3 class="font-serif text-3xl text-gold-400"><?= e($h) ?></h3>
        <p class="mt-3 text-beige-100/70 leading-relaxed"><?= e($b) ?></p>
      </div>
    <?php endforeach; ?>
  </div>
</section>

<?php require __DIR__ . '/../includes/footer.php'; ?>
