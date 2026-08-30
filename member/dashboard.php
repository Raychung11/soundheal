<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_login();

$user = current_user();
$pageTitle = 'My Sanctuary';

// ---- Nudge handlers (one-shot POSTs, redirect back to keep URLs clean)
if (is_post()) {
    csrf_verify();
    $action = (string) input('action', '');

    if ($action === 'dismiss_welcome') {
        db()->prepare('UPDATE users SET onboarded_at = NOW() WHERE id = :u')
            ->execute([':u' => (int) $user['id']]);
        redirect('/member/dashboard.php');
    }

    if ($action === 'save_phone') {
        // Same normalisation the signup / profile forms use so the
        // phonebook lookups stay consistent.
        $phone = normalize_phone((string) input('phone', ''));
        if ($phone !== null) {
            db()->prepare('UPDATE users SET phone = :p, phone_prompt_dismissed_at = NOW() WHERE id = :u')
                ->execute([':p' => $phone, ':u' => (int) $user['id']]);
            flash('dashboard', 'Thanks — we\'ll message you on WhatsApp before each session.', 'success');
        } else {
            flash('dashboard', 'Please share a valid phone number, or tap "Not now".', 'error');
        }
        redirect('/member/dashboard.php');
    }

    if ($action === 'dismiss_phone') {
        db()->prepare('UPDATE users SET phone_prompt_dismissed_at = NOW() WHERE id = :u')
            ->execute([':u' => (int) $user['id']]);
        redirect('/member/dashboard.php');
    }
}

// ---- Nudge state (evaluated once so the two banners can render below)
$flags = db()->prepare(
    'SELECT phone, onboarded_at, phone_prompt_dismissed_at FROM users WHERE id = :u LIMIT 1'
);
$flags->execute([':u' => (int) $user['id']]);
$flagsRow = $flags->fetch() ?: [];
$showWelcome     = empty($flagsRow['onboarded_at']);
$showPhonePrompt = empty($flagsRow['phone']) && empty($flagsRow['phone_prompt_dismissed_at']);

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

// The soonest future booking — used as the hero "next session" card.
$nextStmt = db()->prepare(
    "SELECT b.id AS booking_id, b.booking_ref, b.status, b.quantity,
            e.title, e.starts_at, e.location
       FROM event_bookings b
       JOIN events e ON e.id = b.event_id
      WHERE b.user_id = :u
        AND b.status IN ('pending','paid')
        AND e.starts_at >= NOW()
      ORDER BY e.starts_at ASC LIMIT 1"
);
$nextStmt->execute([':u' => $user['id']]);
$nextSession = $nextStmt->fetch();

$bookings = db()->prepare(
    "SELECT b.*, e.title, e.starts_at, e.location
     FROM event_bookings b
     JOIN events e ON e.id = b.event_id
     WHERE b.user_id = :u
     ORDER BY e.starts_at DESC LIMIT 5"
);
$bookings->execute([':u' => $user['id']]);
$recentBookings = $bookings->fetchAll();

$upcomingCount = (int) db()->query(
    "SELECT COUNT(*) FROM event_bookings b
       JOIN events e ON e.id = b.event_id
      WHERE b.user_id = " . (int) $user['id'] . "
        AND b.status IN ('pending','paid')
        AND e.starts_at >= NOW()"
)->fetchColumn();

$content = db()->query(
    "SELECT id, slug, title, type, duration_seconds
     FROM wellness_content
     WHERE is_published = 1 AND access IN ('public','member')
     ORDER BY created_at DESC LIMIT 4"
)->fetchAll();

$creditBalance = function_exists('credit_balance_for') ? (int) credit_balance_for((int) $user['id']) : 0;

require __DIR__ . '/../includes/header.php';

$hour = (int) (new DateTimeImmutable('now'))->format('G');
$greeting = $hour < 5  ? 'Still awake'
         : ($hour < 12 ? 'Good morning'
         : ($hour < 18 ? 'Good afternoon'
         :              'Good evening'));
$firstName = trim(explode(' ', $user['full_name'])[0]);
$membershipStatusLabel = $activeMembership ? 'Member' : ($trialActive ? 'Trial' : 'Guest');

