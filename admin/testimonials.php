<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_admin();
$pageTitle = 'Testimonials';

if (is_post()) {
    csrf_verify();
    $action = input('action');
    if ($action === 'create') {
        try {
            $avatar = handle_upload('avatar_file', 'testimonials');
        } catch (RuntimeException $e) {
            flash('testimonial', $e->getMessage(), 'error');
            redirect('/admin/testimonials.php');
        }
        $stmt = db()->prepare(
            "INSERT INTO testimonials (author_name, author_title, quote, avatar, rating, is_published)
             VALUES (:n, :t, :q, :a, :r, :p)"
        );
        $stmt->execute([
            ':n' => trim((string)input('author_name', '')),
            ':t' => trim((string)input('author_title', '')),
            ':q' => trim((string)input('quote', '')),
            ':a' => $avatar,
            ':r' => max(1, min(5, (int)input('rating', 5))),
            ':p' => input('is_published') ? 1 : 0,
        ]);
        audit_log('testimonial.create', 'testimonials', (int) db()->lastInsertId());
    } elseif ($action === 'toggle') {
        $id = (int)input('id', 0);
        if ($id) {
            db()->prepare("UPDATE testimonials SET is_published = 1 - is_published WHERE id = :id")->execute([':id' => $id]);
        }
    } elseif ($action === 'delete') {
        $id = (int)input('id', 0);
        if ($id) {
            db()->prepare("DELETE FROM testimonials WHERE id = :id")->execute([':id' => $id]);
            audit_log('testimonial.delete', 'testimonials', $id);
        }
    }
    redirect('/admin/testimonials.php');
}

$rows = db()->query("SELECT * FROM testimonials ORDER BY sort_order ASC, created_at DESC")->fetchAll();
require __DIR__ . '/../includes/admin_layout.php';
?>
<h1 class="font-serif text-3xl text-beige-100">Testimonials</h1>

<form method="post" enctype="multipart/form-data" class="mt-6 grid sm:grid-cols-2 gap-4 max-w-3xl border border-white/5 rounded-2xl p-5 bg-navy-900/40">
  <?= csrf_field() ?>
  <input type="hidden" name="action" value="create">
  <input name="author_name" placeholder="Author name" required class="rounded-full bg-navy-950 border border-white/5 px-4 py-2">
  <input name="author_title" placeholder="Title (optional)" class="rounded-full bg-navy-950 border border-white/5 px-4 py-2">
  <textarea name="quote" placeholder="Quote" rows="3" required class="sm:col-span-2 rounded-2xl bg-navy-950 border border-white/5 px-4 py-2"></textarea>
  <label class="text-sm text-beige-100/70 sm:col-span-2">
    Avatar (optional)
    <input type="file" name="avatar_file" accept="image/*" class="mt-1 w-full text-sm file:mr-3 file:py-2 file:px-4 file:rounded-full file:border-0 file:bg-gold-500/20 file:text-gold-400 hover:file:bg-gold-500/30">
  </label>
  <select name="rating" class="rounded-full bg-navy-950 border border-white/5 px-4 py-2">
    <?php for ($i = 5; $i >= 1; $i--): ?><option value="<?= $i ?>"><?= str_repeat('★', $i) ?></option><?php endfor; ?>
  </select>
  <label class="flex items-center gap-2 text-sm text-beige-100/70"><input type="checkbox" name="is_published" value="1"> Publish</label>
  <button class="sm:col-span-2 px-5 py-3 rounded-full bg-gold-500 text-navy-950 hover:bg-gold-400 transition">Add testimonial</button>
</form>

<div class="mt-8 grid gap-4 md:grid-cols-2">
  <?php foreach ($rows as $r): ?>
    <article class="border border-white/5 rounded-2xl p-5 bg-navy-900/40">
      <p class="font-serif text-beige-100/90 italic">“<?= e($r['quote']) ?>”</p>
      <p class="mt-3 text-sm text-gold-400"><?= e($r['author_name']) ?> <?php if ($r['author_title']): ?><span class="text-beige-100/50">· <?= e($r['author_title']) ?></span><?php endif; ?></p>
      <div class="mt-3 flex gap-3 text-xs">
        <span class="px-2 py-1 rounded-full <?= $r['is_published'] ? 'bg-gold-500/20 text-gold-400' : 'bg-white/5 text-beige-100/50' ?>"><?= $r['is_published'] ? 'Published' : 'Hidden' ?></span>
        <form method="post" class="inline"><?= csrf_field() ?><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><button class="text-gold-400">Toggle</button></form>
        <form method="post" class="inline" onsubmit="return confirm('Delete?');"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><button class="text-red-300/80">Delete</button></form>
      </div>
    </article>
  <?php endforeach; ?>
</div>
<?php require __DIR__ . '/../includes/admin_layout_end.php'; ?>
