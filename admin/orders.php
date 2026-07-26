<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_admin();
$pageTitle = 'Orders';

$status = (string) input('status', '');
$q      = trim((string) input('q', ''));

$where  = ["1=1"];
$params = [];
if (in_array($status, ['pending','paid','preorder','packed','shipped','delivered','cancelled','refunded'], true)) {
    $where[] = "o.status = :st";
    $params[':st'] = $status;
}
if ($q !== '') {
    $where[] = "(o.order_ref LIKE :q OR u.email LIKE :q OR u.full_name LIKE :q)";
    $params[':q'] = '%' . $q . '%';
}
$sql = "SELECT o.*, u.email, u.full_name
          FROM orders o
          JOIN users u ON u.id = o.user_id
         WHERE " . implode(' AND ', $where) . "
         ORDER BY o.created_at DESC
         LIMIT 500";
$stmt = db()->prepare($sql);
$stmt->execute($params);
$orders = $stmt->fetchAll();

$counts = db()->query(
    "SELECT status, COUNT(*) n FROM orders GROUP BY status"
)->fetchAll();
$countMap = [];
foreach ($counts as $r) { $countMap[$r['status']] = (int) $r['n']; }
$totalCount = array_sum($countMap);

$statusPills = [
    ''          => ['All',       $totalCount],
    'pending'   => ['Pending',   $countMap['pending']   ?? 0],
    'paid'      => ['Paid',      $countMap['paid']      ?? 0],
    'preorder'  => ['Preorder',  $countMap['preorder']  ?? 0],
    'packed'    => ['Packed',    $countMap['packed']    ?? 0],
    'shipped'   => ['Shipped',   $countMap['shipped']   ?? 0],
    'delivered' => ['Delivered', $countMap['delivered'] ?? 0],
    'cancelled' => ['Cancelled', $countMap['cancelled'] ?? 0],
    'refunded'  => ['Refunded',  $countMap['refunded']  ?? 0],
];

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

require __DIR__ . '/../includes/admin_layout.php';
?>

<div class="flex items-center justify-between gap-4 flex-wrap">
  <div>
    <h1 class="font-serif text-3xl text-beige-100">Orders</h1>
    <p class="text-beige-100/60 mt-1 text-sm">Shop orders. Move each one through packed → shipped → delivered as you fulfil.</p>
  </div>
  <form method="get" class="flex items-center gap-2">
    <?php if ($status): ?><input type="hidden" name="status" value="<?= e($status) ?>"><?php endif; ?>
    <input name="q" value="<?= e($q) ?>" placeholder="Ref, name, email"
           class="rounded-full bg-navy-900 border border-white/10 px-4 py-2 text-sm focus:border-gold-500/50 focus:outline-none">
    <button class="text-xs text-beige-100/70 hover:text-gold-400 px-3">Search</button>
  </form>
</div>

<div class="mt-4 flex flex-wrap gap-2">
  <?php foreach ($statusPills as $key => [$label, $n]): ?>
    <a href="<?= url('/admin/orders.php' . ($key !== '' ? '?status=' . urlencode($key) : '')) ?>"
       class="px-3 py-1.5 rounded-full text-xs border transition
              <?= $status === $key ? 'border-gold-500/40 bg-gold-500/10 text-gold-400' : 'border-white/10 text-beige-100/70 hover:border-white/25' ?>">
      <?= e($label) ?> <span class="opacity-60"><?= $n ?></span>
    </a>
  <?php endforeach; ?>
</div>

<div class="mt-6 border border-white/5 rounded-3xl bg-navy-900/40 p-6">
  <?php if (!$orders): ?>
    <p class="text-sm text-beige-100/60">No orders match.</p>
  <?php else: ?>
    <div class="overflow-x-auto">
    <table class="w-full text-sm">
      <thead class="text-left text-beige-100/50 text-xs uppercase tracking-wider">
        <tr>
          <th class="py-2">Order</th>
          <th>Customer</th>
          <th>Placed</th>
          <th>Total</th>
          <th>Status</th>
          <th class="text-right"></th>
        </tr>
      </thead>
      <tbody class="divide-y divide-white/5">
        <?php foreach ($orders as $o): ?>
          <tr>
            <td class="py-3">
              <p class="text-beige-100 font-mono text-[12px]"><?= e((string)$o['order_ref']) ?></p>
              <?php if ((int)$o['has_preorder']): ?>
                <span class="text-[10px] text-amber-300 uppercase tracking-widest">Preorder</span>
              <?php endif; ?>
            </td>
            <td>
              <p class="text-beige-100"><?= e((string)$o['full_name']) ?></p>
              <p class="text-[11px] text-beige-100/50"><?= e((string)$o['email']) ?></p>
            </td>
            <td class="text-beige-100/70"><?= e(format_datetime((string)$o['created_at'])) ?></td>
            <td class="text-beige-100"><?= e(format_money((float)$o['total'])) ?></td>
            <td>
              <span class="text-xs px-2 py-1 rounded-full <?= $statusColor[$o['status']] ?? 'bg-white/5' ?>"><?= e((string)$o['status']) ?></span>
            </td>
            <td class="text-right">
              <a href="<?= url('/admin/order.php?id=' . (int)$o['id']) ?>" class="text-xs text-gold-400 hover:text-gold-300">Open →</a>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    </div>
  <?php endif; ?>
</div>

<?php require __DIR__ . '/../includes/admin_layout_end.php'; ?>
