<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_admin();
$pageTitle = 'Members';

$members = db()->query(
    "SELECT u.id, u.full_name, u.email, u.phone, u.status, u.created_at, r.name AS role,
            (SELECT p.name FROM memberships m JOIN membership_plans p ON p.id = m.plan_id
              WHERE m.user_id = u.id AND m.status = 'active' LIMIT 1) AS active_plan
     FROM users u
     JOIN roles r ON r.id = u.role_id
     ORDER BY u.created_at DESC LIMIT 200"
)->fetchAll();

require __DIR__ . '/../includes/admin_layout.php';
?>
<h1 class="font-serif text-3xl text-beige-100">Members</h1>

<div class="mt-6 border border-white/5 rounded-2xl bg-navy-900/40 overflow-x-auto">
  <table class="w-full text-sm">
    <thead class="text-left text-beige-100/50 text-xs uppercase tracking-wider bg-navy-950/40">
      <tr><th class="px-4 py-3">Name</th><th>Email</th><th>Role</th><th>Plan</th><th>Status</th><th>Joined</th></tr>
    </thead>
    <tbody class="divide-y divide-white/5">
      <?php foreach ($members as $m): ?>
        <tr>
          <td class="px-4 py-3 text-beige-100"><?= e($m['full_name']) ?></td>
          <td><?= e($m['email']) ?></td>
          <td><?= e($m['role']) ?></td>
          <td><?= e($m['active_plan'] ?? '—') ?></td>
          <td><?= e($m['status']) ?></td>
          <td><?= e(format_datetime($m['created_at'], 'd M Y')) ?></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php require __DIR__ . '/../includes/admin_layout_end.php'; ?>
