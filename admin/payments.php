<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_admin();
$pageTitle = 'Payments';

if (is_post()) {
    csrf_verify();
    $action = (string) input('action');
    $pid = (int) input('payment_id', 0);

    if ($action === 'settle' && $pid > 0) {
        // Manual rescue: marks a pending payment as paid and issues tickets
        // or activates the membership via the existing settle_payment() path.
        // Use this only when the Billplz webhook can't reach us (e.g. wrong
        // callback URL during checkout). settle_payment() is idempotent.
        $stmt = db()->prepare("SELECT * FROM payments WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $pid]);
        $row = $stmt->fetch();
        if ($row && $row['status'] !== 'paid') {
            db()->prepare("UPDATE payments SET status = 'paid', paid_at = COALESCE(paid_at, NOW()) WHERE id = :id")
                ->execute([':id' => $pid]);

            require_once __DIR__ . '/../api/billplz_create.php';
            if (function_exists('settle_payment')) {
                settle_payment($pid);
            }
            audit_log('payment.manual_settle', 'payments', $pid);
            flash('payments', 'Payment #' . $pid . ' settled manually.', 'success');
        } else {
            flash('payments', 'Nothing to settle.', 'info');
        }
        redirect('/admin/payments.php');
    }
}

$payments = db()->query(
    "SELECT p.*, u.full_name, u.email
     FROM payments p
     LEFT JOIN users u ON u.id = p.user_id
     ORDER BY p.created_at DESC LIMIT 200"
)->fetchAll();

require __DIR__ . '/../includes/admin_layout.php';
?>
<div class="flex items-center justify-between gap-4 flex-wrap">
  <h1 class="font-serif text-3xl text-beige-100">Payments</h1>
  <a href="<?= url('/admin/payment_settings.php') ?>" class="text-sm text-gold-400 hover:text-gold-300">Billplz settings →</a>
</div>

<div class="mt-6 border border-white/5 rounded-2xl bg-navy-900/40 overflow-x-auto">
  <table class="w-full text-sm">
    <thead class="text-left text-beige-100/50 text-xs uppercase tracking-wider bg-navy-950/40">
      <tr>
        <th class="px-4 py-3">When</th><th>Member</th><th>Purpose</th>
        <th>Bill ID</th><th>Amount</th><th>Status</th><th></th>
      </tr>
    </thead>
    <tbody class="divide-y divide-white/5">
      <?php foreach ($payments as $p): ?>
        <tr>
          <td class="px-4 py-3"><?= e(format_datetime($p['created_at'])) ?></td>
          <td><?= e($p['full_name'] ?? '—') ?></td>
          <td><?= e($p['purpose']) ?> #<?= (int)$p['reference_id'] ?></td>
          <td class="font-mono text-xs"><?= e($p['gateway_bill_id'] ?? '') ?></td>
          <td><?= e(format_money((float)$p['amount'], (string)$p['currency'])) ?></td>
          <td>
            <span class="text-xs px-2 py-1 rounded-full <?= $p['status'] === 'paid' ? 'bg-gold-500/20 text-gold-400' : 'bg-white/5 text-beige-100/60' ?>"><?= e($p['status']) ?></span>
          </td>
          <td class="text-right pr-4 whitespace-nowrap">
            <?php if ($p['status'] === 'pending'): ?>
              <form method="post" class="inline" onsubmit="return confirm('Mark this payment as paid and issue tickets / activate membership? Only do this if the Billplz webhook never reached us.');">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="settle">
                <input type="hidden" name="payment_id" value="<?= (int)$p['id'] ?>">
                <button class="text-xs text-gold-400 hover:text-gold-300">Settle</button>
              </form>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$payments): ?>
        <tr><td colspan="7" class="px-4 py-6 text-beige-100/60">No payments recorded yet.</td></tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>
<?php require __DIR__ . '/../includes/admin_layout_end.php'; ?>
