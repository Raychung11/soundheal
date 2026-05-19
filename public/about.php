<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$pageTitle = 'About';

$heroEyebrow  = (string) setting('about_hero_eyebrow', 'Our story');
$heroHeadline = (string) setting('about_hero_headline', 'A sanctuary built on listening.');
$heroImage    = media_src((string) setting('about_hero_image_path', ''));

$storyParas = (string) setting('about_story_paragraphs', <<<'TXT'
Our practice began as a quiet gathering between friends — meeting once a week with bowls and breath, holding space for the noise of the city to settle.

Today we carry that same intention into a wider community: in-person sessions, a curated audio library, and an AI concierge designed to soften the path back to yourself.

We are not a clinic. We are not a fad. We are a sanctuary — small enough to know your name, intentional enough to honour your stillness.
TXT
);
$storyParagraphs = array_values(array_filter(array_map('trim', preg_split('/\n\s*\n/', $storyParas))));
$storyImage      = media_src((string) setting('about_story_image_path', ''));

$guideEyebrow = (string) setting('about_guide_eyebrow', 'Your guide');
$guideName    = (string) setting('about_guide_name', '');
$guideRole    = (string) setting('about_guide_role', '');
$guideBio     = (string) setting('about_guide_bio', '');
$guideImage   = media_src((string) setting('about_guide_image_path', ''));
$guideParas   = array_values(array_filter(array_map('trim', preg_split('/\n\s*\n/', $guideBio))));
$showGuide    = $guideName !== '' || $guideBio !== '' || $guideImage !== '';

$principles = [];
for ($i = 1; $i <= 3; $i++) {
    $principles[] = [
        'label' => (string) setting("about_principle_{$i}_label", ''),
        'body'  => (string) setting("about_principle_{$i}_body", ''),
        'image' => media_src((string) setting("about_principle_{$i}_image_path", '')),
    ];
}

$closingImage    = media_src((string) setting('about_closing_image_path', ''));
$closingEyebrow  = (string) setting('about_closing_eyebrow', 'Quietly, with care');
$closingHeadline = (string) setting('about_closing_headline', 'Founded in Kuala Lumpur, 2024');
$closingBody     = (string) setting('about_closing_body', '');

// Founder's story + the science behind sound. Ships with the founder's
// own words as the default; fully editable from Admin → About page.
$defaultFounderBody = <<<'TXT'
In December 2025, I went through one of the most exhausting periods of my life. I had been struggling with a persistent cough for almost three weeks. Nights were the hardest — excessive phlegm, constant coughing, interrupted sleep, and a body that simply couldn't fully recover. I tried different ways to manage it, yet healing felt frustratingly slow.

By early January, during a company retreat, I was invited to join a gong bath session. To be honest, I joined with curiosity rather than expectation. Something surprising happened.

That night, for the first time in weeks, I slept deeply. My breathing felt calmer, my body felt less tense, and over the next days, the coughing significantly eased. It wasn't an overnight "miracle," but it felt as though my body had finally shifted into recovery mode.

That experience changed my perspective completely — and eventually became one of the reasons I founded Jaemie Sound Bath.
TXT;

$defaultScienceBody = <<<'TXT'
At Jaemie Sound Bath, we do not position sound healing as superstition or a replacement for medical treatment. Instead, we understand the sound bath through the lens of nervous system regulation and deep relaxation.

When the body is under prolonged stress, poor sleep, or inflammation, it can remain in a heightened "fight-or-flight" state. Research increasingly suggests that immersive sound experiences — especially low-frequency instruments such as gongs and singing bowls — may help guide the body into a more relaxed parasympathetic state, often called the "rest and restore" mode.

Everything in our environment — including the human body — naturally vibrates. During a sound bath, gongs and singing bowls produce rich layers of sound waves and low-frequency vibrations that are not only heard through the ears, but often felt physically throughout the body. This is resonance: the way one vibrating system can gently influence another.
TXT;

$defaultSciencePoints = "Encouraging slower breathing patterns\nHelping muscles release physical tightness\nSupporting nervous system regulation\nPromoting a meditative, restorative state\nCreating an environment for deeper rest and recovery";

$founderEyebrow  = (string) setting('about_founder_eyebrow', "Our founder's story");
$founderHeadline = (string) setting('about_founder_headline', 'Why Jaemie Sound Bath exists');
$founderQuote    = (string) setting('about_founder_quote', "Sound doesn't heal the body for you — it may help create the condition where the body can heal itself better.");
$founderBody     = (string) setting('about_founder_body', $defaultFounderBody);
$founderParagraphs = array_values(array_filter(array_map('trim', preg_split('/\n\s*\n/', $founderBody))));
$showFounder     = trim($founderBody) !== '' || trim($founderQuote) !== '';

