<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_admin();
$pageTitle = 'Revenue split';

$errors = [];

if (is_post()) {
    csrf_verify();
    $action = (string) input('action');

    if ($action === 'save_config') {
        $pct = (float) input('revenue_split_partner_pct', 15);
        $pct = max(0, min(100, $pct));
        set_setting('revenue_split_enabled', !empty($_POST['revenue_split_enabled']) ? '1' : '0', 'bool');
        set_setting('revenue_split_partner_pct', (string) $pct, 'string');
        set_setting('revenue_split_partner_label', trim((string) input('revenue_split_partner_label', 'IT partner')), 'string');
        set_setting('revenue_split_company_label', trim((string) input('revenue_split_company_label', 'Company')), 'string');
        $start = trim((string) input('revenue_split_start_date', ''));
        if ($start !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $start)) {
            $errors[] = 'Start date must be in YYYY-MM-DD format.';
        } else {
            set_setting('revenue_split_start_date', $start, 'string');
        }
        if (!$errors) {
            audit_log('revenue_split.config', 'site_settings', null);
            flash('revsplit', 'Revenue-split settings saved.', 'success');
            redirect('/admin/revenue_splits.php');
        }
    } elseif ($action === 'settle_payout') {
        $res = settle_partner_payout(trim((string) input('reference', '')), current_user_id());
        if ($res['ok']) {
            audit_log('revenue_split.payout', 'partner_payouts', (int) $res['payout_id'], ['amount' => $res['amount'], 'count' => $res['count']]);
            flash('revsplit', 'Recorded a payout of ' . format_money((float) $res['amount']) . ' across ' . (int) $res['count'] . ' split(s).', 'success');
        } else {
            flash('revsplit', $res['message'] ?? 'Could not record payout.', 'error');
        }
        redirect('/admin/revenue_splits.php');
    }
}

$cfg     = revenue_split_config();
$summary = revenue_split_summary();

// CSV export of the full ledger for accounting / external books.
if ((string) input('export') === 'csv') {
    $rows = db()->query(
        "SELECT rs.id, rs.payment_id, rs.created_at AS split_at, p.paid_at,
                u.full_name AS member, p.gateway_bill_id,
                rs.purpose, rs.gross_amount, rs.currency,
                rs.company_pct, rs.partner_pct,
                rs.company_amount, rs.partner_amount,
                rs.status, rs.partner_payout_status,
                rs.partner_payout_id, pp.created_at AS payout_at, pp.reference AS payout_reference,
                rs.reversed_at, rs.note
           FROM revenue_splits rs
           JOIN payments p ON p.id = rs.payment_id
           LEFT JOIN users u ON u.id = p.user_id
           LEFT JOIN partner_payouts pp ON pp.id = rs.partner_payout_id
          ORDER BY rs.id DESC"
    )->fetchAll();

    $filename = 'revenue-splits-' . date('Y-m-d') . '.csv';
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF"); // UTF-8 BOM so Excel reads accents correctly
    fputcsv($out, [
        'Split ID', 'Payment ID', 'Split date', 'Payment paid at', 'Member', 'Bill ID',
        'Purpose', 'Gross amount', 'Currency', 'Company %', 'Partner %',
        'Company amount', 'Partner amount', 'Status', 'Payout status',
        'Payout ID', 'Payout date', 'Payout reference', 'Reversed at', 'Note',
    ]);
    foreach ($rows as $r) {
        fputcsv($out, [
            $r['id'], $r['payment_id'], $r['split_at'], $r['paid_at'],
            $r['member'] ?? '', $r['gateway_bill_id'] ?? '',
            $r['purpose'], $r['gross_amount'], $r['currency'],
            $r['company_pct'], $r['partner_pct'],
            $r['company_amount'], $r['partner_amount'],
            $r['status'], $r['partner_payout_status'],
            $r['partner_payout_id'] ?? '', $r['payout_at'] ?? '', $r['payout_reference'] ?? '',
            $r['reversed_at'] ?? '', $r['note'] ?? '',
        ]);
    }
    fclose($out);
    audit_log('revenue_split.export', 'revenue_splits', null, ['rows' => count($rows)]);
    exit;
}

