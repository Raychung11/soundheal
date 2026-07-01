<?php
/**
 * Public event detail page.
 *
 *   /public/event.php?id=<eventId>&date=YYYY-MM-DD
 *
 *   Focused single-session landing that shows the cover, full
 *   description, both package options, seats-left and a
 *   prominent Reserve CTA. Where the sessions calendar
 *   (/public/events.php) is a browsing tool, this is the target
 *   of every share link and the referral flow — the visitor
 *   sees one thing: this session and how to book it.
 */
require_once __DIR__ . '/../includes/bootstrap.php';

$eventId = (int) input('id', 0);
$date    = trim((string) input('date', ''));

$event = null;
if ($eventId > 0) {
    $stmt = db()->prepare(
        "SELECT * FROM events WHERE id = :id AND status = 'published' LIMIT 1"
    );
    $stmt->execute([':id' => $eventId]);
    $event = $stmt->fetch() ?: null;
}

if (!$event) {
    http_response_code(404);
    $pageTitle = 'Session not found';
    require __DIR__ . '/../includes/header.php';
    ?>
    <section class="max-w-xl mx-auto px-6 py-24 text-center">
      <p class="text-gold-400/80 tracking-[0.3em] uppercase text-[11px]">Not here</p>
      <h1 class="font-serif text-4xl text-beige-100 mt-4">This session isn't available.</h1>
      <p class="mt-5 text-beige-100/70 leading-relaxed">The link may be old or the session has closed. Please browse what's coming up.</p>
      <a href="<?= url('/public/events.php') ?>" class="inline-block mt-8 px-7 py-3 rounded-full bg-gold-500 text-navy-950 hover:bg-gold-400 transition">Upcoming sessions</a>
    </section>
    <?php
    require __DIR__ . '/../includes/footer.php';
    exit;
}

// If this is a recurring template AND we were given a date, present the
// occurrence for that date (starts_at/ends_at shifted to the chosen day).
$isRecurring = in_array($event['recurrence'] ?? 'none', ['daily','weekly','monthly'], true);
$dateValid   = $isRecurring && $date !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date);
if ($dateValid) {
    $tStart = date('H:i:s', strtotime((string) $event['starts_at']));
    $tEnd   = date('H:i:s', strtotime((string) $event['ends_at']));
    $event['starts_at'] = $date . ' ' . $tStart;
    $event['ends_at']   = $date . ' ' . $tEnd;
}

// Seats-taken — if a concrete child event already exists for the picked
// recurring date, use its bookings; otherwise there are none yet.
$seatsEventId = (int) $event['id'];
if ($isRecurring && $dateValid) {
    $c = db()->prepare(
        "SELECT id FROM events WHERE parent_event_id = :p AND DATE(starts_at) = :d LIMIT 1"
    );
    $c->execute([':p' => (int) $event['id'], ':d' => $date]);
    $child = $c->fetch();
    if ($child) $seatsEventId = (int) $child['id'];
}
$stmt = db()->prepare(
    "SELECT COALESCE(SUM(quantity),0) FROM event_bookings
      WHERE event_id = :e AND status IN ('pending','paid','attended')"
);
$stmt->execute([':e' => $seatsEventId]);
$seatsTaken = (int) $stmt->fetchColumn();
$remaining  = max(0, (int) $event['capacity'] - $seatsTaken);
$soldOut    = $remaining <= 0;

// Package labels / perks — reuse the per-event overrides from the
// booking flow, falling back to the site-wide Comfort / BYO defaults.
$defaultAPerks = ['Welcome drink', 'Yoga mat provided', 'Cozy blanket provided', 'Full sound healing experience'];
$defaultBPerks = ['Full sound healing experience', 'Bring your own mat and blanket'];
$aLabel = trim((string) ($event['package_a_label'] ?? '')) ?: 'Comfort';
$bLabel = trim((string) ($event['package_b_label'] ?? '')) ?: 'Bring-Your-Own-Zen';
$aPerks = array_values(array_filter(array_map('trim',
    preg_split('/\r?\n/', (string) ($event['package_a_perks'] ?? '')))));
if (!$aPerks) $aPerks = $defaultAPerks;
$bPerks = array_values(array_filter(array_map('trim',
    preg_split('/\r?\n/', (string) ($event['package_b_perks'] ?? '')))));
if (!$bPerks) $bPerks = $defaultBPerks;

// Reserve link — /member/book_event.php gates itself with require_login,
// which stores REQUEST_URI in $_SESSION['_intended'] and bounces to
// login; the URL params survive that round-trip.
$reserveUrl = '/member/book_event.php?event_id=' . (int) $event['id']
            . ($isRecurring && $dateValid ? '&date=' . urlencode($date) : '');

