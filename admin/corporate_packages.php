<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_admin();
$pageTitle = 'Corporate packages';

$errors = [];

if (is_post()) {
    csrf_verify();
    $action = (string) input('action');

    if ($action === 'save') {
        $id           = (int) input('id', 0);
        $name         = trim((string) input('name', ''));
        $slug         = trim((string) input('slug', '')) ?: slugify($name);
        $tagline      = trim((string) input('tagline', ''));
        $description  = trim((string) input('description', ''));
        $seatCount    = trim((string) input('seat_count', '')) === '' ? null : max(1, (int) input('seat_count', 0));
        $sessionCount = trim((string) input('session_count', '')) === '' ? null : max(1, (int) input('session_count', 0));
        $price        = trim((string) input('price', '')) === '' ? null : max(0.0, (float) input('price', 0));
        $status       = in_array(input('status'), ['active','inactive'], true) ? input('status') : 'active';
        $sortOrder    = (int) input('sort_order', 10);

        if ($name === '') $errors[] = 'Package name is required.';
        if (!preg_match('/^[a-z0-9\-]{2,120}$/', $slug)) $errors[] = 'Slug must be lowercase letters, numbers or hyphens.';

        // Image upload — reuse the existing upload helper.
        $image = '';
        if ($id > 0) {
            $cur = db()->prepare("SELECT image FROM corporate_packages WHERE id = :id LIMIT 1");
            $cur->execute([':id' => $id]);
            $image = (string) ($cur->fetchColumn() ?: '');
        }
        try {
            $uploaded = handle_upload('image_file', 'corporate');
            if ($uploaded) {
                if ($image && str_starts_with($image, '/uploads/')) delete_upload($image);
                $image = $uploaded;
            }
        } catch (RuntimeException $e) {
            $errors[] = $e->getMessage();
        }
        if (!empty($_POST['_clear_image']) && $image !== '') {
            if (str_starts_with($image, '/uploads/')) delete_upload($image);
            $image = '';
        }

        if (!$errors) {
            try {
                if ($id > 0) {
                    db()->prepare(
                        "UPDATE corporate_packages
                            SET slug = :slug, name = :name, tagline = :tag, description = :desc,
                                seat_count = :sc, session_count = :ss, price = :price,
                                image = :img, status = :status, sort_order = :sort
                          WHERE id = :id"
                    )->execute([
                        ':slug' => $slug, ':name' => $name, ':tag' => $tagline ?: null,
                        ':desc' => $description ?: null,
                        ':sc' => $seatCount, ':ss' => $sessionCount,
                        ':price' => $price, ':img' => $image ?: null,
                        ':status' => $status, ':sort' => $sortOrder, ':id' => $id,
                    ]);
                    audit_log('corporate_package.update', 'corporate_packages', $id);
                    flash('cp', 'Package updated.', 'success');
                } else {
                    db()->prepare(
                        "INSERT INTO corporate_packages
                            (slug, name, tagline, description, seat_count, session_count, price, image, status, sort_order)
                         VALUES (:slug, :name, :tag, :desc, :sc, :ss, :price, :img, :status, :sort)"
                    )->execute([
                        ':slug' => $slug, ':name' => $name, ':tag' => $tagline ?: null,
                        ':desc' => $description ?: null,
                        ':sc' => $seatCount, ':ss' => $sessionCount,
                        ':price' => $price, ':img' => $image ?: null,
                        ':status' => $status, ':sort' => $sortOrder,
                    ]);
                    $newId = (int) db()->lastInsertId();
                    audit_log('corporate_package.create', 'corporate_packages', $newId);
                    flash('cp', 'Package created.', 'success');
                }
                redirect('/admin/corporate_packages.php');
            } catch (Throwable $e) {
                if (str_contains((string) $e->getMessage(), '1062')) {
                    $errors[] = 'That slug is already in use — pick another.';
                } else {
                    $errors[] = 'Could not save: ' . $e->getMessage();
                }
            }
        }
    } elseif ($action === 'toggle') {
        $id = (int) input('id', 0);
        db()->prepare("UPDATE corporate_packages SET status = IF(status='active','inactive','active') WHERE id = :id")
            ->execute([':id' => $id]);
        redirect('/admin/corporate_packages.php');
    }
}

