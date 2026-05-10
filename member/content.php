<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_login();
$pageTitle = 'Audio Sanctuary';
$user = current_user();

$tracks = db()->query(
    "SELECT id, slug, title, description, type, file_path, cover_image, duration_seconds, access
     FROM wellness_content
     WHERE is_published = 1
     ORDER BY type ASC, created_at DESC"
)->fetchAll();

$grouped = [];
foreach ($tracks as $t) {
    $grouped[$t['type']][] = $t;
}
$typeLabels = [
    'meditation' => 'Meditations',
    'sleep'      => 'Sleep',
    'breathing'  => 'Breathwork',
    'audio'      => 'Sound journeys',
    'article'    => 'Reading',
];

require __DIR__ . '/../includes/header.php';
?>
<section class="max-w-6xl mx-auto px-6 py-16">
  <p class="text-gold-400/80 tracking-[0.3em] uppercase text-xs">Audio sanctuary</p>
  <h1 class="font-serif text-5xl text-beige-100 mt-4">A library for stillness</h1>
  <p class="mt-4 text-beige-100/70 max-w-2xl leading-relaxed">Curated tracks for meditation, sleep and breathwork. Press play, dim the lights, and let the frequencies do the work.</p>

  <?php if (!$tracks): ?>
    <p class="mt-12 text-beige-100/60 italic">New tracks are being woven into the library.</p>
  <?php else: ?>
    <?php foreach ($typeLabels as $key => $label):
      if (empty($grouped[$key])) continue; ?>
      <h2 class="mt-16 font-serif text-2xl text-gold-400"><?= e($label) ?></h2>
      <div class="mt-6 grid sm:grid-cols-2 lg:grid-cols-3 gap-5">
        <?php foreach ($grouped[$key] as $t):
          $isLocked = $t['access'] === 'premium';
          $streamUrl = url('/api/stream_content.php?id=' . (int)$t['id']);
        ?>
          <article class="border border-white/5 rounded-3xl p-5 bg-navy-900/40 hover:border-gold-500/30 transition flex flex-col">
            <?php if (!empty($t['cover_image'])): ?>
              <img src="<?= e(str_starts_with((string)$t['cover_image'], '/') ? url($t['cover_image']) : $t['cover_image']) ?>" class="aspect-[4/3] object-cover rounded-2xl border border-white/5" alt="">
            <?php else: ?>
              <div class="aspect-[4/3] rounded-2xl bg-gradient-to-br from-navy-800 to-navy-950 border border-white/5 flex items-center justify-center">
                <span class="font-serif text-4xl text-gold-400/30">◯</span>
              </div>
            <?php endif; ?>
            <h3 class="font-serif text-xl text-beige-100 mt-4"><?= e($t['title']) ?></h3>
            <?php if (!empty($t['description'])): ?>
              <p class="text-sm text-beige-100/60 mt-2 leading-relaxed line-clamp-3"><?= e($t['description']) ?></p>
            <?php endif; ?>
            <p class="text-xs text-beige-100/40 mt-3">
              <?= max(1, (int) round(((int)$t['duration_seconds'])/60)) ?> min · <span class="capitalize"><?= e($t['type']) ?></span>
              <?php if ($t['access'] !== 'public'): ?> · <span class="text-gold-400/70">Member</span><?php endif; ?>
            </p>

            <?php if ($isLocked): ?>
              <a href="<?= url('/public/membership.php') ?>" class="mt-4 inline-block text-center px-4 py-2 rounded-full border border-gold-500/40 text-gold-400 text-sm hover:bg-gold-500/10 transition">Upgrade to listen</a>
            <?php elseif ($t['type'] === 'article'): ?>
              <a href="<?= e($streamUrl) ?>" class="mt-4 inline-block text-center px-4 py-2 rounded-full bg-gold-500/20 text-gold-400 text-sm hover:bg-gold-500/30 transition">Read</a>
            <?php else: ?>
              <audio controls preload="none" class="mt-4 w-full" data-content-id="<?= (int)$t['id'] ?>">
                <source src="<?= e($streamUrl) ?>" type="audio/mpeg">
              </audio>
            <?php endif; ?>
          </article>
        <?php endforeach; ?>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>
</section>

<script>
// Record listening starts (best-effort, fire-and-forget).
document.querySelectorAll('audio[data-content-id]').forEach((el) => {
  let logged = false;
  el.addEventListener('play', () => {
    if (logged) return;
    logged = true;
    fetch('<?= url('/api/log_play.php') ?>', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': '<?= e(csrf_token()) ?>' },
      body: JSON.stringify({ content_id: parseInt(el.dataset.contentId, 10) })
    }).catch(() => {});
  });
});
</script>

<?php require __DIR__ . '/../includes/footer.php'; ?>