// Open Graph / Twitter meta so the WhatsApp / Facebook preview uses
// this event's cover + short description.
$pageTitle       = (string) $event['title'];
$pageType        = 'article';
if (!empty($event['cover_image'])) {
    $pageImage = (string) $event['cover_image'];
}
$rawDesc = trim((string) ($event['subtitle'] ?: ($event['description'] ?? '')));
$rawDesc = (string) preg_replace('/\s+/', ' ', $rawDesc);
if (function_exists('mb_strlen') && mb_strlen($rawDesc) > 200) {
    $rawDesc = mb_substr($rawDesc, 0, 197) . '…';
}
if ($rawDesc !== '') $pageDescription = $rawDesc;

// Canonical share URL (points back at this page — WhatsApp copies this
// exact URL when someone taps "Copy link").
$base = rtrim((string) config('app.url'), '/');
$shareUrl     = $base . '/public/event.php?id=' . (int) $event['id']
              . ($isRecurring && $dateValid ? '&date=' . urlencode($date) : '');
$shareUrlEnc  = rawurlencode($shareUrl);
$shareTextEnc = rawurlencode($event['title'] . ' · ' . brand_name());

$coverSrc = !empty($event['cover_image']) ? media_src((string) $event['cover_image']) : '';

require __DIR__ . '/../includes/header.php';
?>
<section class="max-w-4xl mx-auto px-4 sm:px-6 py-10 sm:py-14">

  <?php if ($coverSrc): ?>
    <div class="relative aspect-[16/10] sm:aspect-[16/9] rounded-3xl overflow-hidden border border-white/10 bg-navy-900">
      <img src="<?= e($coverSrc) ?>" alt="<?= e($event['title']) ?>"
           class="w-full h-full object-cover" loading="eager">
      <?php if (!empty($event['category'])): ?>
        <span class="absolute top-4 left-4 text-[10px] uppercase tracking-[0.3em] px-3 py-1 rounded-full border border-gold-500/40 bg-navy-950/60 text-gold-400 backdrop-blur capitalize">
          <?= e($event['category']) ?>
        </span>
      <?php endif; ?>
      <?php if ($soldOut): ?>
        <span class="absolute top-4 right-4 text-[10px] uppercase tracking-[0.3em] px-3 py-1 rounded-full bg-navy-950/85 text-beige-100/70 border border-white/10">Fully held</span>
      <?php else: ?>
        <span class="absolute top-4 right-4 text-[10px] uppercase tracking-[0.3em] px-3 py-1 rounded-full bg-navy-950/70 text-gold-400 border border-gold-500/30 backdrop-blur">
          <?= $remaining ?> seat<?= $remaining === 1 ? '' : 's' ?> left
        </span>
      <?php endif; ?>
    </div>
  <?php endif; ?>

  <p class="mt-8 text-[11px] uppercase tracking-[0.4em] text-gold-400/80">
    <?php if ($isRecurring && !$dateValid): ?>
      <?= e(describe_event_schedule($event)) ?>
    <?php else: ?>
      <?= e(format_datetime($event['starts_at'], 'l, d M Y · g:i A')) ?>
    <?php endif; ?>
  </p>
  <h1 class="mt-4 font-serif text-4xl sm:text-5xl text-beige-100 leading-tight"><?= e($event['title']) ?></h1>
  <?php if (!empty($event['subtitle'])): ?>
    <p class="mt-4 text-beige-100/80 text-lg leading-relaxed"><?= e($event['subtitle']) ?></p>
  <?php endif; ?>

  <!-- Meta line -->
  <div class="mt-6 flex flex-wrap gap-x-5 gap-y-2 text-sm text-beige-100/65">
    <?php if (!empty($event['location'])): ?>
      <span class="inline-flex items-center gap-2">
        <svg class="h-4 w-4 text-gold-400/70" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M12 22s7-7.16 7-12a7 7 0 1 0-14 0c0 4.84 7 12 7 12z"/><circle cx="12" cy="10" r="2.5"/></svg>
        <?= e($event['location']) ?>
      </span>
    <?php endif; ?>
    <?php if (!empty($event['facilitator'])): ?>
      <span class="inline-flex items-center gap-2">
        <svg class="h-4 w-4 text-gold-400/70" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><circle cx="12" cy="8" r="3.5"/><path d="M5 21c1-4 4-6 7-6s6 2 7 6"/></svg>
        <?= e($event['facilitator']) ?>
      </span>
    <?php endif; ?>
  </div>

  <?php if (!empty($event['description'])): ?>
    <div class="mt-10 text-beige-100/75 text-base leading-relaxed space-y-4 max-w-3xl">
      <?= render_rich_text((string) $event['description']) ?>
    </div>
  <?php endif; ?>

  <!-- Packages -->
  <h2 class="mt-14 font-serif text-2xl text-beige-100">Choose your package</h2>
  <div class="mt-5 grid sm:grid-cols-2 gap-4">
    <div class="rounded-2xl border border-white/10 bg-navy-900/40 p-6">
      <div class="flex items-start justify-between gap-3">
        <p class="font-serif text-xl text-beige-100"><?= e($aLabel) ?></p>
        <span class="font-serif text-2xl text-gold-400 whitespace-nowrap"><?= e(format_money((float) $event['price_public'])) ?></span>
      </div>
      <ul class="mt-4 space-y-1.5 text-sm text-beige-100/70">
        <?php foreach ($aPerks as $perk): ?>
          <li class="flex gap-2"><span class="text-gold-400">✦</span> <?= e($perk) ?></li>
        <?php endforeach; ?>
      </ul>
    </div>
    <div class="rounded-2xl border border-white/10 bg-navy-900/40 p-6">
      <div class="flex items-start justify-between gap-3">
        <p class="font-serif text-xl text-beige-100"><?= e($bLabel) ?></p>
        <span class="font-serif text-2xl text-gold-400 whitespace-nowrap"><?= e(format_money((float) $event['price_member'])) ?></span>
      </div>
      <ul class="mt-4 space-y-1.5 text-sm text-beige-100/70">
        <?php foreach ($bPerks as $perk): ?>
          <li class="flex gap-2"><span class="text-gold-400">✦</span> <?= e($perk) ?></li>
        <?php endforeach; ?>
      </ul>
    </div>
  </div>

  <!-- CTA + share -->
  <div class="mt-10 border-t border-white/5 pt-8 flex flex-col sm:flex-row gap-4 sm:items-center sm:justify-between">
    <?php if ($soldOut):
      $waitUrl = '/public/waitlist.php?id=' . (int) $eventId
               . ($isRecurring && $dateValid ? '&date=' . urlencode($date) : '');
      // Note: waitlist.php uses ?event=, keep params consistent.
      $waitUrl = '/public/waitlist.php?event=' . (int) $eventId
               . ($isRecurring && $dateValid ? '&date=' . urlencode($date) : '');
    ?>
      <a href="<?= url($waitUrl) ?>"
         class="w-full sm:w-auto text-center px-8 py-4 rounded-full border border-gold-500/50 text-gold-400 font-medium hover:bg-gold-500/10 transition">
        Notify me if a seat opens →
      </a>
    <?php else: ?>
      <a href="<?= url($reserveUrl) ?>"
         class="w-full sm:w-auto text-center px-8 py-4 rounded-full bg-gold-500 text-navy-950 font-medium hover:bg-gold-400 transition shadow-[0_10px_35px_-15px_rgba(201,164,106,0.6)]">
        <?= is_logged_in() ? 'Reserve →' : 'Sign in & reserve →' ?>
      </a>
    <?php endif; ?>
    <div class="flex items-center gap-2 text-[11px] text-beige-100/55">
      <span class="uppercase tracking-[0.25em]">Share</span>
      <a href="https://wa.me/?text=<?= $shareTextEnc ?>%20<?= $shareUrlEnc ?>" target="_blank" rel="noopener"
         class="px-3 py-1.5 rounded-full border border-white/10 hover:border-gold-500/40 hover:text-gold-400 transition">WhatsApp</a>
      <a href="https://www.facebook.com/sharer/sharer.php?u=<?= $shareUrlEnc ?>" target="_blank" rel="noopener"
         class="px-3 py-1.5 rounded-full border border-white/10 hover:border-gold-500/40 hover:text-gold-400 transition">Facebook</a>
      <button type="button" data-copy-link="<?= e($shareUrl) ?>"
              class="px-3 py-1.5 rounded-full border border-white/10 hover:border-gold-500/40 hover:text-gold-400 transition">Copy link</button>
    </div>
  </div>

  <p class="mt-14 text-sm">
    <a href="<?= url('/public/events.php') ?>" class="text-gold-400 hover:text-gold-300">← Browse all upcoming sessions</a>
  </p>
</section>

<script>
document.querySelectorAll('[data-copy-link]').forEach((btn) => {
  btn.addEventListener('click', async () => {
    const url = btn.dataset.copyLink;
    try { await navigator.clipboard.writeText(url); }
    catch (e) {
      const t = document.createElement('textarea');
      t.value = url; document.body.appendChild(t); t.select();
      document.execCommand('copy'); t.remove();
    }
    const original = btn.textContent;
    btn.textContent = 'Copied ✓';
    setTimeout(() => { btn.textContent = original; }, 1600);
  });
});
</script>

<?php require __DIR__ . '/../includes/footer.php'; ?>
