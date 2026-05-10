<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_admin();
$pageTitle = 'About Page';

$errors = [];

$textKeys = [
    'about_hero_eyebrow'         => 'string',
    'about_hero_headline'        => 'string',
    'about_story_paragraphs'     => 'text',
    'about_principle_1_label'    => 'string',
    'about_principle_1_body'     => 'text',
    'about_principle_2_label'    => 'string',
    'about_principle_2_body'     => 'text',
    'about_principle_3_label'    => 'string',
    'about_principle_3_body'     => 'text',
    'about_closing_eyebrow'      => 'string',
    'about_closing_headline'     => 'string',
    'about_closing_body'         => 'text',
];

$imageKeys = [
    'about_hero_image_path',
    'about_story_image_path',
    'about_principle_1_image_path',
    'about_principle_2_image_path',
    'about_principle_3_image_path',
    'about_closing_image_path',
];

if (is_post()) {
    csrf_verify();

    foreach ($textKeys as $k => $type) {
        if (array_key_exists($k, $_POST)) {
            set_setting($k, is_string($_POST[$k]) ? trim($_POST[$k]) : $_POST[$k], $type);
        }
    }

    foreach ($imageKeys as $key) {
        try {
            $uploaded = handle_upload($key . '_file', 'home');
            if ($uploaded) {
                $existing = (string) setting($key, '');
                if (str_starts_with($existing, '/uploads/')) delete_upload($existing);
                set_setting($key, $uploaded, 'string');
                continue;
            }
        } catch (RuntimeException $e) {
            $errors[] = $key . ': ' . $e->getMessage();
        }

        $urlField = '_url_' . $key;
        if (!empty($_POST[$urlField])) {
            $existing = (string) setting($key, '');
            if (str_starts_with($existing, '/uploads/')) delete_upload($existing);
            set_setting($key, trim((string) $_POST[$urlField]), 'string');
        } elseif (!empty($_POST['_clear_' . $key])) {
            $existing = (string) setting($key, '');
            if (str_starts_with($existing, '/uploads/')) delete_upload($existing);
            set_setting($key, '', 'string');
        }
    }

    audit_log('about_settings.update', 'site_settings', null);
    flash('about', 'About page saved.', 'success');
    redirect('/admin/about_settings.php');
}

require __DIR__ . '/../includes/admin_layout.php';

function image_block(string $key, string $label): void
{
    $current = (string) setting($key, '');
    $src = media_src($current);
    ?>
    <div class="border border-white/5 rounded-2xl p-5 bg-navy-950/40 space-y-3">
      <div class="flex items-center justify-between gap-3">
        <p class="text-xs uppercase tracking-widest text-gold-400/80"><?= e($label) ?></p>
        <?php if ($current): ?>
          <label class="flex items-center gap-2 text-xs text-beige-100/60">
            <input type="checkbox" name="_clear_<?= e($key) ?>" value="1"> Clear
          </label>
        <?php endif; ?>
      </div>

      <?php if ($src): ?>
        <img src="<?= e($src) ?>" class="h-32 w-full rounded-xl object-cover border border-white/10" alt="">
      <?php endif; ?>

      <label class="block text-sm text-beige-100/70">
        Upload (replace)
        <input type="file" name="<?= e($key) ?>_file" accept="image/jpeg,image/png,image/webp" class="mt-1 w-full text-sm file:mr-3 file:py-2 file:px-4 file:rounded-full file:border-0 file:bg-gold-500/20 file:text-gold-400 hover:file:bg-gold-500/30">
      </label>
      <label class="block text-sm text-beige-100/70">
        …or paste an image URL
        <input type="url" name="_url_<?= e($key) ?>" placeholder="https://images.unsplash.com/…" class="mt-1 w-full rounded-full bg-navy-900 border border-white/5 px-4 py-2 text-sm">
      </label>
      <p class="text-[11px] text-beige-100/40 break-all">Current: <?= $current ? e($current) : '—' ?></p>
    </div>
    <?php
}

function text_input(string $key, string $label, string $placeholder = ''): void
{
    ?>
    <label class="block">
      <span class="text-xs uppercase tracking-widest text-beige-100/60"><?= e($label) ?></span>
      <input name="<?= e($key) ?>" placeholder="<?= e($placeholder) ?>" value="<?= e((string) setting($key, '')) ?>" class="mt-2 w-full rounded-2xl bg-navy-950 border border-white/5 px-4 py-3">
    </label>
    <?php
}

