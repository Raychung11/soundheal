<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_admin();
$pageTitle = 'Promo codes';

$errors = [];

if (is_post()) {
    csrf_verify();
    $action = (string) input('action');

    if ($action === 'save') {
        $id            = (int) input('id', 0);
        $code          = strtoupper(trim((string) input('code', '')));
        $description   = trim((string) input('description', ''));
        $discountType  = in_array(input('discount_type'), ['percent','fixed'], true) ? input('discount_type') : 'percent';
        $discountValue = max(0.0, (float) input('discount_value', 0));
        $maxUsesRaw    = trim((string) input('max_uses', ''));
        $maxUses       = $maxUsesRaw === '' ? null : max(1, (int) $maxUsesRaw);
        $status        = in_array(input('status'), ['active','disabled'], true) ? input('status') : 'active';
        $validFrom     = trim((string) input('valid_from', '')) ?: null;
        $validUntil    = trim((string) input('valid_until', '')) ?: null;

        if (!preg_match('/^[A-Z0-9_\-]{3,60}$/', $code)) {
            $errors[] = 'Code must be 3–60 characters — letters, numbers, hyphen or underscore only.';
        }
        if ($discountValue <= 0) {
            $errors[] = 'Discount value must be greater than zero.';
        }
        if ($discountType === 'percent' && $discountValue > 100) {
            $errors[] = 'Percent discount cannot exceed 100.';
        }

        if (!$errors) {
            try {
                if ($id > 0) {
                    db()->prepare(
                        "UPDATE promo_codes
                            SET code = :c, description = :d, discount_type = :dt, discount_value = :dv,
                                max_uses = :mu, status = :s, valid_from = :vf, valid_until = :vu
                          WHERE id = :id"
                    )->execute([
                        ':c' => $code, ':d' => $description ?: null,
                        ':dt' => $discountType, ':dv' => $discountValue,
                        ':mu' => $maxUses, ':s' => $status,
                        ':vf' => $validFrom, ':vu' => $validUntil,
                        ':id' => $id,
                    ]);
                    audit_log('promo.update', 'promo_codes', $id);
                    flash('promo', 'Code updated.', 'success');
                } else {
                    db()->prepare(
                        "INSERT INTO promo_codes
                            (code, description, discount_type, discount_value, max_uses, status,
                             valid_from, valid_until, created_by)
                         VALUES (:c, :d, :dt, :dv, :mu, :s, :vf, :vu, :u)"
                    )->execute([
                        ':c' => $code, ':d' => $description ?: null,
                        ':dt' => $discountType, ':dv' => $discountValue,
                        ':mu' => $maxUses, ':s' => $status,
                        ':vf' => $validFrom, ':vu' => $validUntil,
                        ':u' => current_user_id(),
                    ]);
                    $newId = (int) db()->lastInsertId();
                    audit_log('promo.create', 'promo_codes', $newId);
                    flash('promo', 'Code "' . $code . '" created.', 'success');
                }
                redirect('/admin/promo_codes.php');
            } catch (Throwable $e) {
                if (str_contains((string) $e->getMessage(), '1062')) {
                    $errors[] = 'That code already exists — choose another.';
                } else {
                    $errors[] = 'Could not save: ' . $e->getMessage();
                }
            }
        }
    } elseif ($action === 'toggle') {
        $id = (int) input('id', 0);
        db()->prepare("UPDATE promo_codes SET status = IF(status='active','disabled','active') WHERE id = :id")
            ->execute([':id' => $id]);
        redirect('/admin/promo_codes.php');
    }
}

$editingId = (int) input('edit', 0);
$editing   = null;
if ($editingId > 0) {
    $eStmt = db()->prepare("SELECT * FROM promo_codes WHERE id = :id LIMIT 1");
    $eStmt->execute([':id' => $editingId]);
    $editing = $eStmt->fetch() ?: null;
}
$row = $editing ?: [
    'id' => 0, 'code' => '', 'description' => '', 'discount_type' => 'percent',
    'discount_value' => 10, 'max_uses' => null, 'status' => 'active',
    'valid_from' => '', 'valid_until' => '',
];

