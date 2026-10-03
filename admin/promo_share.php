<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_staff_or_admin();
$pageTitle = 'Share a promo';

$codeInput = strtoupper(trim((string) input('code', '')));
$eventIdIn = (int) input('event_id', 0);

$promo = null;
if ($codeInput !== '') {
    $stmt = db()->prepare("SELECT * FROM promo_codes WHERE code = :c LIMIT 1");
    $stmt->execute([':c' => $codeInput]);
    $promo = $stmt->fetch() ?: null;
}

// All promos for the picker dropdown.
$allCodes = db()->query(
    "SELECT code, description, status, discount_type, discount_value
       FROM promo_codes ORDER BY status DESC, created_at DESC LIMIT 100"
)->fetchAll();

// Events to attach the promo to. Only top-level, published, future
// or recurring — same shape as the public calendar.
$events = db()->query(
    "SELECT id, title, starts_at, recurrence
       FROM events
      WHERE status = 'published'
        AND parent_event_id IS NULL
        AND (recurrence IN ('daily','weekly','monthly','custom') OR starts_at >= NOW())
      ORDER BY starts_at ASC LIMIT 200"
)->fetchAll();

$selectedEvent = null;
if ($eventIdIn > 0) {
    foreach ($events as $ev) {
        if ((int) $ev['id'] === $eventIdIn) { $selectedEvent = $ev; break; }
    }
}

// Build the shareable link. Without an event, land on the sessions
// calendar with the promo carried through — admin can pin a
// specific session if they want a direct-to-reserve URL.
$appBase = rtrim((string) config('app.url'), '/');
$shareUrl = '';
if ($promo) {
    if ($selectedEvent) {
        $shareUrl = $appBase . '/public/reserve.php?event_id=' . (int) $selectedEvent['id']
                  . '&promo=' . rawurlencode((string) $promo['code']);
    } else {
        // No event picked → land on the calendar with promo=CODE.
        // The customer picks a session, and reserve.php prefills the
        // code from the querystring once they get there. (Cookie
        // handoff between events.php and reserve.php isn't wired, so
        // for now the calendar-mode link is informational — flag it.)
        $shareUrl = $appBase . '/public/events.php?promo=' . rawurlencode((string) $promo['code']);
    }
}

$qrImageUrl = $shareUrl !== ''
    ? 'https://api.qrserver.com/v1/create-qr-code/?size=480x480&margin=10&data=' . urlencode($shareUrl)
    : '';

$waTextEnc = $promo
    ? rawurlencode(
        'Here\'s a little something from ' . brand_name() . '. Use code ' .
        (string) $promo['code'] . ' at checkout — ' . $shareUrl
    )
    : '';

require __DIR__ . '/../includes/admin_layout.php';
?>

<div class="flex items-center justify-between gap-4 flex-wrap">
  <div>
    <h1 class="font-serif text-3xl text-beige-100">Share a promo</h1>
    <p class="text-beige-100/60 mt-1 text-sm">Generate a one-tap link that pre-fills a promo code on the booking page. Ideal for WhatsApp broadcasts and personal thank-you notes.</p>
  </div>
  <a href="<?= url('/admin/promo_codes.php') ?>" class="text-xs text-beige-100/60 hover:text-gold-400">← Manage codes</a>
</div>

