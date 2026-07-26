<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_admin();
$pageTitle = 'Order';

$id = (int) input('id', 0);
if ($id <= 0) { redirect('/admin/orders.php'); }

if (is_post()) {
    csrf_verify();
    $action = (string) input('action', '');

    if ($action === 'status') {
        $to = (string) input('to', '');
        $allowed = ['pending','paid','preorder','packed','shipped','delivered','cancelled','refunded'];
        if (in_array($to, $allowed, true)) {
            $extras = '';
            $params = [':s' => $to, ':id' => $id];
            if ($to === 'shipped') {
                $extras .= ", shipped_at = COALESCE(shipped_at, NOW())";
            }
            if ($to === 'delivered') {
                $extras .= ", delivered_at = COALESCE(delivered_at, NOW())";
            }
            if ($to === 'paid' || $to === 'preorder') {
                $extras .= ", paid_at = COALESCE(paid_at, NOW())";
            }
            db()->prepare("UPDATE orders SET status = :s $extras WHERE id = :id")->execute($params);
            audit_log('order.status', 'orders', $id, ['to' => $to]);
            flash('order', "Status set to $to.", 'success');
        }
        redirect('/admin/order.php?id=' . $id);
    }

    if ($action === 'tracking') {
        $courier = trim((string) input('tracking_courier', ''));
        $number  = trim((string) input('tracking_number', ''));
        db()->prepare(
            "UPDATE orders SET tracking_courier = :c, tracking_number = :n WHERE id = :id"
        )->execute([':c' => $courier ?: null, ':n' => $number ?: null, ':id' => $id]);
        audit_log('order.tracking', 'orders', $id, ['courier' => $courier, 'number' => $number]);
        flash('order', 'Tracking saved.', 'success');
        redirect('/admin/order.php?id=' . $id);
    }

    if ($action === 'notes') {
        $notes = trim((string) input('notes', ''));
        db()->prepare("UPDATE orders SET notes = :n WHERE id = :id")
            ->execute([':n' => $notes ?: null, ':id' => $id]);
        flash('order', 'Notes saved.', 'success');
        redirect('/admin/order.php?id=' . $id);
    }
}

$order = order_get($id, 0);
if (!$order) { flash('orders', 'Order not found.', 'error'); redirect('/admin/orders.php'); }

$customer = db()->prepare("SELECT id, full_name, email, phone FROM users WHERE id = :u");
$customer->execute([':u' => (int)$order['user_id']]);
$customer = $customer->fetch();

$ship = $order['ship_to_snapshot'] ? (json_decode((string)$order['ship_to_snapshot'], true) ?: []) : [];

$statusColor = [
    'pending'   => 'bg-white/5 text-beige-100/60',
    'paid'      => 'bg-emerald-500/10 text-emerald-300',
    'preorder'  => 'bg-amber-500/15 text-amber-300',
    'packed'    => 'bg-sky-500/15 text-sky-300',
    'shipped'   => 'bg-indigo-500/15 text-indigo-300',
    'delivered' => 'bg-gold-500/20 text-gold-400',
    'cancelled' => 'bg-white/5 text-beige-100/40',
    'refunded'  => 'bg-red-500/10 text-red-300',
];

// Which status flips make sense from the current one? Cancel/refund
// are always available; forward moves depend on where we are.
$forwardMap = [
    'pending'   => ['paid', 'cancelled'],
    'paid'      => ['packed', 'refunded'],
    'preorder'  => ['packed', 'refunded'],
    'packed'    => ['shipped', 'refunded'],
    'shipped'   => ['delivered', 'refunded'],
    'delivered' => ['refunded'],
    'cancelled' => [],
    'refunded'  => [],
];
$nextOptions = $forwardMap[$order['status']] ?? [];

// Look up any invoice + receipt for this order for quick links.
$docStmt = db()->prepare(
    "SELECT id, doc_type, doc_number, access_token, status
       FROM invoices
      WHERE purpose = 'order' AND reference_id = :r
      ORDER BY id"
);
$docStmt->execute([':r' => $id]);
$docs = $docStmt->fetchAll();

require __DIR__ . '/../includes/admin_layout.php';
?>

<div class="flex items-center justify-between gap-4 flex-wrap">
  <div>
    <p class="text-xs uppercase tracking-widest text-beige-100/50">Order</p>
    <h1 class="font-serif text-3xl text-beige-100 mt-1"><?= e((string)$order['order_ref']) ?></h1>
    <p class="text-beige-100/60 text-sm mt-1"><?= e(format_datetime((string)$order['created_at'])) ?> · <?= e(format_money((float)$order['total'])) ?></p>
  </div>
  <div class="flex items-center gap-2">
    <span class="text-xs px-3 py-1.5 rounded-full <?= $statusColor[$order['status']] ?? 'bg-white/5' ?>"><?= e((string)$order['status']) ?></span>
    <?php if ((int)$order['has_preorder']): ?>
      <span class="text-[10px] uppercase tracking-widest text-amber-300 px-3 py-1.5 rounded-full bg-amber-500/10">Includes preorder</span>
    <?php endif; ?>
    <a href="<?= url('/admin/orders.php') ?>" class="text-xs text-beige-100/60 hover:text-gold-400 ml-2">← All orders</a>
  </div>
