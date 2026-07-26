<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_admin();
$pageTitle = 'Products';

$editId  = (int) input('edit', 0);
$editing = null;
$formErrors = [];

if (is_post()) {
    csrf_verify();
    $action = (string) input('action', 'save');

    if ($action === 'delete') {
        $id = (int) input('id', 0);
        if ($id > 0) {
            $img = db()->prepare("SELECT cover_image FROM products WHERE id = :id");
            $img->execute([':id' => $id]);
            $existing = (string) ($img->fetchColumn() ?: '');
            db()->prepare("DELETE FROM products WHERE id = :id")->execute([':id' => $id]);
            if ($existing !== '') delete_upload($existing);
            audit_log('product.delete', 'products', $id);
            flash('products', 'Product removed.', 'success');
        }
        redirect('/admin/products.php');
    }

    if ($action === 'status') {
        $id = (int) input('id', 0);
        $to = (string) input('to', 'draft');
        if ($id > 0 && in_array($to, ['draft','published','archived'], true)) {
            db()->prepare("UPDATE products SET status = :s WHERE id = :id")
                ->execute([':s' => $to, ':id' => $id]);
            audit_log('product.status', 'products', $id, ['status' => $to]);
            flash('products', 'Status updated.', 'success');
        }
        redirect('/admin/products.php');
    }

    // Save (create or update)
    $id           = (int) input('id', 0);
    $title        = trim((string) input('title', ''));
    $subtitle     = trim((string) input('subtitle', ''));
    $description  = trim((string) input('description', ''));
    $price        = max(0.0, (float) input('price', 0));
    $sku          = trim((string) input('sku', ''));
    $stockQty     = max(0, (int) input('stock_qty', 0));
    $isPreorder   = input('is_preorder') ? 1 : 0;
    $preorderEta  = trim((string) input('preorder_eta', ''));
    $weightGrams  = (int) input('weight_grams', 0);
    $weightGrams  = $weightGrams > 0 ? $weightGrams : null;
    $category     = trim((string) input('category', ''));
    $status       = (string) input('status', 'draft');
    if (!in_array($status, ['draft','published','archived'], true)) $status = 'draft';
    $sortOrder    = (int) input('sort_order', 100);
    $slug         = trim((string) input('slug', ''));

    if ($title === '') $formErrors[] = 'Title is required.';
    if ($price < 0)    $formErrors[] = 'Price cannot be negative.';

    if ($slug === '') {
        $slug = slugify($title);
        if ($slug === '') $slug = 'product-' . bin2hex(random_bytes(3));
    } else {
        $slug = slugify($slug);
    }

    // Slug uniqueness: append -2, -3, … if needed
    if (!$formErrors) {
        $base = $slug; $n = 1;
        while (true) {
            $chk = db()->prepare("SELECT id FROM products WHERE slug = :s AND id <> :id LIMIT 1");
            $chk->execute([':s' => $slug, ':id' => $id]);
            if (!$chk->fetchColumn()) break;
            $n++;
            $slug = $base . '-' . $n;
        }
    }

    $cover = null;
    if (!$formErrors) {
        try {
            $uploaded = handle_upload('cover_image_file', 'products');
            if ($uploaded !== null) $cover = $uploaded;
        } catch (RuntimeException $ex) {
            $formErrors[] = $ex->getMessage();
        }
    }

    if (!$formErrors) {
        if ($id > 0) {
            // Wipe existing cover if a new one uploaded.
            if ($cover !== null) {
                $prev = db()->prepare("SELECT cover_image FROM products WHERE id = :id");
                $prev->execute([':id' => $id]);
                $prevPath = (string) ($prev->fetchColumn() ?: '');
                if ($prevPath !== '' && $prevPath !== $cover) delete_upload($prevPath);
            }
            $sql = "UPDATE products SET
                        slug = :slug, title = :title, subtitle = :sub, description = :desc,
                        price = :price, sku = :sku, stock_qty = :stk,
                        is_preorder = :pre, preorder_eta = :eta,
                        weight_grams = :wg, category = :cat,
                        status = :st, sort_order = :sort"
                . ($cover !== null ? ", cover_image = :cover" : '')
                . " WHERE id = :id";
            $params = [
                ':slug' => $slug, ':title' => $title, ':sub' => $subtitle ?: null,
                ':desc' => $description ?: null, ':price' => $price,
                ':sku' => $sku ?: null, ':stk' => $stockQty,
                ':pre' => $isPreorder, ':eta' => $preorderEta ?: null,
                ':wg' => $weightGrams, ':cat' => $category ?: null,
                ':st' => $status, ':sort' => $sortOrder, ':id' => $id,
            ];
            if ($cover !== null) $params[':cover'] = $cover;
            db()->prepare($sql)->execute($params);
            audit_log('product.update', 'products', $id);
            flash('products', 'Product updated.', 'success');
        } else {
            db()->prepare(
                "INSERT INTO products
                    (slug, title, subtitle, description, price, sku, stock_qty,
                     is_preorder, preorder_eta, weight_grams, category,
                     status, sort_order, cover_image, created_by)
                 VALUES
                    (:slug, :title, :sub, :desc, :price, :sku, :stk,
                     :pre, :eta, :wg, :cat,
                     :st, :sort, :cover, :by)"
            )->execute([
                ':slug' => $slug, ':title' => $title, ':sub' => $subtitle ?: null,
                ':desc' => $description ?: null, ':price' => $price,
                ':sku' => $sku ?: null, ':stk' => $stockQty,
                ':pre' => $isPreorder, ':eta' => $preorderEta ?: null,
                ':wg' => $weightGrams, ':cat' => $category ?: null,
                ':st' => $status, ':sort' => $sortOrder,
                ':cover' => $cover, ':by' => current_user_id(),
            ]);
            $newId = (int) db()->lastInsertId();
            audit_log('product.create', 'products', $newId);
            flash('products', 'Product created.', 'success');
        }
        redirect('/admin/products.php');
    }

    // Preserve form state on error
    $editing = [
        'id' => $id, 'slug' => $slug, 'title' => $title, 'subtitle' => $subtitle,
        'description' => $description, 'price' => $price, 'sku' => $sku,
        'stock_qty' => $stockQty, 'is_preorder' => $isPreorder,
        'preorder_eta' => $preorderEta, 'weight_grams' => $weightGrams,
        'category' => $category, 'status' => $status, 'sort_order' => $sortOrder,
        'cover_image' => null,
    ];
}