$scienceEyebrow  = (string) setting('about_science_eyebrow', 'Not magic — resonance');
$scienceHeadline = (string) setting('about_science_headline', 'The science of sound resonance');
$scienceBody     = (string) setting('about_science_body', $defaultScienceBody);
$scienceParagraphs = array_values(array_filter(array_map('trim', preg_split('/\n\s*\n/', $scienceBody))));
$sciencePoints   = array_values(array_filter(array_map('trim',
    preg_split('/\r?\n/', (string) setting('about_science_points', $defaultSciencePoints)))));
$scienceDisclaimer = (string) setting('about_science_disclaimer',
    'Sound bath is a complementary wellness practice and is not intended to diagnose, treat, cure, or replace medical care. Individual experiences may vary.');
$showScience     = $scienceParagraphs || $sciencePoints;

$videosEyebrow  = (string) setting('about_videos_eyebrow', 'In the room');
$videosHeadline = (string) setting('about_videos_headline', 'Moments from our sessions');
$videos = [];
for ($v = 1; $v <= 6; $v++) {
    $vid = youtube_id((string) setting("about_video_{$v}_url", ''));
    if ($vid === '') continue;
    $videos[] = ['id' => $vid, 'caption' => trim((string) setting("about_video_{$v}_caption", ''))];
}
$showVideos = $videos !== [];

require __DIR__ . '/../includes/header.php';
?>

<!-- Hero -->
<section class="relative overflow-hidden">
  <?php if ($heroImage): ?>
    <div class="absolute inset-0">
      <img src="<?= e($heroImage) ?>" alt="" loading="eager"
           onerror="this.style.display='none'"
           class="w-full h-full object-cover opacity-55 scale-105" data-parallax>
      <div class="absolute inset-0 bg-gradient-to-b from-navy-950/80 via-navy-950/55 to-navy-950"></div>
    </div>
  <?php else: ?>
    <div class="absolute inset-0 bg-gradient-to-b from-navy-950 via-navy-900 to-navy-950"></div>
  <?php endif; ?>

  <div class="relative max-w-3xl mx-auto px-6 py-32 md:py-44">
    <p class="text-gold-400/80 tracking-[0.4em] uppercase text-[11px]"><?= e($heroEyebrow) ?></p>
    <h1 class="font-serif text-5xl md:text-7xl text-beige-100 mt-6 leading-[1.05]"><?= nl2br(e($heroHeadline)) ?></h1>
  </div>
</section>

<!-- Story (text + portrait image) -->
<section class="max-w-6xl mx-auto px-6 py-20 md:py-28">
  <div class="grid md:grid-cols-2 gap-12 lg:gap-16 items-center">
    <div class="space-y-6 text-beige-100/80 leading-[1.95] font-light text-lg order-2 md:order-1">
      <?php foreach ($storyParagraphs as $para): ?>
        <p><?= nl2br(e($para)) ?></p>
      <?php endforeach; ?>
    </div>
    <div class="order-1 md:order-2">
      <?php if ($storyImage): ?>
        <div class="relative">
          <div class="absolute -inset-4 rounded-[2rem] border border-gold-500/15 pointer-events-none"></div>
          <img src="<?= e($storyImage) ?>" alt=""
               onerror="this.parentElement.style.display='none'"
               loading="lazy"
               class="rounded-[1.75rem] border border-white/5 w-full aspect-[4/5] object-cover">
        </div>
      <?php else: ?>
        <div class="rounded-[1.75rem] border border-white/5 w-full aspect-[4/5] bg-gradient-to-br from-gold-500/15 via-navy-800 to-navy-950 flex items-center justify-center">
          <span class="font-serif text-6xl text-gold-400/30">◯</span>
        </div>
      <?php endif; ?>
    </div>
  </div>
</section>

<?php if ($showFounder): ?>
<!-- Founder's story -->
<section class="border-t border-white/5">
  <div class="max-w-3xl mx-auto px-6 py-20 md:py-28">
    <p class="text-gold-400/80 tracking-[0.3em] uppercase text-[11px] text-center"><?= e($founderEyebrow) ?></p>
    <h2 class="font-serif text-4xl md:text-5xl text-beige-100 mt-4 text-center leading-tight"><?= e($founderHeadline) ?></h2>
    <?php if (trim($founderQuote) !== ''): ?>
      <blockquote class="mt-12 border-l-2 border-gold-500/40 pl-6 md:pl-8 font-serif text-2xl md:text-3xl text-beige-100/90 italic leading-snug">“<?= e($founderQuote) ?>”</blockquote>
    <?php endif; ?>
    <div class="mt-12 space-y-6 text-beige-100/80 leading-[1.95] font-light text-lg">
      <?php foreach ($founderParagraphs as $para): ?>
        <p><?= nl2br(e($para)) ?></p>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<?php if ($showScience): ?>