$codes = db()->query(
    "SELECT * FROM promo_codes ORDER BY status DESC, created_at DESC"
)->fetchAll();

require __DIR__ . '/../includes/admin_layout.php';
?>

<div class="flex items-center justify-between gap-4 flex-wrap">
  <div>
    <h1 class="font-serif text-3xl text-beige-100">Promo codes</h1>
    <p class="text-beige-100/60 mt-1 text-sm">Percent- or fixed-amount discounts. Codes are case-insensitive at check-out.</p>
  </div>
</div>

<?php foreach ($errors as $err): ?>
  <p class="mt-4 text-red-300/80 text-sm"><?= e($err) ?></p>
<?php endforeach; ?>

<form method="post" class="mt-6 border border-white/5 rounded-3xl p-6 bg-navy-900/40 space-y-5">
  <?= csrf_field() ?>
  <input type="hidden" name="action" value="save">
  <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">

  <h2 class="font-serif text-2xl text-gold-400"><?= $editing ? 'Edit code' : 'New code' ?></h2>

  <div class="grid sm:grid-cols-2 gap-4">
    <label class="block">
      <span class="text-xs uppercase tracking-widest text-beige-100/60">Code</span>
      <input name="code" required maxlength="60" placeholder="e.g. FIRST20"
             value="<?= e((string) $row['code']) ?>"
             class="mt-2 w-full rounded-2xl bg-navy-950 border border-white/5 px-4 py-3 uppercase tracking-widest font-mono">
    </label>
    <label class="block">
      <span class="text-xs uppercase tracking-widest text-beige-100/60">Description <span class="text-beige-100/30">(internal)</span></span>
      <input name="description" maxlength="255" placeholder="Launch promo, corporate partner XYZ, etc."
             value="<?= e((string) $row['description']) ?>"
             class="mt-2 w-full rounded-2xl bg-navy-950 border border-white/5 px-4 py-3">
    </label>
    <label class="block">
      <span class="text-xs uppercase tracking-widest text-beige-100/60">Type</span>
      <select name="discount_type" class="mt-2 w-full rounded-2xl bg-navy-950 border border-white/5 px-4 py-3">
        <option value="percent" <?= ($row['discount_type'] ?? '') === 'percent' ? 'selected' : '' ?>>Percent (%)</option>
        <option value="fixed"   <?= ($row['discount_type'] ?? '') === 'fixed'   ? 'selected' : '' ?>>Fixed amount (MYR)</option>
      </select>
    </label>
    <label class="block">
      <span class="text-xs uppercase tracking-widest text-beige-100/60">Value</span>
      <input name="discount_value" type="number" step="0.01" min="0" required
             value="<?= e((string) $row['discount_value']) ?>"
             class="mt-2 w-full rounded-2xl bg-navy-950 border border-white/5 px-4 py-3">
    </label>
    <label class="block">
      <span class="text-xs uppercase tracking-widest text-beige-100/60">Max total uses <span class="text-beige-100/30">(blank = unlimited)</span></span>
      <input name="max_uses" type="number" min="1" placeholder="unlimited"
             value="<?= e((string) ($row['max_uses'] ?? '')) ?>"
             class="mt-2 w-full rounded-2xl bg-navy-950 border border-white/5 px-4 py-3">
    </label>
    <label class="block">
      <span class="text-xs uppercase tracking-widest text-beige-100/60">Status</span>
      <select name="status" class="mt-2 w-full rounded-2xl bg-navy-950 border border-white/5 px-4 py-3">
        <option value="active"   <?= ($row['status'] ?? '') === 'active'   ? 'selected' : '' ?>>Active</option>
        <option value="disabled" <?= ($row['status'] ?? '') === 'disabled' ? 'selected' : '' ?>>Disabled</option>
      </select>
    </label>
    <label class="block">
      <span class="text-xs uppercase tracking-widest text-beige-100/60">Valid from <span class="text-beige-100/30">(optional)</span></span>
      <input name="valid_from" type="datetime-local"
             value="<?= e(str_replace(' ', 'T', substr((string) ($row['valid_from'] ?? ''), 0, 16))) ?>"
             class="mt-2 w-full rounded-2xl bg-navy-950 border border-white/5 px-4 py-3">
    </label>
    <label class="block">
      <span class="text-xs uppercase tracking-widest text-beige-100/60">Valid until <span class="text-beige-100/30">(optional)</span></span>
      <input name="valid_until" type="datetime-local"
             value="<?= e(str_replace(' ', 'T', substr((string) ($row['valid_until'] ?? ''), 0, 16))) ?>"
             class="mt-2 w-full rounded-2xl bg-navy-950 border border-white/5 px-4 py-3">
    </label>
  </div>

  <div class="flex gap-3">
    <button class="px-6 py-3 rounded-full bg-gold-500 text-navy-950 hover:bg-gold-400 transition"><?= $editing ? 'Save changes' : 'Create code' ?></button>
    <?php if ($editing): ?>
      <a href="<?= url('/admin/promo_codes.php') ?>" class="px-6 py-3 rounded-full border border-white/10 text-beige-100/70 hover:border-white/20">New instead</a>
    <?php endif; ?>
  </div>
