<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$pageTitle = 'Experiences';
require __DIR__ . '/../includes/header.php';

$experiences = [
    ['Sound Bath',         '75 min',  'A 75-minute immersion in crystal bowls and gongs. Lie down. Let the frequencies do the work.'],
    ['Breathwork Journey', '60 min',  'Guided conscious breathing to release stored tension and arrive in the body.'],
    ['Moon Circle',        '90 min',  'Monthly women’s circle held in candlelight — sound, journaling, and gentle ceremony.'],
    ['Couples Tuning',     '60 min',  'A private session for two — synchronised sound and breath to soften connection.'],
    ['Corporate Reset',    '45 min',  'On-site sound healing for teams. 45 minutes to lower the room and lift the focus.'],
    ['1:1 Concierge',      '90 min',  'A bespoke private session crafted around your current emotional landscape.'],
];
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
  <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-6">
    <?php foreach ($experiences as $i => [$title, $duration, $body]): ?>
      <article class="group relative border border-white/5 rounded-3xl p-8 bg-navy-900/40 hover:border-gold-500/30 hover:bg-navy-900/70 transition overflow-hidden">
        <div class="absolute -right-16 -top-16 w-48 h-48 rounded-full border border-gold-500/10 group-hover:border-gold-500/20 transition"></div>
        <p class="text-[11px] uppercase tracking-[0.3em] text-gold-400/70"><?= e($duration) ?></p>
        <h3 class="font-serif text-3xl text-gold-400 mt-3"><?= e($title) ?></h3>
        <p class="mt-5 text-beige-100/70 leading-relaxed text-sm"><?= e($body) ?></p>
        <a href="<?= url('/public/events.php') ?>" class="mt-8 inline-flex items-center gap-2 text-sm text-gold-400 hover:text-gold-300">
          Reserve →
        </a>
      </article>
    <?php endforeach; ?>
  </div>
</section>

<section class="border-t border-white/5">
  <div class="max-w-3xl mx-auto px-6 py-24 text-center">
    <p class="text-gold-400/80 tracking-[0.3em] uppercase text-[11px]">Not sure where to begin?</p>
    <h2 class="font-serif text-4xl text-beige-100 mt-4">Let Aria suggest gently</h2>
    <p class="mt-6 text-beige-100/70 leading-relaxed">Tell our concierge how you're arriving today. She'll quietly recommend the experience that meets you where you are.</p>
    <a href="<?= url('/public/register.php') ?>" class="inline-block mt-10 px-8 py-4 rounded-full bg-gold-500 text-navy-950 font-medium hover:bg-gold-400 transition">Talk with Aria</a>
  </div>
</section>

<?php require __DIR__ . '/../includes/footer.php'; ?>
