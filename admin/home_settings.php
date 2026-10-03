<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_admin();
$pageTitle = 'Home Settings';

$errors = [];
$saved  = false;

// Strings the form writes back.
$keys = [
    'hero_eyebrow'             => 'string',
    'hero_headline'            => 'string',
    'hero_subheadline'         => 'text',
    'hero_cta_primary_label'   => 'string',
    'hero_cta_primary_url'     => 'string',
    'hero_cta_secondary_label' => 'string',
    'hero_cta_secondary_url'   => 'string',
    'hero_audio_label'         => 'string',
    'trial_eyebrow'            => 'string',
    'trial_headline'           => 'string',
    'trial_subheadline'        => 'text',
    'trial_cta_label'          => 'string',
    'trial_duration_days'      => 'int',
    'home_story_eyebrow'       => 'string',
    'home_story_quote'         => 'text',
    'home_story_body'          => 'text',
    'home_story_cta_label'     => 'string',
    'home_video_eyebrow'       => 'string',
    'home_video_headline'      => 'string',
];

if (is_post()) {
    csrf_verify();

    foreach ($keys as $k => $type) {
        if (array_key_exists($k, $_POST)) {
            $val = is_string($_POST[$k]) ? trim($_POST[$k]) : $_POST[$k];
            set_setting($k, $val, $type);
        }
    }
    set_setting('trial_enabled', !empty($_POST['trial_enabled']) ? '1' : '0', 'bool');
    set_setting('home_story_enabled', !empty($_POST['home_story_enabled']) ? '1' : '0', 'bool');
    set_setting('home_video_enabled', !empty($_POST['home_video_enabled']) ? '1' : '0', 'bool');

    // Site theme default. Individual visitors can still override via
    // the navbar toggle (persisted in the sh-theme cookie); this is
    // only the fallback when a visitor hasn't chosen one.
    $themeIn = (string) input('site_theme', 'dark');
    if (!in_array($themeIn, ['dark','light'], true)) $themeIn = 'dark';
    set_setting('site_theme', $themeIn, 'string');

    // File uploads → /uploads/home/...
    foreach ([
        ['hero_image_path_file', 'hero_image_path'],
        ['hero_audio_path_file', 'hero_audio_path'],
        ['trial_audio_path_file','trial_audio_path'],
        ['seo_default_image_file','seo_default_image'],
    ] as [$field, $settingKey]) {
        try {
            $uploaded = handle_upload($field, 'home');
            if ($uploaded) {
                $existing = (string) setting($settingKey, '');
                if (str_starts_with($existing, '/uploads/')) {
                    delete_upload($existing);
                }
                set_setting($settingKey, $uploaded, 'string');
            }
        } catch (RuntimeException $e) {
            $errors[] = $field . ': ' . $e->getMessage();
        }
    }

    // Allow optional explicit URL input for each media slot — overrides the file.
    foreach (['hero_image_path','hero_audio_path','trial_audio_path','seo_default_image'] as $slot) {
        if (!empty($_POST['_url_' . $slot])) {
            $url = trim((string) $_POST['_url_' . $slot]);
            $existing = (string) setting($slot, '');
            if (str_starts_with($existing, '/uploads/')) {
                delete_upload($existing);
            }
            set_setting($slot, $url, 'string');
        } elseif (!empty($_POST['_clear_' . $slot])) {
            $existing = (string) setting($slot, '');
            if (str_starts_with($existing, '/uploads/')) {
                delete_upload($existing);
            }
            set_setting($slot, '', 'string');
        }
    }

    audit_log('home_settings.update', 'site_settings', null);
    flash('home', 'Home settings saved.', 'success');
    redirect('/admin/home_settings.php');
}

require __DIR__ . '/../includes/admin_layout.php';