<!-- The science of sound -->
<section class="border-t border-white/5 bg-navy-900/30">
  <div class="max-w-5xl mx-auto px-6 py-20 md:py-28">
    <p class="text-gold-400/80 tracking-[0.3em] uppercase text-[11px] text-center"><?= e($scienceEyebrow) ?></p>
    <h2 class="font-serif text-4xl md:text-5xl text-beige-100 mt-4 text-center leading-tight"><?= e($scienceHeadline) ?></h2>

    <?php if ($scienceParagraphs): ?>
      <div class="mt-12 max-w-3xl mx-auto space-y-6 text-beige-100/80 leading-[1.95] font-light text-lg">
        <?php foreach ($scienceParagraphs as $para): ?>
          <p><?= nl2br(e($para)) ?></p>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <?php if ($sciencePoints): ?>
      <ul class="mt-14 grid sm:grid-cols-2 gap-4 max-w-3xl mx-auto">
        <?php foreach ($sciencePoints as $point): ?>
          <li class="flex gap-3 items-start border border-white/5 rounded-2xl px-5 py-4 bg-navy-950/40">
            <span class="text-gold-400 mt-0.5">◦</span>
            <span class="text-beige-100/75 text-sm leading-relaxed"><?= e($point) ?></span>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>

    <?php if (trim($scienceDisclaimer) !== ''): ?>
      <p class="mt-14 max-w-3xl mx-auto text-center text-xs text-beige-100/45 italic leading-relaxed"><?= e($scienceDisclaimer) ?></p>
    <?php endif; ?>
  </div>
</section>
<?php endif; ?>

<?php if ($showGuide): ?>
<!-- Meet your guide -->
<section class="border-t border-white/5 bg-navy-900/30">
  <div class="max-w-6xl mx-auto px-6 py-20 md:py-28">
    <div class="grid md:grid-cols-[420px_1fr] gap-12 lg:gap-16 items-center">
      <div>
        <?php if ($guideImage): ?>
          <div class="relative">
            <div class="absolute -inset-4 rounded-[2rem] border border-gold-500/15 pointer-events-none"></div>
            <img src="<?= e($guideImage) ?>" alt="<?= e($guideName) ?>"
                 onerror="this.parentElement.style.display='none'"
                 loading="lazy"
                 class="rounded-[1.75rem] border border-white/5 w-full aspect-[4/5] object-cover">
          </div>
        <?php else: ?>
          <div class="rounded-[1.75rem] border border-white/5 w-full aspect-[4/5] bg-gradient-to-br from-gold-500/15 via-navy-800 to-navy-950 flex items-center justify-center">
            <span class="font-serif text-6xl text-gold-400/30">◯</span>
          </div>
        <?php endif; ?>
      </div>
      <div>
        <p class="text-gold-400/80 tracking-[0.3em] uppercase text-[11px]"><?= e($guideEyebrow) ?></p>
        <?php if ($guideName !== ''): ?>
          <h2 class="font-serif text-4xl md:text-5xl text-beige-100 mt-3"><?= e($guideName) ?></h2>
        <?php endif; ?>
        <?php if ($guideRole !== ''): ?>
          <p class="text-gold-400/90 mt-2 text-sm tracking-wide"><?= e($guideRole) ?></p>
        <?php endif; ?>
        <?php if ($guideParas): ?>
          <div class="mt-6 space-y-5 text-beige-100/80 leading-[1.95] font-light text-lg">
            <?php foreach ($guideParas as $para): ?>
              <p><?= nl2br(e($para)) ?></p>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</section>
<?php endif; ?>

<?php if ($showVideos): ?>
<!-- Videos -->
<section class="border-t border-white/5">
  <div class="max-w-6xl mx-auto px-6 py-20 md:py-28">
    <p class="text-gold-400/80 tracking-[0.3em] uppercase text-[11px] text-center"><?= e($videosEyebrow) ?></p>
    <h2 class="font-serif text-4xl md:text-5xl text-beige-100 mt-4 text-center leading-tight"><?= e($videosHeadline) ?></h2>
    <div class="mt-12 flex gap-5 overflow-x-auto snap-x snap-mandatory pb-4 -mx-6 px-6">
      <?php foreach ($videos as $vid): ?>
        <figure class="snap-center shrink-0 w-[260px] sm:w-[300px]">
          <div class="relative aspect-[9/16] rounded-[1.5rem] overflow-hidden border border-white/10 bg-navy-950">
            <iframe class="absolute inset-0 w-full h-full" loading="lazy"
                    src="https://www.youtube-nocookie.com/embed/<?= e($vid['id']) ?>?rel=0&modestbranding=1&playsinline=1"
                    title="<?= e($vid['caption'] !== '' ? $vid['caption'] : 'Session video') ?>"
                    frameborder="0"
                    allow="accelerometer; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                    allowfullscreen></iframe>
          </div>
          <?php if ($vid['caption'] !== ''): ?>
            <figcaption class="mt-3 text-sm text-beige-100/60 text-center"><?= e($vid['caption']) ?></figcaption>
          <?php endif; ?>
        </figure>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<!-- Principles with imagery -->