function text_area(string $key, string $label, int $rows = 3): void
{
    ?>
    <label class="block">
      <span class="text-xs uppercase tracking-widest text-beige-100/60"><?= e($label) ?></span>
      <textarea name="<?= e($key) ?>" rows="<?= (int) $rows ?>" class="mt-2 w-full rounded-2xl bg-navy-950 border border-white/5 px-4 py-3"><?= e((string) setting($key, '')) ?></textarea>
    </label>
    <?php
}
?>

<div class="flex items-center justify-between gap-4 flex-wrap">
  <div>
    <h1 class="font-serif text-3xl text-beige-100">About page</h1>
    <p class="text-beige-100/60 mt-1 text-sm">Hero, story, principles and closing — copy and imagery.</p>
  </div>
  <a href="<?= url('/public/about.php') ?>" target="_blank" class="text-sm text-gold-400 hover:text-gold-300">View live →</a>
</div>

<?php foreach ($errors as $err): ?>
  <p class="mt-3 text-red-300/80 text-sm"><?= e($err) ?></p>
<?php endforeach; ?>

<form method="post" enctype="multipart/form-data" action="<?= url('/admin/about_settings.php') ?>" class="mt-8 space-y-10 max-w-4xl">
  <?= csrf_field() ?>

  <section class="border border-white/5 rounded-3xl p-6 bg-navy-900/40 space-y-5">
    <h2 class="font-serif text-2xl text-gold-400">Hero</h2>
    <?php text_input('about_hero_eyebrow', 'Eyebrow text'); ?>
    <?php text_input('about_hero_headline', 'Headline'); ?>
    <?php image_block('about_hero_image_path', 'Hero image'); ?>
    <p class="text-[11px] text-beige-100/40">Recommended: 1800 × 900 px (2:1), JPEG/WebP.</p>
  </section>

  <section class="border border-white/5 rounded-3xl p-6 bg-navy-900/40 space-y-5">
    <h2 class="font-serif text-2xl text-gold-400">Story</h2>
    <?php text_area('about_story_paragraphs', 'Paragraphs (separate with a blank line)', 7); ?>
    <?php image_block('about_story_image_path', 'Story image (optional)'); ?>
    <p class="text-[11px] text-beige-100/40">Recommended: 1200 × 1500 px (4:5 portrait), JPEG/WebP.</p>
  </section>

  <section class="border border-white/5 rounded-3xl p-6 bg-navy-900/40 space-y-5">
    <h2 class="font-serif text-2xl text-gold-400">Principles</h2>
    <?php for ($i = 1; $i <= 3; $i++): ?>
      <div class="border-t border-white/5 pt-5 grid sm:grid-cols-[1fr_280px] gap-5">
        <div class="space-y-4">
          <?php text_input("about_principle_{$i}_label", "Principle {$i} · label", "Listen / Hold / Return"); ?>
          <?php text_area("about_principle_{$i}_body", "Principle {$i} · body", 3); ?>
        </div>
        <?php image_block("about_principle_{$i}_image_path", "Principle {$i} · image"); ?>
      </div>
    <?php endfor; ?>
    <p class="text-[11px] text-beige-100/40">Recommended: 900 × 900 px (square), JPEG/WebP.</p>
  </section>

  <section class="border border-white/5 rounded-3xl p-6 bg-navy-900/40 space-y-5">
    <h2 class="font-serif text-2xl text-gold-400">Closing</h2>
    <?php text_input('about_closing_eyebrow', 'Eyebrow text'); ?>
    <?php text_input('about_closing_headline', 'Headline'); ?>
    <?php text_area('about_closing_body', 'Body', 3); ?>
    <?php image_block('about_closing_image_path', 'Closing background image'); ?>
    <p class="text-[11px] text-beige-100/40">Recommended: 1800 × 900 px (2:1), JPEG/WebP.</p>
  </section>

  <div class="flex gap-3">
    <button class="px-6 py-3 rounded-full bg-gold-500 text-navy-950 hover:bg-gold-400 transition">Save about page</button>
    <a href="<?= url('/admin/dashboard.php') ?>" class="px-6 py-3 rounded-full border border-white/10 text-beige-100/70 hover:border-white/20">Done</a>
  </div>
</form>

<?php require __DIR__ . '/../includes/admin_layout_end.php'; ?>
