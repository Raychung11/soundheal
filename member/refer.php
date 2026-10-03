<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_login();
$pageTitle = 'Refer a friend';
$user = current_user();

$code = referral_code_for_user($user['id']);
$link = referral_link_for_user($user['id']);
$rewardDays = (int) setting('referral_signup_trial_days', 7);

// Upcoming one-off sessions the member can refer for, most-recent first.
// Daily-recurring templates are omitted (they'd need a date picker to
// resolve to a specific instance).
$upcomingEvents = db()->query(
    "SELECT id, title, starts_at
       FROM events
      WHERE status = 'published'
        AND parent_event_id IS NULL
        AND (recurrence IS NULL OR recurrence = 'none')
        AND starts_at >= NOW()
      ORDER BY starts_at ASC LIMIT 20"
)->fetchAll();

$eventsData = array_map(function ($e) {
    return [
        'id'    => (int) $e['id'],
        'label' => format_datetime($e['starts_at'], 'D, d M · g:i A') . ' — ' . $e['title'],
    ];
}, $upcomingEvents);

$appBase   = rtrim((string) config('app.url'), '/');
$brandName = brand_name();

$eyebrow     = (string) setting('referral_program_eyebrow',     'Refer a friend');
$headline    = (string) setting('referral_program_headline',    'Share the sanctuary, share the reward.');
$subheadline = (string) setting('referral_program_subheadline', 'For every friend who joins through your link, you each receive an extra week of audio-library access — quietly added, no payment required.');

// Referrals this user has made.
$rows = db()->prepare(
    "SELECT r.*, u.full_name AS referee_name, u.email AS referee_email, u.created_at AS referee_created_at
       FROM referrals r
       JOIN users u ON u.id = r.referee_user_id
      WHERE r.referrer_user_id = :u
      ORDER BY r.signed_up_at DESC"
);
$rows->execute([':u' => $user['id']]);
$referrals = $rows->fetchAll();

$totalSignups   = count($referrals);
$totalRewarded  = count(array_filter($referrals, fn($r) => $r['status'] === 'rewarded'));
$totalDays      = (int) array_sum(array_map(
    fn($r) => $r['status'] === 'rewarded' ? (int) ($r['reward_amount'] ?? 0) : 0,
    $referrals
));

require __DIR__ . '/../includes/header.php';
?>