if ($editId > 0 && !$editing) {
    $stmt = db()->prepare("SELECT * FROM products WHERE id = :id");
    $stmt->execute([':id' => $editId]);
    $editing = $stmt->fetch() ?: null;
}

$isNewForm = !$editing && isset($_GET['edit']) && $_GET['edit'] === 'new';

$products = db()->query(
    "SELECT p.*,
            (SELECT COUNT(*) FROM order_items oi WHERE oi.product_id = p.id) AS times_ordered
       FROM products p
       ORDER BY p.status ASC, p.sort_order ASC, p.id DESC"
)->fetchAll();

require __DIR__ . '/../includes/admin_layout.php';
?>

<div class="flex items-center justify-between gap-4 flex-wrap">
  <div>
    <h1 class="font-serif text-3xl text-beige-100">Products</h1>
    <p class="text-beige-100/60 mt-1 text-sm">Shop items. Pre-order flag marks anything shipping in future; live-stock items cap on quantity.</p>
  </div>
  <?php if (!$editing && !$isNewForm): ?>
    <a href="<?= url('/admin/products.php?edit=new') ?>" class="px-5 py-2.5 rounded-full bg-gold-500 text-navy-950 hover:bg-gold-400 transition text-sm">New product</a>
  <?php endif; ?>
</div>

<?php if ($f = flash('products')): ?>
  <div class="mt-6 border rounded-2xl px-5 py-4 text-sm <?= ($f['type'] ?? 'info') === 'success' ? 'border-gold-500/40 bg-gold-500/5 text-gold-400' : 'border-white/10 bg-navy-900/40 text-beige-100/85' ?>"><?= e($f['message'] ?? '') ?></div>
