<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_admin();
$pageTitle = 'Referral program';

$keys = [
    'referral_program_eyebrow'       => 'string',
    'referral_program_headline'      => 'string',
    'referral_program_subheadline'   => 'text',
    'referral_signup_trial_days'     => 'int',
    'referral_event_reward_default'  => 'string',
];

if (is_post()) {
    csrf_verify();
    foreach ($keys as $k => $type) {
        if (array_key_exists($k, $_POST)) {
            $v = is_string($_POST[$k]) ? trim($_POST[$k]) : $_POST[$k];
            if ($k === 'referral_signup_trial_days') {
                $v = max(0, min(60, (int) $v));
            }
            if ($k === 'referral_event_reward_default') {
                $v = number_format(max(0.0, (float) $v), 2, '.', '');
            }
            set_setting($k, $v, $type);
        }
    }
    audit_log('referral_settings.update', 'site_settings', null);
    flash('referral', 'Referral program saved.', 'success');
    redirect('/admin/referral_settings.php');
}

// Quick stats so admins can see whether it's working.
$pdo = db();
$totalReferrals = (int) $pdo->query("SELECT COUNT(*) FROM referrals")->fetchColumn();
$rewarded       = (int) $pdo->query("SELECT COUNT(*) FROM referrals WHERE status = 'rewarded'")->fetchColumn();
$daysGiven      = (int) $pdo->query(
    "SELECT COALESCE(SUM(reward_amount), 0) FROM referrals WHERE status = 'rewarded'"
)->fetchColumn();
$lastWeek       = (int) $pdo->query(
    "SELECT COUNT(*) FROM referrals WHERE signed_up_at >= NOW() - INTERVAL 7 DAY"
)->fetchColumn();

require __DIR__ . '/../includes/admin_layout.php';
?>

<div class="flex items-center justify-between gap-4 flex-wrap">
  <div>
    <h1 class="font-serif text-3xl text-beige-100">Referral program</h1>
    <p class="text-beige-100/60 mt-1 text-sm">Copy + reward shown to members on /member/refer.php and to invited guests on /public/register.php?ref=…</p>
  </div>
  <a href="<?= url('/member/refer.php') ?>" target="_blank" class="text-sm text-gold-400 hover:text-gold-300">View live →</a>
</div>

<div class="mt-8 grid sm:grid-cols-2 lg:grid-cols-4 gap-4">
  <?php foreach ([
    ['Total referrals',   number_format($totalReferrals)],
    ['Rewarded',          number_format($rewarded)],
    ['Trial days given',  '+' . number_format($daysGiven * 2) . ' (both sides)'],
    ['Last 7 days',       number_format($lastWeek)],
  ] as [$label, $value]): ?>
    <div class="border border-white/5 rounded-2xl p-5 bg-navy-900/40">
      <p class="text-[11px] uppercase tracking-widest text-beige-100/50"><?= e($label) ?></p>
      <p class="font-serif text-3xl text-gold-400 mt-2"><?= e($value) ?></p>
    </div>
  <?php endforeach; ?>
</div>

<form method="post" action="<?= url('/admin/referral_settings.php') ?>" class="mt-10 space-y-6 max-w-3xl border border-white/5 rounded-3xl p-6 bg-navy-900/40">
  <?= csrf_field() ?>

  <h2 class="font-serif text-2xl text-gold-400">Copy</h2>

  <label class="block">
    <span class="text-xs uppercase tracking-widest text-beige-100/60">Eyebrow text</span>
    <input name="referral_program_eyebrow"
           value="<?= e((string) setting('referral_program_eyebrow', '')) ?>"
           placeholder="Refer a friend"
           class="mt-2 w-full rounded-2xl bg-navy-950 border border-white/5 px-4 py-3">
  </label>

  <label class="block">
    <span class="text-xs uppercase tracking-widest text-beige-100/60">Headline</span>
    <input name="referral_program_headline"
           value="<?= e((string) setting('referral_program_headline', '')) ?>"
           placeholder="Share the sanctuary, share the reward."
           class="mt-2 w-full rounded-2xl bg-navy-950 border border-white/5 px-4 py-3 font-serif text-lg">
  </label>

  <label class="block">
    <span class="text-xs uppercase tracking-widest text-beige-100/60">Subheadline</span>
    <textarea name="referral_program_subheadline" rows="4"
              placeholder="For every friend who joins through your link, you each receive an extra week…"
              class="mt-2 w-full rounded-2xl bg-navy-950 border border-white/5 px-4 py-3"><?= e((string) setting('referral_program_subheadline', '')) ?></textarea>
  </label>

  <hr class="border-white/5">

  <h2 class="font-serif text-2xl text-gold-400">Rewards</h2>

  <div class="grid sm:grid-cols-2 gap-5">
    <label class="block">
      <span class="text-xs uppercase tracking-widest text-beige-100/60">Trial days awarded per sign-up</span>
      <input name="referral_signup_trial_days" type="number" min="0" max="60"
             value="<?= (int) setting('referral_signup_trial_days', 7) ?>"
             class="mt-2 w-full rounded-2xl bg-navy-950 border border-white/5 px-4 py-3">
      <span class="text-[11px] text-beige-100/40 mt-1 block">Both the referrer and the new user receive this many extra days of trial access on signup. Set 0 to disable. Capped at 60.</span>
    </label>

    <label class="block">
      <span class="text-xs uppercase tracking-widest text-beige-100/60">Default event reward · MYR</span>
      <input name="referral_event_reward_default" type="number" step="0.01" min="0"
             value="<?= e((string) setting('referral_event_reward_default', '50.00')) ?>"
             class="mt-2 w-full rounded-2xl bg-navy-950 border border-white/5 px-4 py-3">
      <span class="text-[11px] text-beige-100/40 mt-1 block">Cash owed to the referrer once a friend attends a session booked through their link. Override per event on the Events form. Set 0 to disable cash rewards. See <a href="<?= url('/admin/referral_rewards.php') ?>" class="text-gold-400 hover:text-gold-300 underline-offset-4 hover:underline">Referral rewards</a> for the ledger.</span>
    </label>
  </div>

  <div class="flex gap-3 pt-2">
    <button class="px-6 py-3 rounded-full bg-gold-500 text-navy-950 hover:bg-gold-400 transition">Save changes</button>
    <a href="<?= url('/admin/marketing.php') ?>" class="px-6 py-3 rounded-full border border-white/10 text-beige-100/70 hover:border-white/20">Marketing dashboard</a>
  </div>
</form>

<?php require __DIR__ . '/../includes/admin_layout_end.php'; ?>
