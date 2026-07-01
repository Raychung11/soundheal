<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_admin();
$pageTitle = 'Referral rewards';

$errors = [];

if (is_post()) {
    csrf_verify();
    $action = (string) input('action');

    if ($action === 'settle_payout') {
        $referrerId = (int) input('referrer_id', 0);
        $reference  = trim((string) input('reference', ''));
        $res = settle_referral_payout($referrerId, $reference, current_user_id());
        if ($res['ok']) {
            audit_log('referral_rewards.payout', 'referral_payouts', (int) $res['payout_id'], [
                'referrer_id' => $referrerId,
                'amount' => $res['amount'],
                'count'  => $res['count'],
            ]);
            flash('rewards', 'Recorded a payout of ' . format_money((float) $res['amount']) . ' across ' . (int) $res['count'] . ' reward(s).', 'success');
        } else {
            flash('rewards', $res['message'] ?? 'Could not record payout.', 'error');
        }
        redirect('/admin/referral_rewards.php');
    }
}

$summary = referral_rewards_summary();
$defaultAmount = (float) setting('referral_event_reward_default', 50.00);

// Per-referrer balance owed — surfaces who to pay next.
$balances = db()->query(
    "SELECT r.referrer_id, u.full_name, u.email,
            COALESCE(SUM(CASE WHEN r.status='earned'  AND r.payout_status='unpaid' THEN r.amount END),0) AS unpaid,
            COALESCE(SUM(CASE WHEN r.status='pending'                              THEN r.amount END),0) AS pending,
            COALESCE(SUM(CASE WHEN r.payout_status='paid'                          THEN r.amount END),0) AS paid,
            COUNT(CASE WHEN r.status='earned' AND r.payout_status='unpaid' THEN 1 END) AS unpaid_count
       FROM event_referral_rewards r
       JOIN users u ON u.id = r.referrer_id
      GROUP BY r.referrer_id, u.full_name, u.email
     HAVING unpaid > 0 OR pending > 0 OR paid > 0
      ORDER BY unpaid DESC, pending DESC"
)->fetchAll();

$rewards = db()->query(
    "SELECT r.*, b.booking_ref, b.status AS booking_status, e.title AS event_title,
            u.full_name AS referrer_name, referee.full_name AS referee_name
       FROM event_referral_rewards r
       JOIN event_bookings b ON b.id = r.booking_id
       JOIN events e ON e.id = b.event_id
       JOIN users u ON u.id = r.referrer_id
       LEFT JOIN users referee ON referee.id = b.user_id
      ORDER BY r.id DESC LIMIT 100"
)->fetchAll();

$payouts = db()->query(
    "SELECT p.*, u.full_name AS referrer_name, admin.full_name AS by_name
       FROM referral_payouts p
       JOIN users u ON u.id = p.referrer_id
       LEFT JOIN users admin ON admin.id = p.paid_by
      ORDER BY p.id DESC LIMIT 30"
)->fetchAll();

require __DIR__ . '/../includes/admin_layout.php';

function rrw_money($v): string { return format_money((float) $v); }
?>

<div class="flex items-center justify-between gap-4 flex-wrap">
  <div>
    <h1 class="font-serif text-3xl text-beige-100">Referral rewards</h1>
    <p class="text-beige-100/60 mt-1 text-sm">Cash owed to members whose invites turned into attended sessions.</p>
  </div>
  <span class="text-xs px-3 py-1.5 rounded-full bg-gold-500/20 text-gold-400">
    Default <?= e(rrw_money($defaultAmount)) ?> · overrideable per event
  </span>
</div>

<!-- Summary cards -->
<div class="mt-8 grid sm:grid-cols-2 lg:grid-cols-4 gap-4">
  <div class="border border-gold-500/30 rounded-2xl p-5 bg-gold-500/5">
    <p class="text-[11px] uppercase tracking-widest text-gold-400/80">Owed now (earned · unpaid)</p>
    <p class="font-serif text-2xl text-gold-400 mt-2"><?= e(rrw_money($summary['unpaid_earned'])) ?></p>
    <p class="text-[11px] text-beige-100/45 mt-1"><?= (int) $summary['unpaid_count'] ?> reward(s)</p>
  </div>
  <div class="border border-white/5 rounded-2xl p-5 bg-navy-900/40">
    <p class="text-[11px] uppercase tracking-widest text-beige-100/50">Pending (awaiting attendance)</p>
    <p class="font-serif text-2xl text-beige-100 mt-2"><?= e(rrw_money($summary['pending_total'])) ?></p>
  </div>
  <div class="border border-white/5 rounded-2xl p-5 bg-navy-900/40">
    <p class="text-[11px] uppercase tracking-widest text-beige-100/50">Paid out to date</p>
    <p class="font-serif text-2xl text-beige-100 mt-2"><?= e(rrw_money($summary['paid_total'])) ?></p>
  </div>
  <div class="border border-white/5 rounded-2xl p-5 bg-navy-900/40">
    <p class="text-[11px] uppercase tracking-widest text-beige-100/50">Reversed (refunds)</p>
    <p class="font-serif text-2xl text-red-300/80 mt-2"><?= e(rrw_money($summary['reversed_total'])) ?></p>
  </div>
</div>

<!-- Balances owed by referrer -->
<h2 class="mt-12 font-serif text-2xl text-beige-100">Who's owed</h2>
<?php if (!$balances): ?>
  <p class="mt-4 text-beige-100/60 italic">No referral rewards recorded yet. As friends attend sessions booked through <code>?ref=</code> links, they'll appear here.</p>