<section class="max-w-4xl mx-auto px-6 py-16">
  <p class="text-gold-400/80 tracking-[0.3em] uppercase text-[11px]"><?= e($eyebrow) ?></p>
  <h1 class="font-serif text-4xl md:text-5xl text-beige-100 mt-4 leading-tight"><?= e($headline) ?></h1>
  <p class="mt-5 text-beige-100/75 leading-relaxed max-w-2xl"><?= nl2br(e($subheadline)) ?></p>

  <!-- Hero share card -->
  <div x-data="referShare(<?= htmlspecialchars(json_encode([
        'link'      => $link,
        'code'      => $code,
        'appUrl'    => $appBase,
        'brandName' => $brandName,
        'events'    => $eventsData,
       ]), ENT_QUOTES) ?>)"
       class="mt-12 border border-gold-500/30 rounded-3xl bg-navy-900/60 p-6 md:p-10 relative overflow-hidden">
    <div class="absolute -inset-32 opacity-30 pointer-events-none bg-[radial-gradient(circle_at_30%_30%,_rgba(201,164,106,0.4),_transparent_60%)]"></div>

    <!-- Session picker — leave blank for the generic signup link -->
    <?php if ($eventsData): ?>
      <div class="relative flex flex-col sm:flex-row sm:items-center gap-3 mb-6">
        <label class="text-[11px] uppercase tracking-[0.3em] text-gold-400/80 shrink-0">Refer for</label>
        <select x-model="eventId" class="rounded-full bg-navy-950 border border-white/10 px-4 py-2 text-sm text-beige-100 focus:border-gold-500/50 focus:outline-none w-full sm:w-auto">
          <option value="">Any session / general signup</option>
          <template x-for="e in events" :key="e.id">
            <option :value="e.id" x-text="e.label"></option>
          </template>
        </select>
      </div>
    <?php endif; ?>

    <div class="relative grid md:grid-cols-[1fr_auto] gap-6 items-center">
      <div class="min-w-0 space-y-3">
        <div>
          <p class="text-[11px] uppercase tracking-[0.3em] text-gold-400/80">Your invite link</p>
          <p class="mt-2 font-mono text-sm text-beige-100 break-all leading-relaxed" x-text="link"></p>
        </div>
        <p class="text-xs text-beige-100/55">Or share just your code: <span class="font-mono text-gold-400 tracking-widest"><?= e($code) ?></span></p>

        <div class="grid grid-cols-2 sm:grid-cols-4 gap-2 text-sm pt-2">
          <button type="button" @click="copyLink()" class="text-center px-4 py-2.5 rounded-full bg-gold-500 text-navy-950 font-medium hover:bg-gold-400 transition text-sm">
            <span x-show="!copied">Copy link</span>
            <span x-show="copied" x-cloak>Copied ✓</span>
          </button>
          <a :href="whatsappUrl" target="_blank" rel="noopener" class="text-center px-4 py-2.5 rounded-full border border-white/10 text-beige-100/85 hover:border-gold-500/40 hover:text-gold-400 transition">WhatsApp</a>
          <a :href="mailtoUrl" class="text-center px-4 py-2.5 rounded-full border border-white/10 text-beige-100/85 hover:border-gold-500/40 hover:text-gold-400 transition">Email</a>
          <button type="button" @click="systemShare()" x-show="canShare" x-cloak class="text-center px-4 py-2.5 rounded-full border border-gold-500/40 text-gold-400 hover:bg-gold-500/10 transition">Share…</button>
        </div>
      </div>

      <!-- QR code -->
      <div class="shrink-0 text-center md:text-right">
        <p class="text-[11px] uppercase tracking-[0.3em] text-gold-400/80 mb-3">Scan me</p>
        <div class="inline-block bg-white p-2 rounded-2xl">
          <img :src="qrUrl" alt="Scan to open the invite link"
               class="block h-40 w-40 md:h-48 md:w-48" loading="lazy">
        </div>
        <p class="mt-3 text-[10px] text-beige-100/45">Right-click / long-press → save</p>
      </div>
    </div>
  </div>

  <!-- Stats -->
  <div class="mt-10 grid sm:grid-cols-3 gap-4">
    <div class="border border-white/5 rounded-2xl p-5 bg-navy-900/40">
      <p class="text-[11px] uppercase tracking-widest text-beige-100/50">Friends joined</p>
      <p class="font-serif text-3xl text-gold-400 mt-2"><?= number_format($totalSignups) ?></p>
    </div>
    <div class="border border-white/5 rounded-2xl p-5 bg-navy-900/40">
      <p class="text-[11px] uppercase tracking-widest text-beige-100/50">Rewards earned</p>
      <p class="font-serif text-3xl text-gold-400 mt-2"><?= number_format($totalRewarded) ?></p>
    </div>
    <div class="border border-white/5 rounded-2xl p-5 bg-navy-900/40">
      <p class="text-[11px] uppercase tracking-widest text-beige-100/50">Trial days added</p>
      <p class="font-serif text-3xl text-gold-400 mt-2">+<?= number_format($totalDays) ?></p>
    </div>
  </div>

  <!-- Recent referrals -->
  <div class="mt-10 border border-white/5 rounded-3xl bg-navy-900/40 p-6">
    <h2 class="font-serif text-xl text-gold-400">People you've shared with</h2>
    <?php if (!$referrals): ?>
      <p class="text-sm text-beige-100/60 mt-3">No one yet. Send your link to a friend who'd appreciate a quieter hour.</p>
    <?php else: ?>
      <table class="mt-4 w-full text-sm">
        <thead class="text-left text-beige-100/50 text-xs uppercase tracking-wider">
          <tr><th class="py-2">When</th><th>Friend</th><th>Status</th><th class="text-right">Reward</th></tr>
        </thead>
        <tbody class="divide-y divide-white/5">
          <?php foreach ($referrals as $r): ?>
            <tr>
              <td class="py-3 text-beige-100/70"><?= e(format_datetime($r['signed_up_at'], 'd M Y')) ?></td>
              <td>
                <p class="text-beige-100"><?= e($r['referee_name']) ?></p>
                <p class="text-xs text-beige-100/50"><?= e($r['referee_email']) ?></p>
              </td>
              <td>
                <span class="text-xs px-2 py-1 rounded-full <?= $r['status'] === 'rewarded' ? 'bg-gold-500/20 text-gold-400' : 'bg-white/5 text-beige-100/60' ?>"><?= e($r['status']) ?></span>
              </td>
              <td class="text-right text-beige-100/80">
                <?= $r['status'] === 'rewarded' ? '+' . (int) $r['reward_amount'] . ' days' : '—' ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>

  <p class="mt-10 text-xs text-beige-100/40 text-center">No medical claims, no aggressive nudges — just quietly inviting the people you love into the same calm space.</p>
