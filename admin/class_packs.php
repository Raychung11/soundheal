<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_admin();
$pageTitle = 'Class packs';

$editId = (int) input('edit', 0);
$editing = null;

if (is_post()) {
    csrf_verify();
    $action = (string) input('action', 'save');

    if ($action === 'delete') {
        $id = (int) input('id', 0);
        if ($id > 0) {
            db()->prepare("DELETE FROM class_packs WHERE id = :id")->execute([':id' => $id]);
            audit_log('class_pack.delete', 'class_packs', $id);
            flash('packs', 'Pack removed.', 'success');
        }
        redirect('/admin/class_packs.php');
    }

    if ($action === 'toggle') {
        $id = (int) input('id', 0);
        $new = (string) input('to', 'active');
        if ($id > 0 && in_array($new, ['active','inactive'], true)) {
            db()->prepare("UPDATE class_packs SET status = :s WHERE id = :id")
                ->execute([':s' => $new, ':id' => $id]);
            audit_log('class_pack.toggle', 'class_packs', $id, ['status' => $new]);
        }
        redirect('/admin/class_packs.php');
    }

    // Save (create or update)
    $id           = (int) input('id', 0);
    $name         = trim((string) input('name', ''));
    $tagline      = trim((string) input('tagline', ''));
    $description  = trim((string) input('description', ''));
    $paidCredits  = max(1, (int) input('paid_credits', 1));
    $bonusCredits = max(0, (int) input('bonus_credits', 0));
    $price        = max(0.0, (float) input('price', 0));
    $validityDays = max(0, (int) input('validity_days', 0));
    $status       = input('status', 'active') === 'inactive' ? 'inactive' : 'active';
    $sortOrder    = (int) input('sort_order', 0);
    $slug         = trim((string) input('slug', ''));

    $errors = [];
    if ($name === '')     $errors[] = 'Pack name is required.';
    if ($price <= 0)      $errors[] = 'Price must be greater than 0.';

    if ($slug === '') {
        $slug = strtolower(preg_replace('/[^a-z0-9]+/i', '-', $name));
        $slug = trim($slug, '-') ?: 'pack-' . bin2hex(random_bytes(3));
    } else {
        $slug = strtolower(preg_replace('/[^a-z0-9\-]+/', '-', $slug));
    }

    if (!$errors) {
        if ($id > 0) {
            db()->prepare(
                "UPDATE class_packs SET
                    slug = :slug, name = :name, tagline = :tag, description = :desc,
                    paid_credits = :paid, bonus_credits = :bonus, price = :price,
                    validity_days = :valid, status = :status, sort_order = :sort
                  WHERE id = :id"
            )->execute([
                ':slug' => $slug, ':name' => $name, ':tag' => $tagline ?: null, ':desc' => $description ?: null,
                ':paid' => $paidCredits, ':bonus' => $bonusCredits, ':price' => $price,
                ':valid' => $validityDays, ':status' => $status, ':sort' => $sortOrder,
                ':id' => $id,
            ]);
            audit_log('class_pack.update', 'class_packs', $id);
            flash('packs', 'Pack updated.', 'success');
        } else {
            db()->prepare(
                "INSERT INTO class_packs
                    (slug, name, tagline, description, paid_credits, bonus_credits, price, validity_days, status, sort_order)
                 VALUES
                    (:slug, :name, :tag, :desc, :paid, :bonus, :price, :valid, :status, :sort)"
            )->execute([
                ':slug' => $slug, ':name' => $name, ':tag' => $tagline ?: null, ':desc' => $description ?: null,
                ':paid' => $paidCredits, ':bonus' => $bonusCredits, ':price' => $price,
                ':valid' => $validityDays, ':status' => $status, ':sort' => $sortOrder,
            ]);
            $newId = (int) db()->lastInsertId();
            audit_log('class_pack.create', 'class_packs', $newId);
            flash('packs', 'Pack created.', 'success');
        }
        redirect('/admin/class_packs.php');
    }

    // Preserve form state on error
    $editing = [
        'id' => $id, 'slug' => $slug, 'name' => $name, 'tagline' => $tagline, 'description' => $description,
        'paid_credits' => $paidCredits, 'bonus_credits' => $bonusCredits, 'price' => $price,
        'validity_days' => $validityDays, 'status' => $status, 'sort_order' => $sortOrder,
    ];
    $formErrors = $errors;
}

if ($editId > 0 && !$editing) {
    $stmt = db()->prepare("SELECT * FROM class_packs WHERE id = :id");
    $stmt->execute([':id' => $editId]);
    $editing = $stmt->fetch() ?: null;
}

$packs = db()->query(
    "SELECT cp.*,
            (SELECT COUNT(*) FROM pack_purchases pp WHERE pp.pack_id = cp.id AND pp.status = 'active') AS active_purchases
       FROM class_packs cp
       ORDER BY status DESC, sort_order ASC, price ASC"
)->fetchAll();

require __DIR__ . '/../includes/admin_layout.php';
?>