$splits = db()->query(
    "SELECT rs.*, p.gateway_bill_id, u.full_name
       FROM revenue_splits rs
       JOIN payments p ON p.id = rs.payment_id
       LEFT JOIN users u ON u.id = p.user_id
      ORDER BY rs.id DESC LIMIT 100"
)->fetchAll();

$payouts = db()->query(
    "SELECT pp.*, u.full_name AS by_name
       FROM partner_payouts pp
       LEFT JOIN users u ON u.id = pp.paid_by
      ORDER BY pp.id DESC LIMIT 30"
)->fetchAll();

require __DIR__ . '/../includes/admin_layout.php';

function rs_money($v): string { return format_money((float) $v); }
?>

<div class="flex items-center justify-between gap-4 flex-wrap">
  <div>
    <h1 class="font-serif text-3xl text-beige-100">Revenue split</h1>
    <p class="text-beige-100/60 mt-1 text-sm">Auto-splits every settled payment between the company and the IT partner.</p>
  </div>
  <div class="flex items-center gap-3 flex-wrap">
    <span class="text-xs px-3 py-1.5 rounded-full <?= $cfg['enabled'] ? 'bg-gold-500/20 text-gold-400' : 'bg-white/5 text-beige-100/50' ?>">
      <?= $cfg['enabled'] ? 'Active' : 'Paused' ?> · <?= e(rtrim(rtrim((string) $cfg['company_pct'], '0'), '.')) ?>% / <?= e(rtrim(rtrim((string) $cfg['partner_pct'], '0'), '.')) ?>%
    </span>
    <a href="<?= url('/admin/revenue_splits.php?export=csv') ?>" class="text-xs px-4 py-1.5 rounded-full border border-white/10 text-beige-100/70 hover:border-gold-500/40 hover:text-gold-400 transition">Download CSV</a>
  </div>
</div>

<!-- Summary cards -->
<div class="mt-8 grid sm:grid-cols-2 lg:grid-cols-4 gap-4">
  <div class="border border-white/5 rounded-2xl p-5 bg-navy-900/40">
    <p class="text-[11px] uppercase tracking-widest text-beige-100/50">Gross split</p>
    <p class="font-serif text-2xl text-beige-100 mt-2"><?= e(rs_money($summary['gross_active'])) ?></p>
  </div>
  <div class="border border-white/5 rounded-2xl p-5 bg-navy-900/40">
    <p class="text-[11px] uppercase tracking-widest text-beige-100/50"><?= e($cfg['company_label']) ?> share</p>
    <p class="font-serif text-2xl text-beige-100 mt-2"><?= e(rs_money($summary['company_active'])) ?></p>
  </div>
  <div class="border border-gold-500/30 rounded-2xl p-5 bg-gold-500/5">
    <p class="text-[11px] uppercase tracking-widest text-gold-400/80"><?= e($cfg['partner_label']) ?> · owed now</p>
    <p class="font-serif text-2xl text-gold-400 mt-2"><?= e(rs_money($summary['partner_unpaid'])) ?></p>
    <p class="text-[11px] text-beige-100/45 mt-1"><?= (int) $summary['unpaid_count'] ?> unpaid split(s)</p>
  </div>
  <div class="border border-white/5 rounded-2xl p-5 bg-navy-900/40">
    <p class="text-[11px] uppercase tracking-widest text-beige-100/50"><?= e($cfg['partner_label']) ?> · paid out</p>
    <p class="font-serif text-2xl text-beige-100 mt-2"><?= e(rs_money($summary['partner_paid'])) ?></p>
    <?php if ((float) $summary['partner_reversed'] > 0): ?>
      <p class="text-[11px] text-red-300/70 mt-1"><?= e(rs_money($summary['partner_reversed'])) ?> reversed</p>
    <?php endif; ?>
  </div>
