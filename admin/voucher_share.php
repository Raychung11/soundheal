<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_staff_or_admin();
$pageTitle = 'Share a gift voucher';

// Save the editable message template. POST-only + CSRF; leaves
// the URL query params (?code=, ?event_id=) alone so the admin
// stays on the same voucher after save.
if (is_post()) {
    csrf_verify();
    if ((string) input('action', '') === 'save_template') {
        // Bypass input() (which would trim the trailing blank lines
        // that make the message read nicely). Cap length as a light
        // guard; anything over 4KB is definitely not a message.
        $tpl = is_string($_POST['voucher_share_message'] ?? null)
            ? substr((string) $_POST['voucher_share_message'], 0, 4000)
            : '';
        set_setting('voucher_share_message', $tpl, 'text');
        audit_log('voucher_share.template.update', 'site_settings', null);
        flash('vshare', 'Message template saved.', 'success');
    }
    // Preserve the current voucher / event selection on redirect.
    $qs = http_build_query(array_filter([
        'code'     => (string) input('code', ''),
        'event_id' => (int) input('event_id', 0) ?: '',
    ]));
    redirect('/admin/voucher_share.php' . ($qs ? '?' . $qs : ''));
}

$codeInput = strtoupper(trim((string) input('code', '')));
$eventIdIn = (int) input('event_id', 0);

$voucher = null;
if ($codeInput !== '') {
    $stmt = db()->prepare("SELECT * FROM gift_vouchers WHERE code = :c LIMIT 1");
    $stmt->execute([':c' => $codeInput]);
    $voucher = $stmt->fetch() ?: null;
}

// Dropdown of all issued vouchers.
$allVouchers = db()->query(
    "SELECT code, amount, recipient_name, recipient_email, status, expires_at
       FROM gift_vouchers ORDER BY created_at DESC LIMIT 100"
)->fetchAll();

