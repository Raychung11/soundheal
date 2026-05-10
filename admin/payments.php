<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_admin();
$pageTitle = 'Payments';

$payments = db()->query(
    "SELECT p.*, u.full_name, u.email
     FROM payments p
     LEFT JOIN users u ON u.id = p.user_id
     ORDER BY p.created_at DESC LIMIT 200"
)->fetchAll();

require __DIR__ . '/../includes/admin_layout.php';
?>
<h1 class="font-serif text-3xl text-beige-100">Payments</h1>

<div class="mt-6 border border-white/5 rounded-2xl bg-navy-900/40 overflow-x-auto">
  <table class="w-full text-sm">
    <thead class="text-left text-beige-100/50 text-xs uppercase tracking-wider bg-navy-950/40">
      <tr><th class="px-4 py-3">When</th><th>Member</th><th>Purpose</th><th>Bill ID</th><th>Amount</th><th>Status</th></tr>
    </thead>
    <tbody class="divide-y divide-white/5">
      <?php foreach ($payments as $p): ?>
        <tr>
          <td class="px-4 py-3"><?= e(format_datetime($p['created_at'])) ?></td>
          <td><?= e($p['full_name'] ?? '—') ?></td>
          <td><?= e($p['purpose']) ?> #<?= (int)$p['reference_id'] ?></td>
          <td class="font-mono text-xs"><?= e($p['gateway_bill_id'] ?? '') ?></td>
          <td><?= e(format_money((float)$p['amount'], (string)$p['currency'])) ?></td>
          <td><span class="text-xs px-2 py-1 rounded-full <?= $p['status'] === 'paid' ? 'bg-gold-500/20 text-gold-400' : 'bg-white/5 text-beige-100/60' ?>"><?= e($p['status']) ?></span></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$payments): ?>
        <tr><td colspan="6" class="px-4 py-6 text-beige-100/60">No payments recorded yet.</td></tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>
<?php require __DIR__ . '/../includes/admin_layout_end.php'; ?>
