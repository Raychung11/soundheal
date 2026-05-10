<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_staff_or_admin();
$pageTitle = 'Dashboard';

$pdo = db();
$counts = [
    'members'   => (int) $pdo->query("SELECT COUNT(*) FROM users WHERE role_id = 3")->fetchColumn(),
    'active_memberships' => (int) $pdo->query("SELECT COUNT(*) FROM memberships WHERE status = 'active'")->fetchColumn(),
    'upcoming'  => (int) $pdo->query("SELECT COUNT(*) FROM events WHERE status = 'published' AND starts_at >= NOW()")->fetchColumn(),
    'paid_bookings_30d' => (int) $pdo->query("SELECT COUNT(*) FROM event_bookings WHERE status IN ('paid','attended') AND created_at >= NOW() - INTERVAL 30 DAY")->fetchColumn(),
    'revenue_30d' => (float) $pdo->query("SELECT COALESCE(SUM(amount), 0) FROM payments WHERE status = 'paid' AND paid_at >= NOW() - INTERVAL 30 DAY")->fetchColumn(),
    'corporate_new' => (int) $pdo->query("SELECT COUNT(*) FROM corporate_inquiries WHERE status = 'new'")->fetchColumn(),
];

$recentBookings = $pdo->query(
    "SELECT b.booking_ref, b.status, b.total_amount, e.title, u.full_name, b.created_at
     FROM event_bookings b
     JOIN events e ON e.id = b.event_id
     JOIN users u ON u.id = b.user_id
     ORDER BY b.created_at DESC LIMIT 8"
)->fetchAll();

require __DIR__ . '/../includes/admin_layout.php';
?>
<h1 class="font-serif text-3xl text-beige-100">Operations</h1>
<p class="text-beige-100/60 mt-1 text-sm">Last 30 days overview.</p>

<div class="mt-8 grid sm:grid-cols-2 lg:grid-cols-3 gap-4">
  <?php foreach ([
    ['Members',           number_format($counts['members'])],
    ['Active memberships',number_format($counts['active_memberships'])],
    ['Upcoming sessions', number_format($counts['upcoming'])],
    ['Paid bookings (30d)', number_format($counts['paid_bookings_30d'])],
    ['Revenue (30d)',     format_money($counts['revenue_30d'])],
    ['New corporate leads', number_format($counts['corporate_new'])],
  ] as [$label, $value]): ?>
    <div class="border border-white/5 rounded-2xl p-5 bg-navy-900/40">
      <p class="text-xs uppercase tracking-widest text-beige-100/50"><?= e($label) ?></p>
      <p class="font-serif text-3xl text-gold-400 mt-2"><?= e($value) ?></p>
    </div>
  <?php endforeach; ?>
</div>

<div class="mt-10 border border-white/5 rounded-2xl bg-navy-900/40 p-6">
  <h2 class="font-serif text-xl text-gold-400">Recent bookings</h2>
  <?php if (!$recentBookings): ?>
    <p class="mt-4 text-beige-100/60">No bookings yet.</p>
  <?php else: ?>
    <table class="mt-4 w-full text-sm">
      <thead class="text-left text-beige-100/50 text-xs uppercase tracking-wider">
        <tr><th class="py-2">Ref</th><th>Member</th><th>Session</th><th>Status</th><th class="text-right">Amount</th></tr>
      </thead>
      <tbody class="divide-y divide-white/5">
        <?php foreach ($recentBookings as $b): ?>
          <tr>
            <td class="py-2"><?= e($b['booking_ref']) ?></td>
            <td><?= e($b['full_name']) ?></td>
            <td><?= e($b['title']) ?></td>
            <td><?= e($b['status']) ?></td>
            <td class="text-right"><?= e(format_money((float)$b['total_amount'])) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>
<?php require __DIR__ . '/../includes/admin_layout_end.php'; ?>
