<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_staff_or_admin();
$pageTitle = 'Gallery';

$errors = [];

if (is_post()) {
    csrf_verify();
    $action = (string) input('action');

    if ($action === 'upload') {
        $caption    = trim((string) input('caption', ''));
        $category   = trim((string) input('category', ''));
        $eventId    = (int) input('event_id', 0) ?: null;
        $sortOrder  = (int) input('sort_order', 100);

        // Accept a multi-file upload — the browser posts the files as
        // gallery_files[] and we iterate. Falls back to the single
        // 'gallery_file' field if only one photo came through.
        $files = [];
        if (!empty($_FILES['gallery_files']['name'][0])) {
            for ($i = 0, $n = count($_FILES['gallery_files']['name']); $i < $n; $i++) {
                $files[] = "gallery_files_$i";
                // Re-shape one row into the flat $_FILES[key] format
                // handle_upload() expects.
                $_FILES["gallery_files_$i"] = [
                    'name'     => $_FILES['gallery_files']['name'][$i],
                    'type'     => $_FILES['gallery_files']['type'][$i],
                    'tmp_name' => $_FILES['gallery_files']['tmp_name'][$i],
                    'error'    => $_FILES['gallery_files']['error'][$i],
                    'size'     => $_FILES['gallery_files']['size'][$i],
                ];
            }
        } elseif (!empty($_FILES['gallery_file']['name'])) {
            $files[] = 'gallery_file';
        }

        if (!$files) {
            $errors[] = 'Please pick at least one photo.';
        }

        $uploadedCount = 0;
        foreach ($files as $field) {
            try {
                $path = handle_upload($field, 'gallery');
                if (!$path) continue;
                db()->prepare(
                    "INSERT INTO gallery_photos (image, caption, category, event_id, sort_order, created_by)
                     VALUES (:i, :c, :cat, :e, :s, :u)"
                )->execute([
                    ':i'   => $path,
                    ':c'   => $caption ?: null,
                    ':cat' => $category ?: null,
                    ':e'   => $eventId,
                    ':s'   => $sortOrder,
                    ':u'   => current_user_id(),
                ]);
                $uploadedCount++;
            } catch (RuntimeException $e) {
                $errors[] = $e->getMessage();
            }
        }
        if ($uploadedCount > 0) {
            audit_log('gallery.upload', 'gallery_photos', null, ['count' => $uploadedCount]);
            flash('gallery', $uploadedCount === 1 ? 'Photo added.' : $uploadedCount . ' photos added.', 'success');
            redirect('/admin/gallery.php');
        }
    } elseif ($action === 'add_video') {
        $videoUrl  = trim((string) input('video_url', ''));
        $caption   = trim((string) input('caption', ''));
        $category  = trim((string) input('category', ''));
        $eventId   = (int) input('event_id', 0) ?: null;
        $sortOrder = (int) input('sort_order', 100);

        // Reject anything we can't turn into a playable embed. Reusing
        // the resolver keeps this in sync with what the public
        // lightbox will actually render.
        if ($videoUrl === '' || gallery_video_embed_url($videoUrl) === '') {
            $errors[] = 'Please paste a YouTube or Vimeo URL. Other platforms aren\'t supported yet.';
        }

        // Optional custom thumbnail — required for Vimeo (no free
        // thumbnail API); optional for YouTube (falls back to
        // i.ytimg.com/vi/<id>/hqdefault.jpg).
        $thumbPath = null;
        if (empty($errors)) {
            try {
                $thumbPath = handle_upload('gallery_file', 'gallery');
            } catch (RuntimeException $e) {
                $errors[] = $e->getMessage();
            }
            if (!$errors && !$thumbPath && vimeo_id($videoUrl) !== '' && youtube_id($videoUrl) === '') {
                $errors[] = 'Vimeo videos need a thumbnail image — please upload one.';
            }
        }

        if (!$errors) {
            db()->prepare(
                "INSERT INTO gallery_photos (image, video_url, caption, category, event_id, sort_order, created_by)
                 VALUES (:i, :v, :c, :cat, :e, :s, :u)"
            )->execute([
                ':i'   => $thumbPath,
                ':v'   => $videoUrl,
                ':c'   => $caption ?: null,
                ':cat' => $category ?: null,
                ':e'   => $eventId,
                ':s'   => $sortOrder,
                ':u'   => current_user_id(),
            ]);
            audit_log('gallery.add_video', 'gallery_photos', (int) db()->lastInsertId());
            flash('gallery', 'Video added.', 'success');
            redirect('/admin/gallery.php');
        }
    } elseif ($action === 'save') {
        $id        = (int) input('id', 0);
        $caption   = trim((string) input('caption', ''));
        $category  = trim((string) input('category', ''));
        $eventId   = (int) input('event_id', 0) ?: null;
        $sortOrder = (int) input('sort_order', 100);
        $status    = in_array(input('status'), ['visible','hidden'], true) ? input('status') : 'visible';
        $videoUrl  = trim((string) input('video_url', ''));
        // Empty string means "clear the video" — treat as NULL.
        if ($videoUrl !== '' && gallery_video_embed_url($videoUrl) === '') {
            $errors[] = 'Video URL must be a YouTube or Vimeo link.';
        }
        if ($id > 0 && !$errors) {
            db()->prepare(
                "UPDATE gallery_photos SET caption = :c, category = :cat, event_id = :e,
                    sort_order = :s, status = :st, video_url = :v WHERE id = :id"
            )->execute([
                ':c'   => $caption ?: null,
                ':cat' => $category ?: null,
                ':e'   => $eventId,
                ':s'   => $sortOrder,
                ':st'  => $status,
                ':v'   => $videoUrl ?: null,
                ':id'  => $id,
            ]);
            audit_log('gallery.update', 'gallery_photos', $id);
            flash('gallery', 'Item updated.', 'success');
        }
        redirect('/admin/gallery.php');
    } elseif ($action === 'toggle') {
        $id = (int) input('id', 0);
        db()->prepare("UPDATE gallery_photos SET status = IF(status='visible','hidden','visible') WHERE id = :id")
            ->execute([':id' => $id]);
        audit_log('gallery.toggle', 'gallery_photos', $id);
        redirect('/admin/gallery.php');
    } elseif ($action === 'delete') {
        $id = (int) input('id', 0);
        $cur = db()->prepare("SELECT image FROM gallery_photos WHERE id = :id LIMIT 1");
        $cur->execute([':id' => $id]);
        $path = (string) ($cur->fetchColumn() ?: '');
        if ($path !== '') delete_upload($path);
        db()->prepare("DELETE FROM gallery_photos WHERE id = :id")->execute([':id' => $id]);
        audit_log('gallery.delete', 'gallery_photos', $id);
        flash('gallery', 'Photo removed.', 'info');
        redirect('/admin/gallery.php');
    }
}

