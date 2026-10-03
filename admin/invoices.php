<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_staff_or_admin();
$pageTitle = 'Invoices';

// Mark-paid action for manual invoices — offline bank-transfer path.
if (is_post()) {
    csrf_verify();
    $act = (string) input('action', '');
    $id  = (int) input('id', 0);
    if ($act === 'mark_paid' && $id > 0) {
        $note = trim((string) input('note', ''));
        if (mark_invoice_paid_manually($id, $note !== '' ? $note : null)) {
            flash('invoice', 'Invoice marked paid.', 'success');
        } else {
            flash('invoice', 'Invoice could not be updated (already paid or voided?).', 'error');
        }
    }
    redirect('/admin/invoices.php');
}

$filterType   = input('type');
$filterStatus = input('status');
$search       = trim((string) input('q', ''));

$where = [];
$params = [];
if (in_array($filterType, ['invoice','receipt'], true)) {
    $where[] = 'i.doc_type = :type';
    $params[':type'] = $filterType;
}
if (in_array($filterStatus, ['due','paid','refunded','void'], true)) {
    $where[] = 'i.status = :status';
    $params[':status'] = $filterStatus;
}
if ($search !== '') {
    $where[] = '(i.doc_number LIKE :q OR u.email LIKE :q OR u.full_name LIKE :q)';
    $params[':q'] = '%' . $search . '%';
}
$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$stmt = db()->prepare(
    "SELECT i.*, u.full_name, u.email
       FROM invoices i
       LEFT JOIN users u ON u.id = i.user_id
       {$whereSql}
       ORDER BY i.issued_at DESC, i.id DESC
       LIMIT 200"
);
$stmt->execute($params);
$rows = $stmt->fetchAll();

require __DIR__ . '/../includes/admin_layout.php';
?>

<div class="flex items-center justify-between gap-4 flex-wrap">
  <div>
    <h1 class="font-serif text-3xl text-beige-100">Invoices &amp; receipts</h1>
    <p class="text-beige-100/60 mt-1 text-sm">Auto-issued on every booking + membership. Manual B2B invoices (speaker fees, sponsorships) can be created below.</p>
  </div>
  <a href="<?= url('/admin/invoice_new.php') ?>" class="px-5 py-2.5 rounded-full bg-gold-500 text-navy-950 hover:bg-gold-400 transition text-sm">+ New invoice</a>
</div>

<form method="get" class="mt-6 flex flex-wrap gap-2 items-center text-sm">
  <input name="q" value="<?= e($search) ?>" placeholder="Search number / name / email…"
         class="flex-1 min-w-[200px] rounded-full bg-navy-900 border border-white/5 px-4 py-2">
  <select name="type" class="rounded-full bg-navy-900 border border-white/5 px-4 py-2">
    <option value="">All types</option>
    <option value="invoice"  <?= $filterType === 'invoice'  ? 'selected' : '' ?>>Invoice</option>
    <option value="receipt"  <?= $filterType === 'receipt'  ? 'selected' : '' ?>>Receipt</option>
  </select>
  <select name="status" class="rounded-full bg-navy-900 border border-white/5 px-4 py-2">
    <option value="">All status</option>
    <?php foreach (['due','paid','refunded','void'] as $opt): ?>
      <option value="<?= $opt ?>" <?= $filterStatus === $opt ? 'selected' : '' ?>><?= $opt ?></option>
    <?php endforeach; ?>
  </select>
  <button class="px-4 py-2 rounded-full bg-gold-500 text-navy-950 hover:bg-gold-400 transition">Filter</button>
  <?php if ($filterType || $filterStatus || $search !== ''): ?>
    <a href="<?= url('/admin/invoices.php') ?>" class="text-beige-100/60 hover:text-gold-400 text-xs">Clear</a>
  <?php endif; ?>
</form>

<div class="mt-6 border border-white/5 rounded-2xl bg-navy-900/40 overflow-x-auto">
  <table class="w-full text-sm">
    <thead class="text-left text-beige-100/50 text-xs uppercase tracking-wider bg-navy-950/40">
      <tr>
        <th class="px-4 py-3">Number</th>
        <th>Type</th>
        <th>Customer</th>
        <th>Issued</th>
        <th>For</th>
        <th class="text-right">Amount</th>
        <th>Status</th>
        <th></th>
      </tr>
    </thead>
    <tbody class="divide-y divide-white/5">
      <?php foreach ($rows as $r):
        // Manual (company) invoices carry the bill-to name in the
        // customer_snapshot JSON — user_id points at the admin who
        // created it, not the invoicee, so we surface the snapshot.
        $isManual = ($r['bill_to_type'] ?? 'user') === 'company' || ($r['purpose'] ?? '') === 'manual';
        $displayName = $r['full_name'] ?? '—';
        $displayEmail = $r['email'] ?? '';
        if ($isManual) {
            $snap = json_decode((string) ($r['customer_snapshot'] ?? ''), true) ?: [];
            $displayName  = (string) ($snap['name']  ?? $displayName);
            $displayEmail = (string) ($snap['email'] ?? '');
        }
      ?>
        <tr>
          <td class="px-4 py-3 font-mono text-beige-100"><?= e($r['doc_number'] ?? '—') ?></td>
          <td class="capitalize"><?= e($r['doc_type']) ?><?php if ($isManual): ?><span class="ml-2 text-[10px] uppercase tracking-widest text-gold-400/70 border border-gold-500/30 rounded-full px-2 py-0.5">B2B</span><?php endif; ?></td>
          <td>
            <p class="text-beige-100"><?= e($displayName) ?></p>
            <p class="text-xs text-beige-100/45"><?= e($displayEmail) ?></p>
          </td>
          <td><?= e(format_datetime($r['issued_at'], 'd M Y')) ?></td>
          <td class="capitalize text-beige-100/65">
            <?= e($r['purpose']) ?><?php if (!$isManual && $r['reference_id']): ?> #<?= (int) $r['reference_id'] ?><?php endif; ?>
          </td>
          <td class="text-right"><?= e(format_money((float) $r['total'], (string) $r['currency'])) ?></td>
          <td>
            <span class="text-xs px-2 py-1 rounded-full <?= $r['status'] === 'paid' ? 'bg-gold-500/20 text-gold-400' : 'bg-white/5 text-beige-100/65' ?>">
              <?= e($r['status']) ?>
            </span>
          </td>
          <td class="text-right pr-4 whitespace-nowrap">
            <a href="<?= url('/member/document.php?id=' . (int) $r['id'] . '&t=' . urlencode((string) $r['access_token'])) ?>"
               class="text-gold-400/85 hover:text-gold-300 text-sm mr-3" target="_blank">View →</a>
            <?php if ($isManual && $r['status'] === 'due'): ?>
              <form method="post" class="inline" onsubmit="return confirm('Mark this invoice as paid?');">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="mark_paid">
                <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                <button class="text-xs text-gold-400/85 hover:text-gold-300">Mark paid</button>
              </form>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$rows): ?>
        <tr><td colspan="8" class="px-4 py-6 text-beige-100/60">No documents match those filters.</td></tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>

<?php require __DIR__ . '/../includes/admin_layout_end.php'; ?>
