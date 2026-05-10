<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_admin();
$pageTitle = 'Content';

if (is_post()) {
    csrf_verify();
    $action = input('action');
    if ($action === 'create') {
        $title = trim((string) input('title', ''));
        if ($title !== '') {
            try {
                $audioPath = handle_upload('audio_file', 'content');
                $coverPath = handle_upload('cover_file', 'content');
            } catch (RuntimeException $e) {
                flash('content', $e->getMessage(), 'error');
                redirect('/admin/content.php');
            }
            $stmt = db()->prepare(
                "INSERT INTO wellness_content (slug, title, description, type, file_path, cover_image, duration_seconds, access, is_published)
                 VALUES (:slug, :title, :desc, :type, :file, :cover, :dur, :access, :pub)"
            );
            $stmt->execute([
                ':slug' => slugify($title) . '-' . substr(bin2hex(random_bytes(2)), 0, 4),
                ':title' => $title,
                ':desc' => trim((string) input('description', '')),
                ':type' => input('type', 'audio'),
                ':file' => $audioPath ?: trim((string) input('file_path', '')),
                ':cover'=> $coverPath ?: null,
                ':dur'  => max(0, (int) input('duration_seconds', 0)),
                ':access' => input('access', 'member'),
                ':pub'  => input('is_published') ? 1 : 0,
            ]);
            audit_log('content.create', 'wellness_content', (int) db()->lastInsertId());
        }
    } elseif ($action === 'update') {
        $id = (int) input('id', 0);
        if ($id) {
            $existing = db()->prepare('SELECT * FROM wellness_content WHERE id = :id LIMIT 1');
            $existing->execute([':id' => $id]);
            $row = $existing->fetch();
            if ($row) {
                try {
                    $audioPath = handle_upload('audio_file', 'content');
                    $coverPath = handle_upload('cover_file', 'content');
                } catch (RuntimeException $e) {
                    flash('content', $e->getMessage(), 'error');
                    redirect('/admin/content.php');
                }

                // If a new audio was uploaded, delete the old uploaded one (not external URLs).
                if ($audioPath && str_starts_with((string) $row['file_path'], '/uploads/')) {
                    delete_upload($row['file_path']);
                }
                if ($coverPath && str_starts_with((string) ($row['cover_image'] ?? ''), '/uploads/')) {
                    delete_upload($row['cover_image']);
                }

                $title = trim((string) input('title', $row['title']));
                $extUrl = trim((string) input('file_path', ''));
                $newFilePath = $audioPath ?: ($extUrl !== '' ? $extUrl : $row['file_path']);
                $newCover    = $coverPath ?: $row['cover_image'];

                $stmt = db()->prepare(
                    "UPDATE wellness_content SET
                       title = :title, description = :desc, type = :type,
                       file_path = :file, cover_image = :cover,
                       duration_seconds = :dur, access = :access, is_published = :pub
                     WHERE id = :id"
                );
                $stmt->execute([
                    ':title' => $title,
                    ':desc'  => trim((string) input('description', (string)($row['description'] ?? ''))),
                    ':type'  => input('type', $row['type']),
                    ':file'  => $newFilePath,
                    ':cover' => $newCover,
                    ':dur'   => max(0, (int) input('duration_seconds', (int)$row['duration_seconds'])),
                    ':access'=> input('access', $row['access']),
                    ':pub'   => input('is_published') ? 1 : 0,
                    ':id'    => $id,
                ]);
                audit_log('content.update', 'wellness_content', $id);
            }
        }
    } elseif ($action === 'delete') {
        $id = (int) input('id', 0);
        if ($id) {
            $row = db()->prepare('SELECT file_path, cover_image FROM wellness_content WHERE id = :id');
            $row->execute([':id' => $id]);
            if ($r = $row->fetch()) {
                delete_upload($r['file_path']);
                delete_upload($r['cover_image']);
            }
            db()->prepare('DELETE FROM wellness_content WHERE id = :id')->execute([':id' => $id]);
            audit_log('content.delete', 'wellness_content', $id);
        }
    } elseif ($action === 'toggle') {
        $id = (int) input('id', 0);
        if ($id) {
            db()->prepare("UPDATE wellness_content SET is_published = 1 - is_published WHERE id = :id")
                ->execute([':id' => $id]);
            audit_log('content.toggle', 'wellness_content', $id);
        }
    }
    redirect('/admin/content.php');
}

