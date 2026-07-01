<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$pageTitle = 'Sound Healing & Wellness Sanctuary';
$pageDescription = 'A calm, premium sound healing sanctuary. Sessions, breathwork, meditation and a curated audio library — for the modern soul.';

// Pull homepage settings from the admin-controlled site_settings table.
$heroEyebrow         = (string) setting('hero_eyebrow', 'A sanctuary for stillness');
$heroHeadline        = (string) setting('hero_headline', 'Return to the sound of yourself.');
$heroSubheadline     = (string) setting('hero_subheadline', 'Curated sound healing sessions, breathwork journeys and a quiet audio sanctuary — held with intention, designed for the modern soul.');
$heroCtaPrimaryLabel = (string) setting('hero_cta_primary_label', 'Reserve a session');
$heroCtaPrimaryUrl   = (string) setting('hero_cta_primary_url', '/public/events.php');
$heroCtaSecondaryLabel = (string) setting('hero_cta_secondary_label', 'Become a member');
$heroCtaSecondaryUrl   = (string) setting('hero_cta_secondary_url', '/public/membership.php');
$heroImage           = media_src((string) setting('hero_image_path', ''));
$heroAudio           = media_src((string) setting('hero_audio_path', ''));
$heroAudioLabel      = (string) setting('hero_audio_label', 'Press play. Begin softly.');

$trialEnabled        = (bool)   setting('trial_enabled', true);
$trialEyebrow        = (string) setting('trial_eyebrow', 'A gift on the threshold');
$trialHeadline       = (string) setting('trial_headline', 'Try a 5-minute sound bath, on us.');
$trialSubheadline    = (string) setting('trial_subheadline', 'Press play, dim the lights, and feel what we mean. No payment, no commitment — just a quiet first step.');
$trialAudio          = media_src((string) setting('trial_audio_path', ''));
$trialCtaLabel       = (string) setting('trial_cta_label', 'Start your 7-day free trial');
$trialDays           = (int)    setting('trial_duration_days', 7);

$homeStoryEnabled    = (bool)   setting('home_story_enabled', true);
$homeStoryEyebrow    = (string) setting('home_story_eyebrow', 'Our story');
$homeStoryQuote      = (string) setting('home_story_quote', "Sound doesn't heal the body for you — it may help create the condition where the body can heal itself better.");
$homeStoryBody       = (string) setting('home_story_body', 'Jaemie Sound Bath was born from a personal experience — weeks of exhaustion and restless nights that eased after a single gong bath. Not magic, but resonance: sound gently guiding the body back into rest and recovery.');
$homeStoryCtaLabel   = (string) setting('home_story_cta_label', 'Read our story');

$homeVideoEnabled    = (bool)   setting('home_video_enabled', true);
$homeVideoId = '';
for ($v = 1; $v <= 6; $v++) {
    $hv = youtube_id((string) setting("about_video_{$v}_url", ''));
    if ($hv !== '') { $homeVideoId = $hv; break; }
}
$homeVideoEyebrow    = (string) setting('home_video_eyebrow', 'Watch');
$homeVideoHeadline   = (string) setting('home_video_headline', 'Step inside a session');
$showHomeVideo       = $homeVideoEnabled && $homeVideoId !== '';

// Templates + non-recurring future events (children of recurring templates
// are excluded — the expansion helper resolves them per date).
$upcomingRaw = db()->query(
    "SELECT e.* FROM events e
      WHERE e.status = 'published'
        AND (e.audience IS NULL OR e.audience = 'public')
        AND e.parent_event_id IS NULL
        AND (e.recurrence IN ('daily','weekly','monthly') OR e.starts_at >= NOW())
      ORDER BY e.starts_at ASC"
)->fetchAll();
$upcoming = array_slice(expand_event_occurrences($upcomingRaw, 14), 0, 3);

$testimonials = db()->query(
    "SELECT author_name, author_title, quote, rating
     FROM testimonials
     WHERE is_published = 1
     ORDER BY sort_order ASC, created_at DESC
     LIMIT 3"
)->fetchAll();

require __DIR__ . '/../includes/header.php';

// Helpers — link out to internal paths or external URLs gracefully.
$asLink = function (string $u): string {
    return (str_starts_with($u, 'http://') || str_starts_with($u, 'https://')) ? $u : url($u);
};