function media_block(string $key, string $label, string $accept, string $type = 'image'): void
{
    $current = (string) setting($key, '');
    $src = media_src($current);
    ?>
    <div class="border border-white/5 rounded-2xl p-5 bg-navy-950/40 space-y-3">
      <div class="flex items-center justify-between gap-3">
        <p class="text-xs uppercase tracking-widest text-gold-400/80"><?= e($label) ?></p>
        <?php if ($current): ?>
          <label class="flex items-center gap-2 text-xs text-beige-100/60">
            <input type="checkbox" name="_clear_<?= e($key) ?>" value="1"> Clear current
          </label>
        <?php endif; ?>
      </div>

      <?php if ($src && $type === 'image'): ?>
        <img src="<?= e($src) ?>" class="h-32 w-auto rounded-xl border border-white/10 object-cover" alt="">
      <?php elseif ($src && $type === 'audio'): ?>
        <audio controls preload="none" src="<?= e($src) ?>" class="w-full"></audio>
      <?php endif; ?>

      <label class="block text-sm text-beige-100/70">
        Upload (replace)
        <input type="file" name="<?= e($key) ?>_file" accept="<?= e($accept) ?>" class="mt-1 w-full text-sm file:mr-3 file:py-2 file:px-4 file:rounded-full file:border-0 file:bg-gold-500/20 file:text-gold-400 hover:file:bg-gold-500/30">
      </label>
      <label class="block text-sm text-beige-100/70">
        …or paste an external URL
        <input type="url" name="_url_<?= e($key) ?>" placeholder="https://…" class="mt-1 w-full rounded-full bg-navy-900 border border-white/5 px-4 py-2 text-sm">
      </label>
      <p class="text-[11px] text-beige-100/40 break-all">Current: <?= $current ? e($current) : '—' ?></p>
    </div>
    <?php
}
?>

<div class="flex items-center justify-between gap-4 flex-wrap">
  <div>
    <h1 class="font-serif text-3xl text-beige-100">Home page</h1>
    <p class="text-beige-100/60 mt-1 text-sm">Banner, ambient audio, copy and the free-trial moment.</p>
  </div>
  <a href="<?= url('/public/index.php') ?>" target="_blank" class="text-sm text-gold-400 hover:text-gold-300">View live →</a>
</div>

<?php foreach ($errors as $err): ?>
  <p class="mt-3 text-red-300/80 text-sm"><?= e($err) ?></p>
<?php endforeach; ?>