<?php endif; ?>

<?php if ($editing || $isNewForm): ?>
  <?php $p = $editing ?: ['id'=>0,'slug'=>'','title'=>'','subtitle'=>'','description'=>'','price'=>0,'sku'=>'','stock_qty'=>0,'is_preorder'=>1,'preorder_eta'=>'','weight_grams'=>null,'category'=>'','status'=>'draft','sort_order'=>100,'cover_image'=>null]; ?>
  <form method="post" enctype="multipart/form-data" class="mt-8 space-y-6 max-w-3xl border border-white/5 rounded-3xl p-6 bg-navy-900/40">
    <?= csrf_field() ?>
    <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">

    <?php if (!empty($formErrors)): ?>
      <div class="border border-red-400/40 bg-red-500/5 text-red-200 rounded-2xl px-5 py-4 text-sm">
        <?php foreach ($formErrors as $err): ?><p><?= e($err) ?></p><?php endforeach; ?>
      </div>
    <?php endif; ?>

    <h2 class="font-serif text-2xl text-gold-400"><?= $p['id'] ? 'Edit product' : 'New product' ?></h2>

    <label class="block">
      <span class="text-xs uppercase tracking-widest text-beige-100/60">Title</span>
      <input name="title" required value="<?= e((string)$p['title']) ?>"
             class="mt-2 w-full rounded-2xl bg-navy-950 border border-white/5 px-4 py-3 focus:border-gold-500/50 focus:outline-none">
    </label>

    <label class="block">
      <span class="text-xs uppercase tracking-widest text-beige-100/60">Subtitle (optional, one line)</span>
      <input name="subtitle" value="<?= e((string)($p['subtitle'] ?? '')) ?>"
             class="mt-2 w-full rounded-2xl bg-navy-950 border border-white/5 px-4 py-3 focus:border-gold-500/50 focus:outline-none">
    </label>

    <label class="block">
      <span class="text-xs uppercase tracking-widest text-beige-100/60">Description</span>
      <textarea name="description" rows="5"
                class="mt-2 w-full rounded-2xl bg-navy-950 border border-white/5 px-4 py-3 focus:border-gold-500/50 focus:outline-none"><?= e((string)($p['description'] ?? '')) ?></textarea>
    </label>

    <div class="grid sm:grid-cols-3 gap-4">
      <label class="block">
        <span class="text-xs uppercase tracking-widest text-beige-100/60">Price (RM)</span>
        <input name="price" type="number" step="0.01" min="0" required value="<?= e(number_format((float)$p['price'], 2, '.', '')) ?>"
               class="mt-2 w-full rounded-2xl bg-navy-950 border border-white/5 px-4 py-3 focus:border-gold-500/50 focus:outline-none">
      </label>
      <label class="block">
        <span class="text-xs uppercase tracking-widest text-beige-100/60">SKU</span>
        <input name="sku" value="<?= e((string)($p['sku'] ?? '')) ?>"
               class="mt-2 w-full rounded-2xl bg-navy-950 border border-white/5 px-4 py-3 font-mono text-sm focus:border-gold-500/50 focus:outline-none">
      </label>
      <label class="block">
        <span class="text-xs uppercase tracking-widest text-beige-100/60">Stock qty</span>
        <input name="stock_qty" type="number" min="0" value="<?= (int)$p['stock_qty'] ?>"
               class="mt-2 w-full rounded-2xl bg-navy-950 border border-white/5 px-4 py-3 focus:border-gold-500/50 focus:outline-none">
        <span class="text-[11px] text-beige-100/40 mt-1 block">Ignored when pre-order is on.</span>
      </label>
    </div>

    <div class="border border-white/5 rounded-2xl p-4 bg-navy-950/40">
      <label class="flex items-center gap-3">
        <input name="is_preorder" type="checkbox" value="1" <?= (int)$p['is_preorder'] ? 'checked' : '' ?>
               class="w-4 h-4 rounded border-white/20 bg-navy-950">
        <span class="text-sm text-beige-100">This is a pre-order item</span>
      </label>
      <label class="block mt-3">
        <span class="text-xs uppercase tracking-widest text-beige-100/60">Pre-order ETA (shown to buyers)</span>
        <input name="preorder_eta" value="<?= e((string)($p['preorder_eta'] ?? '')) ?>" placeholder="e.g. Ships mid-September"
               class="mt-2 w-full rounded-2xl bg-navy-950 border border-white/5 px-4 py-3 focus:border-gold-500/50 focus:outline-none">
      </label>
    </div>

    <div class="grid sm:grid-cols-3 gap-4">
      <label class="block">
        <span class="text-xs uppercase tracking-widest text-beige-100/60">Category (optional)</span>
        <input name="category" value="<?= e((string)($p['category'] ?? '')) ?>" placeholder="e.g. Singing bowls"
               class="mt-2 w-full rounded-2xl bg-navy-950 border border-white/5 px-4 py-3 focus:border-gold-500/50 focus:outline-none">
      </label>
      <label class="block">
        <span class="text-xs uppercase tracking-widest text-beige-100/60">Weight (grams, optional)</span>
        <input name="weight_grams" type="number" min="0" value="<?= (int)($p['weight_grams'] ?? 0) ?>"
               class="mt-2 w-full rounded-2xl bg-navy-950 border border-white/5 px-4 py-3 focus:border-gold-500/50 focus:outline-none">
      </label>
      <label class="block">
        <span class="text-xs uppercase tracking-widest text-beige-100/60">Sort order</span>
        <input name="sort_order" type="number" value="<?= (int)$p['sort_order'] ?>"
               class="mt-2 w-full rounded-2xl bg-navy-950 border border-white/5 px-4 py-3 focus:border-gold-500/50 focus:outline-none">
      </label>
    </div>

    <div class="grid sm:grid-cols-2 gap-4">
      <label class="block">
        <span class="text-xs uppercase tracking-widest text-beige-100/60">Status</span>
        <select name="status"
                class="mt-2 w-full rounded-2xl bg-navy-950 border border-white/5 px-4 py-3 focus:border-gold-500/50 focus:outline-none">
          <option value="draft"     <?= $p['status'] === 'draft'     ? 'selected' : '' ?>>Draft (hidden)</option>
          <option value="published" <?= $p['status'] === 'published' ? 'selected' : '' ?>>Published</option>
          <option value="archived"  <?= $p['status'] === 'archived'  ? 'selected' : '' ?>>Archived</option>
        </select>
      </label>
      <label class="block">
        <span class="text-xs uppercase tracking-widest text-beige-100/60">Slug (URL)</span>
        <input name="slug" value="<?= e((string)$p['slug']) ?>" placeholder="auto-generated from title"
               class="mt-2 w-full rounded-2xl bg-navy-950 border border-white/5 px-4 py-3 font-mono text-sm focus:border-gold-500/50 focus:outline-none">
      </label>
    </div>

    <div>
      <span class="block text-xs uppercase tracking-widest text-beige-100/60">Cover image</span>
      <?php if (!empty($p['cover_image'])): ?>
        <div class="mt-2 w-40 aspect-square rounded-2xl overflow-hidden border border-white/10">
          <img src="<?= e(media_src((string)$p['cover_image'])) ?>" class="w-full h-full object-cover" alt="">
        </div>
      <?php endif; ?>
      <input name="cover_image_file" type="file" accept="image/jpeg,image/png,image/webp"
             class="mt-2 block text-sm text-beige-100/80 file:mr-4 file:px-4 file:py-2 file:rounded-full file:border-0 file:bg-gold-500 file:text-navy-950 hover:file:bg-gold-400">
      <span class="text-[11px] text-beige-100/40 mt-1 block">JPG / PNG / WebP, up to 5 MB.</span>
    </div>

    <div class="flex flex-wrap gap-3 pt-2">
      <button class="px-6 py-3 rounded-full bg-gold-500 text-navy-950 hover:bg-gold-400 transition"><?= $p['id'] ? 'Save changes' : 'Create product' ?></button>
      <a href="<?= url('/admin/products.php') ?>" class="px-6 py-3 rounded-full border border-white/10 text-beige-100/70 hover:border-white/20">Cancel</a>
    </div>
  </form>