$photos = db()->query(
    "SELECT g.*, e.title AS event_title
       FROM gallery_photos g
       LEFT JOIN events e ON e.id = g.event_id
      ORDER BY g.sort_order ASC, g.id DESC"
)->fetchAll();

$eventOptions = db()->query(
    "SELECT id, title, starts_at FROM events
      WHERE status IN ('published','archived') AND parent_event_id IS NULL
      ORDER BY starts_at DESC LIMIT 100"
)->fetchAll();

// Distinct existing categories → surface them as a datalist for the
// upload form so admins reuse the same tag spelling.
$categorySuggestions = db()->query(
    "SELECT DISTINCT category FROM gallery_photos
      WHERE category IS NOT NULL AND category <> ''
      ORDER BY category ASC"
)->fetchAll(PDO::FETCH_COLUMN);

require __DIR__ . '/../includes/admin_layout.php';
?>

<div class="flex items-center justify-between gap-4 flex-wrap">
  <div>
    <h1 class="font-serif text-3xl text-beige-100">Gallery</h1>
    <p class="text-beige-100/60 mt-1 text-sm">Public-facing photo grid at <a href="<?= url('/public/gallery.php') ?>" target="_blank" class="text-gold-400 hover:text-gold-300">/public/gallery.php</a>. Upload activity photos, tag with a category, hide any that shouldn't be public.</p>
  </div>
</div>

<?php foreach ($errors as $err): ?>
  <p class="mt-4 text-red-300/80 text-sm"><?= e($err) ?></p>
<?php endforeach; ?>

<!-- Upload form. Multi-file — admin can drop several photos at once
     and each gets its own row. Category + event stamped on all of
     them; edit per-photo afterwards for finer tuning. -->
