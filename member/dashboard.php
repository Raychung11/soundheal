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

$trialRow = db()->prepare("SELECT trial_ends_at FROM users WHERE id = :u");
$trialRow->execute([':u' => $user['id']]);
$trialEndsAt = $trialRow->fetchColumn();
$trialActive = $trialEndsAt && strtotime($trialEndsAt) > time() && !$activeMembership;

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
<?php
$hour = (int) (new DateTimeImmutable('now'))->format('G');
$greeting = $hour < 5  ? 'Still awake'
         : ($hour < 12 ? 'Good morning'
         : ($hour < 18 ? 'Good afternoon'
         :              'Good evening'));
$firstName = trim(explode(' ', $user['full_name'])[0]);
?>
<section class="max-w-6xl mx-auto px-6 py-16">
  <p class="text-gold-400/80 tracking-[0.3em] uppercase text-xs"><?= e($greeting) ?></p>
  <h1 class="font-serif text-5xl text-beige-100 mt-4"><?= e($firstName) ?>.</h1>
  <p class="mt-4 text-beige-100/60 max-w-xl leading-relaxed">A quiet check-in: how is your nervous system today? Choose a single word — Aria will meet you there.</p>

  <div class="mt-6 flex flex-wrap gap-2">
    <?php foreach (['anxious','tired','peaceful','overwhelmed','curious','heavy','open'] as $mood): ?>
      <a href="<?= url('/member/wellness_journey.php?mood=' . urlencode($mood)) ?>"
         class="px-4 py-2 rounded-full border border-white/10 text-sm text-beige-100/70 hover:border-gold-500/40 hover:text-gold-400 hover:bg-gold-500/5 transition capitalize"><?= e($mood) ?></a>
    <?php endforeach; ?>
  </div>

  <div class="mt-12 grid md:grid-cols-3 gap-6">
    <div class="border border-white/5 rounded-3xl p-6 bg-navy-900/40">
      <p class="text-xs uppercase tracking-widest text-gold-400/80">Membership</p>
      <?php if ($activeMembership): ?>
        <p class="font-serif text-2xl text-beige-100 mt-3"><?= e($activeMembership['plan_name']) ?></p>
        <p class="text-sm text-beige-100/60 mt-1">Renews <?= e(format_datetime($activeMembership['expires_at'], 'd M Y')) ?></p>
        <a href="<?= url('/member/my_membership.php') ?>" class="mt-4 inline-block text-sm text-gold-400 hover:text-gold-300">Manage →</a>
      <?php elseif ($trialActive):
        $hoursLeft = max(0, (int) round((strtotime($trialEndsAt) - time()) / 3600));
        $daysLeft  = max(0, (int) round($hoursLeft / 24));
      ?>
        <p class="font-serif text-2xl text-gold-400 mt-3">Free trial</p>
        <p class="text-sm text-beige-100/60 mt-1">
          <?= $daysLeft >= 1 ? $daysLeft . ' day' . ($daysLeft === 1 ? '' : 's') : $hoursLeft . ' hours' ?> remaining
        </p>
        <a href="<?= url('/public/membership.php') ?>" class="mt-4 inline-block text-sm text-gold-400 hover:text-gold-300">Continue with a plan →</a>
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
    <a href="<?= url('/member/refer.php') ?>" class="sm:col-span-2 lg:col-span-3 border border-gold-500/30 rounded-3xl p-6 bg-navy-900/60 hover:bg-navy-900/80 transition flex flex-col md:flex-row md:items-center md:justify-between gap-4">
      <div>
        <p class="text-xs uppercase tracking-widest text-gold-400/80">Refer a friend</p>
        <p class="font-serif text-2xl text-beige-100 mt-2">Share the sanctuary</p>
        <p class="text-sm text-beige-100/60 mt-1">You and your friend each receive an extra <?= (int) setting('referral_signup_trial_days', 7) ?> days of trial when they sign up.</p>
      </div>
      <span class="text-gold-400 text-sm">Get my link →</span>
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
      <div class="flex items-center justify-between">
        <h2 class="font-serif text-2xl text-gold-400">Audio sanctuary</h2>
        <a href="<?= url('/member/content.php') ?>" class="text-sm text-gold-400/80 hover:text-gold-300">Open library →</a>
      </div>
      <?php if (!$content): ?>
        <p class="mt-4 text-beige-100/60">New audio journeys coming soon.</p>
      <?php else: ?>
        <ul class="mt-4 space-y-3">
          <?php foreach ($content as $c): ?>
            <li class="flex items-center justify-between">
              <div>
                <a href="<?= url('/member/content.php#track-' . (int)$c['id']) ?>" class="text-beige-100 hover:text-gold-400 transition"><?= e($c['title']) ?></a>
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