<!-- Selector -->
<form method="get" class="mt-8 grid sm:grid-cols-2 gap-4 border border-white/5 rounded-3xl p-6 bg-navy-900/40">
  <label class="block">
    <span class="text-xs uppercase tracking-widest text-beige-100/60">Promo code</span>
    <select name="code" class="mt-2 w-full rounded-2xl bg-navy-950 border border-white/5 px-4 py-3 font-mono"
            onchange="this.form.submit()">
      <option value="">— pick a code —</option>
      <?php foreach ($allCodes as $c):
        $summary = $c['discount_type'] === 'percent'
            ? number_format((float) $c['discount_value'], 0) . '% off'
            : format_money((float) $c['discount_value']) . ' off';
        $label = $c['code'] . ' · ' . $summary . ($c['status'] === 'disabled' ? ' · disabled' : '');
      ?>
        <option value="<?= e((string) $c['code']) ?>" <?= $codeInput === $c['code'] ? 'selected' : '' ?>><?= e($label) ?></option>
      <?php endforeach; ?>
    </select>
  </label>

  <label class="block">
    <span class="text-xs uppercase tracking-widest text-beige-100/60">Session <span class="text-beige-100/30">(optional)</span></span>
    <select name="event_id" class="mt-2 w-full rounded-2xl bg-navy-950 border border-white/5 px-4 py-3"
            onchange="this.form.submit()">
      <option value="">— any session (send to calendar) —</option>
      <?php foreach ($events as $ev): ?>
        <option value="<?= (int) $ev['id'] ?>" <?= $eventIdIn === (int) $ev['id'] ? 'selected' : '' ?>>
          <?= e($ev['title']) ?> · <?= e(format_datetime($ev['starts_at'], 'd M Y · g:i A')) ?>
        </option>
      <?php endforeach; ?>
    </select>
    <span class="text-[11px] text-beige-100/40 mt-1 block">Pin a specific session to skip the calendar step. For recurring templates, the recipient still picks a date on the reserve page.</span>
  </label>
</form>

<?php if (!$promo && $codeInput !== ''): ?>
  <p class="mt-6 text-red-300/80 text-sm">No promo code found for "<?= e($codeInput) ?>".</p>