<form method="post" enctype="multipart/form-data" class="mt-6 border border-white/5 rounded-3xl p-6 bg-navy-900/40 space-y-5">
  <?= csrf_field() ?>
  <input type="hidden" name="action" value="upload">
  <h2 class="font-serif text-2xl text-gold-400">Add photos</h2>

  <label class="block">
    <span class="text-xs uppercase tracking-widest text-beige-100/60">Photos <span class="text-beige-100/30">(JPG / PNG / WebP, up to 8 MB each)</span></span>
    <input type="file" name="gallery_files[]" accept="image/jpeg,image/png,image/webp" multiple required
           class="mt-2 w-full text-sm text-beige-100/70 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:bg-gold-500/20 file:text-gold-400 hover:file:bg-gold-500/30">
  </label>

  <div class="grid sm:grid-cols-3 gap-4">
    <label class="block sm:col-span-2">
      <span class="text-xs uppercase tracking-widest text-beige-100/60">Caption <span class="text-beige-100/30">(optional — applied to all uploads)</span></span>
      <input name="caption" maxlength="255" class="mt-2 w-full rounded-2xl bg-navy-900 border border-white/5 px-4 py-3">
    </label>
    <label class="block">
      <span class="text-xs uppercase tracking-widest text-beige-100/60">Category</span>
      <input name="category" list="gallery-category-list" maxlength="80" placeholder="e.g. Gong Bath"
             class="mt-2 w-full rounded-2xl bg-navy-900 border border-white/5 px-4 py-3">
      <datalist id="gallery-category-list">
        <?php foreach ($categorySuggestions as $cat): ?>
          <option value="<?= e($cat) ?>">
        <?php endforeach; ?>
      </datalist>
    </label>
    <label class="block sm:col-span-2">
      <span class="text-xs uppercase tracking-widest text-beige-100/60">From event <span class="text-beige-100/30">(optional)</span></span>
      <select name="event_id" class="mt-2 w-full rounded-2xl bg-navy-900 border border-white/5 px-4 py-3">
        <option value="">— not linked —</option>
        <?php foreach ($eventOptions as $eo): ?>
          <option value="<?= (int) $eo['id'] ?>"><?= e($eo['title']) ?> · <?= e(format_datetime($eo['starts_at'], 'd M Y')) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <label class="block">
      <span class="text-xs uppercase tracking-widest text-beige-100/60">Sort order</span>
      <input name="sort_order" type="number" value="100" class="mt-2 w-full rounded-2xl bg-navy-900 border border-white/5 px-4 py-3">
      <span class="text-[11px] text-beige-100/40 mt-1 block">Lower = appears first.</span>
    </label>
  </div>

  <button class="px-6 py-3 rounded-full bg-gold-500 text-navy-950 font-medium hover:bg-gold-400 transition">
    Upload
  </button>
</form>

<!-- Add video (YouTube / Vimeo) as a first-class gallery item.
     Kept as a separate form so admins pick photos OR videos per
     submit — no ambiguous "both are set" state. -->
