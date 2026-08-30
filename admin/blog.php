<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_admin();
$pageTitle = 'Journal';

$editId     = (int) input('edit', 0);
$editing    = null;
$formErrors = [];

if (is_post()) {
    csrf_verify();
    $action = (string) input('action', 'save');

    if ($action === 'delete') {
        $id = (int) input('id', 0);
        if ($id > 0) {
            $img = db()->prepare("SELECT cover_image FROM blog_posts WHERE id = :id");
            $img->execute([':id' => $id]);
            $existing = (string) ($img->fetchColumn() ?: '');
            db()->prepare("DELETE FROM blog_posts WHERE id = :id")->execute([':id' => $id]);
            if ($existing !== '') delete_upload($existing);
            audit_log('blog.delete', 'blog_posts', $id);
            flash('blog', 'Post removed.', 'success');
        }
        redirect('/admin/blog.php');
    }

    if ($action === 'status') {
        $id = (int) input('id', 0);
        $to = (string) input('to', 'draft');
        if ($id > 0 && in_array($to, ['draft','published','archived'], true)) {
            // Stamp published_at the first time the post goes live so
            // it has a real date for the listing sort — but don't
            // rewrite it on re-publish.
            if ($to === 'published') {
                db()->prepare(
                    "UPDATE blog_posts SET status = :s,
                            published_at = COALESCE(published_at, NOW())
                      WHERE id = :id"
                )->execute([':s' => $to, ':id' => $id]);
            } else {
                db()->prepare("UPDATE blog_posts SET status = :s WHERE id = :id")
                    ->execute([':s' => $to, ':id' => $id]);
            }
            audit_log('blog.status', 'blog_posts', $id, ['to' => $to]);
            flash('blog', 'Status updated.', 'success');
        }
        redirect('/admin/blog.php');
    }

    // Save (create or update).
    $id       = (int) input('id', 0);
    $title    = trim((string) input('title', ''));
    $subtitle = trim((string) input('subtitle', ''));
    $excerpt  = trim((string) input('excerpt', ''));
    // Body bypasses input()'s trim so trailing blank lines survive —
    // matters for cleanly-formatted posts.
    $body = is_string($_POST['body'] ?? null) ? (string) $_POST['body'] : '';
    $tags     = trim((string) input('tags', ''));
    $status   = (string) input('status', 'draft');
    if (!in_array($status, ['draft','published','archived'], true)) $status = 'draft';
    $slug = trim((string) input('slug', ''));

    if ($title === '') $formErrors[] = 'Title is required.';

    if ($slug === '') {
        $slug = slugify($title);
        if ($slug === '') $slug = 'post-' . bin2hex(random_bytes(3));
    } else {
        $slug = slugify($slug);
    }
    // Unique slug — append -2, -3 …
    if (!$formErrors) {
        $base = $slug; $n = 1;
        while (true) {
            $chk = db()->prepare("SELECT id FROM blog_posts WHERE slug = :s AND id <> :id LIMIT 1");
            $chk->execute([':s' => $slug, ':id' => $id]);
            if (!$chk->fetchColumn()) break;
            $n++;
            $slug = $base . '-' . $n;
        }
    }

    $cover = null;
    if (!$formErrors) {
        try {
            $uploaded = handle_upload('cover_image_file', 'blog');
            if ($uploaded !== null) $cover = $uploaded;
        } catch (RuntimeException $ex) {
            $formErrors[] = $ex->getMessage();
        }
    }

    if (!$formErrors) {
        if ($id > 0) {
            if ($cover !== null) {
                $prev = db()->prepare("SELECT cover_image FROM blog_posts WHERE id = :id");
                $prev->execute([':id' => $id]);
                $prevPath = (string) ($prev->fetchColumn() ?: '');
                if ($prevPath !== '' && $prevPath !== $cover) delete_upload($prevPath);
            }
            $sql = "UPDATE blog_posts SET
                        slug = :slug, title = :title, subtitle = :sub,
                        excerpt = :ex, body = :body, tags = :tags,
                        status = :st"
                . ($cover !== null ? ", cover_image = :cover" : '')
                . " WHERE id = :id";
            $params = [
                ':slug' => $slug, ':title' => $title, ':sub' => $subtitle ?: null,
                ':ex' => $excerpt ?: null, ':body' => $body ?: null,
                ':tags' => $tags ?: null, ':st' => $status, ':id' => $id,
            ];
            if ($cover !== null) $params[':cover'] = $cover;
            db()->prepare($sql)->execute($params);
            // Stamp published_at only when transitioning INTO published.
            if ($status === 'published') {
                db()->prepare(
                    "UPDATE blog_posts SET published_at = COALESCE(published_at, NOW()) WHERE id = :id"
                )->execute([':id' => $id]);
            }
            audit_log('blog.update', 'blog_posts', $id);
            flash('blog', 'Post saved.', 'success');
        } else {
            db()->prepare(
                "INSERT INTO blog_posts
                    (slug, title, subtitle, excerpt, body, cover_image, tags,
                     status, published_at, author_id)
                 VALUES
                    (:slug, :title, :sub, :ex, :body, :cover, :tags,
                     :st, :pub, :by)"
            )->execute([
                ':slug' => $slug, ':title' => $title, ':sub' => $subtitle ?: null,
                ':ex' => $excerpt ?: null, ':body' => $body ?: null,
                ':cover' => $cover, ':tags' => $tags ?: null,
                ':st' => $status,
                ':pub' => $status === 'published' ? date('Y-m-d H:i:s') : null,
                ':by'  => current_user_id(),
            ]);
            $newId = (int) db()->lastInsertId();
            audit_log('blog.create', 'blog_posts', $newId);
            flash('blog', 'Post created.', 'success');
        }
        redirect('/admin/blog.php');
    }

    $editing = [
        'id' => $id, 'slug' => $slug, 'title' => $title, 'subtitle' => $subtitle,
        'excerpt' => $excerpt, 'body' => $body, 'tags' => $tags,
        'status' => $status, 'cover_image' => null,
    ];
}