<?php elseif ($promo): ?>
  <?php
    // Promo summary chips.
    $disc = $promo['discount_type'] === 'percent'
        ? number_format((float) $promo['discount_value'], 0) . '% off'
        : format_money((float) $promo['discount_value']) . ' off';
    $used = (int) ($promo['used_count'] ?? 0);
    $cap  = $promo['max_uses'] !== null ? (int) $promo['max_uses'] : null;
    $window = '';
    if (!empty($promo['valid_from']) || !empty($promo['valid_until'])) {
      $from = !empty($promo['valid_from']) ? format_datetime($promo['valid_from'], 'd M Y') : 'anytime';
      $until = !empty($promo['valid_until']) ? format_datetime($promo['valid_until'], 'd M Y') : 'no expiry';
      $window = $from . ' → ' . $until;
    }
  ?>

  <!-- Promo overview -->
  <div class="mt-8 grid sm:grid-cols-3 gap-4">
    <div class="border border-gold-500/30 rounded-2xl p-5 bg-gold-500/5">
      <p class="text-[10px] uppercase tracking-widest text-gold-400/80">Code</p>
      <p class="font-mono text-xl text-gold-400 mt-2 tracking-widest"><?= e($promo['code']) ?></p>
      <p class="text-[11px] text-beige-100/50 mt-1"><?= e($disc) ?></p>
    </div>
    <div class="border border-white/5 rounded-2xl p-5 bg-navy-900/40">
      <p class="text-[10px] uppercase tracking-widest text-beige-100/50">Usage</p>
      <p class="font-serif text-xl text-beige-100 mt-2">
        <?= $used ?><?= $cap !== null ? ' / ' . $cap : '' ?>
      </p>
      <p class="text-[11px] text-beige-100/50 mt-1"><?= $cap !== null ? 'redemptions' : 'unlimited' ?></p>
    </div>
    <div class="border border-white/5 rounded-2xl p-5 bg-navy-900/40">
      <p class="text-[10px] uppercase tracking-widest text-beige-100/50">Window</p>
      <p class="text-sm text-beige-100 mt-2"><?= $window !== '' ? e($window) : 'Always valid' ?></p>
      <p class="text-[11px] text-beige-100/50 mt-1">
        Status: <span class="<?= ($promo['status'] ?? '') === 'active' ? 'text-gold-400' : 'text-red-300/70' ?>"><?= e($promo['status']) ?></span>
      </p>
    </div>
  </div>

  <?php if (($promo['status'] ?? '') !== 'active'): ?>
    <p class="mt-4 text-sm text-red-300/80">This code is currently <strong><?= e($promo['status']) ?></strong> — recipients will see "invalid code" until you re-enable it.</p>
  <?php endif; ?>

  <!-- Share panel -->
  <section class="mt-8 border border-white/5 rounded-3xl p-6 bg-navy-900/40 grid md:grid-cols-[1fr_auto] gap-8 items-start">
    <div class="space-y-4">
      <div>
        <p class="text-[10px] uppercase tracking-widest text-gold-400/80">Share link</p>
        <?php if ($selectedEvent): ?>
          <p class="text-xs text-beige-100/60 mt-1">Pinned to <strong class="text-beige-100"><?= e($selectedEvent['title']) ?></strong> · <?= e(format_datetime($selectedEvent['starts_at'], 'd M Y · g:i A')) ?>.</p>
        <?php else: ?>
          <p class="text-xs text-beige-100/60 mt-1">Sends the recipient to the sessions calendar with the code carried through — they pick the session, then the reserve page auto-fills the code.</p>
        <?php endif; ?>
      </div>

      <div class="rounded-2xl border border-white/10 bg-navy-950/60 p-4"
           x-data="{ copied: false, copy() {
             navigator.clipboard.writeText(<?= htmlspecialchars(json_encode($shareUrl), ENT_QUOTES, 'UTF-8') ?>).then(() => {
               this.copied = true; setTimeout(() => this.copied = false, 1600);
             });
           } }">
        <p class="font-mono text-xs text-beige-100/85 break-all"><?= e($shareUrl) ?></p>
        <div class="mt-3 flex flex-wrap gap-2">
          <button type="button" @click="copy()" class="px-4 py-2 rounded-full bg-gold-500 text-navy-950 text-sm font-medium hover:bg-gold-400">
            <span x-text="copied ? 'Copied ✓' : 'Copy link'"></span>
          </button>
          <a href="https://wa.me/?text=<?= $waTextEnc ?>" target="_blank" rel="noopener"
             class="px-4 py-2 rounded-full border border-white/10 text-beige-100/85 text-sm hover:border-gold-500/40 hover:text-gold-400">WhatsApp</a>
          <a href="https://www.facebook.com/sharer/sharer.php?u=<?= rawurlencode($shareUrl) ?>" target="_blank" rel="noopener"
             class="px-4 py-2 rounded-full border border-white/10 text-beige-100/85 text-sm hover:border-gold-500/40 hover:text-gold-400">Facebook</a>
          <a href="mailto:?subject=<?= rawurlencode('A little something from ' . brand_name()) ?>&body=<?= rawurlencode('Use code ' . $promo['code'] . ' at checkout — ' . $shareUrl) ?>"
             class="px-4 py-2 rounded-full border border-white/10 text-beige-100/85 text-sm hover:border-gold-500/40 hover:text-gold-400">Email</a>
          <a href="<?= e($shareUrl) ?>" target="_blank" rel="noopener"
             class="px-4 py-2 rounded-full border border-white/10 text-beige-100/60 text-sm hover:border-gold-500/40 hover:text-gold-400">Preview →</a>
        </div>
      </div>

      <div class="rounded-2xl border border-white/10 bg-navy-950/40 p-5">
        <p class="text-[10px] uppercase tracking-widest text-gold-400/80">Ready-to-paste message</p>
        <pre class="mt-3 whitespace-pre-wrap text-sm text-beige-100/85 font-sans leading-relaxed">Hi there — sending a little something from <?= e(brand_name()) ?>.

Use code <?= e($promo['code']) ?> at checkout for <?= e($disc) ?> your booking.

Reserve here: <?= e($shareUrl) ?>

Warm regards,
<?= e(brand_name()) ?></pre>
      </div>
    </div>

    <div class="text-center">
      <p class="text-[10px] uppercase tracking-widest text-gold-400/80 mb-3">QR code</p>
      <?php if ($qrImageUrl !== ''): ?>
        <img src="<?= e($qrImageUrl) ?>" alt="Promo QR" class="w-56 h-56 rounded-2xl border border-white/10 bg-white">
        <p class="text-[11px] text-beige-100/50 mt-3 max-w-[14rem] mx-auto">Print on a card, drop into a slide, or point a phone at it. Scanning takes them straight to the reserve page with the code pre-filled.</p>
      <?php endif; ?>
    </div>
  </section>

<?php else: ?>
  <p class="mt-8 text-beige-100/60 italic">Pick a promo code above to generate a share link.</p>
<?php endif; ?>

<?php require __DIR__ . '/../includes/admin_layout_end.php'; ?>