$rows = db()->query("SELECT * FROM wellness_content ORDER BY created_at DESC LIMIT 100")->fetchAll();
require __DIR__ . '/../includes/admin_layout.php';
?>
<h1 class="font-serif text-3xl text-beige-100">Content library</h1>

<form method="post" enctype="multipart/form-data" class="mt-6 grid sm:grid-cols-2 gap-4 max-w-3xl border border-white/5 rounded-2xl p-5 bg-navy-900/40">
  <?= csrf_field() ?>
  <input type="hidden" name="action" value="create">
  <input name="title" placeholder="Title" required class="rounded-full bg-navy-950 border border-white/5 px-4 py-2">
  <select name="type" class="rounded-full bg-navy-950 border border-white/5 px-4 py-2">
    <?php foreach (['audio','meditation','sleep','breathing','article'] as $t): ?>
      <option><?= $t ?></option>
    <?php endforeach; ?>
  </select>
  <label class="text-sm text-beige-100/70 sm:col-span-2">
    Audio file
    <input type="file" name="audio_file" accept="audio/*" class="mt-1 w-full text-sm file:mr-3 file:py-2 file:px-4 file:rounded-full file:border-0 file:bg-gold-500/20 file:text-gold-400 hover:file:bg-gold-500/30">
  </label>
  <label class="text-sm text-beige-100/70 sm:col-span-2">
    Cover image (optional)
    <input type="file" name="cover_file" accept="image/*" class="mt-1 w-full text-sm file:mr-3 file:py-2 file:px-4 file:rounded-full file:border-0 file:bg-gold-500/20 file:text-gold-400 hover:file:bg-gold-500/30">
  </label>
  <input name="file_path" placeholder="…or paste an external URL" class="sm:col-span-2 rounded-full bg-navy-950 border border-white/5 px-4 py-2">
  <input name="duration_seconds" type="number" min="0" placeholder="Duration (seconds)" class="rounded-full bg-navy-950 border border-white/5 px-4 py-2">
  <select name="access" class="rounded-full bg-navy-950 border border-white/5 px-4 py-2">
    <?php foreach (['public','member','premium'] as $a): ?>
      <option><?= $a ?></option>
    <?php endforeach; ?>
  </select>
  <label class="flex items-center gap-2 text-sm text-beige-100/70">
    <input type="checkbox" name="is_published" value="1"> Publish immediately
  </label>
  <textarea name="description" placeholder="Description" rows="2" class="sm:col-span-2 rounded-2xl bg-navy-950 border border-white/5 px-4 py-2"></textarea>
  <button class="sm:col-span-2 px-5 py-3 rounded-full bg-gold-500 text-navy-950 hover:bg-gold-400 transition">Add to library</button>
</form>

