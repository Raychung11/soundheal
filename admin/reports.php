<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_admin();
$pageTitle = 'Reports';

$pdo = db();
$revenueByMonth = $pdo->query(
    "SELECT DATE_FORMAT(paid_at, '%Y-%m') AS ym, SUM(amount) AS total, COUNT(*) AS n
       FROM payments WHERE status = 'paid' AND paid_at IS NOT NULL
       GROUP BY ym ORDER BY ym DESC LIMIT 12"
)->fetchAll();

$topEvents = $pdo->query(
    "SELECT e.title, COUNT(b.id) AS bookings, SUM(b.total_amount) AS revenue
       FROM event_bookings b JOIN events e ON e.id = b.event_id
       WHERE b.status IN ('paid','attended')
       GROUP BY e.id ORDER BY revenue DESC LIMIT 10"
)->fetchAll();

$membershipMix = $pdo->query(
    "SELECT p.name, COUNT(m.id) AS active
       FROM membership_plans p
       LEFT JOIN memberships m ON m.plan_id = p.id AND m.status = 'active'
       GROUP BY p.id ORDER BY active DESC"
)->fetchAll();

require __DIR__ . '/../includes/admin_layout.php';
?>
<h1 class="font-serif text-3xl text-beige-100">Reports</h1>

<div class="mt-8 grid lg:grid-cols-2 gap-6">
  <div class="border border-white/5 rounded-2xl p-6 bg-navy-900/40">
    <h2 class="font-serif text-xl text-gold-400">Revenue by month</h2>
    <table class="mt-4 w-full text-sm">
      <thead class="text-left text-beige-100/50 text-xs uppercase tracking-wider">
        <tr><th class="py-2">Month</th><th>Payments</th><th class="text-right">Revenue</th></tr>
      </thead>
      <tbody class="divide-y divide-white/5">
        <?php foreach ($revenueByMonth as $r): ?>
          <tr><td class="py-2"><?= e($r['ym']) ?></td><td><?= (int)$r['n'] ?></td><td class="text-right"><?= e(format_money((float)$r['total'])) ?></td></tr>
        <?php endforeach; ?>
        <?php if (!$revenueByMonth): ?><tr><td colspan="3" class="py-4 text-beige-100/60">No data.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>

  <div class="border border-white/5 rounded-2xl p-6 bg-navy-900/40">
    <h2 class="font-serif text-xl text-gold-400">Top sessions</h2>
    <table class="mt-4 w-full text-sm">
      <thead class="text-left text-beige-100/50 text-xs uppercase tracking-wider">
        <tr><th class="py-2">Session</th><th>Bookings</th><th class="text-right">Revenue</th></tr>
      </thead>
      <tbody class="divide-y divide-white/5">
        <?php foreach ($topEvents as $r): ?>
          <tr><td class="py-2"><?= e($r['title']) ?></td><td><?= (int)$r['bookings'] ?></td><td class="text-right"><?= e(format_money((float)$r['revenue'])) ?></td></tr>
        <?php endforeach; ?>
        <?php if (!$topEvents): ?><tr><td colspan="3" class="py-4 text-beige-100/60">No data.</td></tr><?php endif; ?>
      </tbody>
    </table>
  </div>

  <div class="border border-white/5 rounded-2xl p-6 bg-navy-900/40 lg:col-span-2">
    <h2 class="font-serif text-xl text-gold-400">Membership mix</h2>
    <div class="mt-4 grid sm:grid-cols-3 gap-4">
      <?php foreach ($membershipMix as $m): ?>
        <div class="border border-white/5 rounded-xl p-4">
          <p class="text-beige-100"><?= e($m['name']) ?></p>
          <p class="font-serif text-3xl text-gold-400 mt-2"><?= (int)$m['active'] ?> <span class="text-sm text-beige-100/50">active</span></p>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>
<?php require __DIR__ . '/../includes/admin_layout_end.php'; ?>