<div class="flex items-center justify-between gap-4 flex-wrap">
  <div>
    <h1 class="font-serif text-3xl text-beige-100">Class packs</h1>
    <p class="text-beige-100/60 mt-1 text-sm">Multi-class bundles members can buy. Each credit is redeemed for one offline session.</p>
  </div>
  <?php if (!$editing): ?>
    <a href="<?= url('/admin/class_packs.php?edit=new') ?>" class="px-5 py-2.5 rounded-full bg-gold-500 text-navy-950 hover:bg-gold-400 transition text-sm">New pack</a>
  <?php endif; ?>
</div>

<?php if ($f = flash('packs')): ?>
  <div class="mt-6 border rounded-2xl px-5 py-4 text-sm <?= ($f['type'] ?? 'info') === 'success' ? 'border-gold-500/40 bg-gold-500/5 text-gold-400' : 'border-white/10 bg-navy-900/40 text-beige-100/85' ?>"><?= e($f['message'] ?? '') ?></div>
<?php endif; ?>

<?php if ($editing || $editId === 0 && isset($_GET['edit']) && $_GET['edit'] === 'new'): ?>
  <?php $e = $editing ?: ['id'=>0,'slug'=>'','name'=>'','tagline'=>'','description'=>'','paid_credits'=>4,'bonus_credits'=>1,'price'=>400,'validity_days'=>90,'status'=>'active','sort_order'=>10]; ?>
  <form method="post" class="mt-8 space-y-6 max-w-3xl border border-white/5 rounded-3xl p-6 bg-navy-900/40">
    <?= csrf_field() ?>
    <input type="hidden" name="id" value="<?= (int)$e['id'] ?>">

    <?php if (!empty($formErrors)): ?>
      <div class="border border-red-400/40 bg-red-500/5 text-red-200 rounded-2xl px-5 py-4 text-sm">
        <?php foreach ($formErrors as $err): ?><p><?= e($err) ?></p><?php endforeach; ?>
      </div>
    <?php endif; ?>

    <h2 class="font-serif text-2xl text-gold-400"><?= $e['id'] ? 'Edit pack' : 'New pack' ?></h2>

    <label class="block">
      <span class="text-xs uppercase tracking-widest text-beige-100/60">Pack name</span>
      <input name="name" required value="<?= e($e['name']) ?>" placeholder="e.g. 5-Class Pack"
             class="mt-2 w-full rounded-2xl bg-navy-950 border border-white/5 px-4 py-3 focus:border-gold-500/50 focus:outline-none">
    </label>

    <label class="block">
      <span class="text-xs uppercase tracking-widest text-beige-100/60">Tagline (short, optional)</span>
      <input name="tagline" value="<?= e($e['tagline'] ?? '') ?>" placeholder="e.g. Pay for 4, gather for 5"
             class="mt-2 w-full rounded-2xl bg-navy-950 border border-white/5 px-4 py-3 focus:border-gold-500/50 focus:outline-none">
    </label>

    <label class="block">
      <span class="text-xs uppercase tracking-widest text-beige-100/60">Description (optional)</span>
      <textarea name="description" rows="3"
                class="mt-2 w-full rounded-2xl bg-navy-950 border border-white/5 px-4 py-3 focus:border-gold-500/50 focus:outline-none"><?= e($e['description'] ?? '') ?></textarea>
    </label>

    <div class="grid sm:grid-cols-3 gap-4">
      <label class="block">
        <span class="text-xs uppercase tracking-widest text-beige-100/60">Paid credits</span>
        <input name="paid_credits" type="number" min="1" required value="<?= (int)$e['paid_credits'] ?>"
               class="mt-2 w-full rounded-2xl bg-navy-950 border border-white/5 px-4 py-3 focus:border-gold-500/50 focus:outline-none">
      </label>
      <label class="block">
        <span class="text-xs uppercase tracking-widest text-beige-100/60">Bonus credits</span>
        <input name="bonus_credits" type="number" min="0" value="<?= (int)$e['bonus_credits'] ?>"
               class="mt-2 w-full rounded-2xl bg-navy-950 border border-white/5 px-4 py-3 focus:border-gold-500/50 focus:outline-none">
      </label>
      <label class="block">
        <span class="text-xs uppercase tracking-widest text-beige-100/60">Price (RM)</span>
        <input name="price" type="number" step="0.01" min="0" required value="<?= e(number_format((float)$e['price'], 2, '.', '')) ?>"
               class="mt-2 w-full rounded-2xl bg-navy-950 border border-white/5 px-4 py-3 focus:border-gold-500/50 focus:outline-none">
      </label>
    </div>

    <div class="grid sm:grid-cols-3 gap-4">
      <label class="block">
        <span class="text-xs uppercase tracking-widest text-beige-100/60">Validity (days)</span>
        <input name="validity_days" type="number" min="0" value="<?= (int)$e['validity_days'] ?>"
               class="mt-2 w-full rounded-2xl bg-navy-950 border border-white/5 px-4 py-3 focus:border-gold-500/50 focus:outline-none">
        <span class="text-[11px] text-beige-100/40 mt-1 block">0 = no expiry.</span>
      </label>
      <label class="block">
        <span class="text-xs uppercase tracking-widest text-beige-100/60">Status</span>
        <select name="status"
                class="mt-2 w-full rounded-2xl bg-navy-950 border border-white/5 px-4 py-3 focus:border-gold-500/50 focus:outline-none">
          <option value="active"   <?= $e['status'] === 'active'   ? 'selected' : '' ?>>Active</option>
          <option value="inactive" <?= $e['status'] === 'inactive' ? 'selected' : '' ?>>Inactive</option>
        </select>
      </label>
      <label class="block">
        <span class="text-xs uppercase tracking-widest text-beige-100/60">Sort order</span>
        <input name="sort_order" type="number" value="<?= (int)$e['sort_order'] ?>"
               class="mt-2 w-full rounded-2xl bg-navy-950 border border-white/5 px-4 py-3 focus:border-gold-500/50 focus:outline-none">
      </label>
    </div>

    <label class="block">
      <span class="text-xs uppercase tracking-widest text-beige-100/60">Slug (URL-safe, optional)</span>
      <input name="slug" value="<?= e($e['slug'] ?? '') ?>" placeholder="auto-generated from name"
             class="mt-2 w-full rounded-2xl bg-navy-950 border border-white/5 px-4 py-3 font-mono text-sm focus:border-gold-500/50 focus:outline-none">
    </label>

    <div class="flex flex-wrap gap-3 pt-2">
      <button class="px-6 py-3 rounded-full bg-gold-500 text-navy-950 hover:bg-gold-400 transition"><?= $e['id'] ? 'Save changes' : 'Create pack' ?></button>
      <a href="<?= url('/admin/class_packs.php') ?>" class="px-6 py-3 rounded-full border border-white/10 text-beige-100/70 hover:border-white/20">Cancel</a>
    </div>
  </form>