$editingId = (int) input('edit', 0);
$editing = null;
if ($editingId > 0) {
    $eStmt = db()->prepare("SELECT * FROM corporate_packages WHERE id = :id LIMIT 1");
    $eStmt->execute([':id' => $editingId]);
    $editing = $eStmt->fetch() ?: null;
}
$row = $editing ?: [
    'id' => 0, 'slug' => '', 'name' => '', 'tagline' => '', 'description' => '',
    'seat_count' => 20, 'session_count' => 1, 'price' => null, 'image' => '',
    'status' => 'active', 'sort_order' => 10,
];

$packages = db()->query(
    "SELECT * FROM corporate_packages ORDER BY status DESC, sort_order ASC, id ASC"
)->fetchAll();

require __DIR__ . '/../includes/admin_layout.php';
?>

<div class="flex items-center justify-between gap-4 flex-wrap">
  <div>
    <h1 class="font-serif text-3xl text-beige-100">Corporate packages</h1>
    <p class="text-beige-100/60 mt-1 text-sm">Displayed on <a href="<?= url('/public/corporate.php') ?>" target="_blank" class="text-gold-400 hover:text-gold-300">/public/corporate.php</a> with an inline enquiry form.</p>
  </div>
</div>

<?php foreach ($errors as $err): ?>
  <p class="mt-4 text-red-300/80 text-sm"><?= e($err) ?></p>
<?php endforeach; ?>

<form method="post" enctype="multipart/form-data" class="mt-6 border border-white/5 rounded-3xl p-6 bg-navy-900/40 space-y-5">
  <?= csrf_field() ?>
  <input type="hidden" name="action" value="save">
  <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">

  <h2 class="font-serif text-2xl text-gold-400"><?= $editing ? 'Edit package' : 'New package' ?></h2>

  <div class="grid sm:grid-cols-2 gap-4">
    <label class="block">
      <span class="text-xs uppercase tracking-widest text-beige-100/60">Name</span>
      <input name="name" required maxlength="200" value="<?= e((string) $row['name']) ?>"
             class="mt-2 w-full rounded-2xl bg-navy-950 border border-white/5 px-4 py-3">
    </label>
    <label class="block">
      <span class="text-xs uppercase tracking-widest text-beige-100/60">Slug <span class="text-beige-100/30">(auto if blank)</span></span>
      <input name="slug" maxlength="120" placeholder="team-reset-half-day"
             value="<?= e((string) $row['slug']) ?>"
             class="mt-2 w-full rounded-2xl bg-navy-950 border border-white/5 px-4 py-3 font-mono">
    </label>
    <label class="block sm:col-span-2">
      <span class="text-xs uppercase tracking-widest text-beige-100/60">Tagline</span>
      <input name="tagline" maxlength="255" value="<?= e((string) $row['tagline']) ?>"
             class="mt-2 w-full rounded-2xl bg-navy-950 border border-white/5 px-4 py-3">
    </label>
    <label class="block sm:col-span-2">
      <span class="text-xs uppercase tracking-widest text-beige-100/60">Description</span>
      <textarea name="description" rows="6"
                class="mt-2 w-full rounded-2xl bg-navy-950 border border-white/5 px-4 py-3"><?= e((string) $row['description']) ?></textarea>
      <span class="text-[11px] text-beige-100/40 mt-1 block">Blank lines split paragraphs; emoji or "-" bullet markers become a list on the public page.</span>
    </label>
    <label class="block">
      <span class="text-xs uppercase tracking-widest text-beige-100/60">Seat count <span class="text-beige-100/30">(blank = flexible)</span></span>
      <input name="seat_count" type="number" min="1" value="<?= e((string) ($row['seat_count'] ?? '')) ?>"
             class="mt-2 w-full rounded-2xl bg-navy-950 border border-white/5 px-4 py-3">
    </label>
    <label class="block">
      <span class="text-xs uppercase tracking-widest text-beige-100/60">Sessions included <span class="text-beige-100/30">(blank = one-off)</span></span>
      <input name="session_count" type="number" min="1" value="<?= e((string) ($row['session_count'] ?? '')) ?>"
             class="mt-2 w-full rounded-2xl bg-navy-950 border border-white/5 px-4 py-3">
    </label>
    <label class="block">
      <span class="text-xs uppercase tracking-widest text-beige-100/60">Price · MYR <span class="text-beige-100/30">(blank = "on request")</span></span>
      <input name="price" type="number" step="0.01" min="0" value="<?= e($row['price'] !== null ? (string) $row['price'] : '') ?>"
             class="mt-2 w-full rounded-2xl bg-navy-950 border border-white/5 px-4 py-3">
    </label>
    <label class="block">
      <span class="text-xs uppercase tracking-widest text-beige-100/60">Sort order</span>
      <input name="sort_order" type="number" value="<?= (int) $row['sort_order'] ?>"
             class="mt-2 w-full rounded-2xl bg-navy-950 border border-white/5 px-4 py-3">
    </label>
    <label class="block">
      <span class="text-xs uppercase tracking-widest text-beige-100/60">Status</span>
      <select name="status" class="mt-2 w-full rounded-2xl bg-navy-950 border border-white/5 px-4 py-3">
        <option value="active"   <?= ($row['status'] ?? '') === 'active'   ? 'selected' : '' ?>>Active</option>
        <option value="inactive" <?= ($row['status'] ?? '') === 'inactive' ? 'selected' : '' ?>>Inactive</option>
      </select>
    </label>
    <label class="block sm:col-span-2">
      <span class="text-xs uppercase tracking-widest text-beige-100/60">Cover image</span>
      <?php if (!empty($row['image'])): ?>
        <div class="mt-2 flex items-center gap-3">
          <img src="<?= e(media_src((string) $row['image'])) ?>" alt="" class="h-24 w-40 object-cover rounded-xl border border-white/10">
          <label class="text-xs text-beige-100/60 flex items-center gap-2">
            <input type="checkbox" name="_clear_image" value="1"> Clear current
          </label>
        </div>
      <?php endif; ?>
      <input type="file" name="image_file" accept="image/jpeg,image/png,image/webp"
             class="mt-2 w-full text-sm file:mr-3 file:py-2 file:px-4 file:rounded-full file:border-0 file:bg-gold-500/20 file:text-gold-400 hover:file:bg-gold-500/30">
    </label>
  </div>

  <div class="flex gap-3">
    <button class="px-6 py-3 rounded-full bg-gold-500 text-navy-950 hover:bg-gold-400 transition"><?= $editing ? 'Save changes' : 'Create package' ?></button>
    <?php if ($editing): ?>
      <a href="<?= url('/admin/corporate_packages.php') ?>" class="px-6 py-3 rounded-full border border-white/10 text-beige-100/70 hover:border-white/20">New instead</a>
    <?php endif; ?>
  </div>