</div>

<div class="mt-10 grid lg:grid-cols-2 gap-6">
  <!-- Config -->
  <form method="post" class="border border-white/5 rounded-3xl p-6 bg-navy-900/40 space-y-5">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="save_config">
    <h2 class="font-serif text-2xl text-gold-400">Settings</h2>

    <label class="flex items-center gap-2 text-sm text-beige-100/70">
      <input type="checkbox" name="revenue_split_enabled" value="1" <?= $cfg['enabled'] ? 'checked' : '' ?>>
      Auto-split new payments
    </label>

    <div class="grid sm:grid-cols-2 gap-4">
      <label class="block">
        <span class="text-xs uppercase tracking-widest text-beige-100/60">Partner share (%)</span>
        <input name="revenue_split_partner_pct" type="number" step="0.01" min="0" max="100" value="<?= e((string) $cfg['partner_pct']) ?>" class="mt-2 w-full rounded-2xl bg-navy-950 border border-white/5 px-4 py-3">
        <span class="text-[11px] text-beige-100/40">Company keeps the remaining <?= e(rtrim(rtrim((string) $cfg['company_pct'], '0'), '.')) ?>%.</span>
      </label>
      <label class="block">
        <span class="text-xs uppercase tracking-widest text-beige-100/60">Cutover date</span>
        <input name="revenue_split_start_date" type="date" value="<?= e($cfg['start_date']) ?>" class="mt-2 w-full rounded-2xl bg-navy-950 border border-white/5 px-4 py-3">
        <span class="text-[11px] text-beige-100/40">Only payments settled on/after this date are split.</span>
      </label>
      <label class="block">
        <span class="text-xs uppercase tracking-widest text-beige-100/60">Company label</span>
        <input name="revenue_split_company_label" value="<?= e($cfg['company_label']) ?>" class="mt-2 w-full rounded-2xl bg-navy-950 border border-white/5 px-4 py-3">
      </label>
      <label class="block">
        <span class="text-xs uppercase tracking-widest text-beige-100/60">Partner label</span>
        <input name="revenue_split_partner_label" value="<?= e($cfg['partner_label']) ?>" class="mt-2 w-full rounded-2xl bg-navy-950 border border-white/5 px-4 py-3">
      </label>
    </div>
    <button class="px-6 py-3 rounded-full bg-gold-500 text-navy-950 hover:bg-gold-400 transition">Save settings</button>
  </form>

  <!-- Payout -->
  <form method="post" class="border border-white/5 rounded-3xl p-6 bg-navy-900/40 space-y-5" onsubmit="return confirm('Record a payout of <?= e(rs_money($summary['partner_unpaid'])) ?> to <?= e(addslashes($cfg['partner_label'])) ?>? This marks the current unpaid splits as paid.');">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="settle_payout">
    <h2 class="font-serif text-2xl text-gold-400">Record a partner payout</h2>
    <p class="text-sm text-beige-100/70 leading-relaxed">When you've transferred the partner their share, log it here. This settles every <strong>unpaid</strong> split into one payout batch for clean records.</p>
    <div class="rounded-2xl border border-gold-500/20 bg-gold-500/5 p-4">
      <p class="text-[11px] uppercase tracking-widest text-gold-400/80">Outstanding to <?= e($cfg['partner_label']) ?></p>
      <p class="font-serif text-3xl text-gold-400 mt-1"><?= e(rs_money($summary['partner_unpaid'])) ?></p>
    </div>
    <label class="block">
      <span class="text-xs uppercase tracking-widest text-beige-100/60">Bank reference / note (optional)</span>
      <input name="reference" placeholder="e.g. DuitNow transfer 24 May" class="mt-2 w-full rounded-2xl bg-navy-950 border border-white/5 px-4 py-3">
    </label>
    <button class="px-6 py-3 rounded-full bg-gold-500 text-navy-950 hover:bg-gold-400 transition <?= (int) $summary['unpaid_count'] === 0 ? 'opacity-50 pointer-events-none' : '' ?>">Mark as paid out</button>
  </form>