<?php endif; ?>

<div class="mt-8 border border-white/5 rounded-3xl bg-navy-900/40 p-6">
  <h2 class="font-serif text-2xl text-gold-400">All packs</h2>
  <?php if (!$packs): ?>
    <p class="text-sm text-beige-100/60 mt-3">No packs yet. Create your first one above.</p>
  <?php else: ?>
    <table class="mt-4 w-full text-sm">
      <thead class="text-left text-beige-100/50 text-xs uppercase tracking-wider">
        <tr>
          <th class="py-2">Name</th>
          <th>Credits</th>
          <th>Price</th>
          <th>Validity</th>
          <th>Active buyers</th>
          <th>Status</th>
          <th class="text-right">Actions</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-white/5">
        <?php foreach ($packs as $p): ?>
          <tr>
            <td class="py-3">
              <p class="text-beige-100"><?= e($p['name']) ?></p>
              <p class="text-[11px] text-beige-100/45"><?= e($p['tagline'] ?? '') ?></p>
            </td>
            <td>
              <?= (int)$p['paid_credits'] ?>
              <?php if ((int)$p['bonus_credits'] > 0): ?>
                <span class="text-gold-400/80">+ <?= (int)$p['bonus_credits'] ?> bonus</span>
              <?php endif; ?>
            </td>
            <td><?= e(format_money((float)$p['price'])) ?></td>
            <td><?= (int)$p['validity_days'] > 0 ? (int)$p['validity_days'] . ' days' : 'No expiry' ?></td>
            <td><?= (int)$p['active_purchases'] ?></td>
            <td>
              <span class="text-xs px-2 py-1 rounded-full <?= $p['status'] === 'active' ? 'bg-gold-500/20 text-gold-400' : 'bg-white/5 text-beige-100/55' ?>"><?= e($p['status']) ?></span>
            </td>
            <td class="text-right">
              <div class="inline-flex items-center gap-2">
                <a href="<?= url('/admin/class_packs.php?edit=' . (int)$p['id']) ?>" class="text-xs text-gold-400 hover:text-gold-300">Edit</a>
                <form method="post" class="inline" onsubmit="return confirm('Toggle this pack?');">
                  <?= csrf_field() ?>
                  <input type="hidden" name="action" value="toggle">
                  <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
                  <input type="hidden" name="to" value="<?= $p['status'] === 'active' ? 'inactive' : 'active' ?>">
                  <button class="text-xs text-beige-100/60 hover:text-gold-400"><?= $p['status'] === 'active' ? 'Deactivate' : 'Activate' ?></button>
                </form>
                <?php if ((int)$p['active_purchases'] === 0): ?>
                  <form method="post" class="inline" onsubmit="return confirm('Delete this pack permanently?');">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
                    <button class="text-xs text-red-300/80 hover:text-red-200">Delete</button>
                  </form>
                <?php endif; ?>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>

<?php require __DIR__ . '/../includes/admin_layout_end.php'; ?>
