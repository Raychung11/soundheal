<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_admin();
$pageTitle = 'Corporate leads';

if (is_post()) {
    csrf_verify();
    $id = (int) input('id', 0);
    $status = input('status');
    if ($id && in_array($status, ['new','contacted','proposal_sent','won','lost'], true)) {
        db()->prepare("UPDATE corporate_inquiries SET status = :s, assigned_to = :u WHERE id = :id")
            ->execute([':s' => $status, ':u' => current_user_id(), ':id' => $id]);
        audit_log('corporate.update', 'corporate_inquiries', $id, ['status' => $status]);
    }
    redirect('/admin/corporate_leads.php');
}

$rows = db()->query(
    "SELECT ci.*, cp.name AS package_name
       FROM corporate_inquiries ci
       LEFT JOIN corporate_packages cp ON cp.id = ci.package_id
      ORDER BY ci.created_at DESC LIMIT 200"
)->fetchAll();
require __DIR__ . '/../includes/admin_layout.php';
?>
<h1 class="font-serif text-3xl text-beige-100">Corporate inquiries</h1>

<div class="mt-6 space-y-4">
  <?php foreach ($rows as $r): ?>
    <article class="border border-white/5 rounded-2xl p-5 bg-navy-900/40">
      <div class="flex flex-wrap justify-between gap-3">
        <div>
          <p class="font-serif text-xl text-beige-100"><?= e($r['company_name']) ?></p>
          <p class="text-sm text-beige-100/60"><?= e($r['contact_name']) ?> · <?= e($r['contact_email']) ?> <?php if ($r['contact_phone']): ?>· <?= e($r['contact_phone']) ?><?php endif; ?></p>
          <p class="text-xs text-beige-100/40 mt-1"><?= e(format_datetime($r['created_at'])) ?> · Team size: <?= e($r['team_size'] ?? '—') ?></p>
          <?php if (!empty($r['package_name'])): ?>
            <p class="mt-2 inline-block text-[10px] uppercase tracking-widest px-3 py-1 rounded-full border border-gold-500/25 bg-gold-500/5 text-gold-400">
              Interested in: <?= e($r['package_name']) ?>
            </p>
          <?php endif; ?>
        </div>
        <form method="post" class="flex items-center gap-2">
          <?= csrf_field() ?>
          <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
          <select name="status" class="rounded-full bg-navy-950 border border-white/5 px-3 py-1 text-sm">
            <?php foreach (['new','contacted','proposal_sent','won','lost'] as $opt): ?>
              <option value="<?= $opt ?>" <?= $r['status'] === $opt ? 'selected' : '' ?>><?= $opt ?></option>
            <?php endforeach; ?>
          </select>
          <button class="text-sm text-gold-400">Update</button>
        </form>
      </div>
      <?php if ($r['message']): ?>
        <p class="mt-3 text-sm text-beige-100/80 leading-relaxed"><?= nl2br(e($r['message'])) ?></p>
      <?php endif; ?>
    </article>
  <?php endforeach; ?>
  <?php if (!$rows): ?>
    <p class="text-beige-100/60">No corporate inquiries yet.</p>
  <?php endif; ?>
</div>
<?php require __DIR__ . '/../includes/admin_layout_end.php'; ?>
