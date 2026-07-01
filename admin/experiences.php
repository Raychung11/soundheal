<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_admin();
$pageTitle = 'Experiences';

$editId  = (int) input('edit', 0);
$editing = null;
$formErrors = [];

if (is_post()) {
    csrf_verify();
    $action = (string) input('action', 'save');

    if ($action === 'delete') {
        $id = (int) input('id', 0);
        if ($id > 0) {
            db()->prepare("DELETE FROM experiences WHERE id = :id")->execute([':id' => $id]);
            audit_log('experience.delete', 'experiences', $id);
            flash('experiences', 'Experience removed.', 'success');
        }
        redirect('/admin/experiences.php');
    }

    if ($action === 'toggle') {
        $id = (int) input('id', 0);
        $to = (string) input('to', 'active');
        if ($id > 0 && in_array($to, ['active','inactive'], true)) {
            db()->prepare("UPDATE experiences SET status = :s WHERE id = :id")
                ->execute([':s' => $to, ':id' => $id]);
            audit_log('experience.toggle', 'experiences', $id, ['status' => $to]);
        }
        redirect('/admin/experiences.php');
    }

    // Save
    $id          = (int) input('id', 0);
    $title       = trim((string) input('title', ''));
    $duration    = trim((string) input('duration', ''));
    $description = trim((string) input('description', ''));
    $status      = input('status', 'active') === 'inactive' ? 'inactive' : 'active';
    $sortOrder   = (int) input('sort_order', 0);
    $slug        = trim((string) input('slug', ''));
    $coverImage  = trim((string) input('cover_image', ''));   // hidden input keeps current path

    if ($title === '') $formErrors[] = 'Title is required.';

    if ($slug === '') {
        $slug = strtolower(preg_replace('/[^a-z0-9]+/i', '-', $title));
        $slug = trim($slug, '-') ?: 'exp-' . bin2hex(random_bytes(3));
    } else {
        $slug = strtolower(preg_replace('/[^a-z0-9\-]+/', '-', $slug));
    }

    // Optional file upload — replaces any existing cover.
    try {
        $uploaded = handle_upload('cover_image_file', 'experiences');
        if ($uploaded) {
            if ($coverImage !== '' && str_starts_with($coverImage, '/uploads/')) {
                delete_upload($coverImage);
            }
            $coverImage = $uploaded;
        }
    } catch (Throwable $e) {
        $formErrors[] = $e->getMessage();
    }

    if (!empty($_POST['remove_cover']) && $coverImage !== '') {
        if (str_starts_with($coverImage, '/uploads/')) {
            delete_upload($coverImage);
        }
        $coverImage = '';
    }

    if (!$formErrors) {
        if ($id > 0) {
            db()->prepare(
                "UPDATE experiences
                    SET slug = :slug, title = :title, duration = :dur, description = :desc,
                        cover_image = :cover, status = :status, sort_order = :sort
                  WHERE id = :id"
            )->execute([
                ':slug' => $slug, ':title' => $title, ':dur' => $duration ?: null,
                ':desc' => $description ?: null, ':cover' => $coverImage ?: null,
                ':status' => $status, ':sort' => $sortOrder, ':id' => $id,
            ]);
            audit_log('experience.update', 'experiences', $id);
            flash('experiences', 'Experience updated.', 'success');
        } else {
            db()->prepare(
                "INSERT INTO experiences (slug, title, duration, description, cover_image, status, sort_order)
                 VALUES (:slug, :title, :dur, :desc, :cover, :status, :sort)"
            )->execute([
                ':slug' => $slug, ':title' => $title, ':dur' => $duration ?: null,
                ':desc' => $description ?: null, ':cover' => $coverImage ?: null,
                ':status' => $status, ':sort' => $sortOrder,
            ]);
            $newId = (int) db()->lastInsertId();
            audit_log('experience.create', 'experiences', $newId);
            flash('experiences', 'Experience created.', 'success');
        }

        // Auto-claim any orphan events whose title matches this experience
        // (normalised — emojis and punctuation stripped, case-insensitive).
        // Preserves manual links: only touches events with experience_id NULL.
        $expId    = $id > 0 ? $id : $newId;
        $normTitle = strtolower(preg_replace('/[^A-Za-z0-9]+/', '', $title));
        if ($expId > 0 && $normTitle !== '') {
            $linked = db()->prepare(
                "UPDATE events e
                    SET e.experience_id = :xid
                  WHERE e.experience_id IS NULL
                    AND e.parent_event_id IS NULL
                    AND (
                         LOWER(REGEXP_REPLACE(e.title, '[^A-Za-z0-9]+', ''))
                           LIKE CONCAT('%', :nt, '%')
                      OR :nt2
                           LIKE CONCAT('%', LOWER(REGEXP_REPLACE(e.title, '[^A-Za-z0-9]+', '')), '%')
                    )"
            );
            $linked->execute([':xid' => $expId, ':nt' => $normTitle, ':nt2' => $normTitle]);
            if ($linked->rowCount() > 0) {
                audit_log('experience.autolink', 'experiences', $expId, ['events_linked' => $linked->rowCount()]);
            }
        }

        redirect('/admin/experiences.php');
    }

    // Preserve form on error
    $editing = [
        'id' => $id, 'slug' => $slug, 'title' => $title, 'duration' => $duration,
        'description' => $description, 'cover_image' => $coverImage,
        'status' => $status, 'sort_order' => $sortOrder,
    ];
}