if ($editId > 0 && !$editing) {
    $stmt = db()->prepare("SELECT * FROM blog_posts WHERE id = :id");
    $stmt->execute([':id' => $editId]);
    $editing = $stmt->fetch() ?: null;
}

$isNewForm = !$editing && isset($_GET['edit']) && $_GET['edit'] === 'new';

$posts = db()->query(
    "SELECT id, slug, title, cover_image, status, published_at, created_at, tags
       FROM blog_posts
       ORDER BY status ASC, COALESCE(published_at, created_at) DESC"
)->fetchAll();

require __DIR__ . '/../includes/admin_layout.php';
?>

<div class="flex items-center justify-between gap-4 flex-wrap">
  <div>
    <h1 class="font-serif text-3xl text-beige-100">Journal</h1>
    <p class="text-beige-100/60 mt-1 text-sm">Reflections, session notes and updates. Instagram, YouTube and Vimeo embeds land inline via <code class="text-gold-400/70 text-[11px]">[instagram: url]</code> markers in the body.</p>
  </div>
  <?php if (!$editing && !$isNewForm): ?>
    <a href="<?= url('/admin/blog.php?edit=new') ?>" class="px-5 py-2.5 rounded-full bg-gold-500 text-navy-950 hover:bg-gold-400 transition text-sm">New post</a>
  <?php endif; ?>
</div>

<?php if ($f = flash('blog')): ?>
  <div class="mt-6 border rounded-2xl px-5 py-4 text-sm <?= ($f['type'] ?? 'info') === 'success' ? 'border-gold-500/40 bg-gold-500/5 text-gold-400' : 'border-white/10 bg-navy-900/40 text-beige-100/85' ?>"><?= e($f['message'] ?? '') ?></div>
<?php endif; ?>

