<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_login();
$pageTitle = 'My Orders';
$user = current_user();

$stmt = db()->prepare(
    "SELECT o.*,
            (SELECT id FROM invoices
              WHERE doc_type='receipt' AND purpose='order' AND reference_id = o.id
              ORDER BY id DESC LIMIT 1) AS receipt_id,
            (SELECT access_token FROM invoices
              WHERE doc_type='receipt' AND purpose='order' AND reference_id = o.id
              ORDER BY id DESC LIMIT 1) AS receipt_token,
            (SELECT id FROM invoices
              WHERE doc_type='invoice' AND purpose='order' AND reference_id = o.id
              ORDER BY id DESC LIMIT 1) AS invoice_id,
            (SELECT access_token FROM invoices
              WHERE doc_type='invoice' AND purpose='order' AND reference_id = o.id
              ORDER BY id DESC LIMIT 1) AS invoice_token
       FROM orders o
      WHERE o.user_id = :u
      ORDER BY o.created_at DESC"
);
$stmt->execute([':u' => $user['id']]);
$orders = $stmt->fetchAll();

// Batch-load line items so we can show a one-line summary per order.
$itemsByOrder = [];
if ($orders) {
    $ids = array_map(fn($o) => (int) $o['id'], $orders);
    $ph  = implode(',', array_fill(0, count($ids), '?'));
    $it  = db()->prepare("SELECT order_id, title_snapshot, quantity, is_preorder, preorder_eta FROM order_items WHERE order_id IN ($ph) ORDER BY id");
    $it->execute($ids);
    foreach ($it->fetchAll() as $row) {
        $itemsByOrder[(int) $row['order_id']][] = $row;
    }
}

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

require __DIR__ . '/../includes/header.php';
?>
<section class="max-w-5xl mx-auto px-6 py-16">
  <h1 class="font-serif text-4xl text-beige-100">My orders</h1>
  <p class="mt-2 text-beige-100/60">Everything you've ordered from the shop — including pre-orders in progress.</p>

  <?php if ($f = flash('payment')): ?>
    <div class="mt-6 border rounded-2xl px-5 py-4 text-sm <?= ($f['type'] ?? 'info') === 'error' ? 'border-red-400/40 bg-red-500/5 text-red-200' : 'border-gold-500/40 bg-gold-500/5 text-gold-400' ?>"><?= e($f['message'] ?? '') ?></div>
  <?php endif; ?>

  <?php if (!$orders): ?>
    <div class="mt-12 border border-white/5 rounded-3xl p-12 text-center bg-navy-900/40">
      <p class="text-beige-100/70">You haven't placed an order yet.</p>
      <a href="<?= url('/public/shop.php') ?>" class="inline-block mt-6 px-6 py-3 rounded-full bg-gold-500 text-navy-950 hover:bg-gold-400 transition">Visit the shop</a>
    </div>
  <?php else: ?>
    <div class="mt-10 space-y-4">
      <?php foreach ($orders as $o): $items = $itemsByOrder[(int)$o['id']] ?? []; ?>
        <div class="border border-white/5 rounded-3xl p-6 bg-navy-900/40">
          <div class="flex items-start justify-between gap-4 flex-wrap">
            <div>
              <p class="text-xs uppercase tracking-widest text-beige-100/50"><?= e(format_datetime((string)$o['created_at'], 'D, d M Y')) ?></p>
              <p class="font-mono text-beige-100 mt-1"><?= e((string)$o['order_ref']) ?></p>
            </div>
            <div class="flex items-center gap-2">
              <span class="text-xs px-2 py-1 rounded-full <?= $statusColor[$o['status']] ?? 'bg-white/5' ?>"><?= e((string)$o['status']) ?></span>
              <span class="text-beige-100 font-serif"><?= e(format_money((float)$o['total'])) ?></span>
            </div>
          </div>
          <?php if ($items): ?>
            <ul class="mt-4 text-sm text-beige-100/75 space-y-1">
              <?php foreach ($items as $it): ?>
                <li>
                  <?= (int)$it['quantity'] ?> × <?= e((string)$it['title_snapshot']) ?>
                  <?php if ((int)$it['is_preorder']): ?>
                    <span class="text-[11px] text-amber-300 ml-1">· pre-order<?= $it['preorder_eta'] ? ' · ' . e((string)$it['preorder_eta']) : '' ?></span>
                  <?php endif; ?>
                </li>
              <?php endforeach; ?>
            </ul>
          <?php endif; ?>
          <?php if ($o['tracking_number']): ?>
            <p class="mt-3 text-xs text-beige-100/70">Tracking · <?= e((string)($o['tracking_courier'] ?? '')) ?> <span class="font-mono"><?= e((string)$o['tracking_number']) ?></span></p>
          <?php endif; ?>
          <?php $docs = []; ?>
          <?php if ($o['invoice_id']): $docs[] = ['Invoice', (int)$o['invoice_id'], (string)$o['invoice_token']]; endif; ?>
          <?php if ($o['receipt_id']): $docs[] = ['Receipt', (int)$o['receipt_id'], (string)$o['receipt_token']]; endif; ?>
          <?php if ($docs || $o['status'] === 'pending'): ?>
            <div class="mt-4 pt-3 border-t border-white/5 flex flex-wrap items-center gap-3 text-sm">
              <?php foreach ($docs as [$label, $did, $tok]): ?>
                <a class="text-gold-400 hover:text-gold-300" href="<?= url('/member/document.php?id=' . $did . '&t=' . urlencode($tok)) ?>" target="_blank"><?= e($label) ?> →</a>
              <?php endforeach; ?>
              <?php if ($o['status'] === 'pending'): ?>
                <a class="text-gold-400 hover:text-gold-300" href="<?= url('/api/billplz_create.php?purpose=order&ref=' . (int)$o['id']) ?>">Complete payment →</a>
              <?php endif; ?>
            </div>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</section>
<?php require __DIR__ . '/../includes/footer.php'; ?>