<form method="post" enctype="multipart/form-data" action="<?= url('/admin/home_settings.php') ?>" class="mt-8 space-y-10 max-w-4xl">
  <?= csrf_field() ?>

  <!-- Site theme default. Visitors can still flip via the navbar
       toggle — the cookie wins over this setting. -->
  <?php $currentTheme = (string) setting('site_theme', 'dark'); ?>
  <section class="border border-white/5 rounded-3xl p-6 bg-navy-900/40 space-y-5">
    <div class="flex items-start justify-between gap-4 flex-wrap">
      <div>
        <h2 class="font-serif text-2xl text-gold-400">Site theme</h2>
        <p class="text-[11px] text-beige-100/45 mt-1">The default palette new visitors see. Anyone can still flip their own view via the sun / moon toggle in the top nav.</p>
      </div>
    </div>
    <div class="grid sm:grid-cols-2 gap-4">
      <label class="cursor-pointer">
        <input type="radio" name="site_theme" value="dark" <?= $currentTheme === 'dark' ? 'checked' : '' ?> class="peer sr-only">
        <div class="rounded-2xl border border-white/10 p-5 flex items-center gap-4 peer-checked:border-gold-500/50 peer-checked:bg-gold-500/10 hover:border-gold-500/30 transition">
          <div class="w-16 h-12 rounded-lg bg-[#0a1027] border border-white/10 flex items-center justify-center">
            <span class="text-[10px] uppercase tracking-widest text-[#c9a46a]">Sound</span>
          </div>
          <div>
            <p class="text-beige-100">Dark</p>
            <p class="text-[11px] text-beige-100/50 mt-1">Navy base with cream text. The original.</p>
          </div>
        </div>
      </label>
      <label class="cursor-pointer">
        <input type="radio" name="site_theme" value="light" <?= $currentTheme === 'light' ? 'checked' : '' ?> class="peer sr-only">
        <div class="rounded-2xl border border-white/10 p-5 flex items-center gap-4 peer-checked:border-gold-500/50 peer-checked:bg-gold-500/10 hover:border-gold-500/30 transition">
          <div class="w-16 h-12 rounded-lg bg-[#faf5ea] border border-black/10 flex items-center justify-center">
            <span class="text-[10px] uppercase tracking-widest text-[#a67d3b]">Sound</span>
          </div>
          <div>
            <p class="text-beige-100">Light</p>
            <p class="text-[11px] text-beige-100/50 mt-1">Warm cream base with deep navy text.</p>
          </div>
        </div>
      </label>
    </div>
  </section>

  <section class="border border-white/5 rounded-3xl p-6 bg-navy-900/40 space-y-5">
    <h2 class="font-serif text-2xl text-gold-400">Hero</h2>

    <label class="block">
      <span class="text-xs uppercase tracking-widest text-beige-100/60">Eyebrow text</span>
      <input name="hero_eyebrow" value="<?= e((string) setting('hero_eyebrow', '')) ?>" class="mt-2 w-full rounded-2xl bg-navy-950 border border-white/5 px-4 py-3">
    </label>
    <label class="block">
      <span class="text-xs uppercase tracking-widest text-beige-100/60">Headline</span>
      <input name="hero_headline" value="<?= e((string) setting('hero_headline', '')) ?>" class="mt-2 w-full rounded-2xl bg-navy-950 border border-white/5 px-4 py-3 font-serif text-lg">
    </label>
    <label class="block">
      <span class="text-xs uppercase tracking-widest text-beige-100/60">Subheadline</span>
      <textarea name="hero_subheadline" rows="3" class="mt-2 w-full rounded-2xl bg-navy-950 border border-white/5 px-4 py-3"><?= e((string) setting('hero_subheadline', '')) ?></textarea>
    </label>

    <div class="grid sm:grid-cols-2 gap-5">
      <label class="block">
        <span class="text-xs uppercase tracking-widest text-beige-100/60">Primary CTA label</span>
        <input name="hero_cta_primary_label" value="<?= e((string) setting('hero_cta_primary_label', '')) ?>" class="mt-2 w-full rounded-2xl bg-navy-950 border border-white/5 px-4 py-3">
      </label>
      <label class="block">
        <span class="text-xs uppercase tracking-widest text-beige-100/60">Primary CTA URL</span>
        <input name="hero_cta_primary_url" value="<?= e((string) setting('hero_cta_primary_url', '')) ?>" class="mt-2 w-full rounded-2xl bg-navy-950 border border-white/5 px-4 py-3">
      </label>
      <label class="block">
        <span class="text-xs uppercase tracking-widest text-beige-100/60">Secondary CTA label</span>
        <input name="hero_cta_secondary_label" value="<?= e((string) setting('hero_cta_secondary_label', '')) ?>" class="mt-2 w-full rounded-2xl bg-navy-950 border border-white/5 px-4 py-3">
      </label>
      <label class="block">
        <span class="text-xs uppercase tracking-widest text-beige-100/60">Secondary CTA URL</span>
        <input name="hero_cta_secondary_url" value="<?= e((string) setting('hero_cta_secondary_url', '')) ?>" class="mt-2 w-full rounded-2xl bg-navy-950 border border-white/5 px-4 py-3">
      </label>
    </div>

    <div class="grid sm:grid-cols-2 gap-5">
      <?php media_block('hero_image_path', 'Banner image', 'image/jpeg,image/png,image/webp', 'image'); ?>
      <div class="space-y-3">
        <?php media_block('hero_audio_path', 'Ambient audio (optional)', 'audio/*', 'audio'); ?>
        <label class="block">
          <span class="text-xs uppercase tracking-widest text-beige-100/60">Audio caption</span>
          <input name="hero_audio_label" value="<?= e((string) setting('hero_audio_label', '')) ?>" class="mt-2 w-full rounded-2xl bg-navy-950 border border-white/5 px-4 py-3">
        </label>
      </div>
    </div>
  </section>

  <section class="border border-white/5 rounded-3xl p-6 bg-navy-900/40 space-y-5">
    <div>
      <h2 class="font-serif text-2xl text-gold-400">Social share preview</h2>
      <p class="text-sm text-beige-100/60 mt-1">
        The image that appears when someone shares any page from this site on WhatsApp, Facebook, LinkedIn, or Slack.
        Recommended size <strong class="text-beige-100/80">1200 × 630</strong> (landscape). If empty, the banner image above is used as the fallback.
      </p>
    </div>
    <div class="grid sm:grid-cols-2 gap-5 items-start">
      <?php media_block('seo_default_image', 'Social share image (OG)', 'image/jpeg,image/png,image/webp', 'image'); ?>
      <div class="text-sm text-beige-100/70 space-y-3 leading-relaxed">
        <p class="text-xs uppercase tracking-widest text-gold-400/80">What to know</p>
        <ul class="space-y-2 list-disc list-inside text-beige-100/70">
          <li>Landscape crops best — most feeds show a wide preview card.</li>
          <li>Keep the important subject inside a horizontal band; the top and bottom edges are often trimmed.</li>
          <li>WhatsApp and Facebook cache aggressively. After changing this, share the link with fresh URL params (or use each platform's link debugger) to force a re-crawl.</li>
        </ul>
      </div>
    </div>
  </section>

  <section class="border border-white/5 rounded-3xl p-6 bg-navy-900/40 space-y-5">
    <div class="flex items-center justify-between gap-3 flex-wrap">
      <h2 class="font-serif text-2xl text-gold-400">Free trial section</h2>
      <label class="flex items-center gap-2 text-sm text-beige-100/70">
        <input type="checkbox" name="trial_enabled" value="1" <?= setting('trial_enabled', true) ? 'checked' : '' ?>> Show on home page
      </label>
    </div>

    <label class="block">
      <span class="text-xs uppercase tracking-widest text-beige-100/60">Eyebrow text</span>
      <input name="trial_eyebrow" value="<?= e((string) setting('trial_eyebrow', '')) ?>" class="mt-2 w-full rounded-2xl bg-navy-950 border border-white/5 px-4 py-3">
    </label>
    <label class="block">
      <span class="text-xs uppercase tracking-widest text-beige-100/60">Headline</span>
      <input name="trial_headline" value="<?= e((string) setting('trial_headline', '')) ?>" class="mt-2 w-full rounded-2xl bg-navy-950 border border-white/5 px-4 py-3 font-serif text-lg">
    </label>
    <label class="block">
      <span class="text-xs uppercase tracking-widest text-beige-100/60">Subheadline</span>
      <textarea name="trial_subheadline" rows="3" class="mt-2 w-full rounded-2xl bg-navy-950 border border-white/5 px-4 py-3"><?= e((string) setting('trial_subheadline', '')) ?></textarea>
    </label>

    <div class="grid sm:grid-cols-2 gap-5">
      <label class="block">
        <span class="text-xs uppercase tracking-widest text-beige-100/60">CTA label</span>
        <input name="trial_cta_label" value="<?= e((string) setting('trial_cta_label', '')) ?>" class="mt-2 w-full rounded-2xl bg-navy-950 border border-white/5 px-4 py-3">
      </label>
      <label class="block">
        <span class="text-xs uppercase tracking-widest text-beige-100/60">Trial duration (days)</span>
        <input name="trial_duration_days" type="number" min="1" max="90" value="<?= (int) setting('trial_duration_days', 7) ?>" class="mt-2 w-full rounded-2xl bg-navy-950 border border-white/5 px-4 py-3">
      </label>
    </div>

    <?php media_block('trial_audio_path', 'Sample audio', 'audio/*', 'audio'); ?>
  </section>

  <section class="border border-white/5 rounded-3xl p-6 bg-navy-900/40 space-y-5">
    <div class="flex items-center justify-between gap-3 flex-wrap">
      <h2 class="font-serif text-2xl text-gold-400">Founder's story teaser</h2>
      <label class="flex items-center gap-2 text-sm text-beige-100/70">
        <input type="checkbox" name="home_story_enabled" value="1" <?= setting('home_story_enabled', true) ? 'checked' : '' ?>> Show on home page
      </label>
    </div>
    <p class="text-[11px] text-beige-100/45">A short pull-quote + excerpt linking to the full About story. Leave fields blank to keep the built-in default copy.</p>

    <label class="block">
      <span class="text-xs uppercase tracking-widest text-beige-100/60">Eyebrow text</span>
      <input name="home_story_eyebrow" value="<?= e((string) setting('home_story_eyebrow', '')) ?>" class="mt-2 w-full rounded-2xl bg-navy-950 border border-white/5 px-4 py-3">
    </label>
    <label class="block">
      <span class="text-xs uppercase tracking-widest text-beige-100/60">Pull-quote (shown large)</span>
      <textarea name="home_story_quote" rows="2" class="mt-2 w-full rounded-2xl bg-navy-950 border border-white/5 px-4 py-3"><?= e((string) setting('home_story_quote', '')) ?></textarea>
    </label>
    <label class="block">
      <span class="text-xs uppercase tracking-widest text-beige-100/60">Excerpt</span>
      <textarea name="home_story_body" rows="3" class="mt-2 w-full rounded-2xl bg-navy-950 border border-white/5 px-4 py-3"><?= e((string) setting('home_story_body', '')) ?></textarea>
    </label>
    <label class="block">
      <span class="text-xs uppercase tracking-widest text-beige-100/60">Button label</span>
      <input name="home_story_cta_label" value="<?= e((string) setting('home_story_cta_label', '')) ?>" class="mt-2 w-full rounded-2xl bg-navy-950 border border-white/5 px-4 py-3">
    </label>
    <p class="text-[11px] text-beige-100/40">The button always links to the About page.</p>
  </section>

  <section class="border border-white/5 rounded-3xl p-6 bg-navy-900/40 space-y-5">
    <div class="flex items-center justify-between gap-3 flex-wrap">
      <h2 class="font-serif text-2xl text-gold-400">Featured video</h2>
      <label class="flex items-center gap-2 text-sm text-beige-100/70">
        <input type="checkbox" name="home_video_enabled" value="1" <?= setting('home_video_enabled', true) ? 'checked' : '' ?>> Show on home page
      </label>
    </div>
    <p class="text-[11px] text-beige-100/45">Automatically features the <strong>first</strong> video from the About page → “Videos” section. Add your YouTube links there; this just controls the home-page heading and visibility.</p>
    <label class="block">
      <span class="text-xs uppercase tracking-widest text-beige-100/60">Eyebrow text</span>
      <input name="home_video_eyebrow" value="<?= e((string) setting('home_video_eyebrow', '')) ?>" class="mt-2 w-full rounded-2xl bg-navy-950 border border-white/5 px-4 py-3">
    </label>
    <label class="block">
      <span class="text-xs uppercase tracking-widest text-beige-100/60">Headline</span>
      <input name="home_video_headline" value="<?= e((string) setting('home_video_headline', '')) ?>" class="mt-2 w-full rounded-2xl bg-navy-950 border border-white/5 px-4 py-3 font-serif text-lg">
    </label>
  </section>

  <div class="flex gap-3">
    <button class="px-6 py-3 rounded-full bg-gold-500 text-navy-950 hover:bg-gold-400 transition">Save home settings</button>
    <a href="<?= url('/admin/dashboard.php') ?>" class="px-6 py-3 rounded-full border border-white/10 text-beige-100/70 hover:border-white/20">Done</a>
  </div>
</form>

<?php require __DIR__ . '/../includes/admin_layout_end.php'; ?>