<form method="post" enctype="multipart/form-data" class="mt-6 border border-white/5 rounded-3xl p-6 bg-navy-900/40 space-y-5">
  <?= csrf_field() ?>
  <input type="hidden" name="action" value="add_video">
  <h2 class="font-serif text-2xl text-gold-400">Add a video</h2>
  <p class="text-[11px] text-beige-100/45">Paste a YouTube or Vimeo URL — the tile shows a play overlay on the gallery and the lightbox plays the embed. YouTube auto-fills the thumbnail; Vimeo needs one uploaded below.</p>

  <label class="block">
    <span class="text-xs uppercase tracking-widest text-beige-100/60">Video URL</span>
    <input name="video_url" required maxlength="500" placeholder="https://www.youtube.com/watch?v=… or https://vimeo.com/…"
           class="mt-2 w-full rounded-2xl bg-navy-900 border border-white/5 px-4 py-3 font-mono text-sm">
  </label>

  <label class="block">
    <span class="text-xs uppercase tracking-widest text-beige-100/60">Custom thumbnail <span class="text-beige-100/30">(optional for YouTube · required for Vimeo)</span></span>
    <input type="file" name="gallery_file" accept="image/jpeg,image/png,image/webp"
           class="mt-2 w-full text-sm text-beige-100/70 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:bg-gold-500/20 file:text-gold-400 hover:file:bg-gold-500/30">
  </label>

  <div class="grid sm:grid-cols-3 gap-4">
    <label class="block sm:col-span-2">
      <span class="text-xs uppercase tracking-widest text-beige-100/60">Caption</span>
      <input name="caption" maxlength="255" class="mt-2 w-full rounded-2xl bg-navy-900 border border-white/5 px-4 py-3">
    </label>
    <label class="block">
      <span class="text-xs uppercase tracking-widest text-beige-100/60">Category</span>
      <input name="category" list="gallery-category-list" maxlength="80"
             class="mt-2 w-full rounded-2xl bg-navy-900 border border-white/5 px-4 py-3">
    </label>
    <label class="block sm:col-span-2">
      <span class="text-xs uppercase tracking-widest text-beige-100/60">From event <span class="text-beige-100/30">(optional)</span></span>
      <select name="event_id" class="mt-2 w-full rounded-2xl bg-navy-900 border border-white/5 px-4 py-3">
        <option value="">— not linked —</option>
        <?php foreach ($eventOptions as $eo): ?>
          <option value="<?= (int) $eo['id'] ?>"><?= e($eo['title']) ?> · <?= e(format_datetime($eo['starts_at'], 'd M Y')) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <label class="block">
      <span class="text-xs uppercase tracking-widest text-beige-100/60">Sort order</span>
      <input name="sort_order" type="number" value="100" class="mt-2 w-full rounded-2xl bg-navy-900 border border-white/5 px-4 py-3">
    </label>
  </div>

  <button class="px-6 py-3 rounded-full bg-gold-500 text-navy-950 font-medium hover:bg-gold-400 transition">
    Add video
  </button>
</form>

<!-- Photo grid. Compact tiles with per-photo controls. Clicking Edit
     opens an inline form; keeps the page single-URL. -->