</form>

<h2 class="mt-12 font-serif text-2xl text-beige-100">All packages</h2>
<div class="mt-4 border border-white/5 rounded-2xl bg-navy-900/40 overflow-x-auto">
  <table class="w-full text-sm">
    <thead class="text-left text-beige-100/50 text-xs uppercase tracking-wider bg-navy-950/40">
      <tr>
        <th class="px-4 py-3">Name</th><th>Price</th><th>Seats · Sessions</th><th>Sort</th><th>Status</th><th></th>
      </tr>
    </thead>
    <tbody class="divide-y divide-white/5">
      <?php foreach ($packages as $p): ?>
        <tr>
          <td class="px-4 py-3">
            <p class="text-beige-100"><?= e($p['name']) ?></p>
            <?php if (!empty($p['tagline'])): ?>
              <p class="text-xs text-beige-100/50 mt-0.5"><?= e($p['tagline']) ?></p>
            <?php endif; ?>
          </td>
          <td><?= $p['price'] !== null ? e(format_money((float) $p['price'])) : '<span class="text-beige-100/50">on request</span>' ?></td>
          <td class="text-beige-100/70"><?= e((string) ($p['seat_count'] ?? '—')) ?> pax · <?= e((string) ($p['session_count'] ?? '—')) ?> ses.</td>
          <td><?= (int) $p['sort_order'] ?></td>
          <td>
            <span class="text-xs px-2 py-1 rounded-full <?= $p['status'] === 'active' ? 'bg-gold-500/20 text-gold-400' : 'bg-white/5 text-beige-100/50' ?>"><?= e($p['status']) ?></span>
          </td>
          <td class="text-right pr-4 whitespace-nowrap">
            <a href="<?= url('/admin/corporate_packages.php?edit=' . (int) $p['id']) ?>" class="text-xs text-gold-400 hover:text-gold-300">Edit</a>
            <form method="post" class="inline ml-2">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="toggle">
              <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
              <button class="text-xs text-beige-100/55 hover:text-gold-400"><?= $p['status'] === 'active' ? 'Deactivate' : 'Activate' ?></button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$packages): ?>
        <tr><td colspan="6" class="px-4 py-6 text-beige-100/55">No packages yet.</td></tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>

<?php require __DIR__ . '/../includes/admin_layout_end.php'; ?>