<?php endif; ?>

<div class="mt-8 border border-white/5 rounded-3xl bg-navy-900/40 p-6">
  <h2 class="font-serif text-2xl text-gold-400">All products</h2>
  <?php if (!$products): ?>
    <p class="text-sm text-beige-100/60 mt-3">No products yet.</p>
  <?php else: ?>
    <div class="overflow-x-auto">
    <table class="mt-4 w-full text-sm">
      <thead class="text-left text-beige-100/50 text-xs uppercase tracking-wider">
        <tr>
          <th class="py-2">Title</th>
          <th>Price</th>
          <th>Stock</th>
          <th>Type</th>
          <th>Ordered</th>
          <th>Status</th>
          <th class="text-right">Actions</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-white/5">
        <?php foreach ($products as $row): ?>
          <tr>
            <td class="py-3">
              <div class="flex items-center gap-3">
                <?php if (!empty($row['cover_image'])): ?>
                  <img src="<?= e(media_src((string)$row['cover_image'])) ?>" class="w-10 h-10 object-cover rounded-lg" alt="">
                <?php else: ?>
                  <div class="w-10 h-10 rounded-lg bg-white/5"></div>
                <?php endif; ?>
                <div>
                  <p class="text-beige-100"><?= e((string)$row['title']) ?></p>
                  <p class="text-[11px] text-beige-100/45 font-mono">/<?= e((string)$row['slug']) ?></p>
                </div>
              </div>
            </td>
            <td><?= e(format_money((float)$row['price'])) ?></td>
            <td><?= (int)$row['is_preorder'] ? '—' : (int)$row['stock_qty'] ?></td>
            <td>
              <?php if ((int)$row['is_preorder']): ?>
                <span class="text-xs px-2 py-1 rounded-full bg-amber-500/15 text-amber-300">Pre-order</span>
              <?php else: ?>
                <span class="text-xs px-2 py-1 rounded-full bg-emerald-500/10 text-emerald-300">In stock</span>
              <?php endif; ?>
            </td>
            <td class="text-beige-100/70"><?= (int)$row['times_ordered'] ?></td>
            <td>
              <?php $s = (string)$row['status']; ?>
              <span class="text-xs px-2 py-1 rounded-full <?= $s === 'published' ? 'bg-gold-500/20 text-gold-400' : ($s === 'draft' ? 'bg-white/5 text-beige-100/55' : 'bg-white/5 text-beige-100/40') ?>"><?= e($s) ?></span>
            </td>
            <td class="text-right">
              <div class="inline-flex items-center gap-2">
                <a href="<?= url('/admin/products.php?edit=' . (int)$row['id']) ?>" class="text-xs text-gold-400 hover:text-gold-300">Edit</a>
                <?php $flip = $s === 'published' ? 'draft' : 'published'; ?>
                <form method="post" class="inline">
                  <?= csrf_field() ?>
                  <input type="hidden" name="action" value="status">
                  <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
                  <input type="hidden" name="to" value="<?= e($flip) ?>">
                  <button class="text-xs text-beige-100/60 hover:text-gold-400"><?= $s === 'published' ? 'Unpublish' : 'Publish' ?></button>
                </form>
                <?php if ((int)$row['times_ordered'] === 0): ?>
                  <form method="post" class="inline" onsubmit="return confirm('Delete this product permanently?');">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
                    <button class="text-xs text-red-300/80 hover:text-red-200">Delete</button>
                  </form>
                <?php endif; ?>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    </div>
  <?php endif; ?>
</div>

<?php require __DIR__ . '/../includes/admin_layout_end.php'; ?>