if ($editId > 0 && !$editing) {
    $stmt = db()->prepare("SELECT * FROM experiences WHERE id = :id");
    $stmt->execute([':id' => $editId]);
    $editing = $stmt->fetch() ?: null;
}

$showForm = $editing || (isset($_GET['edit']) && $_GET['edit'] === 'new');

$rows = db()->query(
    "SELECT * FROM experiences ORDER BY status DESC, sort_order ASC, title ASC"
)->fetchAll();

require __DIR__ . '/../includes/admin_layout.php';
?>

<div class="flex items-center justify-between gap-4 flex-wrap">
  <div>
    <h1 class="font-serif text-3xl text-beige-100">Experiences</h1>
    <p class="text-beige-100/60 mt-1 text-sm">Session types shown on <code class="text-gold-400/70">/public/experiences.php</code>. Inactive entries are hidden from visitors.</p>
  </div>
  <?php if (!$showForm): ?>
    <a href="<?= url('/admin/experiences.php?edit=new') ?>" class="px-5 py-2.5 rounded-full bg-gold-500 text-navy-950 hover:bg-gold-400 transition text-sm">New experience</a>
  <?php endif; ?>
</div>

<?php if ($f = flash('experiences')): ?>
  <div class="mt-6 border rounded-2xl px-5 py-4 text-sm <?= ($f['type'] ?? 'info') === 'success' ? 'border-gold-500/40 bg-gold-500/5 text-gold-400' : 'border-white/10 bg-navy-900/40 text-beige-100/85' ?>"><?= e($f['message'] ?? '') ?></div>
<?php endif; ?>

<?php if ($showForm): ?>
  <?php $e = $editing ?: ['id'=>0,'slug'=>'','title'=>'','duration'=>'','description'=>'','cover_image'=>'','status'=>'active','sort_order'=>10]; ?>
  <form method="post" enctype="multipart/form-data" class="mt-8 space-y-6 max-w-3xl border border-white/5 rounded-3xl p-6 bg-navy-900/40">
    <?= csrf_field() ?>
    <input type="hidden" name="id" value="<?= (int)$e['id'] ?>">
    <input type="hidden" name="cover_image" value="<?= e((string)($e['cover_image'] ?? '')) ?>">

    <?php if (!empty($formErrors)): ?>
      <div class="border border-red-400/40 bg-red-500/5 text-red-200 rounded-2xl px-5 py-4 text-sm">
        <?php foreach ($formErrors as $err): ?><p><?= e($err) ?></p><?php endforeach; ?>
      </div>
    <?php endif; ?>

    <h2 class="font-serif text-2xl text-gold-400"><?= $e['id'] ? 'Edit experience' : 'New experience' ?></h2>

    <label class="block">
      <span class="text-xs uppercase tracking-widest text-beige-100/60">Title</span>
      <input name="title" required value="<?= e($e['title']) ?>" placeholder="e.g. Sound Bath"
             class="mt-2 w-full rounded-2xl bg-navy-950 border border-white/5 px-4 py-3 focus:border-gold-500/50 focus:outline-none">
    </label>

    <label class="block">
      <span class="text-xs uppercase tracking-widest text-beige-100/60">Duration (short label)</span>
      <input name="duration" value="<?= e($e['duration'] ?? '') ?>" placeholder="e.g. 75 min"
             class="mt-2 w-full rounded-2xl bg-navy-950 border border-white/5 px-4 py-3 focus:border-gold-500/50 focus:outline-none">
    </label>

    <label class="block">
      <span class="text-xs uppercase tracking-widest text-beige-100/60">Description</span>
      <textarea name="description" rows="4"
                class="mt-2 w-full rounded-2xl bg-navy-950 border border-white/5 px-4 py-3 focus:border-gold-500/50 focus:outline-none"><?= e($e['description'] ?? '') ?></textarea>
    </label>

    <div class="block">
      <span class="text-xs uppercase tracking-widest text-beige-100/60">Cover image</span>
      <?php if (!empty($e['cover_image'])): ?>
        <div class="mt-2 flex items-center gap-4">
          <img src="<?= e(str_starts_with((string)$e['cover_image'], '/') ? url($e['cover_image']) : $e['cover_image']) ?>" alt="" class="h-32 w-auto rounded-xl object-cover border border-white/10">
          <label class="inline-flex items-center gap-2 text-xs text-red-300/80 hover:text-red-200">
            <input type="checkbox" name="remove_cover" value="1" class="accent-red-400"> Remove this image
          </label>
        </div>
      <?php endif; ?>
      <input type="file" name="cover_image_file" accept="image/jpeg,image/png,image/webp"
             class="mt-2 w-full text-sm text-beige-100/70 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:bg-gold-500/20 file:text-gold-400 hover:file:bg-gold-500/30">
      <span class="text-[11px] text-beige-100/40 mt-1 block">JPG, PNG, or WebP. Recommended 1600×1000 (landscape). Max 5 MB.</span>
    </div>

    <div class="grid sm:grid-cols-2 gap-4">
      <label class="block">
        <span class="text-xs uppercase tracking-widest text-beige-100/60">Status</span>
        <select name="status"
                class="mt-2 w-full rounded-2xl bg-navy-950 border border-white/5 px-4 py-3 focus:border-gold-500/50 focus:outline-none">
          <option value="active"   <?= $e['status'] === 'active'   ? 'selected' : '' ?>>Active (visible)</option>
          <option value="inactive" <?= $e['status'] === 'inactive' ? 'selected' : '' ?>>Inactive (hidden)</option>
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
      <input name="slug" value="<?= e($e['slug'] ?? '') ?>" placeholder="auto-generated from title"
             class="mt-2 w-full rounded-2xl bg-navy-950 border border-white/5 px-4 py-3 font-mono text-sm focus:border-gold-500/50 focus:outline-none">
    </label>

    <div class="flex flex-wrap gap-3 pt-2">
      <button class="px-6 py-3 rounded-full bg-gold-500 text-navy-950 hover:bg-gold-400 transition"><?= $e['id'] ? 'Save changes' : 'Create experience' ?></button>
      <a href="<?= url('/admin/experiences.php') ?>" class="px-6 py-3 rounded-full border border-white/10 text-beige-100/70 hover:border-white/20">Cancel</a>
    </div>
  </form>