<?php else: ?>
  <div class="mt-4 border border-white/5 rounded-2xl bg-navy-900/40 overflow-x-auto">
    <table class="w-full text-sm">
      <thead class="text-left text-beige-100/50 text-xs uppercase tracking-wider bg-navy-950/40">
        <tr>
          <th class="px-4 py-3">Referrer</th>
          <th class="text-right">Pending</th>
          <th class="text-right">Owed now</th>
          <th class="text-right">Paid to date</th>
          <th></th>
        </tr>
      </thead>
      <tbody class="divide-y divide-white/5">
        <?php foreach ($balances as $bal): ?>
          <tr>
            <td class="px-4 py-3">
              <p class="text-beige-100"><?= e($bal['full_name']) ?></p>
              <p class="text-xs text-beige-100/45"><?= e($bal['email']) ?></p>
            </td>
            <td class="text-right text-beige-100/70"><?= e(rrw_money($bal['pending'])) ?></td>
            <td class="text-right text-gold-400 font-medium">
              <?= e(rrw_money($bal['unpaid'])) ?>
              <?php if ((int) $bal['unpaid_count'] > 0): ?>
                <span class="text-[10px] text-beige-100/45 block"><?= (int) $bal['unpaid_count'] ?> reward(s)</span>
              <?php endif; ?>
            </td>
            <td class="text-right text-beige-100/70"><?= e(rrw_money($bal['paid'])) ?></td>
            <td class="text-right pr-4">
              <?php if ((float) $bal['unpaid'] > 0): ?>
                <form method="post" class="inline"
                      onsubmit="return confirm('Record a payout of <?= e(rrw_money($bal['unpaid'])) ?> to <?= e(addslashes($bal['full_name'])) ?>?');">
                  <?= csrf_field() ?>
                  <input type="hidden" name="action" value="settle_payout">
                  <input type="hidden" name="referrer_id" value="<?= (int) $bal['referrer_id'] ?>">
                  <input type="text" name="reference" placeholder="Bank ref (optional)" class="text-xs rounded-full bg-navy-950 border border-white/10 px-3 py-1.5 mr-2 w-40">
                  <button class="text-xs px-3 py-1.5 rounded-full bg-gold-500 text-navy-950 hover:bg-gold-400 transition">Record payout</button>
                </form>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
<?php endif; ?>

<!-- Recent rewards -->
<h2 class="mt-12 font-serif text-2xl text-beige-100">Recent rewards</h2>
<div class="mt-4 border border-white/5 rounded-2xl bg-navy-900/40 overflow-x-auto">
  <table class="w-full text-sm">
    <thead class="text-left text-beige-100/50 text-xs uppercase tracking-wider bg-navy-950/40">
      <tr>
        <th class="px-4 py-3">Created</th>
        <th>Referrer</th>
        <th>Friend / Booking</th>
        <th>Session</th>
        <th class="text-right">Amount</th>
        <th>Status</th>
        <th>Payout</th>
      </tr>
    </thead>
    <tbody class="divide-y divide-white/5">
      <?php foreach ($rewards as $r): ?>
        <tr class="<?= $r['status'] === 'reversed' ? 'opacity-50' : '' ?>">
          <td class="px-4 py-3 whitespace-nowrap"><?= e(format_datetime($r['created_at'], 'd M Y')) ?></td>
          <td><?= e($r['referrer_name'] ?? '—') ?></td>
          <td>
            <p><?= e($r['referee_name'] ?? '—') ?></p>
            <p class="text-xs text-beige-100/45"><?= e($r['booking_ref']) ?></p>
          </td>
          <td class="max-w-[220px] truncate"><?= e($r['event_title']) ?></td>
          <td class="text-right text-gold-400"><?= e(rrw_money($r['amount'])) ?></td>
          <td>
            <span class="text-xs px-2 py-1 rounded-full <?= match ($r['status']) { 'earned' => 'bg-gold-500/20 text-gold-400', 'reversed' => 'bg-red-500/15 text-red-300/80', default => 'bg-white/5 text-beige-100/60' } ?>">
              <?= e($r['status']) ?>
            </span>
          </td>
          <td>
            <span class="text-xs px-2 py-1 rounded-full <?= $r['payout_status'] === 'paid' ? 'bg-gold-500/20 text-gold-400' : 'bg-white/5 text-beige-100/50' ?>">
              <?= e($r['payout_status']) ?>
            </span>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$rewards): ?>
        <tr><td colspan="7" class="px-4 py-6 text-beige-100/55">No rewards yet.</td></tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>

<?php if ($payouts): ?>
<h2 class="mt-12 font-serif text-2xl text-beige-100">Payout history</h2>
<div class="mt-4 border border-white/5 rounded-2xl bg-navy-900/40 overflow-x-auto">
  <table class="w-full text-sm">
    <thead class="text-left text-beige-100/50 text-xs uppercase tracking-wider bg-navy-950/40">
      <tr><th class="px-4 py-3">When</th><th>Referrer</th><th class="text-right">Amount</th><th>Rewards</th><th>Reference</th><th>By</th></tr>
    </thead>
    <tbody class="divide-y divide-white/5">
      <?php foreach ($payouts as $po): ?>
        <tr>
          <td class="px-4 py-3 whitespace-nowrap"><?= e(format_datetime($po['created_at'])) ?></td>
          <td><?= e($po['referrer_name'] ?? '—') ?></td>
          <td class="text-right text-gold-400"><?= e(rrw_money($po['amount'])) ?></td>
          <td><?= (int) $po['reward_count'] ?></td>
          <td><?= e($po['reference'] ?? '—') ?></td>
          <td><?= e($po['by_name'] ?? '—') ?></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php endif; ?>

<?php require __DIR__ . '/../includes/admin_layout_end.php'; ?>
