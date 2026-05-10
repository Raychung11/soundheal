<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_login();
$pageTitle = 'My Membership';
$user = current_user();

$stmt = db()->prepare(
    "SELECT m.*, p.name AS plan_name, p.price, p.billing_cycle, p.description
     FROM memberships m
     JOIN membership_plans p ON p.id = m.plan_id
     WHERE m.user_id = :u
     ORDER BY m.created_at DESC"
);
$stmt->execute([':u' => $user['id']]);
$history = $stmt->fetchAll();
$active = null;
foreach ($history as $row) {
    if ($row['status'] === 'active') { $active = $row; break; }
}

require __DIR__ . '/../includes/header.php';
?>
<section class="max-w-4xl mx-auto px-6 py-16">
  <h1 class="font-serif text-4xl text-beige-100">My membership</h1>

  <?php if ($active): ?>
    <div class="mt-8 border border-gold-500/30 rounded-3xl p-8 bg-navy-900/60">
      <p class="text-xs uppercase tracking-widest text-gold-400">Active</p>
      <p class="font-serif text-3xl text-beige-100 mt-2"><?= e($active['plan_name']) ?></p>
      <p class="text-sm text-beige-100/60 mt-1"><?= e($active['description']) ?></p>
      <div class="mt-6 grid sm:grid-cols-3 gap-4 text-sm">
        <div><p class="text-beige-100/50 text-xs uppercase">Started</p><p class="text-beige-100"><?= e(format_datetime($active['started_at'], 'd M Y')) ?></p></div>
        <div><p class="text-beige-100/50 text-xs uppercase">Renews</p><p class="text-beige-100"><?= e(format_datetime($active['expires_at'], 'd M Y')) ?></p></div>
        <div><p class="text-beige-100/50 text-xs uppercase">Cycle</p><p class="text-beige-100 capitalize"><?= e($active['billing_cycle']) ?></p></div>
      </div>
    </div>
  <?php else: ?>
    <div class="mt-8 border border-white/5 rounded-3xl p-8 bg-navy-900/40">
      <p class="text-beige-100/70">You don't have an active membership.</p>
      <a href="<?= url('/public/membership.php') ?>" class="inline-block mt-4 px-6 py-3 rounded-full bg-gold-500 text-navy-950 hover:bg-gold-400 transition">Choose a plan</a>
    </div>
  <?php endif; ?>

  <?php if ($history): ?>
    <h2 class="mt-16 font-serif text-2xl text-gold-400">History</h2>
    <table class="mt-4 w-full text-sm">
      <thead class="text-left text-beige-100/50 text-xs uppercase tracking-wider">
        <tr><th class="py-2">Plan</th><th>Status</th><th>Started</th><th>Expires</th></tr>
      </thead>
      <tbody class="divide-y divide-white/5">
        <?php foreach ($history as $h): ?>
          <tr>
            <td class="py-3"><?= e($h['plan_name']) ?></td>
            <td><?= e($h['status']) ?></td>
            <td><?= e(format_datetime($h['started_at'], 'd M Y')) ?></td>
            <td><?= e(format_datetime($h['expires_at'], 'd M Y')) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</section>
<?php require __DIR__ . '/../includes/footer.php'; ?>