// Sessions to optionally pin the voucher to. Same shape as the
// promo share picker.
$events = db()->query(
    "SELECT id, title, starts_at
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

// Build the share URL. reserve.php's single "code" input accepts
// promo codes AND gift voucher codes; validate_gift_voucher() runs
// first, so a voucher wins over a promo of the same name. The URL
// carries the code in ?promo= for consistency with the promo share
// panel (the query name is a bit historical but the reserve.php
// prefill handler reads it either way).
$appBase = rtrim((string) config('app.url'), '/');
$shareUrl = '';
if ($voucher) {
    $shareUrl = $selectedEvent
        ? $appBase . '/public/reserve.php?event_id=' . (int) $selectedEvent['id']
              . '&promo=' . rawurlencode((string) $voucher['code'])
        : $appBase . '/public/events.php?promo=' . rawurlencode((string) $voucher['code']);
}

$qrImageUrl = $shareUrl !== ''
    ? 'https://api.qrserver.com/v1/create-qr-code/?size=480x480&margin=10&data=' . urlencode($shareUrl)
    : '';

$recipient = $voucher ? trim((string) ($voucher['recipient_name'] ?? '')) : '';
$greetingName = $recipient !== '' ? $recipient : 'friend';

// Editable message template. Placeholders get substituted at render
// time; the raw template stays in site_settings so the admin's copy
// choices survive across every voucher share.
$messageTemplate = (string) setting(
    'voucher_share_message',
    "Hi {NAME} — a small gift from {BRAND}.\n\nYour voucher code is {CODE} (worth {AMOUNT}).\n\nRedeem here: {URL}\n\nWarm regards,\n{BRAND}"
);
$messageBody = '';
if ($voucher) {
    $expiryText = !empty($voucher['expires_at'])
        ? format_datetime($voucher['expires_at'], 'd M Y')
        : '';
    $messageBody = strtr($messageTemplate, [
        '{NAME}'   => $greetingName,
        '{BRAND}'  => brand_name(),
        '{CODE}'   => (string) $voucher['code'],
        '{AMOUNT}' => format_money((float) $voucher['amount']),
        '{URL}'    => $shareUrl,
        '{EXPIRY}' => $expiryText,
    ]);
}

$waTextEnc = $messageBody !== '' ? rawurlencode($messageBody) : '';

require __DIR__ . '/../includes/admin_layout.php';
?>

<div class="flex items-center justify-between gap-4 flex-wrap">
  <div>
    <h1 class="font-serif text-3xl text-beige-100">Share a gift voucher</h1>
    <p class="text-beige-100/60 mt-1 text-sm">One-tap link that pre-fills the voucher code on the booking page. Send it as a WhatsApp message, drop the QR into a gift card, or hand it to the recipient in person.</p>
  </div>
  <a href="<?= url('/admin/gift_vouchers.php') ?>" class="text-xs text-beige-100/60 hover:text-gold-400">← All vouchers</a>
</div>

<!-- Selector -->
<form method="get" class="mt-8 grid sm:grid-cols-2 gap-4 border border-white/5 rounded-3xl p-6 bg-navy-900/40">
  <label class="block">
    <span class="text-xs uppercase tracking-widest text-beige-100/60">Voucher</span>
    <select name="code" class="mt-2 w-full rounded-2xl bg-navy-950 border border-white/5 px-4 py-3 font-mono"
            onchange="this.form.submit()">
      <option value="">— pick a voucher —</option>
      <?php foreach ($allVouchers as $v):
        $for = trim((string) ($v['recipient_name'] ?? '')) !== ''
            ? ' · for ' . (string) $v['recipient_name']
            : '';
        $stat = $v['status'] !== 'issued' ? ' · ' . $v['status'] : '';
        $label = $v['code'] . ' · ' . format_money((float) $v['amount']) . $for . $stat;
      ?>
        <option value="<?= e((string) $v['code']) ?>" <?= $codeInput === $v['code'] ? 'selected' : '' ?>><?= e($label) ?></option>
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
    <span class="text-[11px] text-beige-100/40 mt-1 block">Leave unpinned when the voucher is open-ended — recipient picks a session on the calendar and the code carries through.</span>
  </label>
</form>

<?php if (!$voucher && $codeInput !== ''): ?>
  <p class="mt-6 text-red-300/80 text-sm">No voucher found for "<?= e($codeInput) ?>".</p>
<?php elseif ($voucher):
  $expLabel = !empty($voucher['expires_at']) ? format_datetime($voucher['expires_at'], 'd M Y') : 'No expiry';
  $statusColor = match ($voucher['status'] ?? '') {
      'issued'   => 'text-gold-400',
      'redeemed' => 'text-beige-100/60',
      'expired', 'revoked' => 'text-red-300/70',
      default => 'text-beige-100/60',
  };
?>

  <!-- Voucher overview -->
  <div class="mt-8 grid sm:grid-cols-3 gap-4">
    <div class="border border-gold-500/30 rounded-2xl p-5 bg-gold-500/5">
      <p class="text-[10px] uppercase tracking-widest text-gold-400/80">Code</p>
      <p class="font-mono text-xl text-gold-400 mt-2 tracking-widest"><?= e($voucher['code']) ?></p>
      <p class="text-[11px] text-beige-100/50 mt-1"><?= e(format_money((float) $voucher['amount'])) ?> value</p>
    </div>
    <div class="border border-white/5 rounded-2xl p-5 bg-navy-900/40">
      <p class="text-[10px] uppercase tracking-widest text-beige-100/50">For</p>
      <p class="text-sm text-beige-100 mt-2"><?= e($recipient !== '' ? $recipient : '—') ?></p>
      <p class="text-[11px] text-beige-100/50 mt-1"><?= e((string) ($voucher['recipient_email'] ?? '')) ?></p>
    </div>
    <div class="border border-white/5 rounded-2xl p-5 bg-navy-900/40">
      <p class="text-[10px] uppercase tracking-widest text-beige-100/50">Expires</p>
      <p class="text-sm text-beige-100 mt-2"><?= e($expLabel) ?></p>
      <p class="text-[11px] mt-1">
        Status: <span class="<?= $statusColor ?>"><?= e($voucher['status']) ?></span>
      </p>
    </div>
  </div>

  <?php if (($voucher['status'] ?? '') !== 'issued'): ?>
    <p class="mt-4 text-sm text-red-300/80">This voucher is <strong><?= e($voucher['status']) ?></strong> — the recipient will see "voucher already used / expired" if they try to redeem.</p>
  <?php endif; ?>

  <!-- Share panel -->
  <section class="mt-8 border border-white/5 rounded-3xl p-6 bg-navy-900/40 grid md:grid-cols-[1fr_auto] gap-8 items-start">
    <div class="space-y-4">
      <div>
        <p class="text-[10px] uppercase tracking-widest text-gold-400/80">Share link</p>
        <?php if ($selectedEvent): ?>
          <p class="text-xs text-beige-100/60 mt-1">Pinned to <strong class="text-beige-100"><?= e($selectedEvent['title']) ?></strong> · <?= e(format_datetime($selectedEvent['starts_at'], 'd M Y · g:i A')) ?>.</p>
        <?php else: ?>
          <p class="text-xs text-beige-100/60 mt-1">Open-ended — recipient picks any session on the calendar, and the code auto-fills at checkout.</p>
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
          <a href="mailto:<?= e((string) ($voucher['recipient_email'] ?? '')) ?>?subject=<?= rawurlencode('A gift from ' . brand_name()) ?>&body=<?= rawurlencode($messageBody) ?>"
             class="px-4 py-2 rounded-full border border-white/10 text-beige-100/85 text-sm hover:border-gold-500/40 hover:text-gold-400">Email<?= !empty($voucher['recipient_email']) ? ' →' : '' ?></a>
          <a href="<?= e($shareUrl) ?>" target="_blank" rel="noopener"
             class="px-4 py-2 rounded-full border border-white/10 text-beige-100/60 text-sm hover:border-gold-500/40 hover:text-gold-400">Preview →</a>
        </div>
      </div>

      <!-- Preview of the message with placeholders substituted. This
           is exactly what the WhatsApp / Email buttons above will
           send. Updates live when the template below is saved. -->
      <div class="rounded-2xl border border-white/10 bg-navy-950/40 p-5">
        <p class="text-[10px] uppercase tracking-widest text-gold-400/80">Ready-to-paste message</p>
        <pre class="mt-3 whitespace-pre-wrap text-sm text-beige-100/85 font-sans leading-relaxed"><?= e($messageBody) ?></pre>
      </div>

      <!-- Editable template. Saves back to site_settings so every
           subsequent voucher share uses the new copy. Placeholders
           in {CURLY_BRACES} are substituted at send time. -->
      <form method="post" class="rounded-2xl border border-gold-500/20 bg-gold-500/5 p-5">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="save_template">
        <div class="flex items-start justify-between gap-3 flex-wrap">
          <div>
            <p class="text-[10px] uppercase tracking-widest text-gold-400/80">Message template · edit &amp; save</p>
            <p class="text-[11px] text-beige-100/50 mt-1">Placeholders substitute per-voucher when sending: <code class="text-gold-400/85">{NAME}</code> <code class="text-gold-400/85">{BRAND}</code> <code class="text-gold-400/85">{CODE}</code> <code class="text-gold-400/85">{AMOUNT}</code> <code class="text-gold-400/85">{URL}</code> <code class="text-gold-400/85">{EXPIRY}</code></p>
          </div>
        </div>
        <textarea name="voucher_share_message" rows="10"
                  class="mt-3 w-full rounded-2xl bg-navy-950 border border-white/5 px-4 py-3 text-sm text-beige-100 font-sans leading-relaxed"><?= e($messageTemplate) ?></textarea>
        <button class="mt-3 px-5 py-2 rounded-full bg-gold-500 text-navy-950 text-sm font-medium hover:bg-gold-400">Save template</button>
      </form>
    </div>

    <div class="text-center">
      <p class="text-[10px] uppercase tracking-widest text-gold-400/80 mb-3">QR code</p>
      <?php if ($qrImageUrl !== ''): ?>
        <img src="<?= e($qrImageUrl) ?>" alt="Voucher QR" class="w-56 h-56 rounded-2xl border border-white/10 bg-white">
        <p class="text-[11px] text-beige-100/50 mt-3 max-w-[14rem] mx-auto">Drop into a gift card design, or point the recipient's phone at it. Scanning takes them straight to the reserve page with the voucher pre-filled.</p>
      <?php endif; ?>
    </div>
  </section>

<?php else: ?>
  <p class="mt-8 text-beige-100/60 italic">Pick a voucher above to generate its share link.</p>
<?php endif; ?>

<?php require __DIR__ . '/../includes/admin_layout_end.php'; ?>