</div>

<!-- Recent splits -->
<h2 class="font-serif text-2xl text-beige-100 mt-12">Recent splits</h2>
<div class="mt-4 border border-white/5 rounded-2xl bg-navy-900/40 overflow-x-auto">
  <table class="w-full text-sm">
    <thead class="text-left text-beige-100/50 text-xs uppercase tracking-wider bg-navy-950/40">
      <tr>
        <th class="px-4 py-3">When</th><th>Member</th><th>Purpose</th>
        <th class="text-right">Gross</th><th class="text-right"><?= e($cfg['company_label']) ?></th>
        <th class="text-right"><?= e($cfg['partner_label']) ?></th><th>Status</th><th>Payout</th>
      </tr>
    </thead>
    <tbody class="divide-y divide-white/5">
      <?php foreach ($splits as $s): ?>
        <tr class="<?= $s['status'] === 'reversed' ? 'opacity-50' : '' ?>">
          <td class="px-4 py-3 whitespace-nowrap"><?= e(format_datetime($s['created_at'], 'd M Y')) ?></td>
          <td><?= e($s['full_name'] ?? '—') ?></td>
          <td class="capitalize"><?= e(str_replace('_', ' ', (string) $s['purpose'])) ?> #<?= (int) $s['payment_id'] ?></td>
          <td class="text-right"><?= e(rs_money($s['gross_amount'])) ?></td>
          <td class="text-right"><?= e(rs_money($s['company_amount'])) ?></td>
          <td class="text-right text-gold-400"><?= e(rs_money($s['partner_amount'])) ?></td>
          <td>
            <span class="text-xs px-2 py-1 rounded-full <?= $s['status'] === 'active' ? 'bg-white/5 text-beige-100/60' : 'bg-red-500/15 text-red-300/80' ?>"><?= e($s['status']) ?></span>
          </td>
          <td>
            <span class="text-xs px-2 py-1 rounded-full <?= $s['partner_payout_status'] === 'paid' ? 'bg-gold-500/20 text-gold-400' : 'bg-white/5 text-beige-100/50' ?>"><?= e($s['partner_payout_status']) ?></span>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$splits): ?>
        <tr><td colspan="8" class="px-4 py-6 text-beige-100/60">No splits recorded yet. They'll appear here as payments settle on/after the cutover date.</td></tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>

<?php if ($payouts): ?>
<h2 class="font-serif text-2xl text-beige-100 mt-12">Payout history</h2>
<div class="mt-4 border border-white/5 rounded-2xl bg-navy-900/40 overflow-x-auto">
  <table class="w-full text-sm">
    <thead class="text-left text-beige-100/50 text-xs uppercase tracking-wider bg-navy-950/40">
      <tr><th class="px-4 py-3">When</th><th class="text-right">Amount</th><th>Splits</th><th>Reference</th><th>By</th></tr>
    </thead>
    <tbody class="divide-y divide-white/5">
      <?php foreach ($payouts as $po): ?>
        <tr>
          <td class="px-4 py-3 whitespace-nowrap"><?= e(format_datetime($po['created_at'])) ?></td>
          <td class="text-right text-gold-400"><?= e(rs_money($po['amount'])) ?></td>
          <td><?= (int) $po['split_count'] ?></td>
          <td><?= e($po['reference'] ?? '—') ?></td>
          <td><?= e($po['by_name'] ?? '—') ?></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php endif; ?>

<?php require __DIR__ . '/../includes/admin_layout_end.php'; ?>