// Organization structured data for richer search/social results.
$ldBase = rtrim((string) config('app.url'), '/');
$ldSocial = array_values(array_filter([
    (string) setting('company_social_instagram', ''),
    (string) setting('company_social_facebook', ''),
    (string) setting('company_social_tiktok', ''),
    (string) setting('company_social_youtube', ''),
], fn($u) => str_starts_with((string) $u, 'http')));
$orgLd = ['@context' => 'https://schema.org', '@type' => 'Organization', 'name' => $brandName, 'url' => $ldBase];
if ($seoImage !== '') $orgLd['logo'] = $seoImage;
if ($ldSocial) $orgLd['sameAs'] = $ldSocial;
?>
<script type="application/ld+json"><?= json_encode($orgLd, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?></script>

<section class="relative overflow-hidden">
  <?php if ($heroImage): ?>
    <div class="absolute inset-0">
      <img src="<?= e($heroImage) ?>" alt="" class="w-full h-full object-cover opacity-50 scale-105" data-parallax>
      <div class="absolute inset-0 bg-gradient-to-b from-navy-950/85 via-navy-950/70 to-navy-950"></div>
    </div>
  <?php else: ?>
    <div class="absolute inset-0 bg-gradient-to-b from-navy-950 via-navy-900 to-navy-950"></div>
    <div class="absolute inset-0 opacity-40 bg-[radial-gradient(ellipse_at_top,_var(--tw-gradient-stops))] from-gold-500/40 via-transparent to-transparent"></div>
  <?php endif; ?>

  <div class="absolute inset-x-0 top-0 h-[80vh] pointer-events-none overflow-hidden">
    <div class="ripple ripple-1"></div>
    <div class="ripple ripple-2"></div>
    <div class="ripple ripple-3"></div>
  </div>

  <div class="relative max-w-5xl mx-auto px-6 py-32 md:py-44 text-center">
    <p class="text-gold-400/80 tracking-[0.5em] uppercase text-[11px]"><?= e($heroEyebrow) ?></p>
    <h1 class="font-serif text-5xl md:text-7xl lg:text-8xl text-beige-100 mt-8 leading-[1.05]"><?= e($heroHeadline) ?></h1>
    <p class="mt-10 text-lg md:text-xl text-beige-100/80 max-w-2xl mx-auto leading-[1.85] font-light"><?= nl2br(e($heroSubheadline)) ?></p>

    <div class="mt-14 flex flex-col sm:flex-row gap-4 justify-center items-center">
      <a href="<?= e($asLink($heroCtaPrimaryUrl)) ?>" class="px-8 py-4 rounded-full bg-gold-500 text-navy-950 font-medium hover:bg-gold-400 transition shadow-[0_8px_30px_-10px_rgba(201,164,106,0.4)]"><?= e($heroCtaPrimaryLabel) ?></a>
      <a href="<?= e($asLink($heroCtaSecondaryUrl)) ?>" class="px-8 py-4 rounded-full border border-gold-500/50 text-gold-400 hover:bg-gold-500/10 transition"><?= e($heroCtaSecondaryLabel) ?></a>
    </div>

    <?php if ($heroAudio): ?>
      <div class="mt-14 max-w-md mx-auto" x-data="ambient('<?= e($heroAudio) ?>')">
        <button type="button" @click="toggle()" class="w-full flex items-center justify-center gap-3 px-5 py-3 rounded-full border border-gold-500/30 text-gold-400 bg-navy-900/40 hover:bg-navy-900/60 backdrop-blur transition group">
          <span class="relative flex h-2.5 w-2.5">
            <span x-show="playing" class="absolute inset-0 rounded-full bg-gold-400 animate-ping"></span>
            <span class="relative rounded-full h-2.5 w-2.5 bg-gold-400"></span>
          </span>
          <span class="text-xs uppercase tracking-[0.3em]" x-text="playing ? 'Listening' : <?= json_encode($heroAudioLabel) ?>"></span>
        </button>
        <p class="text-[11px] text-beige-100/40 mt-3 tracking-widest uppercase">Wear headphones if you can</p>
      </div>
    <?php endif; ?>
  </div>
</section>

<?php if ($trialEnabled): ?>
<section class="relative overflow-hidden border-y border-white/5 bg-navy-900/40">
  <div class="absolute inset-0 opacity-30 bg-[radial-gradient(circle_at_30%_50%,_rgba(201,164,106,0.25),_transparent_60%)]"></div>
  <div class="relative max-w-5xl mx-auto px-6 py-24 grid md:grid-cols-2 gap-12 items-center">
    <div>
      <p class="text-gold-400/80 tracking-[0.4em] uppercase text-[11px]"><?= e($trialEyebrow) ?></p>
      <h2 class="font-serif text-4xl md:text-5xl text-beige-100 mt-5 leading-tight"><?= e($trialHeadline) ?></h2>
      <p class="mt-6 text-beige-100/75 leading-[1.85] font-light"><?= nl2br(e($trialSubheadline)) ?></p>

      <ul class="mt-8 space-y-2 text-sm text-beige-100/70">
        <li class="flex gap-3"><span class="text-gold-400">◦</span> No payment information required</li>
        <li class="flex gap-3"><span class="text-gold-400">◦</span> Cancel anytime — softly, in one click</li>
      </ul>

      <div class="mt-10 flex flex-col sm:flex-row gap-4">
        <a href="<?= url('/public/register.php?trial=1') ?>" class="inline-block text-center px-7 py-4 rounded-full bg-gold-500 text-navy-950 font-medium hover:bg-gold-400 transition shadow-[0_8px_30px_-10px_rgba(201,164,106,0.4)]"><?= e($trialCtaLabel) ?></a>
        <a href="<?= url('/public/login.php') ?>" class="inline-block text-center px-7 py-4 rounded-full border border-white/10 text-beige-100/80 hover:border-gold-500/40 hover:text-gold-400 transition">I already have an account</a>
      </div>
    </div>

    <div class="relative" x-data="trialPlayer('<?= e($trialAudio) ?>')">
      <div class="absolute -inset-6 rounded-[2.5rem] border border-gold-500/10 pointer-events-none"></div>
      <div class="absolute -inset-2 rounded-[2.2rem] border border-gold-500/15 pointer-events-none"></div>
      <div class="relative rounded-[2rem] border border-white/5 bg-navy-950/80 backdrop-blur p-8">
        <div class="flex items-center justify-between text-xs text-beige-100/50 uppercase tracking-widest">
          <span>Sample · 5 min</span>
          <span x-text="time"></span>
        </div>

        <button type="button" @click="toggle()"
                class="mt-8 mx-auto block h-24 w-24 rounded-full border border-gold-500/40 bg-gold-500/10 hover:bg-gold-500/20 transition relative">
          <span x-show="!playing" class="absolute inset-0 flex items-center justify-center text-gold-400 text-3xl">▶</span>
          <span x-show="playing" class="absolute inset-0 flex items-center justify-center gap-1.5">
            <span class="w-1.5 h-8 bg-gold-400 rounded-full animate-pulse"></span>
            <span class="w-1.5 h-12 bg-gold-400 rounded-full animate-pulse" style="animation-delay:0.15s;"></span>
            <span class="w-1.5 h-6 bg-gold-400 rounded-full animate-pulse" style="animation-delay:0.3s;"></span>
            <span class="w-1.5 h-10 bg-gold-400 rounded-full animate-pulse" style="animation-delay:0.45s;"></span>
          </span>
        </button>

        <div class="mt-8 h-1 rounded-full bg-white/5 overflow-hidden">
          <div class="h-full bg-gradient-to-r from-gold-500/60 to-gold-300 transition-all duration-300" :style="`width: ${progress}%`"></div>
        </div>

        <p class="mt-6 text-center text-sm text-beige-100/60" x-show="!playing">Press to begin. Close your eyes.</p>
        <p class="mt-6 text-center text-sm text-gold-400" x-show="playing">Breathing in… and out.</p>
      </div>
    </div>
  </div>
</section>
<?php endif; ?>

<section class="max-w-6xl mx-auto px-6 py-24">
  <div class="grid md:grid-cols-2 gap-10">
    <?php
    $pillars = [
      ['Sound', 'Crystal bowls, gongs and tuning forks tuned to the frequencies your nervous system listens for.'],
      ['Breath', 'Guided breathwork to soften the chest, slow the heart and arrive in the present moment.'],
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
        <?php foreach ($upcoming as $event):
          $cover = $event['cover_image'] ?? '';
          $coverSrc = $cover ? media_src($cover) : '';
          $upIsOcc = !empty($event['_template_id']);
          $upKey   = $upIsOcc
              ? 'event-' . (int) $event['_template_id'] . '-' . str_replace('-', '', (string) $event['_occurrence_date'])
              : 'event-' . (int) $event['id'];
          $upUrl   = $upIsOcc
              ? '/public/events.php?event=' . (int) $event['_template_id'] . '&date=' . urlencode((string) $event['_occurrence_date']) . '#' . $upKey
              : '/public/events.php#' . $upKey;
        ?>
          <a href="<?= url($upUrl) ?>" class="group block rounded-3xl overflow-hidden border border-white/5 bg-navy-950 hover:border-gold-500/40 transition">
            <div class="aspect-[4/3] bg-gradient-to-br from-navy-800 to-navy-900 flex items-center justify-center">
              <?php if ($coverSrc): ?>
                <img src="<?= e($coverSrc) ?>" alt="<?= e($event['title']) ?>" class="object-cover w-full h-full">
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
  <p class="mt-10 text-center">
    <a href="<?= url('/public/share_experience.php') ?>" class="text-gold-400 hover:text-gold-300 text-sm">Share your experience →</a>
  </p>
</section>
<?php endif; ?>

<?php if ($showHomeVideo): ?>
<section class="relative overflow-hidden border-t border-white/5">
  <div class="relative max-w-3xl mx-auto px-6 py-24 text-center">
    <p class="text-gold-400/80 tracking-[0.4em] uppercase text-[11px]"><?= e($homeVideoEyebrow) ?></p>
    <h2 class="font-serif text-4xl md:text-5xl text-beige-100 mt-5 leading-tight"><?= e($homeVideoHeadline) ?></h2>
    <div class="mt-12 mx-auto w-full max-w-[320px]">
      <div class="relative aspect-[9/16] rounded-[1.75rem] overflow-hidden border border-white/10 bg-navy-950 shadow-[0_20px_60px_-20px_rgba(201,164,106,0.25)]">
        <iframe class="absolute inset-0 w-full h-full" loading="lazy"
                src="https://www.youtube-nocookie.com/embed/<?= e($homeVideoId) ?>?rel=0&modestbranding=1&playsinline=1"
                title="<?= e($homeVideoHeadline) ?>" frameborder="0"
                allow="accelerometer; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
                allowfullscreen></iframe>
      </div>
    </div>
    <a href="<?= url('/public/about.php') ?>" class="inline-block mt-10 text-gold-400 hover:text-gold-300 text-sm">See more from the sanctuary →</a>
  </div>
</section>
<?php endif; ?>

<?php if ($homeStoryEnabled): ?>
<section class="relative overflow-hidden border-t border-white/5 bg-navy-900/40">
  <div class="absolute inset-0 opacity-30 bg-[radial-gradient(circle_at_70%_50%,_rgba(201,164,106,0.22),_transparent_60%)]"></div>
  <div class="relative max-w-4xl mx-auto px-6 py-24 text-center">
    <p class="text-gold-400/80 tracking-[0.4em] uppercase text-[11px]"><?= e($homeStoryEyebrow) ?></p>
    <?php if (trim($homeStoryQuote) !== ''): ?>
      <blockquote class="mt-8 font-serif text-3xl md:text-4xl text-beige-100 leading-snug">“<?= e($homeStoryQuote) ?>”</blockquote>
    <?php endif; ?>
    <?php if (trim($homeStoryBody) !== ''): ?>
      <p class="mt-8 text-beige-100/75 leading-[1.95] font-light max-w-2xl mx-auto"><?= nl2br(e($homeStoryBody)) ?></p>
    <?php endif; ?>
    <a href="<?= url('/public/about.php') ?>" class="inline-block mt-10 px-8 py-4 rounded-full border border-gold-500/50 text-gold-400 hover:bg-gold-500/10 transition"><?= e($homeStoryCtaLabel) ?></a>
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

<script>
function ambient(src) {
  return {
    audio: null, playing: false,
    toggle() {
      if (!this.audio) {
        this.audio = new Audio(src);
        this.audio.loop = true;
        this.audio.volume = 0.45;
        this.audio.addEventListener('ended', () => this.playing = false);
      }
      if (this.playing) { this.audio.pause(); this.playing = false; }
      else { this.audio.play().then(() => this.playing = true).catch(() => this.playing = false); }
    }
  }
}
function trialPlayer(src) {
  return {
    audio: null, playing: false, progress: 0, time: '0:00',
    toggle() {
      if (!src) return;
      if (!this.audio) {
        this.audio = new Audio(src);
        this.audio.preload = 'metadata';
        this.audio.addEventListener('timeupdate', () => {
          if (this.audio.duration) {
            this.progress = (this.audio.currentTime / this.audio.duration) * 100;
            const m = Math.floor(this.audio.currentTime / 60);
            const s = Math.floor(this.audio.currentTime % 60).toString().padStart(2, '0');
            this.time = `${m}:${s}`;
          }
        });
        this.audio.addEventListener('ended', () => { this.playing = false; this.progress = 0; this.time = '0:00'; });
      }
      if (this.playing) { this.audio.pause(); this.playing = false; }
      else { this.audio.play().then(() => this.playing = true).catch(() => this.playing = false); }
    }
  }
}

// Soft parallax on the hero image.
document.addEventListener('scroll', () => {
  document.querySelectorAll('[data-parallax]').forEach((el) => {
    const y = window.scrollY * 0.18;
    el.style.transform = `translate3d(0, ${y}px, 0) scale(1.05)`;
  });
}, { passive: true });
</script>

<?php require __DIR__ . '/../includes/footer.php'; ?>
