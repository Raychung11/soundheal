<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_login();

$user = current_user();
$pageTitle = 'My Sanctuary';

$membership = db()->prepare(
    "SELECT m.*, p.name AS plan_name, p.billing_cycle
     FROM memberships m
     JOIN membership_plans p ON p.id = m.plan_id
     WHERE m.user_id = :u AND m.status = 'active'
     ORDER BY m.expires_at DESC LIMIT 1"
);
$membership->execute([':u' => $user['id']]);
$activeMembership = $membership->fetch();

$bookings = db()->prepare(
    "SELECT b.*, e.title, e.starts_at, e.location
     FROM event_bookings b
     JOIN events e ON e.id = b.event_id
     WHERE b.user_id = :u
     ORDER BY e.starts_at DESC LIMIT 5"
);
$bookings->execute([':u' => $user['id']]);
$recentBookings = $bookings->fetchAll();

$content = db()->query(
    "SELECT slug, title, type, duration_seconds
     FROM wellness_content
     WHERE is_published = 1 AND access IN ('public','member')
     ORDER BY created_at DESC LIMIT 4"
)->fetchAll();

require __DIR__ . '/../includes/header.php';
?>
<section class="max-w-6xl mx-auto px-6 py-16">
  <p class="text-gold-400/80 tracking-[0.3em] uppercase text-xs">Welcome back</p>
  <h1 class="font-serif text-5xl text-beige-100 mt-4"><?= e($user['full_name']) ?></h1>
  <p class="mt-3 text-beige-100/60">A quiet check-in: how is your nervous system today?</p>

  <div class="mt-12 grid md:grid-cols-3 gap-6">
    <div class="border border-white/5 rounded-3xl p-6 bg-navy-900/40">
      <p class="text-xs uppercase tracking-widest text-gold-400/80">Membership</p>
      <?php if ($activeMembership): ?>
        <p class="font-serif text-2xl text-beige-100 mt-3"><?= e($activeMembership['plan_name']) ?></p>
        <p class="text-sm text-beige-100/60 mt-1">Renews <?= e(format_datetime($activeMembership['expires_at'], 'd M Y')) ?></p>
        <a href="<?= url('/member/my_membership.php') ?>" class="mt-4 inline-block text-sm text-gold-400 hover:text-gold-300">Manage →</a>
      <?php else: ?>
        <p class="text-beige-100/70 mt-3">Not a member yet.</p>
        <a href="<?= url('/public/membership.php') ?>" class="mt-4 inline-block text-sm text-gold-400 hover:text-gold-300">Choose a plan →</a>
      <?php endif; ?>
    </div>
    <a href="<?= url('/member/my_bookings.php') ?>" class="border border-white/5 rounded-3xl p-6 bg-navy-900/40 hover:border-gold-500/30 transition">
      <p class="text-xs uppercase tracking-widest text-gold-400/80">Bookings</p>
      <p class="font-serif text-2xl text-beige-100 mt-3"><?= count($recentBookings) ?> recent</p>
      <p class="text-sm text-beige-100/60 mt-1">View tickets and history</p>
    </a>
    <a href="<?= url('/member/wellness_journey.php') ?>" class="border border-white/5 rounded-3xl p-6 bg-navy-900/40 hover:border-gold-500/30 transition">
      <p class="text-xs uppercase tracking-widest text-gold-400/80">Journey</p>
      <p class="font-serif text-2xl text-beige-100 mt-3">Talk with Aria</p>
      <p class="text-sm text-beige-100/60 mt-1">Your calm wellness concierge</p>
    </a>
  </div>

  <div class="mt-16 grid md:grid-cols-2 gap-8">
    <div class="border border-white/5 rounded-3xl p-8 bg-navy-900/40">
      <h2 class="font-serif text-2xl text-gold-400">Recent bookings</h2>
      <?php if (!$recentBookings): ?>
        <p class="mt-4 text-beige-100/60">No sessions reserved yet. <a href="<?= url('/public/events.php') ?>" class="text-gold-400">Browse the calendar →</a></p>
      <?php else: ?>
        <ul class="mt-4 space-y-4">
          <?php foreach ($recentBookings as $b): ?>
            <li class="flex justify-between items-start border-b border-white/5 pb-3">
              <div>
                <p class="text-beige-100"><?= e($b['title']) ?></p>
                <p class="text-xs text-beige-100/50"><?= e(format_datetime($b['starts_at'])) ?></p>
              </div>
              <span class="text-xs px-3 py-1 rounded-full <?= $b['status'] === 'paid' ? 'bg-gold-500/20 text-gold-400' : 'bg-white/5 text-beige-100/60' ?>"><?= e($b['status']) ?></span>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </div>

    <div class="border border-white/5 rounded-3xl p-8 bg-navy-900/40">
      <h2 class="font-serif text-2xl text-gold-400">Audio sanctuary</h2>
      <?php if (!$content): ?>
        <p class="mt-4 text-beige-100/60">New audio journeys coming soon.</p>
      <?php else: ?>
        <ul class="mt-4 space-y-3">
          <?php foreach ($content as $c): ?>
            <li class="flex items-center justify-between">
              <div>
                <p class="text-beige-100"><?= e($c['title']) ?></p>
                <p class="text-xs text-beige-100/50 capitalize"><?= e($c['type']) ?> · <?= max(1, (int)round(((int)$c['duration_seconds'])/60)) ?> min</p>
              </div>
              <span class="text-gold-400 text-sm">▶</span>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </div>
  </div>
</section>
<?php require __DIR__ . '/../includes/footer.php'; ?>
