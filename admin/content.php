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
            $stmt = db()->prepare(
                "INSERT INTO wellness_content (slug, title, description, type, file_path, duration_seconds, access, is_published)
                 VALUES (:slug, :title, :desc, :type, :file, :dur, :access, :pub)"
            );
            $stmt->execute([
                ':slug' => slugify($title) . '-' . substr(bin2hex(random_bytes(2)), 0, 4),
                ':title' => $title,
                ':desc' => trim((string) input('description', '')),
                ':type' => input('type', 'audio'),
                ':file' => trim((string) input('file_path', '')),
                ':dur'  => max(0, (int) input('duration_seconds', 0)),
                ':access' => input('access', 'member'),
                ':pub'  => input('is_published') ? 1 : 0,
            ]);
            audit_log('content.create', 'wellness_content', (int) db()->lastInsertId());
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

<form method="post" class="mt-6 grid sm:grid-cols-2 gap-4 max-w-3xl border border-white/5 rounded-2xl p-5 bg-navy-900/40">
  <?= csrf_field() ?>
  <input type="hidden" name="action" value="create">
  <input name="title" placeholder="Title" required class="rounded-full bg-navy-950 border border-white/5 px-4 py-2">
  <select name="type" class="rounded-full bg-navy-950 border border-white/5 px-4 py-2">
    <?php foreach (['audio','meditation','sleep','breathing','article'] as $t): ?>
      <option><?= $t ?></option>
    <?php endforeach; ?>
  </select>
  <input name="file_path" placeholder="File path / URL" class="rounded-full bg-navy-950 border border-white/5 px-4 py-2">
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

<div class="mt-8 border border-white/5 rounded-2xl bg-navy-900/40 overflow-x-auto">
  <table class="w-full text-sm">
    <thead class="text-left text-beige-100/50 text-xs uppercase tracking-wider bg-navy-950/40">
      <tr><th class="px-4 py-3">Title</th><th>Type</th><th>Access</th><th>Published</th><th></th></tr>
    </thead>
    <tbody class="divide-y divide-white/5">
      <?php foreach ($rows as $r): ?>
        <tr>
          <td class="px-4 py-3"><?= e($r['title']) ?></td>
          <td><?= e($r['type']) ?></td>
          <td><?= e($r['access']) ?></td>
          <td><?= $r['is_published'] ? 'Yes' : 'No' ?></td>
          <td class="text-right pr-4">
            <form method="post" class="inline">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="toggle">
              <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
              <button class="text-gold-400 text-sm"><?= $r['is_published'] ? 'Unpublish' : 'Publish' ?></button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$rows): ?>
        <tr><td colspan="5" class="px-4 py-6 text-beige-100/60">Your library is quiet for now.</td></tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>
<?php require __DIR__ . '/../includes/admin_layout_end.php'; ?>
