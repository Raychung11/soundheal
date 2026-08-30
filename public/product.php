<?php
require_once __DIR__ . '/../includes/bootstrap.php';

$slug = trim((string) input('slug', ''));
$product = $slug !== '' ? product_get_by_slug($slug) : null;
if (!$product) {
    http_response_code(404);
    $pageTitle = 'Not found';
    require __DIR__ . '/../includes/header.php';
    echo '<div class="max-w-3xl mx-auto px-6 py-24 text-center">
            <h1 class="font-serif text-4xl text-beige-100">This piece is no longer available</h1>
            <p class="mt-4 text-beige-100/70">It may have been retired or is being reshelved. Browse the <a href="' . url('/public/shop.php') . '" class="text-gold-400 hover:text-gold-300">full shop</a>.</p>
          </div>';
    require __DIR__ . '/../includes/footer.php';
    exit;
}

$pageTitle = (string) $product['title'];

if (is_post()) {
    csrf_verify();
    $action = (string) input('action', 'add');
    if ($action === 'add') {
        $qty = max(1, (int) input('qty', 1));
        cart_add((int) $product['id'], $qty);
        flash('cart', 'Added to your cart.', 'success');
        redirect('/public/cart.php');
    }
}

$maxQty = (int) $product['is_preorder'] ? 10 : max(1, min(10, (int) $product['stock_qty']));
$outOfStock = !$product['is_preorder'] && (int) $product['stock_qty'] <= 0;
$img = (string) ($product['cover_image'] ?? '');

require __DIR__ . '/../includes/header.php';
?>

<section class="max-w-6xl mx-auto px-6 pt-12 md:pt-20 pb-24">
  <p class="text-xs text-beige-100/50">
    <a href="<?= url('/public/shop.php') ?>" class="hover:text-gold-400">Shop</a>
    <?php if (!empty($product['category'])): ?>
      <span class="mx-2 text-beige-100/30">/</span>
      <a href="<?= url('/public/shop.php?category=' . urlencode((string)$product['category'])) ?>" class="hover:text-gold-400"><?= e((string)$product['category']) ?></a>
    <?php endif; ?>
  </p>

  <div class="mt-6 grid md:grid-cols-2 gap-10">
    <div class="border border-white/5 rounded-3xl bg-navy-900/40 overflow-hidden aspect-square">
      <?php if ($img !== ''): ?>
        <img src="<?= e(media_src($img)) ?>" alt="<?= e((string)$product['title']) ?>" class="w-full h-full object-cover">
      <?php else: ?>
        <div class="w-full h-full flex items-center justify-center text-beige-100/25">No image yet</div>
      <?php endif; ?>
    </div>

    <div>
      <?php if (!empty($product['category'])): ?>
        <p class="text-[11px] uppercase tracking-[0.35em] text-gold-400/70"><?= e((string)$product['category']) ?></p>
      <?php endif; ?>
      <h1 class="font-serif text-4xl md:text-5xl text-beige-100 mt-3"><?= e((string)$product['title']) ?></h1>
      <?php if (!empty($product['subtitle'])): ?>
        <p class="text-lg text-beige-100/75 mt-3"><?= e((string)$product['subtitle']) ?></p>
      <?php endif; ?>

      <p class="font-serif text-4xl text-gold-400 mt-8"><?= e(format_money((float)$product['price'])) ?></p>

      <div class="mt-4">
        <?php if ((int)$product['is_preorder']): ?>
          <p class="inline-flex items-center gap-2 px-3 py-1.5 rounded-full bg-amber-500/10 text-amber-300 text-xs">
            <span class="w-1.5 h-1.5 rounded-full bg-amber-300"></span>
            Pre-order<?= $product['preorder_eta'] ? ' · ' . e((string)$product['preorder_eta']) : '' ?>
          </p>
        <?php elseif ($outOfStock): ?>
          <p class="inline-flex items-center gap-2 px-3 py-1.5 rounded-full bg-white/5 text-beige-100/60 text-xs">Currently sold out</p>
        <?php else: ?>
          <p class="inline-flex items-center gap-2 px-3 py-1.5 rounded-full bg-emerald-500/10 text-emerald-300 text-xs">
            <span class="w-1.5 h-1.5 rounded-full bg-emerald-300"></span>
            In stock · ready to ship
          </p>
        <?php endif; ?>
      </div>

      <?php if (!empty($product['description'])): ?>
        <div class="mt-8 text-beige-100/75 text-sm leading-[1.85] space-y-3">
          <?= render_rich_text((string) $product['description']) ?>
        </div>
      <?php endif; ?>

      <?php if (!$outOfStock): ?>
        <form method="post" class="mt-10 flex items-end gap-3">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="add">
          <label class="block">
            <span class="text-xs uppercase tracking-widest text-beige-100/60">Quantity</span>
            <input name="qty" type="number" min="1" max="<?= $maxQty ?>" value="1"
                   class="mt-2 w-24 rounded-2xl bg-navy-950 border border-white/10 px-4 py-3 text-beige-100 focus:border-gold-500/50 focus:outline-none">
          </label>
          <button class="px-6 py-3 rounded-full bg-gold-500 text-navy-950 hover:bg-gold-400 transition font-medium">
            Add to cart
          </button>
        </form>
      <?php else: ?>
        <p class="mt-10 text-sm text-beige-100/60">
          <a href="<?= url('/public/contact.php') ?>" class="text-gold-400 hover:text-gold-300">Let us know</a> if you'd like to be notified when this piece returns.
        </p>
      <?php endif; ?>

      <?php if (!empty($product['sku'])): ?>
        <p class="mt-6 text-[11px] text-beige-100/40 font-mono">SKU · <?= e((string)$product['sku']) ?></p>
      <?php endif; ?>
    </div>
  </div>
</section>

<?php require __DIR__ . '/../includes/footer.php'; ?>
