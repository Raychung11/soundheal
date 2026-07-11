<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$pageTitle = trim((string) setting('partners_page_headline', 'Our partners'));

$partners = db()->query(
    "SELECT id, name, slug, category, description, website_url, logo_url, sort_order
       FROM partners
      WHERE show_on_public_page = 1
        AND status = 'active'
      ORDER BY category IS NULL, category ASC, sort_order ASC, name ASC"
)->fetchAll();

// Group by category so each block renders under its header. Rows with
// no category fall into the 'Friends of the sanctuary' bucket at the
// end.
$grouped = [];
foreach ($partners as $p) {
    $cat = trim((string) ($p['category'] ?? ''));
    if ($cat === '') $cat = 'Friends of the sanctuary';
    $grouped[$cat][] = $p;
}

$eyebrow  = trim((string) setting('partners_page_eyebrow', 'Partners'));
$headline = trim((string) setting('partners_page_headline', 'The circle around the sound'));
$intro    = trim((string) setting('partners_page_intro', 'Friends and neighbours we hold space with.'));

require __DIR__ . '/../includes/header.php';
?>

<section class="relative overflow-hidden">
  <div class="absolute inset-0 bg-gradient-to-b from-navy-950 to-transparent"></div>
  <div class="relative max-w-6xl mx-auto px-6 py-20 md:py-28">
    <p class="text-gold-400/80 tracking-[0.4em] uppercase text-[11px]"><?= e($eyebrow) ?></p>
    <h1 class="font-serif text-5xl md:text-6xl text-beige-100 mt-6 leading-tight"><?= e($headline) ?></h1>
    <p class="mt-6 max-w-2xl text-beige-100/70 leading-[1.85] font-light"><?= e($intro) ?></p>
  </div>
</section>

<section class="max-w-6xl mx-auto px-6 pb-24 space-y-16">
  <?php if (!$partners): ?>
    <div class="border border-white/5 rounded-3xl p-12 text-center bg-navy-900/40">
      <p class="font-serif text-2xl text-beige-100/80">The circle is still forming.</p>
      <p class="mt-3 text-beige-100/60">Partners we love will appear here as we welcome them in.</p>
    </div>
  <?php else: ?>
    <?php foreach ($grouped as $cat => $rows): ?>
      <div>
        <p class="text-[11px] uppercase tracking-[0.3em] text-gold-400/80"><?= e($cat) ?></p>
        <div class="mt-6 grid sm:grid-cols-2 lg:grid-cols-3 gap-6">
          <?php foreach ($rows as $p):
            $desc = trim((string) ($p['description'] ?? ''));
            $web  = trim((string) ($p['website_url'] ?? ''));
            $logo = trim((string) ($p['logo_url'] ?? ''));
            $isLink = $web !== '';
            $tag = $isLink ? 'a' : 'div';
          ?>
            <<?= $tag ?><?php if ($isLink): ?> href="<?= e($web) ?>" target="_blank" rel="noopener"<?php endif; ?>
              class="group block border border-white/5 rounded-3xl bg-navy-900/40 <?= $isLink ? 'hover:border-gold-500/40 hover:bg-navy-900/70 transition' : '' ?> overflow-hidden flex flex-col">
              <?php if ($logo !== ''): ?>
                <div class="relative aspect-[16/10] bg-navy-950/50 flex items-center justify-center overflow-hidden">
                  <img src="<?= e($logo) ?>" alt="<?= e($p['name']) ?>" loading="lazy"
                       onerror="this.style.display='none'"
                       class="max-h-full max-w-[75%] object-contain transition duration-500 <?= $isLink ? 'group-hover:scale-[1.03]' : '' ?>">
                </div>
              <?php else: ?>
                <div class="relative aspect-[16/10] flex items-center justify-center bg-gradient-to-br from-navy-800 to-navy-950">
                  <span class="font-serif text-4xl text-gold-400/25"><?= e(mb_substr((string) $p['name'], 0, 1)) ?></span>
                </div>
              <?php endif; ?>
              <div class="p-6 flex-1 flex flex-col">
                <h3 class="font-serif text-xl text-beige-100"><?= e($p['name']) ?></h3>
                <?php if ($desc !== ''): ?>
                  <p class="mt-3 text-sm text-beige-100/70 leading-relaxed"><?= e($desc) ?></p>
                <?php endif; ?>
                <?php if ($isLink): ?>
                  <p class="mt-4 pt-4 border-t border-white/5 text-[11px] uppercase tracking-[0.3em] text-gold-400/85 group-hover:text-gold-400">
                    Visit →
                  </p>
                <?php endif; ?>
              </div>
            </<?= $tag ?>>
          <?php endforeach; ?>
        </div>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>

  <div class="border-t border-white/5 pt-10 text-center">
    <p class="text-sm text-beige-100/60">Want to bring the sound to your space?</p>
    <a href="<?= url('/public/contact.php') ?>" class="mt-4 inline-block px-6 py-3 rounded-full bg-gold-500 text-navy-950 hover:bg-gold-400 transition text-sm">Say hello</a>
  </div>
</section>

<?php require __DIR__ . '/../includes/footer.php'; ?>