</div>

<?php if ($f = flash('order')): ?>
  <div class="mt-6 border rounded-2xl px-5 py-4 text-sm <?= ($f['type'] ?? 'info') === 'success' ? 'border-gold-500/40 bg-gold-500/5 text-gold-400' : 'border-white/10 bg-navy-900/40 text-beige-100/85' ?>"><?= e($f['message'] ?? '') ?></div>
<?php endif; ?>

<div class="grid lg:grid-cols-[1fr_320px] gap-8 mt-6">
  <div class="space-y-6">
    <div class="border border-white/5 rounded-3xl bg-navy-900/40 p-6">
      <h2 class="font-serif text-xl text-gold-400">Line items</h2>
      <table class="mt-4 w-full text-sm">
        <thead class="text-left text-beige-100/50 text-xs uppercase tracking-wider">
          <tr><th class="py-2">Item</th><th>Qty</th><th>Unit</th><th class="text-right">Amount</th></tr>
        </thead>
        <tbody class="divide-y divide-white/5">
          <?php foreach ($order['items'] as $it): ?>
            <tr>
              <td class="py-3">
                <p class="text-beige-100"><?= e((string)$it['title_snapshot']) ?></p>
                <?php if ((int)$it['is_preorder']): ?>
                  <p class="text-[11px] text-amber-300 mt-0.5">Pre-order<?= $it['preorder_eta'] ? ' · ' . e((string)$it['preorder_eta']) : '' ?></p>
                <?php endif; ?>
                <?php if ($it['product_id']): ?>
                  <a href="<?= url('/admin/products.php?edit=' . (int)$it['product_id']) ?>" class="text-[11px] text-beige-100/40 hover:text-gold-400">Product #<?= (int)$it['product_id'] ?></a>
                <?php else: ?>
                  <p class="text-[11px] text-beige-100/35">Product deleted</p>
                <?php endif; ?>
              </td>
              <td class="text-beige-100/85"><?= (int)$it['quantity'] ?></td>
              <td class="text-beige-100/70"><?= e(format_money((float)$it['unit_price'])) ?></td>
              <td class="text-right text-beige-100"><?= e(format_money((float)$it['amount'])) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
        <tfoot class="text-sm">
          <tr class="text-beige-100/60"><td colspan="3" class="pt-4 text-right">Subtotal</td><td class="pt-4 text-right"><?= e(format_money((float)$order['subtotal'])) ?></td></tr>
          <?php if ((float)$order['shipping'] > 0): ?>
            <tr class="text-beige-100/60"><td colspan="3" class="text-right">Shipping</td><td class="text-right"><?= e(format_money((float)$order['shipping'])) ?></td></tr>
          <?php endif; ?>
          <?php if ((float)$order['tax'] > 0): ?>
            <tr class="text-beige-100/60"><td colspan="3" class="text-right">Tax</td><td class="text-right"><?= e(format_money((float)$order['tax'])) ?></td></tr>
          <?php endif; ?>
          <tr class="text-beige-100 text-base"><td colspan="3" class="pt-2 text-right">Total</td><td class="pt-2 text-right"><?= e(format_money((float)$order['total'])) ?></td></tr>
        </tfoot>
      </table>
    </div>

    <div class="border border-white/5 rounded-3xl bg-navy-900/40 p-6">
      <h2 class="font-serif text-xl text-gold-400">Ship to</h2>
      <?php if (!$ship): ?>
        <p class="text-sm text-beige-100/50 mt-3">No delivery address captured.</p>
      <?php else: ?>
        <div class="mt-3 text-sm text-beige-100/85 space-y-1">
          <?php if (!empty($ship['name'])): ?><p class="text-beige-100"><?= e((string)$ship['name']) ?></p><?php endif; ?>
          <?php if (!empty($ship['phone'])): ?><p><?= e((string)$ship['phone']) ?></p><?php endif; ?>
          <?php foreach (['address_line1','address_line2'] as $f): if (!empty($ship[$f])): ?>
            <p><?= e((string)$ship[$f]) ?></p>
          <?php endif; endforeach; ?>
          <?php if (!empty($ship['city']) || !empty($ship['postcode'])): ?>
            <p><?= e(trim(($ship['postcode'] ?? '') . ' ' . ($ship['city'] ?? ''))) ?></p>
          <?php endif; ?>
          <?php if (!empty($ship['country'])): ?><p><?= e((string)$ship['country']) ?></p><?php endif; ?>
          <?php if (!empty($ship['notes'])): ?>
            <p class="mt-3 text-beige-100/60 italic border-l-2 border-white/10 pl-3"><?= e((string)$ship['notes']) ?></p>
          <?php endif; ?>
        </div>
      <?php endif; ?>
    </div>

    <form method="post" class="border border-white/5 rounded-3xl bg-navy-900/40 p-6">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="tracking">
      <h2 class="font-serif text-xl text-gold-400">Tracking</h2>
      <div class="grid sm:grid-cols-2 gap-4 mt-3">
        <label class="block">
          <span class="text-xs uppercase tracking-widest text-beige-100/60">Courier</span>
          <input name="tracking_courier" value="<?= e((string)($order['tracking_courier'] ?? '')) ?>" placeholder="e.g. J&T, Poslaju"
                 class="mt-2 w-full rounded-2xl bg-navy-950 border border-white/5 px-4 py-3 focus:border-gold-500/50 focus:outline-none">
        </label>
        <label class="block">
          <span class="text-xs uppercase tracking-widest text-beige-100/60">Tracking number</span>
          <input name="tracking_number" value="<?= e((string)($order['tracking_number'] ?? '')) ?>"
                 class="mt-2 w-full rounded-2xl bg-navy-950 border border-white/5 px-4 py-3 font-mono text-sm focus:border-gold-500/50 focus:outline-none">
        </label>
      </div>
      <button class="mt-4 px-5 py-2 rounded-full bg-gold-500 text-navy-950 text-sm hover:bg-gold-400">Save tracking</button>
    </form>

    <form method="post" class="border border-white/5 rounded-3xl bg-navy-900/40 p-6">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="notes">
      <h2 class="font-serif text-xl text-gold-400">Internal notes</h2>
      <textarea name="notes" rows="4" class="mt-3 w-full rounded-2xl bg-navy-950 border border-white/5 px-4 py-3 focus:border-gold-500/50 focus:outline-none"><?= e((string)($order['notes'] ?? '')) ?></textarea>
      <button class="mt-3 px-5 py-2 rounded-full bg-gold-500 text-navy-950 text-sm hover:bg-gold-400">Save notes</button>
    </form>
  </div>

  <aside class="space-y-6">
    <div class="border border-white/5 rounded-3xl bg-navy-900/40 p-6">
      <h3 class="text-xs uppercase tracking-widest text-beige-100/50">Customer</h3>
      <?php if ($customer): ?>
        <p class="mt-2 text-beige-100"><?= e((string)$customer['full_name']) ?></p>
        <p class="text-sm text-beige-100/70"><?= e((string)$customer['email']) ?></p>
        <?php if (!empty($customer['phone'])): ?>
          <p class="text-sm text-beige-100/70"><?= e((string)$customer['phone']) ?></p>
        <?php endif; ?>
      <?php else: ?>
        <p class="text-sm text-beige-100/50 mt-2">User removed.</p>
      <?php endif; ?>
    </div>

    <div class="border border-white/5 rounded-3xl bg-navy-900/40 p-6">
      <h3 class="text-xs uppercase tracking-widest text-beige-100/50">Move forward</h3>
      <?php if (!$nextOptions): ?>
        <p class="text-sm text-beige-100/55 mt-2">Order is closed.</p>
      <?php else: ?>
        <div class="mt-3 flex flex-col gap-2">
          <?php foreach ($nextOptions as $to): ?>
            <form method="post">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="status">
              <input type="hidden" name="to" value="<?= e($to) ?>">
              <button class="w-full text-left px-3 py-2 rounded-full border border-white/10 text-sm text-beige-100 hover:border-gold-500/50 hover:text-gold-400">
                Mark as <?= e($to) ?>
              </button>
            </form>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
      <p class="text-[11px] text-beige-100/40 mt-3">Payment status flips automatically after Billplz settles. Use these to advance fulfilment.</p>
    </div>

    <?php if ($docs): ?>
    <div class="border border-white/5 rounded-3xl bg-navy-900/40 p-6">
      <h3 class="text-xs uppercase tracking-widest text-beige-100/50">Documents</h3>
      <ul class="mt-2 text-sm space-y-1">
        <?php foreach ($docs as $d): ?>
          <li>
            <a href="<?= url('/member/document.php?id=' . (int)$d['id'] . '&t=' . urlencode((string)$d['access_token'])) ?>" target="_blank"
               class="text-gold-400 hover:text-gold-300">
              <?= e((string)($d['doc_number'] ?? ucfirst($d['doc_type']))) ?>
            </a>
            <span class="text-beige-100/50 text-xs">· <?= e((string)$d['status']) ?></span>
          </li>
        <?php endforeach; ?>
      </ul>
    </div>
    <?php endif; ?>
  </aside>
</div>

<?php require __DIR__ . '/../includes/admin_layout_end.php'; ?>
