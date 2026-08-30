<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$pageTitle = 'Journal';
$pageDescription = 'Reflections, session notes and quiet moments from the sanctuary.';

$activeTag = trim((string) input('tag', ''));
$posts     = blog_list_published($activeTag !== '' ? $activeTag : null);
$tags      = blog_active_tags();

require __DIR__ . '/../includes/header.php';
?>

<section class="relative">
  <div class="absolute inset-0 bg-gradient-to-b from-navy-950 to-transparent"></div>
  <div class="relative max-w-6xl mx-auto px-6 py-24 md:py-28">
    <p class="text-gold-400/80 tracking-[0.4em] uppercase text-[11px]">Journal</p>
    <h1 class="font-serif text-5xl md:text-6xl text-beige-100 mt-6 leading-tight">Reflections from the sanctuary</h1>
    <p class="mt-6 max-w-2xl text-beige-100/70 leading-[1.85] font-light">Session notes, quiet moments, and stories from the members who've held space with us.</p>
  </div>
</section>

<section class="max-w-6xl mx-auto px-6 pb-24">
  <?php if ($tags): ?>
    <div class="flex flex-wrap gap-2 mb-8">
      <a href="<?= url('/public/blog.php') ?>"
         class="px-3 py-1.5 rounded-full text-xs border transition
                <?= $activeTag === '' ? 'border-gold-500/40 bg-gold-500/10 text-gold-400' : 'border-white/10 text-beige-100/70 hover:border-white/25' ?>">All</a>
      <?php foreach ($tags as $t): ?>
        <a href="<?= url('/public/blog.php?tag=' . urlencode((string)$t['label'])) ?>"
           class="px-3 py-1.5 rounded-full text-xs border transition
                  <?= strcasecmp($activeTag, (string)$t['label']) === 0 ? 'border-gold-500/40 bg-gold-500/10 text-gold-400' : 'border-white/10 text-beige-100/70 hover:border-white/25' ?>">
          <?= e((string)$t['label']) ?> <span class="opacity-60"><?= (int)$t['count'] ?></span>
        </a>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <?php if (!$posts): ?>
    <div class="border border-white/5 rounded-3xl p-10 bg-navy-900/40 text-center">
      <p class="text-beige-100/70">No entries here yet — we're gathering our thoughts. <a href="<?= url('/public/contact.php') ?>" class="text-gold-400 hover:text-gold-300">Say hello</a> in the meantime.</p>
    </div>
  <?php else: ?>
    <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-6">
      <?php foreach ($posts as $p):
        $postUrl = '/public/blog_post.php?slug=' . urlencode((string)$p['slug']);
      ?>
        <article class="group border border-white/5 rounded-3xl bg-navy-900/40 overflow-hidden hover:border-gold-500/30 transition flex flex-col">
          <a href="<?= url($postUrl) ?>" class="block aspect-[16/10] overflow-hidden bg-navy-950/60">
            <?php if (!empty($p['cover_image'])): ?>
              <img src="<?= e(media_src((string)$p['cover_image'])) ?>" alt="<?= e((string)$p['title']) ?>" loading="lazy"
                   class="w-full h-full object-cover transition duration-700 group-hover:scale-[1.03]">
            <?php else: ?>
              <div class="w-full h-full flex items-center justify-center text-beige-100/25">Journal</div>
            <?php endif; ?>
          </a>
          <div class="p-6 flex-1 flex flex-col">
            <p class="text-[10px] uppercase tracking-[0.3em] text-gold-400/70">
              <?= e(format_datetime((string)$p['published_at'], 'd M Y')) ?>
            </p>
            <h3 class="font-serif text-2xl text-beige-100 mt-2 leading-tight">
              <a href="<?= url($postUrl) ?>" class="hover:text-gold-400 transition"><?= e((string)$p['title']) ?></a>
            </h3>
            <?php if (!empty($p['excerpt'])): ?>
              <p class="mt-3 text-sm text-beige-100/70 leading-relaxed line-clamp-3"><?= e((string)$p['excerpt']) ?></p>
            <?php endif; ?>
            <div class="mt-auto pt-5 flex items-center justify-between text-xs">
              <?php if (!empty($p['author_name'])): ?>
                <span class="text-beige-100/50">by <?= e((string)$p['author_name']) ?></span>
              <?php else: ?><span></span><?php endif; ?>
              <a href="<?= url($postUrl) ?>" class="text-gold-400 hover:text-gold-300 whitespace-nowrap">Read →</a>
            </div>
          </div>
        </article>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</section>

<?php require __DIR__ . '/../includes/footer.php'; ?>