<div class="mt-10">
  <h2 class="font-serif text-2xl text-beige-100">All photos (<?= count($photos) ?>)</h2>
  <?php if (!$photos): ?>
    <p class="mt-4 text-beige-100/60 italic">No photos yet. Upload the first ones above.</p>
  <?php else: ?>
    <div class="mt-5 grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-4">
      <?php foreach ($photos as $p):
        $thumb = gallery_thumbnail_url($p);
        $isVideo = !empty($p['video_url']);
      ?>
        <article class="rounded-2xl border border-white/5 bg-navy-900/40 overflow-hidden <?= $p['status'] === 'hidden' ? 'opacity-60' : '' ?>"
                 x-data="{ editing: false }">
          <div class="relative aspect-square bg-navy-950">
            <?php if ($thumb !== ''): ?>
              <img src="<?= e($thumb) ?>" alt="<?= e((string) ($p['caption'] ?? '')) ?>"
                   loading="lazy" class="absolute inset-0 w-full h-full object-cover">
            <?php else: ?>
              <span class="absolute inset-0 flex items-center justify-center font-serif text-4xl text-gold-400/30">◯</span>
            <?php endif; ?>
            <?php if ($isVideo): ?>
              <span class="absolute inset-0 flex items-center justify-center pointer-events-none">
                <span class="h-12 w-12 rounded-full bg-navy-950/70 border border-gold-500/50 flex items-center justify-center backdrop-blur">
                  <svg class="h-5 w-5 text-gold-400 translate-x-0.5" viewBox="0 0 24 24" fill="currentColor"><path d="M8 5v14l11-7z"/></svg>
                </span>
              </span>
              <span class="absolute top-2 right-2 text-[10px] uppercase tracking-widest px-2 py-0.5 rounded-full bg-navy-950/80 text-gold-400 border border-gold-500/40">Video</span>
            <?php endif; ?>
            <?php if ($p['status'] === 'hidden'): ?>
              <span class="absolute top-2 left-2 text-[10px] uppercase tracking-widest px-2 py-0.5 rounded-full bg-navy-950/80 text-beige-100/60 border border-white/10">Hidden</span>
            <?php endif; ?>
          </div>
          <div class="p-3 space-y-1">
            <p class="text-xs text-beige-100 truncate"><?= e((string) ($p['caption'] ?? 'Untitled')) ?></p>
            <p class="text-[11px] text-beige-100/50">
              <?php if (!empty($p['category'])): ?><?= e($p['category']) ?><?php else: ?><span class="italic">no category</span><?php endif; ?>
              <?php if (!empty($p['event_title'])): ?> · <span class="text-beige-100/40">from <?= e($p['event_title']) ?></span><?php endif; ?>
            </p>
            <div class="pt-2 flex items-center justify-between text-[11px]">
              <button type="button" @click="editing = !editing" class="text-gold-400 hover:text-gold-300">Edit</button>
              <div class="flex items-center gap-3">
                <form method="post" class="inline">
                  <?= csrf_field() ?>
                  <input type="hidden" name="action" value="toggle">
                  <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
                  <button class="text-beige-100/55 hover:text-gold-400"><?= $p['status'] === 'hidden' ? 'Show' : 'Hide' ?></button>
                </form>
                <form method="post" class="inline" onsubmit="return confirm('Delete this photo permanently?');">
                  <?= csrf_field() ?>
                  <input type="hidden" name="action" value="delete">
                  <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
                  <button class="text-red-300/70 hover:text-red-300">Delete</button>
                </form>
              </div>
            </div>
          </div>

          <!-- Inline edit form -->
          <div x-show="editing" x-cloak x-transition class="border-t border-white/5 p-3 bg-navy-950/50">
            <form method="post" class="space-y-2 text-xs">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="save">
              <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
              <label class="block">
                <span class="text-[10px] uppercase tracking-widest text-beige-100/50">Caption</span>
                <input name="caption" maxlength="255" value="<?= e((string) ($p['caption'] ?? '')) ?>"
                       class="mt-1 w-full rounded-xl bg-navy-900 border border-white/5 px-3 py-2">
              </label>
              <label class="block">
                <span class="text-[10px] uppercase tracking-widest text-beige-100/50">Category</span>
                <input name="category" list="gallery-category-list" maxlength="80" value="<?= e((string) ($p['category'] ?? '')) ?>"
                       class="mt-1 w-full rounded-xl bg-navy-900 border border-white/5 px-3 py-2">
              </label>
              <label class="block">
                <span class="text-[10px] uppercase tracking-widest text-beige-100/50">Video URL <span class="text-beige-100/30">(YouTube / Vimeo — leave blank for photo-only)</span></span>
                <input name="video_url" maxlength="500" value="<?= e((string) ($p['video_url'] ?? '')) ?>"
                       class="mt-1 w-full rounded-xl bg-navy-900 border border-white/5 px-3 py-2 font-mono text-[11px]">
              </label>
              <label class="block">
                <span class="text-[10px] uppercase tracking-widest text-beige-100/50">Event</span>
                <select name="event_id" class="mt-1 w-full rounded-xl bg-navy-900 border border-white/5 px-3 py-2">
                  <option value="">— not linked —</option>
                  <?php foreach ($eventOptions as $eo): ?>
                    <option value="<?= (int) $eo['id'] ?>" <?= (int) $p['event_id'] === (int) $eo['id'] ? 'selected' : '' ?>>
                      <?= e($eo['title']) ?> · <?= e(format_datetime($eo['starts_at'], 'd M Y')) ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </label>
              <div class="grid grid-cols-2 gap-2">
                <label class="block">
                  <span class="text-[10px] uppercase tracking-widest text-beige-100/50">Sort</span>
                  <input name="sort_order" type="number" value="<?= (int) $p['sort_order'] ?>"
                         class="mt-1 w-full rounded-xl bg-navy-900 border border-white/5 px-3 py-2">
                </label>
                <label class="block">
                  <span class="text-[10px] uppercase tracking-widest text-beige-100/50">Status</span>
                  <select name="status" class="mt-1 w-full rounded-xl bg-navy-900 border border-white/5 px-3 py-2">
                    <option value="visible" <?= $p['status'] === 'visible' ? 'selected' : '' ?>>Visible</option>
                    <option value="hidden"  <?= $p['status'] === 'hidden'  ? 'selected' : '' ?>>Hidden</option>
                  </select>
                </label>
              </div>
              <button class="mt-1 w-full px-3 py-2 rounded-full bg-gold-500 text-navy-950 font-medium hover:bg-gold-400 transition">Save</button>
            </form>
          </div>
        </article>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>

<?php require __DIR__ . '/../includes/admin_layout_end.php'; ?>