</section>

<script>
function referShare(opts) {
  return {
    baseLink: opts.link,
    code: opts.code,
    appUrl: opts.appUrl,
    brandName: opts.brandName,
    events: opts.events || [],
    eventId: '',
    copied: false,
    canShare: !!(navigator.share),

    get selectedEvent() {
      if (this.eventId === '' || this.eventId === null) return null;
      return this.events.find(e => String(e.id) === String(this.eventId)) || null;
    },
    get link() {
      const ev = this.selectedEvent;
      if (!ev) return this.baseLink;
      return `${this.appUrl}/public/event.php?id=${ev.id}&ref=${encodeURIComponent(this.code)}`;
    },
    get qrUrl() {
      return `https://api.qrserver.com/v1/create-qr-code/?size=320x320&margin=8&data=${encodeURIComponent(this.link)}`;
    },
    get whatsappUrl() {
      const ev = this.selectedEvent;
      const msg = ev
        ? `Join me at ${ev.label} — ${this.link}`
        : `A quiet invitation to ${this.brandName}: ${this.link}`;
      return `https://wa.me/?text=${encodeURIComponent(msg)}`;
    },
    get mailtoUrl() {
      const ev = this.selectedEvent;
      const subject = ev ? `Come join me — ${ev.label}` : `A quiet invitation to ${this.brandName}`;
      const body    = (ev
        ? `Hi,\n\nI'd love you to join me for ${ev.label}. Reserve your seat here:\n\n${this.link}\n\nWith care.`
        : `Hi,\n\nI've been enjoying ${this.brandName} — a calm space for sound healing. This link gets us both an extra trial:\n\n${this.link}\n\nWith care.`);
      return `mailto:?subject=${encodeURIComponent(subject)}&body=${encodeURIComponent(body)}`;
    },
    async copyLink() {
      try {
        await navigator.clipboard.writeText(this.link);
      } catch (e) {
        const ta = document.createElement('textarea');
        ta.value = this.link;
        document.body.appendChild(ta);
        ta.select();
        try { document.execCommand('copy'); } catch (_) {}
        ta.remove();
      }
      this.copied = true;
      setTimeout(() => { this.copied = false; }, 2400);
    },
    systemShare() {
      if (!navigator.share) return;
      const ev = this.selectedEvent;
      navigator.share({
        title: ev ? ev.label : this.brandName,
        text: 'A quiet invitation.',
        url: this.link,
      }).catch(() => {});
    }
  }
}
</script>

<?php require __DIR__ . '/../includes/footer.php'; ?>