<div class="mt-8 space-y-3">
  <?php if (!$rows): ?>
    <div class="border border-white/5 rounded-2xl p-6 bg-navy-900/40 text-beige-100/60">Your library is quiet for now.</div>
  <?php endif; ?>

  <?php foreach ($rows as $r): ?>
    <div x-data="{ open: false }" class="border border-white/5 rounded-2xl bg-navy-900/40 overflow-hidden">
      <div class="grid md:grid-cols-[1fr_auto] gap-4 items-center px-5 py-4">
        <div class="min-w-0">
          <p class="text-beige-100"><?= e($r['title']) ?></p>
          <p class="text-xs text-beige-100/50 mt-1 capitalize">
            <?= e($r['type']) ?> · <?= e($r['access']) ?> ·
            <?= $r['is_published'] ? '<span class="text-gold-400">Published</span>' : 'Draft' ?> ·
            <?= max(1, (int) round(((int)$r['duration_seconds'])/60)) ?> min
          </p>
        </div>
        <div class="flex flex-wrap items-center gap-3 justify-end">
          <button type="button" @click="open = !open" class="text-gold-400 text-sm" x-text="open ? 'Close' : 'Edit'"></button>
          <form method="post" class="inline">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="toggle">
            <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
            <button class="text-beige-100/70 hover:text-gold-400 text-sm"><?= $r['is_published'] ? 'Unpublish' : 'Publish' ?></button>
          </form>
          <form method="post" class="inline" onsubmit="return confirm('Delete this audio?');">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
            <button class="text-red-300/80 hover:text-red-300 text-sm">Delete</button>
          </form>
        </div>
      </div>

      <div x-show="open" x-cloak x-transition class="border-t border-white/5 px-5 py-5 bg-navy-950/40">
        <form method="post" enctype="multipart/form-data" class="grid sm:grid-cols-2 gap-4">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="update">
          <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">

          <label class="block sm:col-span-2">
            <span class="text-xs uppercase tracking-widest text-beige-100/60">Title</span>
            <input name="title" required value="<?= e($r['title']) ?>" class="mt-1 w-full rounded-full bg-navy-900 border border-white/5 px-4 py-2">
          </label>

          <label class="block">
            <span class="text-xs uppercase tracking-widest text-beige-100/60">Type</span>
            <select name="type" class="mt-1 w-full rounded-full bg-navy-900 border border-white/5 px-4 py-2">
              <?php foreach (['audio','meditation','sleep','breathing','article'] as $t): ?>
                <option value="<?= $t ?>" <?= $r['type'] === $t ? 'selected' : '' ?>><?= $t ?></option>
              <?php endforeach; ?>
            </select>
          </label>

          <label class="block">
            <span class="text-xs uppercase tracking-widest text-beige-100/60">Access</span>
            <select name="access" class="mt-1 w-full rounded-full bg-navy-900 border border-white/5 px-4 py-2">
              <?php foreach (['public','member','premium'] as $a): ?>
                <option value="<?= $a ?>" <?= $r['access'] === $a ? 'selected' : '' ?>><?= $a ?></option>
              <?php endforeach; ?>
            </select>
          </label>

          <label class="block">
            <span class="text-xs uppercase tracking-widest text-beige-100/60">Duration (seconds)</span>
            <input type="number" min="0" name="duration_seconds" value="<?= (int)$r['duration_seconds'] ?>" class="mt-1 w-full rounded-full bg-navy-900 border border-white/5 px-4 py-2">
          </label>

          <label class="flex items-center gap-2 text-sm text-beige-100/70">
            <input type="checkbox" name="is_published" value="1" <?= $r['is_published'] ? 'checked' : '' ?>>
            Published
          </label>

          <label class="block sm:col-span-2">
            <span class="text-xs uppercase tracking-widest text-beige-100/60">Description</span>
            <textarea name="description" rows="2" class="mt-1 w-full rounded-2xl bg-navy-900 border border-white/5 px-4 py-2"><?= e($r['description'] ?? '') ?></textarea>
          </label>

          <div class="sm:col-span-2 grid sm:grid-cols-2 gap-4 border-t border-white/5 pt-4">
            <div>
              <p class="text-xs uppercase tracking-widest text-beige-100/60">Audio file</p>
              <p class="text-[11px] text-beige-100/40 mt-1 break-all">Current: <?= $r['file_path'] ? e($r['file_path']) : '—' ?></p>
              <?php if (!empty($r['file_path'])): $src = media_src((string) $r['file_path']); ?>
                <audio controls preload="none" src="<?= e($src) ?>" class="mt-2 w-full"></audio>
              <?php endif; ?>
              <input type="file" name="audio_file" accept="audio/*" class="mt-2 w-full text-sm file:mr-3 file:py-2 file:px-4 file:rounded-full file:border-0 file:bg-gold-500/20 file:text-gold-400 hover:file:bg-gold-500/30">
              <input type="text" name="file_path" placeholder="…or replace with an external URL" class="mt-2 w-full rounded-full bg-navy-900 border border-white/5 px-4 py-2 text-sm">
            </div>

            <div>
              <p class="text-xs uppercase tracking-widest text-beige-100/60">Cover image</p>
              <p class="text-[11px] text-beige-100/40 mt-1 break-all">Current: <?= $r['cover_image'] ? e($r['cover_image']) : '—' ?></p>
              <?php if (!empty($r['cover_image'])): $cs = media_src((string) $r['cover_image']); ?>
                <img src="<?= e($cs) ?>" class="mt-2 h-24 w-auto rounded-xl object-cover border border-white/10" alt="">
              <?php endif; ?>
              <input type="file" name="cover_file" accept="image/*" class="mt-2 w-full text-sm file:mr-3 file:py-2 file:px-4 file:rounded-full file:border-0 file:bg-gold-500/20 file:text-gold-400 hover:file:bg-gold-500/30">
            </div>
          </div>

          <div class="sm:col-span-2 flex gap-3">
            <button class="px-5 py-2 rounded-full bg-gold-500 text-navy-950 hover:bg-gold-400 transition">Save changes</button>
            <button type="button" @click="open = false" class="px-5 py-2 rounded-full border border-white/10 text-beige-100/70 hover:border-white/20">Cancel</button>
          </div>
        </form>
      </div>
    </div>
  <?php endforeach; ?>
</div>
<?php require __DIR__ . '/../includes/admin_layout_end.php'; ?>