$quickTiles = [
    ['Book a session', '/public/events.php',           '<rect x="3.5" y="5" width="17" height="15" rx="2"/><path d="M3.5 9.5h17M8 3.5v3M16 3.5v3M12 13v5M9.5 15.5h5"/>'],
    ['My tickets',     '/member/my_bookings.php',      '<path d="M4 8a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2 2 2 0 0 0 0 4 2 2 0 0 1-2 2H6a2 2 0 0 1-2-2 2 2 0 0 0 0-4Z"/><path d="M14 6.5v11"/>'],
    ['Credits',        '/member/my_credits.php',       '<path d="M12 3.5 13.8 9 19.5 10 13.8 11 12 16.5 10.2 11 4.5 10 10.2 9 12 3.5Z"/>'],
    ['Talk to Aria',   '/member/wellness_journey.php', '<path d="M4 5h16v12H8l-4 3z"/><path d="M8 10h8M8 13h5"/>'],
    ['Membership',     '/member/my_membership.php',    '<path d="m12 3 2.7 5.5 6.3 1-4.6 4.5 1.1 6.5L12 17l-5.5 3 1.1-6.5L3 9.5l6.3-1z"/>'],
    ['Refer a friend', '/member/refer.php',            '<path d="M20 7 9 18l-5-5"/><path d="m22 4-2 2"/>'],
];
?>
<section class="max-w-6xl mx-auto px-4 sm:px-6 pt-10 pb-12">
  <!-- Greeting + avatar -->
  <div class="flex items-center justify-between gap-4">
    <div class="min-w-0">
      <p class="text-gold-400/80 tracking-[0.3em] uppercase text-[10px] md:text-xs"><?= e($greeting) ?></p>
      <h1 class="font-serif text-3xl md:text-5xl text-beige-100 mt-2 truncate"><?= e($firstName) ?>.</h1>
    </div>
    <a href="<?= url('/member/profile.php') ?>"
       class="shrink-0 flex h-12 w-12 items-center justify-center rounded-full border border-gold-500/30 bg-gold-500/10 text-gold-400 font-serif text-lg hover:bg-gold-500/20 transition"
       aria-label="Account">
      <?= e(strtoupper(substr($firstName, 0, 1) ?: '·')) ?>
    </a>
  </div>

  <?php if ($f = flash('dashboard')): ?>
    <div class="mt-4 border rounded-2xl px-5 py-3 text-sm
                <?= ($f['type'] ?? 'info') === 'success'
                    ? 'border-gold-500/40 bg-gold-500/5 text-gold-400'
                    : (($f['type'] ?? '') === 'error'
                        ? 'border-red-400/40 bg-red-500/5 text-red-200'
                        : 'border-white/10 bg-navy-900/40 text-beige-100/85') ?>">
      <?= e($f['message'] ?? '') ?>
    </div>
  <?php endif; ?>

  <?php if ($showWelcome): ?>
    <!-- Welcome strip: renders once, on first-ever dashboard visit
         (onboarded_at IS NULL). Sets the tone and points at the
         three things this account can do right now. Dismissed on
         "Got it" — never shown again. -->
    <section class="mt-6 rounded-3xl border border-gold-500/30 bg-gradient-to-br from-gold-500/10 via-navy-900/60 to-navy-950 p-6 sm:p-7">
      <p class="text-[10px] uppercase tracking-[0.35em] text-gold-400/80">Welcome — held with care</p>
      <h2 class="font-serif text-2xl sm:text-3xl text-beige-100 mt-3 leading-tight">Three quiet ways to begin</h2>
      <div class="mt-5 grid sm:grid-cols-3 gap-3">
        <a href="<?= url('/public/events.php') ?>"
           class="group flex flex-col items-start gap-2 rounded-2xl border border-white/10 bg-navy-950/50 p-4 hover:border-gold-500/40 transition">
          <span class="flex h-9 w-9 items-center justify-center rounded-full bg-gold-500/15 text-gold-400">
            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><rect x="3.5" y="5" width="17" height="15" rx="2"/><path d="M3.5 9.5h17M8 3.5v3M16 3.5v3"/></svg>
          </span>
          <p class="font-serif text-lg text-beige-100 leading-tight">Reserve a session</p>
          <p class="text-[12px] text-beige-100/60 leading-relaxed">Pick a sound bath, breathwork or gong bath — held small, held with intention.</p>
          <span class="text-gold-400 text-xs mt-1 group-hover:translate-x-0.5 transition">Browse →</span>
        </a>
        <a href="<?= url('/member/content.php') ?>"
           class="group flex flex-col items-start gap-2 rounded-2xl border border-white/10 bg-navy-950/50 p-4 hover:border-gold-500/40 transition">
          <span class="flex h-9 w-9 items-center justify-center rounded-full bg-gold-500/15 text-gold-400">
            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M9 18V5l12-2v13"/><circle cx="6" cy="18" r="3"/><circle cx="18" cy="16" r="3"/></svg>
          </span>
          <p class="font-serif text-lg text-beige-100 leading-tight">Your audio library</p>
          <p class="text-[12px] text-beige-100/60 leading-relaxed">Recorded sound baths and meditations, always here between sessions.</p>
          <span class="text-gold-400 text-xs mt-1 group-hover:translate-x-0.5 transition">Listen →</span>
        </a>
        <a href="<?= url('/member/wellness_journey.php') ?>"
           class="group flex flex-col items-start gap-2 rounded-2xl border border-white/10 bg-navy-950/50 p-4 hover:border-gold-500/40 transition">
          <span class="flex h-9 w-9 items-center justify-center rounded-full bg-gold-500/15 text-gold-400">
            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M4 5h16v12H8l-4 3z"/><path d="M8 10h8M8 13h5"/></svg>
          </span>
          <p class="font-serif text-lg text-beige-100 leading-tight">Talk with Aria</p>
          <p class="text-[12px] text-beige-100/60 leading-relaxed">Our quiet wellness concierge — share how you're arriving today.</p>
          <span class="text-gold-400 text-xs mt-1 group-hover:translate-x-0.5 transition">Begin →</span>
        </a>
      </div>
      <form method="post" class="mt-5">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="dismiss_welcome">
        <button class="text-xs text-beige-100/55 hover:text-gold-400 tracking-widest uppercase">Got it, thank you</button>
      </form>
    </section>
  <?php endif; ?>

  <?php if ($showPhonePrompt): ?>
    <!-- Phone-prompt banner: rendered when phone is NULL and the
         member hasn't already dismissed it. Recovers the number for
         Google-first / magic-link members without a form to sign
         them up. Single field + Save + Not now. -->
    <form method="post"
          class="mt-4 rounded-2xl border border-white/10 bg-navy-900/40 p-4 sm:p-5 flex flex-col sm:flex-row sm:items-center gap-3 sm:gap-4">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="save_phone">
      <div class="min-w-0 sm:flex-1">
        <p class="text-sm text-beige-100">Add your phone for WhatsApp session reminders?</p>
        <p class="text-[11px] text-beige-100/50 mt-0.5">One-liner only — we never share it, no marketing.</p>
      </div>
      <div class="flex items-stretch rounded-2xl bg-navy-950 border border-white/10 focus-within:border-gold-500/50 transition overflow-hidden">
        <span class="px-3 flex items-center text-gold-400 text-sm bg-navy-950/60 border-r border-white/5">+60</span>
        <input name="phone" type="tel" inputmode="tel" autocomplete="tel-national"
               placeholder="12 345 6789" required
               class="w-40 sm:w-48 bg-transparent px-3 py-2 text-sm focus:outline-none">
      </div>
      <button class="px-4 py-2 rounded-full bg-gold-500 text-navy-950 text-sm hover:bg-gold-400 transition">Save</button>
    </form>
    <form method="post" class="-mt-1 flex justify-end">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="dismiss_phone">
      <button class="text-xs text-beige-100/55 hover:text-gold-400 uppercase tracking-widest px-2 py-1">Not now</button>
    </form>
  <?php endif; ?>

  <!-- Hero: next session, or a "reserve" prompt if none -->
  <?php if ($nextSession): ?>
    <a href="<?= url('/member/my_tickets.php?booking=' . (int) $nextSession['booking_id']) ?>"
       class="group mt-6 block rounded-3xl border border-gold-500/30 bg-gradient-to-br from-gold-500/10 via-navy-900/60 to-navy-950 p-6 sm:p-8 hover:border-gold-500/50 transition">
      <p class="text-[10px] uppercase tracking-[0.35em] text-gold-400/80">Your next session</p>
      <p class="font-serif text-2xl sm:text-3xl text-beige-100 mt-3 leading-tight"><?= e($nextSession['title']) ?></p>
      <p class="text-sm text-gold-400 mt-2"><?= e(format_datetime($nextSession['starts_at'], 'D, d M · g:i A')) ?></p>
      <?php if (!empty($nextSession['location'])): ?>
        <p class="text-xs text-beige-100/55 mt-1"><?= e($nextSession['location']) ?></p>
      <?php endif; ?>
      <div class="mt-5 flex items-center justify-between">
        <span class="text-xs px-3 py-1 rounded-full <?= $nextSession['status'] === 'paid' ? 'bg-gold-500/20 text-gold-400' : 'bg-white/10 text-beige-100/70' ?>"><?= e($nextSession['status']) ?></span>
        <span class="text-gold-400 text-sm group-hover:translate-x-0.5 transition">Show ticket →</span>
      </div>
    </a>
  <?php else: ?>
    <a href="<?= url('/public/events.php') ?>"
       class="mt-6 block rounded-3xl border border-white/10 bg-navy-900/40 p-6 sm:p-8 hover:border-gold-500/30 transition">
      <p class="text-[10px] uppercase tracking-[0.35em] text-gold-400/80">No session booked yet</p>
      <p class="font-serif text-2xl sm:text-3xl text-beige-100 mt-3 leading-tight">Reserve your next seat</p>
      <p class="text-sm text-beige-100/60 mt-2 leading-relaxed">Browse upcoming sound baths and breathwork — held with intention.</p>
      <span class="inline-block mt-4 text-gold-400 text-sm">Browse the calendar →</span>
    </a>
  <?php endif; ?>

  <!-- Stat strip -->
  <div class="mt-4 grid grid-cols-3 gap-3">
    <a href="<?= url('/member/my_credits.php') ?>" class="rounded-2xl border border-white/5 bg-navy-900/40 p-4 hover:border-gold-500/30 transition">
      <p class="text-[10px] uppercase tracking-widest text-beige-100/50">Credits</p>
      <p class="font-serif text-2xl text-gold-400 mt-1"><?= $creditBalance ?></p>
    </a>
    <a href="<?= url('/member/my_bookings.php') ?>" class="rounded-2xl border border-white/5 bg-navy-900/40 p-4 hover:border-gold-500/30 transition">
      <p class="text-[10px] uppercase tracking-widest text-beige-100/50">Upcoming</p>
      <p class="font-serif text-2xl text-beige-100 mt-1"><?= $upcomingCount ?></p>
    </a>
    <a href="<?= url('/member/my_membership.php') ?>" class="rounded-2xl border border-white/5 bg-navy-900/40 p-4 hover:border-gold-500/30 transition">
      <p class="text-[10px] uppercase tracking-widest text-beige-100/50">Status</p>
      <p class="font-serif text-lg text-beige-100 mt-1"><?= e($membershipStatusLabel) ?></p>
      <?php if ($activeMembership): ?>
        <p class="text-[10px] text-beige-100/45 mt-0.5">renews <?= e(format_datetime($activeMembership['expires_at'], 'd M')) ?></p>
      <?php elseif ($trialActive):
        $daysLeft = max(0, (int) round((strtotime($trialEndsAt) - time()) / 86400));
      ?>
        <p class="text-[10px] text-beige-100/45 mt-0.5"><?= $daysLeft ?> day<?= $daysLeft === 1 ? '' : 's' ?> left</p>
      <?php endif; ?>
    </a>
  </div>

  <!-- Quick actions -->
  <h2 class="mt-10 font-serif text-xl text-beige-100">Quick actions</h2>
  <div class="mt-4 grid grid-cols-3 sm:grid-cols-6 gap-3">
    <?php foreach ($quickTiles as [$qLabel, $qPath, $qIcon]): ?>
      <a href="<?= url($qPath) ?>"
         class="group flex flex-col items-center gap-2 rounded-2xl border border-white/5 bg-navy-900/40 p-3 hover:border-gold-500/30 hover:bg-navy-900/60 transition">
        <span class="flex h-11 w-11 items-center justify-center rounded-full bg-gold-500/10 text-gold-400 group-hover:bg-gold-500/20 transition">
          <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><?= $qIcon ?></svg>
        </span>
        <span class="text-[11px] text-beige-100/75 text-center leading-tight"><?= e($qLabel) ?></span>
      </a>
    <?php endforeach; ?>
  </div>

  <!-- Mood check-in -->
  <h2 class="mt-10 font-serif text-xl text-beige-100">A quiet check-in</h2>
  <p class="mt-1 text-beige-100/55 text-sm">How is your nervous system today? Choose a single word — Aria will meet you there.</p>
  <div class="mt-4 -mx-4 px-4 sm:mx-0 sm:px-0 flex gap-2 overflow-x-auto pb-1">
    <?php foreach (['anxious','tired','peaceful','overwhelmed','curious','heavy','open'] as $mood): ?>
      <a href="<?= url('/member/wellness_journey.php?mood=' . urlencode($mood)) ?>"
         class="shrink-0 px-4 py-2 rounded-full border border-white/10 text-sm text-beige-100/75 hover:border-gold-500/40 hover:text-gold-400 hover:bg-gold-500/5 transition capitalize whitespace-nowrap"><?= e($mood) ?></a>
    <?php endforeach; ?>
  </div>

  <!-- Recent bookings + audio library -->
  <div class="mt-12 grid md:grid-cols-2 gap-6">
    <div class="border border-white/5 rounded-3xl p-6 bg-navy-900/40">
      <div class="flex items-center justify-between">
        <h2 class="font-serif text-xl text-gold-400">Recent bookings</h2>
        <a href="<?= url('/member/my_bookings.php') ?>" class="text-xs text-gold-400/80 hover:text-gold-300">All →</a>
      </div>
      <?php if (!$recentBookings): ?>
        <p class="mt-4 text-beige-100/60">No sessions reserved yet. <a href="<?= url('/public/events.php') ?>" class="text-gold-400">Browse the calendar →</a></p>
      <?php else: ?>
        <ul class="mt-4 space-y-3">
          <?php foreach ($recentBookings as $b): ?>
            <li class="flex justify-between items-start gap-3 border-b border-white/5 pb-3 last:border-0">
              <div class="min-w-0">
                <p class="text-beige-100 truncate"><?= e($b['title']) ?></p>
                <p class="text-xs text-beige-100/50"><?= e(format_datetime($b['starts_at'])) ?></p>
              </div>
              <span class="shrink-0 text-xs px-3 py-1 rounded-full <?= $b['status'] === 'paid' ? 'bg-gold-500/20 text-gold-400' : 'bg-white/5 text-beige-100/60' ?>"><?= e($b['status']) ?></span>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </div>

    <div class="border border-white/5 rounded-3xl p-6 bg-navy-900/40">
      <div class="flex items-center justify-between">
        <h2 class="font-serif text-xl text-gold-400">Audio sanctuary</h2>
        <a href="<?= url('/member/content.php') ?>" class="text-xs text-gold-400/80 hover:text-gold-300">Open library →</a>
      </div>
      <?php if (!$content): ?>
        <p class="mt-4 text-beige-100/60">New audio journeys coming soon.</p>
      <?php else: ?>
        <ul class="mt-4 space-y-3">
          <?php foreach ($content as $c): ?>
            <li class="flex items-center justify-between gap-3">
              <div class="min-w-0">
                <a href="<?= url('/member/content.php#track-' . (int) $c['id']) ?>" class="text-beige-100 hover:text-gold-400 transition block truncate"><?= e($c['title']) ?></a>
                <p class="text-xs text-beige-100/50 capitalize"><?= e($c['type']) ?> · <?= max(1, (int) round(((int) $c['duration_seconds']) / 60)) ?> min</p>
              </div>
              <span class="shrink-0 text-gold-400 text-sm">▶</span>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </div>
  </div>

  <!-- Refer card -->
  <a href="<?= url('/member/refer.php') ?>"
     class="mt-8 block border border-gold-500/30 rounded-3xl p-6 bg-navy-900/60 hover:bg-navy-900/80 transition">
    <div class="flex items-center justify-between gap-3 flex-wrap">
      <div>
        <p class="text-[10px] uppercase tracking-[0.35em] text-gold-400/80">Refer a friend</p>
        <p class="font-serif text-xl text-beige-100 mt-2">Share the sanctuary</p>
        <p class="text-sm text-beige-100/60 mt-1">You and your friend each receive an extra <?= (int) setting('referral_signup_trial_days', 7) ?> days of trial when they sign up.</p>
      </div>
      <span class="text-gold-400 text-sm">Get my link →</span>
    </div>
  </a>
</section>
<?php require __DIR__ . '/../includes/footer.php'; ?>
