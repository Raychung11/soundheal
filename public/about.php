<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$pageTitle = 'About';
require __DIR__ . '/../includes/header.php';
?>
<section class="relative overflow-hidden">
  <div class="absolute inset-0 bg-gradient-to-b from-navy-950 to-transparent"></div>
  <div class="relative max-w-3xl mx-auto px-6 py-24 md:py-32">
    <p class="text-gold-400/80 tracking-[0.4em] uppercase text-[11px]">Our story</p>
    <h1 class="font-serif text-5xl md:text-6xl text-beige-100 mt-6 leading-[1.05]">A sanctuary built on<br><span class="italic text-gold-400">listening</span>.</h1>
  </div>
</section>

<section class="max-w-3xl mx-auto px-6 pb-16">
  <div class="space-y-7 text-beige-100/80 leading-[1.95] font-light text-lg">
    <p><?= e(config('app.name')) ?> began as a quiet practice between friends — gathering once a week with bowls and breath, holding space for the noise of the city to settle.</p>
    <p>Today we carry that same intention into a wider community: in-person sessions, a curated audio library, and an AI concierge designed to soften the path back to yourself.</p>
    <p>We are not a clinic. We are not a fad. We are a sanctuary — small enough to know your name, intentional enough to honour your stillness.</p>
  </div>
</section>

<section class="border-t border-white/5 bg-navy-900/30">
  <div class="max-w-5xl mx-auto px-6 py-20 grid md:grid-cols-3 gap-12">
    <?php foreach ([
      ['Listen', 'We begin every session by listening — to your breath, your body, the room.'],
      ['Hold',   'Held space is the work. The container is more important than the technique.'],
      ['Return', 'Wellness is not a destination. We help you return to yourself, gently, often.'],
    ] as [$h, $b]): ?>
      <div>
        <p class="text-[11px] uppercase tracking-[0.3em] text-gold-400/60">Principle</p>
        <h3 class="font-serif text-3xl text-gold-400 mt-2"><?= e($h) ?></h3>
        <p class="mt-4 text-beige-100/70 leading-relaxed"><?= e($b) ?></p>
      </div>
    <?php endforeach; ?>
  </div>
</section>

<section class="max-w-3xl mx-auto px-6 py-24 text-center">
  <p class="text-gold-400/80 tracking-[0.3em] uppercase text-[11px]">Quietly, with care</p>
  <h2 class="font-serif text-4xl text-beige-100 mt-4">Founded in Kuala Lumpur, 2024</h2>
  <p class="mt-6 text-beige-100/70 leading-relaxed">By a small circle of practitioners and operators who believe wellness should be calm, premium, and within reach.</p>
</section>

<?php require __DIR__ . '/../includes/footer.php'; ?>