</form>

<h2 class="mt-12 font-serif text-2xl text-beige-100">All codes</h2>
<div class="mt-4 border border-white/5 rounded-2xl bg-navy-900/40 overflow-x-auto">
  <table class="w-full text-sm">
    <thead class="text-left text-beige-100/50 text-xs uppercase tracking-wider bg-navy-950/40">
      <tr>
        <th class="px-4 py-3">Code</th><th>Discount</th><th>Uses</th><th>Window</th><th>Status</th><th></th>
      </tr>
    </thead>
    <tbody class="divide-y divide-white/5">
      <?php foreach ($codes as $c): ?>
        <tr>
          <td class="px-4 py-3">
            <p class="font-mono text-beige-100 tracking-widest"><?= e($c['code']) ?></p>
            <?php if (!empty($c['description'])): ?>
              <p class="text-[11px] text-beige-100/45 mt-0.5"><?= e($c['description']) ?></p>
            <?php endif; ?>
          </td>
          <td>
            <?php if ($c['discount_type'] === 'percent'): ?>
              <?= e(rtrim(rtrim((string) $c['discount_value'], '0'), '.')) ?>%
            <?php else: ?>
              <?= e(format_money((float) $c['discount_value'])) ?>
            <?php endif; ?>
          </td>
          <td>
            <?= (int) $c['used_count'] ?><?= $c['max_uses'] !== null ? ' / ' . (int) $c['max_uses'] : '' ?>
          </td>
          <td class="text-xs text-beige-100/60">
            <?php if ($c['valid_from']): ?>from <?= e(format_datetime($c['valid_from'], 'd M')) ?><br><?php endif; ?>
            <?php if ($c['valid_until']): ?>until <?= e(format_datetime($c['valid_until'], 'd M Y')) ?><?php endif; ?>
            <?php if (!$c['valid_from'] && !$c['valid_until']): ?>anytime<?php endif; ?>
          </td>
          <td>
            <span class="text-xs px-2 py-1 rounded-full <?= $c['status'] === 'active' ? 'bg-gold-500/20 text-gold-400' : 'bg-white/5 text-beige-100/50' ?>"><?= e($c['status']) ?></span>
          </td>
          <td class="text-right pr-4 whitespace-nowrap">
            <a href="<?= url('/admin/promo_share.php?code=' . rawurlencode((string) $c['code'])) ?>" class="text-xs text-gold-400/85 hover:text-gold-300 mr-3">Share →</a>
            <a href="<?= url('/admin/promo_codes.php?edit=' . (int) $c['id']) ?>" class="text-xs text-gold-400 hover:text-gold-300">Edit</a>
            <form method="post" class="inline ml-2">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="toggle">
              <input type="hidden" name="id" value="<?= (int) $c['id'] ?>">
              <button class="text-xs text-beige-100/55 hover:text-gold-400"><?= $c['status'] === 'active' ? 'Disable' : 'Enable' ?></button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$codes): ?>
        <tr><td colspan="6" class="px-4 py-6 text-beige-100/55">No codes yet.</td></tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>

<?php require __DIR__ . '/../includes/admin_layout_end.php'; ?>
