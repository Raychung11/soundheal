<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$pageTitle = 'Sound Healing & Wellness Sanctuary';
$pageDescription = 'A calm, premium sound healing sanctuary. Sessions, breathwork, meditation and a curated audio library — for the modern soul.';

$upcoming = db()->query(
    "SELECT id, slug, title, subtitle, starts_at, location, cover_image
     FROM events
     WHERE status = 'published' AND starts_at >= NOW()
     ORDER BY starts_at ASC
     LIMIT 3"
)->fetchAll();

$testimonials = db()->query(
    "SELECT author_name, author_title, quote, rating
     FROM testimonials
     WHERE is_published = 1
     ORDER BY sort_order ASC, created_at DESC
     LIMIT 3"
)->fetchAll();

require __DIR__ . '/../includes/header.php';
?>

<section class="relative overflow-hidden">
  <div class="absolute inset-0 bg-gradient-to-b from-navy-950 via-navy-900 to-navy-950"></div>
  <div class="absolute inset-0 opacity-30 bg-[radial-gradient(ellipse_at_top,_var(--tw-gradient-stops))] from-gold-500/30 via-transparent to-transparent"></div>
  <div class="relative max-w-5xl mx-auto px-6 py-28 md:py-40 text-center">
    <p class="text-gold-400/80 tracking-[0.4em] uppercase text-xs">A sanctuary for stillness</p>
    <h1 class="font-serif text-5xl md:text-7xl text-beige-100 mt-6 leading-tight">
      Return to the<br><span class="text-gold-400 italic">sound</span> of yourself.
    </h1>
    <p class="mt-8 text-lg md:text-xl text-beige-100/80 max-w-2xl mx-auto leading-relaxed">
      Curated sound healing sessions, breathwork journeys and a quiet audio sanctuary —
      held with intention, designed for the modern soul.
    </p>
    <div class="mt-12 flex flex-col sm:flex-row gap-4 justify-center">
      <a href="<?= url('/public/events.php') ?>" class="px-8 py-4 rounded-full bg-gold-500 text-navy-950 font-medium hover:bg-gold-400 transition">Reserve a session</a>
      <a href="<?= url('/public/membership.php') ?>" class="px-8 py-4 rounded-full border border-gold-500/50 text-gold-400 hover:bg-gold-500/10 transition">Become a member</a>
    </div>
  </div>
</section>

<section class="max-w-6xl mx-auto px-6 py-24">
  <div class="grid md:grid-cols-3 gap-10">
    <?php
    $pillars = [
      ['Sound', 'Crystal bowls, gongs and tuning forks tuned to the frequencies your nervous system listens for.'],
      ['Breath', 'Guided breathwork to soften the chest, slow the heart and arrive in the present moment.'],
      ['Stillness', 'A held container — minimal, cinematic, and quiet enough to hear yourself again.'],
    ];
    foreach ($pillars as [$title, $body]): ?>
      <div class="border border-white/5 rounded-3xl p-8 bg-navy-900/40 hover:bg-navy-900/70 transition">
        <h3 class="font-serif text-3xl text-gold-400"><?= e($title) ?></h3>
        <p class="mt-4 text-beige-100/70 leading-relaxed"><?= e($body) ?></p>
      </div>
    <?php endforeach; ?>
  </div>
</section>

<section class="bg-navy-900/40 border-y border-white/5">
  <div class="max-w-6xl mx-auto px-6 py-20">
    <div class="flex items-end justify-between mb-10 flex-wrap gap-4">
      <div>
        <p class="text-gold-400/80 tracking-[0.3em] uppercase text-xs">Upcoming</p>
        <h2 class="font-serif text-4xl text-beige-100 mt-2">Sessions held with intention</h2>
      </div>
      <a href="<?= url('/public/events.php') ?>" class="text-gold-400 hover:text-gold-300 text-sm">View all sessions →</a>
    </div>

    <?php if (!$upcoming): ?>
      <p class="text-beige-100/60 italic">New sessions are being woven into the calendar. Check back shortly.</p>
    <?php else: ?>
      <div class="grid md:grid-cols-3 gap-8">
        <?php foreach ($upcoming as $event): ?>
          <a href="<?= url('/public/events.php#event-' . (int)$event['id']) ?>" class="group block rounded-3xl overflow-hidden border border-white/5 bg-navy-950 hover:border-gold-500/40 transition">
            <div class="aspect-[4/3] bg-gradient-to-br from-navy-800 to-navy-900 flex items-center justify-center">
              <?php if (!empty($event['cover_image'])): ?>
                <img src="<?= e($event['cover_image']) ?>" alt="<?= e($event['title']) ?>" class="object-cover w-full h-full">
              <?php else: ?>
                <span class="font-serif text-5xl text-gold-400/30">◯</span>
              <?php endif; ?>
            </div>
            <div class="p-6">
              <p class="text-xs uppercase tracking-[0.3em] text-gold-400/70"><?= e(format_datetime($event['starts_at'], 'D, d M · g:i A')) ?></p>
              <h3 class="font-serif text-2xl mt-2 text-beige-100 group-hover:text-gold-400 transition"><?= e($event['title']) ?></h3>
              <?php if (!empty($event['subtitle'])): ?>
                <p class="text-sm text-beige-100/60 mt-2"><?= e($event['subtitle']) ?></p>
              <?php endif; ?>
              <?php if (!empty($event['location'])): ?>
                <p class="text-xs text-beige-100/50 mt-4"><?= e($event['location']) ?></p>
              <?php endif; ?>
            </div>
          </a>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</section>

<?php if ($testimonials): ?>
<section class="max-w-5xl mx-auto px-6 py-24">
  <p class="text-gold-400/80 tracking-[0.3em] uppercase text-xs text-center">Voices of the sanctuary</p>
  <div class="mt-12 grid md:grid-cols-3 gap-8">
    <?php foreach ($testimonials as $t): ?>
      <figure class="border border-white/5 rounded-3xl p-8 bg-navy-900/40">
        <blockquote class="font-serif text-xl text-beige-100/90 leading-relaxed">“<?= e($t['quote']) ?>”</blockquote>
        <figcaption class="mt-6 text-sm text-gold-400"><?= e($t['author_name']) ?><?php if (!empty($t['author_title'])): ?> <span class="text-beige-100/50">· <?= e($t['author_title']) ?></span><?php endif; ?></figcaption>
      </figure>
    <?php endforeach; ?>
  </div>
</section>
<?php endif; ?>

<section class="border-t border-white/5">
  <div class="max-w-3xl mx-auto px-6 py-24 text-center">
    <p class="text-gold-400/80 tracking-[0.3em] uppercase text-xs">Begin gently</p>
    <h2 class="font-serif text-4xl md:text-5xl text-beige-100 mt-4">Meet Aria, your wellness concierge</h2>
    <p class="mt-6 text-beige-100/70 leading-relaxed">A soft, AI-guided companion who listens to where you are today and quietly suggests what might soothe. Available the moment you sign in.</p>
    <a href="<?= url('/public/register.php') ?>" class="inline-block mt-10 px-8 py-4 rounded-full bg-gold-500 text-navy-950 font-medium hover:bg-gold-400 transition">Create your sanctuary</a>
  </div>
</section>

<?php require __DIR__ . '/../includes/footer.php'; ?>
