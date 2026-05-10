<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$pageTitle = 'Experiences';
require __DIR__ . '/../includes/header.php';

$experiences = [
    ['Sound Bath', 'A 75-minute immersion in crystal bowls and gongs. Lie down. Let the frequencies do the work.'],
    ['Breathwork Journey', 'Guided conscious breathing to release stored tension and arrive in the body.'],
    ['Moon Circle', 'Monthly women’s circle held in candlelight — sound, journaling, and gentle ceremony.'],
    ['Couples Tuning', 'A private session for two — synchronised sound and breath to soften connection.'],
    ['Corporate Reset', 'On-site sound healing for teams. 45 minutes to lower the room and lift the focus.'],
    ['1:1 Concierge', 'A bespoke private session crafted around your current emotional landscape.'],
];
?>
<section class="max-w-6xl mx-auto px-6 py-24">
  <p class="text-gold-400/80 tracking-[0.3em] uppercase text-xs">Experiences</p>
  <h1 class="font-serif text-5xl text-beige-100 mt-4">Curated for stillness</h1>
  <p class="mt-6 max-w-2xl text-beige-100/70 leading-relaxed">Each experience is held with intention. Choose what your body is asking for today, or let Aria — our wellness concierge — quietly suggest where to begin.</p>

  <div class="mt-16 grid md:grid-cols-2 lg:grid-cols-3 gap-6">
    <?php foreach ($experiences as [$title, $body]): ?>
      <article class="border border-white/5 rounded-3xl p-8 bg-navy-900/40 hover:border-gold-500/30 transition">
        <h3 class="font-serif text-2xl text-gold-400"><?= e($title) ?></h3>
        <p class="mt-4 text-beige-100/70 leading-relaxed text-sm"><?= e($body) ?></p>
      </article>
    <?php endforeach; ?>
  </div>
</section>
<?php require __DIR__ . '/../includes/footer.php'; ?>