<?php endif; ?>

<div class="mt-8 border border-white/5 rounded-3xl bg-navy-900/40 p-6">
  <h2 class="font-serif text-2xl text-gold-400">All experiences</h2>
  <?php if (!$rows): ?>
    <p class="text-sm text-beige-100/60 mt-3">No experiences yet. Create your first one above.</p>
  <?php else: ?>
    <table class="mt-4 w-full text-sm">
      <thead class="text-left text-beige-100/50 text-xs uppercase tracking-wider">
        <tr>
          <th class="py-2">Title</th>
          <th>Duration</th>
          <th>Sort</th>
          <th>Status</th>
          <th class="text-right">Actions</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-white/5">
        <?php foreach ($rows as $r): ?>
          <tr>
            <td class="py-3">
              <div class="flex items-center gap-3">
                <?php if (!empty($r['cover_image'])): ?>
                  <img src="<?= e(str_starts_with((string)$r['cover_image'], '/') ? url($r['cover_image']) : $r['cover_image']) ?>" alt="" class="h-10 w-14 rounded-md object-cover border border-white/5">
                <?php else: ?>
                  <div class="h-10 w-14 rounded-md border border-white/5 bg-navy-950/50"></div>
                <?php endif; ?>
                <div>
                  <p class="text-beige-100"><?= e($r['title']) ?></p>
                  <?php if (!empty($r['description'])): ?>
                    <p class="text-[11px] text-beige-100/45 mt-0.5 line-clamp-2 max-w-md"><?= e(mb_strimwidth((string)$r['description'], 0, 110, '…')) ?></p>
                  <?php endif; ?>
                </div>
              </div>
            </td>
            <td class="text-beige-100/70"><?= e($r['duration'] ?? '—') ?></td>
            <td class="text-beige-100/70"><?= (int)$r['sort_order'] ?></td>
            <td>
              <span class="text-xs px-2 py-1 rounded-full <?= $r['status'] === 'active' ? 'bg-gold-500/20 text-gold-400' : 'bg-white/5 text-beige-100/55' ?>"><?= e($r['status']) ?></span>
            </td>
            <td class="text-right">
              <div class="inline-flex items-center gap-3">
                <a href="<?= url('/admin/experiences.php?edit=' . (int)$r['id']) ?>" class="text-xs text-gold-400 hover:text-gold-300">Edit</a>
                <form method="post" class="inline">
                  <?= csrf_field() ?>
                  <input type="hidden" name="action" value="toggle">
                  <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                  <input type="hidden" name="to" value="<?= $r['status'] === 'active' ? 'inactive' : 'active' ?>">
                  <button class="text-xs text-beige-100/60 hover:text-gold-400"><?= $r['status'] === 'active' ? 'Deactivate' : 'Activate' ?></button>
                </form>
                <form method="post" class="inline" onsubmit="return confirm('Delete this experience permanently?');">
                  <?= csrf_field() ?>
                  <input type="hidden" name="action" value="delete">
                  <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                  <button class="text-xs text-red-300/80 hover:text-red-200">Delete</button>
                </form>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>

<?php require __DIR__ . '/../includes/admin_layout_end.php'; ?>