<?php if ($editing || $isNewForm): ?>
  <?php $p = $editing ?: ['id'=>0,'slug'=>'','title'=>'','subtitle'=>'','excerpt'=>'','body'=>'','tags'=>'','status'=>'draft','cover_image'=>null]; ?>
  <form method="post" enctype="multipart/form-data" class="mt-8 space-y-6 max-w-4xl border border-white/5 rounded-3xl p-6 bg-navy-900/40">
    <?= csrf_field() ?>
    <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">

    <?php if (!empty($formErrors)): ?>
      <div class="border border-red-400/40 bg-red-500/5 text-red-200 rounded-2xl px-5 py-4 text-sm">
        <?php foreach ($formErrors as $err): ?><p><?= e($err) ?></p><?php endforeach; ?>
      </div>
    <?php endif; ?>

    <h2 class="font-serif text-2xl text-gold-400"><?= $p['id'] ? 'Edit post' : 'New post' ?></h2>

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
      <span class="text-xs uppercase tracking-widest text-beige-100/60">Excerpt (shown on list cards + social share)</span>
      <textarea name="excerpt" rows="2"
                class="mt-2 w-full rounded-2xl bg-navy-950 border border-white/5 px-4 py-3 focus:border-gold-500/50 focus:outline-none"><?= e((string)($p['excerpt'] ?? '')) ?></textarea>
    </label>

    <div>
      <div class="flex items-baseline justify-between">
        <span class="text-xs uppercase tracking-widest text-beige-100/60">Body</span>
        <span class="text-[11px] text-beige-100/45">Embed a social post by writing e.g. <code class="text-gold-400/80">[instagram: https://instagram.com/p/XXX/]</code> on its own line.</span>
      </div>
      <textarea name="body" rows="14"
                class="mt-2 w-full rounded-2xl bg-navy-950 border border-white/5 px-4 py-3 font-mono text-sm leading-relaxed focus:border-gold-500/50 focus:outline-none"
                placeholder="Take a slow breath in…

Some words here about the session that just held space for the room.

[instagram: https://www.instagram.com/p/XXX/]

More reflections after the embed."><?= e((string)($p['body'] ?? '')) ?></textarea>
      <p class="text-[11px] text-beige-100/45 mt-2">Also supported: <code class="text-gold-400/80">[youtube: url]</code>, <code class="text-gold-400/80">[vimeo: url]</code>. Blank line between paragraphs. Start a line with <code class="text-gold-400/80">-</code> or an emoji to make a bulleted list.</p>
    </div>

    <div class="grid sm:grid-cols-2 gap-4">
      <label class="block">
        <span class="text-xs uppercase tracking-widest text-beige-100/60">Tags (comma-separated)</span>
        <input name="tags" value="<?= e((string)($p['tags'] ?? '')) ?>" placeholder="reflection, gong bath, community"
               class="mt-2 w-full rounded-2xl bg-navy-950 border border-white/5 px-4 py-3 focus:border-gold-500/50 focus:outline-none">
      </label>
      <label class="block">
        <span class="text-xs uppercase tracking-widest text-beige-100/60">Slug</span>
        <input name="slug" value="<?= e((string)$p['slug']) ?>" placeholder="auto-generated from title"
               class="mt-2 w-full rounded-2xl bg-navy-950 border border-white/5 px-4 py-3 font-mono text-sm focus:border-gold-500/50 focus:outline-none">
      </label>
    </div>

    <label class="block">
      <span class="text-xs uppercase tracking-widest text-beige-100/60">Status</span>
      <select name="status"
              class="mt-2 w-full rounded-2xl bg-navy-950 border border-white/5 px-4 py-3 focus:border-gold-500/50 focus:outline-none">
        <option value="draft"     <?= $p['status'] === 'draft'     ? 'selected' : '' ?>>Draft (hidden)</option>
        <option value="published" <?= $p['status'] === 'published' ? 'selected' : '' ?>>Published</option>
        <option value="archived"  <?= $p['status'] === 'archived'  ? 'selected' : '' ?>>Archived</option>
      </select>
    </label>

    <div>
      <span class="block text-xs uppercase tracking-widest text-beige-100/60">Cover image</span>
      <?php if (!empty($p['cover_image'])): ?>
        <div class="mt-2 max-w-md aspect-[16/9] rounded-2xl overflow-hidden border border-white/10">
          <img src="<?= e(media_src((string)$p['cover_image'])) ?>" class="w-full h-full object-cover" alt="">
        </div>
      <?php endif; ?>
      <input name="cover_image_file" type="file" accept="image/jpeg,image/png,image/webp"
             class="mt-2 block text-sm text-beige-100/80 file:mr-4 file:px-4 file:py-2 file:rounded-full file:border-0 file:bg-gold-500 file:text-navy-950 hover:file:bg-gold-400">
      <span class="text-[11px] text-beige-100/40 mt-1 block">Landscape works best. Doubles as the social-share card for this post.</span>
    </div>

    <div class="flex flex-wrap gap-3 pt-2">
      <button class="px-6 py-3 rounded-full bg-gold-500 text-navy-950 hover:bg-gold-400 transition"><?= $p['id'] ? 'Save changes' : 'Create post' ?></button>
      <?php if ($p['id']): ?>
        <a href="<?= url('/public/blog_post.php?slug=' . urlencode((string)$p['slug'])) ?>" target="_blank"
           class="px-6 py-3 rounded-full border border-white/10 text-beige-100/70 hover:border-white/20">View live →</a>
      <?php endif; ?>
      <a href="<?= url('/admin/blog.php') ?>" class="px-6 py-3 rounded-full border border-white/10 text-beige-100/70 hover:border-white/20">Cancel</a>
    </div>
  </form>
<?php endif; ?>

<div class="mt-8 border border-white/5 rounded-3xl bg-navy-900/40 p-6">
  <h2 class="font-serif text-2xl text-gold-400">All posts</h2>
  <?php if (!$posts): ?>
    <p class="text-sm text-beige-100/60 mt-3">No posts yet.</p>
  <?php else: ?>
    <div class="overflow-x-auto">
    <table class="mt-4 w-full text-sm">
      <thead class="text-left text-beige-100/50 text-xs uppercase tracking-wider">
        <tr>
          <th class="py-2">Title</th>
          <th>Tags</th>
          <th>Status</th>
          <th>Published</th>
          <th class="text-right">Actions</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-white/5">
        <?php foreach ($posts as $row): ?>
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
            <td class="text-beige-100/70 text-xs"><?= e((string)($row['tags'] ?? '—')) ?></td>
            <td>
              <?php $s = (string) $row['status']; ?>
              <span class="text-xs px-2 py-1 rounded-full <?= $s === 'published' ? 'bg-gold-500/20 text-gold-400' : ($s === 'draft' ? 'bg-white/5 text-beige-100/55' : 'bg-white/5 text-beige-100/40') ?>"><?= e($s) ?></span>
            </td>
            <td class="text-beige-100/60 text-xs whitespace-nowrap"><?= $row['published_at'] ? e(format_datetime((string)$row['published_at'], 'd M Y')) : '—' ?></td>
            <td class="text-right">
              <div class="inline-flex items-center gap-2">
                <a href="<?= url('/admin/blog.php?edit=' . (int)$row['id']) ?>" class="text-xs text-gold-400 hover:text-gold-300">Edit</a>
                <?php $flip = $s === 'published' ? 'draft' : 'published'; ?>
                <form method="post" class="inline">
                  <?= csrf_field() ?>
                  <input type="hidden" name="action" value="status">
                  <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
                  <input type="hidden" name="to" value="<?= e($flip) ?>">
                  <button class="text-xs text-beige-100/60 hover:text-gold-400"><?= $s === 'published' ? 'Unpublish' : 'Publish' ?></button>
                </form>
                <form method="post" class="inline" onsubmit="return confirm('Delete this post permanently?');">
                  <?= csrf_field() ?>
                  <input type="hidden" name="action" value="delete">
                  <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
                  <button class="text-xs text-red-300/80 hover:text-red-200">Delete</button>
                </form>
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
