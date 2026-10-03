<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$pageTitle = 'Shop';

$category   = trim((string) input('category', ''));
$products   = products_list_published($category !== '' ? $category : null);
$categories = product_categories_active();

require __DIR__ . '/../includes/header.php';
?>

<section class="relative">
  <div class="absolute inset-0 bg-gradient-to-b from-navy-950 to-transparent"></div>
  <div class="relative max-w-6xl mx-auto px-6 py-24 md:py-28">
    <p class="text-gold-400/80 tracking-[0.4em] uppercase text-[11px]">Shop</p>
    <h1 class="font-serif text-5xl md:text-6xl text-beige-100 mt-6 leading-tight">Objects to bring the practice home</h1>
    <p class="mt-6 max-w-2xl text-beige-100/70 leading-[1.85] font-light">Small-batch instruments, tools and keepsakes. Many pieces are made to order — we're honest about lead times so you know exactly when your package will land.</p>
  </div>
</section>

<section class="max-w-6xl mx-auto px-6 pb-24">
  <?php if ($categories): ?>
    <div class="flex flex-wrap gap-2 mb-8">
      <a href="<?= url('/public/shop.php') ?>"
         class="px-3 py-1.5 rounded-full text-xs border transition
                <?= $category === '' ? 'border-gold-500/40 bg-gold-500/10 text-gold-400' : 'border-white/10 text-beige-100/70 hover:border-white/25' ?>">All</a>
      <?php foreach ($categories as $c): ?>
        <a href="<?= url('/public/shop.php?category=' . urlencode((string)$c['category'])) ?>"
           class="px-3 py-1.5 rounded-full text-xs border transition
                  <?= $category === $c['category'] ? 'border-gold-500/40 bg-gold-500/10 text-gold-400' : 'border-white/10 text-beige-100/70 hover:border-white/25' ?>">
          <?= e((string)$c['category']) ?> <span class="opacity-60"><?= (int)$c['n'] ?></span>
        </a>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <?php if (!$products): ?>
    <div class="border border-white/5 rounded-3xl p-10 bg-navy-900/40 text-center">
      <p class="text-beige-100/70">New pieces are being finished by hand. Check back soon, or <a href="<?= url('/public/contact.php') ?>" class="text-gold-400 hover:text-gold-300">reach out</a> for a bespoke commission.</p>
    </div>
  <?php else: ?>
    <div class="grid sm:grid-cols-2 lg:grid-cols-3 gap-6">
      <?php foreach ($products as $p):
        $productUrl = '/public/product.php?slug=' . urlencode((string)$p['slug']);
        $img = (string) ($p['cover_image'] ?? '');
      ?>
        <article class="group border border-white/5 rounded-3xl bg-navy-900/40 overflow-hidden hover:border-gold-500/30 transition flex flex-col">
          <a href="<?= url($productUrl) ?>" class="block aspect-square overflow-hidden bg-navy-950/60">
            <?php if ($img !== ''): ?>
              <img src="<?= e(media_src($img)) ?>" alt="<?= e((string)$p['title']) ?>" loading="lazy"
                   class="w-full h-full object-cover transition duration-700 group-hover:scale-[1.03]">
            <?php else: ?>
              <div class="w-full h-full flex items-center justify-center text-beige-100/25">No image</div>
            <?php endif; ?>
          </a>
          <div class="p-6 flex-1 flex flex-col">
            <?php if (!empty($p['category'])): ?>
              <p class="text-[10px] uppercase tracking-[0.3em] text-gold-400/70"><?= e((string)$p['category']) ?></p>
            <?php endif; ?>
            <h3 class="font-serif text-2xl text-beige-100 mt-2"><a href="<?= url($productUrl) ?>" class="hover:text-gold-400 transition"><?= e((string)$p['title']) ?></a></h3>
            <?php if (!empty($p['subtitle'])): ?>
              <p class="text-sm text-beige-100/65 mt-1"><?= e((string)$p['subtitle']) ?></p>
            <?php endif; ?>
            <div class="mt-auto pt-5 flex items-end justify-between gap-3">
              <div>
                <p class="font-serif text-2xl text-gold-400"><?= e(format_money((float)$p['price'])) ?></p>
                <?php if ((int)$p['is_preorder']): ?>
                  <p class="text-[11px] text-amber-300 mt-0.5">Pre-order<?= $p['preorder_eta'] ? ' · ' . e((string)$p['preorder_eta']) : '' ?></p>
                <?php elseif ((int)$p['stock_qty'] <= 0): ?>
                  <p class="text-[11px] text-beige-100/50 mt-0.5">Sold out</p>
                <?php else: ?>
                  <p class="text-[11px] text-emerald-300 mt-0.5">In stock</p>
                <?php endif; ?>
              </div>
              <a href="<?= url($productUrl) ?>" class="text-xs text-gold-400 hover:text-gold-300 whitespace-nowrap">View →</a>
            </div>
          </div>
        </article>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</section>

<?php require __DIR__ . '/../includes/footer.php'; ?>