<section class="border-t border-white/5 bg-navy-900/40">
  <div class="max-w-6xl mx-auto px-6 py-20">
    <p class="text-gold-400/80 tracking-[0.3em] uppercase text-[11px] text-center">How we hold space</p>
    <h2 class="font-serif text-4xl md:text-5xl text-beige-100 mt-3 text-center">Three quiet principles</h2>

    <div class="mt-14 grid md:grid-cols-3 gap-6">
      <?php foreach ($principles as $i => $p):
        if ($p['label'] === '' && $p['body'] === '') continue;
      ?>
        <article class="group rounded-3xl border border-white/5 bg-navy-950/40 overflow-hidden hover:border-gold-500/30 transition flex flex-col">
          <div class="relative aspect-[4/3] bg-gradient-to-br from-navy-800 to-navy-950 overflow-hidden">
            <?php if ($p['image']): ?>
              <img src="<?= e($p['image']) ?>" alt="<?= e($p['label']) ?>"
                   onerror="this.style.display='none'"
                   loading="lazy"
                   class="absolute inset-0 w-full h-full object-cover transition duration-700 group-hover:scale-[1.04]">
              <div class="absolute inset-0 bg-gradient-to-t from-navy-950/80 to-transparent"></div>
            <?php else: ?>
              <div class="absolute inset-0 flex items-center justify-center">
                <span class="font-serif text-5xl text-gold-400/30">◯</span>
              </div>
            <?php endif; ?>
            <span class="absolute top-4 left-4 text-[10px] uppercase tracking-[0.4em] text-gold-400/80">Principle 0<?= $i + 1 ?></span>
          </div>
          <div class="p-7">
            <h3 class="font-serif text-3xl text-gold-400"><?= e($p['label']) ?></h3>
            <p class="mt-4 text-beige-100/70 leading-relaxed text-sm"><?= nl2br(e($p['body'])) ?></p>
          </div>
        </article>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<!-- Closing -->
<section class="relative overflow-hidden border-t border-white/5">
  <?php if ($closingImage): ?>
    <div class="absolute inset-0">
      <img src="<?= e($closingImage) ?>" alt=""
           onerror="this.style.display='none'"
           loading="lazy"
           class="w-full h-full object-cover opacity-40">
      <div class="absolute inset-0 bg-gradient-to-b from-navy-950/85 via-navy-950/70 to-navy-950"></div>
    </div>
  <?php endif; ?>
  <div class="relative max-w-3xl mx-auto px-6 py-24 md:py-32 text-center">
    <p class="text-gold-400/80 tracking-[0.3em] uppercase text-[11px]"><?= e($closingEyebrow) ?></p>
    <h2 class="font-serif text-4xl md:text-5xl text-beige-100 mt-4"><?= e($closingHeadline) ?></h2>
    <?php if ($closingBody !== ''): ?>
      <p class="mt-6 text-beige-100/75 leading-[1.95] font-light max-w-2xl mx-auto"><?= nl2br(e($closingBody)) ?></p>
    <?php endif; ?>

    <div class="mt-12 flex flex-col sm:flex-row gap-4 justify-center">
      <a href="<?= url('/public/events.php') ?>" class="px-7 py-3.5 rounded-full bg-gold-500 text-navy-950 font-medium hover:bg-gold-400 transition">Browse upcoming sessions</a>
      <a href="<?= url('/public/membership.php') ?>" class="px-7 py-3.5 rounded-full border border-gold-500/40 text-gold-400 hover:bg-gold-500/10 transition">Become a member</a>
    </div>
  </div>
</section>

<script>
// Soft parallax on the hero image (matches the home page).
document.addEventListener('scroll', () => {
  document.querySelectorAll('[data-parallax]').forEach((el) => {
    const y = window.scrollY * 0.18;
    el.style.transform = `translate3d(0, ${y}px, 0) scale(1.05)`;
  });
}, { passive: true });
</script>

<?php require __DIR__ . '/../includes/footer.php'; ?>
