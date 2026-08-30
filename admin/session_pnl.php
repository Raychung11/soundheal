<?php
/**
 * Session P&L — per-session revenue, attendance, package mix,
 * referral rewards owed and the 85/15 split.
 *
 *   /admin/session_pnl.php?from=YYYY-MM-DD&to=YYYY-MM-DD
 *   /admin/session_pnl.php?...&export=csv
 *
 *   Answers the operator's core question: "was this event worth
 *   running again?" Same booking rules as the seats display
 *   elsewhere: 'paid' and 'attended' count as revenue; refunded /
 *   cancelled do not.
 */
require_once __DIR__ . '/../includes/bootstrap.php';
require_admin();
$pageTitle = 'Session P&L';

$from = trim((string) input('from', date('Y-m-d', strtotime('-60 days'))));
$to   = trim((string) input('to',   date('Y-m-d', strtotime('+30 days'))));
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) $from = date('Y-m-d', strtotime('-60 days'));
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $to))   $to   = date('Y-m-d', strtotime('+30 days'));

$partnerPct = (float) setting('revenue_split_partner_pct', 15);
$partnerPct = max(0.0, min(100.0, $partnerPct));
$companyPct = round(100 - $partnerPct, 2);
$partnerLabel = (string) setting('revenue_split_partner_label', 'IT partner');
$companyLabel = (string) setting('revenue_split_company_label', 'Company');

$rows = db()->prepare(
    "SELECT e.id, e.title, e.starts_at, e.capacity, e.recurrence, e.parent_event_id, e.status AS event_status,
            COUNT(DISTINCT CASE WHEN b.status IN ('paid','attended') THEN b.id END) AS bookings_confirmed,
            COALESCE(SUM(CASE WHEN b.status IN ('paid','attended') THEN b.quantity END), 0)          AS seats_confirmed,
            COALESCE(SUM(CASE WHEN b.status = 'attended'          THEN b.quantity END), 0)           AS seats_attended,
            COALESCE(SUM(CASE WHEN b.status IN ('paid','attended') THEN b.total_amount END), 0)       AS gross_revenue,
            COALESCE(SUM(CASE WHEN b.status IN ('paid','attended') THEN b.discount_amount END), 0)    AS total_discounts,
            COALESCE(SUM(CASE WHEN b.status IN ('paid','attended') AND b.paid_with_credit = 1 THEN b.quantity END), 0) AS credit_seats,
            COALESCE(SUM(CASE WHEN b.status IN ('paid','attended') AND b.package = 'comfort' THEN b.quantity END), 0)  AS pkg_a_seats,
            COALESCE(SUM(CASE WHEN b.status IN ('paid','attended') AND b.package = 'byo'     THEN b.quantity END), 0)  AS pkg_b_seats,
            (SELECT COALESCE(SUM(rr.amount), 0) FROM event_referral_rewards rr
               JOIN event_bookings bb ON bb.id = rr.booking_id
              WHERE bb.event_id = e.id AND rr.status IN ('pending','earned')) AS referral_owed
       FROM events e
       LEFT JOIN event_bookings b ON b.event_id = e.id
      WHERE e.starts_at BETWEEN :from AND :to
      GROUP BY e.id, e.title, e.starts_at, e.capacity, e.recurrence, e.parent_event_id, e.status
      ORDER BY e.starts_at DESC"
);
$rows->execute([':from' => $from . ' 00:00:00', ':to' => $to . ' 23:59:59']);
$sessions = $rows->fetchAll();

// Aggregate totals across the filtered window.
$totalGross = 0.0; $totalDiscount = 0.0; $totalReferral = 0.0;
$totalSeatsConfirmed = 0; $totalSeatsAttended = 0;
foreach ($sessions as $s) {
    $totalGross          += (float) $s['gross_revenue'];
    $totalDiscount       += (float) $s['total_discounts'];
    $totalReferral       += (float) $s['referral_owed'];
    $totalSeatsConfirmed += (int)   $s['seats_confirmed'];
    $totalSeatsAttended  += (int)   $s['seats_attended'];
}
$totalCompany = round($totalGross * $companyPct / 100, 2);
$totalPartner = round($totalGross - $totalCompany, 2);
$netAfterAll  = round($totalCompany - $totalReferral - $totalDiscount, 2); // rough "net" — discounts already reduced gross but shown for visibility

// CSV export
if ((string) input('export', '') === 'csv') {
    $filename = 'session-pnl-' . $from . '-to-' . $to . '.csv';
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, [
        'Event ID', 'Title', 'Starts', 'Capacity', 'Bookings', 'Seats confirmed', 'Seats attended',
        'Attendance %', 'Gross MYR', 'Discounts MYR', 'Credit seats',
        'Comfort seats', 'BYO seats', 'Referral owed MYR',
        $companyLabel . ' MYR (' . $companyPct . '%)',
        $partnerLabel . ' MYR (' . $partnerPct . '%)',
    ]);
    foreach ($sessions as $s) {
        $gross     = (float) $s['gross_revenue'];
        $company   = round($gross * $companyPct / 100, 2);
        $partner   = round($gross - $company, 2);
        $attRate   = (int) $s['seats_confirmed'] > 0
            ? round(((int) $s['seats_attended'] / (int) $s['seats_confirmed']) * 100, 1) : 0;
        fputcsv($out, [
            (int) $s['id'], $s['title'], $s['starts_at'], (int) $s['capacity'],
            (int) $s['bookings_confirmed'],
            (int) $s['seats_confirmed'], (int) $s['seats_attended'],
            $attRate,
            number_format($gross, 2),
            number_format((float) $s['total_discounts'], 2),
            (int) $s['credit_seats'],
            (int) $s['pkg_a_seats'], (int) $s['pkg_b_seats'],
            number_format((float) $s['referral_owed'], 2),
            number_format($company, 2), number_format($partner, 2),
        ]);
    }
    fclose($out);
    audit_log('session_pnl.export', 'events', null, ['from' => $from, 'to' => $to]);
    exit;
}

require __DIR__ . '/../includes/admin_layout.php';
?>

<div class="flex items-center justify-between gap-4 flex-wrap">
  <div>
    <h1 class="font-serif text-3xl text-beige-100">Session P&amp;L</h1>
    <p class="text-beige-100/60 mt-1 text-sm">Per-session revenue, attendance, package mix and the <?= e($companyLabel) ?>&nbsp;/&nbsp;<?= e($partnerLabel) ?> split.</p>
  </div>
</div>

<form method="get" class="mt-6 flex flex-wrap gap-3 items-end">
  <label class="block">
    <span class="text-[11px] uppercase tracking-widest text-beige-100/55">From</span>
    <input type="date" name="from" value="<?= e($from) ?>" class="mt-1 rounded-full bg-navy-950 border border-white/10 px-4 py-2 text-sm">
  </label>
  <label class="block">
    <span class="text-[11px] uppercase tracking-widest text-beige-100/55">To</span>
    <input type="date" name="to" value="<?= e($to) ?>" class="mt-1 rounded-full bg-navy-950 border border-white/10 px-4 py-2 text-sm">
  </label>
  <button class="px-5 py-2 rounded-full bg-gold-500 text-navy-950 hover:bg-gold-400 transition text-sm">Apply</button>
  <a href="<?= url('/admin/session_pnl.php?from=' . urlencode($from) . '&to=' . urlencode($to) . '&export=csv') ?>"
     class="px-4 py-2 rounded-full border border-white/10 text-beige-100/70 hover:border-gold-500/40 hover:text-gold-400 transition text-sm">Download CSV</a>
</form>

<!-- Totals -->
<div class="mt-8 grid sm:grid-cols-2 lg:grid-cols-5 gap-3">
  <div class="border border-white/5 rounded-2xl p-4 bg-navy-900/40">
    <p class="text-[11px] uppercase tracking-widest text-beige-100/50">Gross revenue</p>
    <p class="font-serif text-2xl text-beige-100 mt-1"><?= e(format_money($totalGross)) ?></p>
  </div>
  <div class="border border-gold-500/25 rounded-2xl p-4 bg-gold-500/5">
    <p class="text-[11px] uppercase tracking-widest text-gold-400/80"><?= e($companyLabel) ?> · <?= e(rtrim(rtrim((string) $companyPct, '0'), '.')) ?>%</p>
    <p class="font-serif text-2xl text-gold-400 mt-1"><?= e(format_money($totalCompany)) ?></p>
  </div>
  <div class="border border-white/5 rounded-2xl p-4 bg-navy-900/40">
    <p class="text-[11px] uppercase tracking-widest text-beige-100/50"><?= e($partnerLabel) ?> · <?= e(rtrim(rtrim((string) $partnerPct, '0'), '.')) ?>%</p>
    <p class="font-serif text-2xl text-beige-100 mt-1"><?= e(format_money($totalPartner)) ?></p>
  </div>
  <div class="border border-white/5 rounded-2xl p-4 bg-navy-900/40">
    <p class="text-[11px] uppercase tracking-widest text-beige-100/50">Referral rewards owed</p>
    <p class="font-serif text-2xl text-beige-100 mt-1"><?= e(format_money($totalReferral)) ?></p>
  </div>
  <div class="border border-white/5 rounded-2xl p-4 bg-navy-900/40">
    <p class="text-[11px] uppercase tracking-widest text-beige-100/50">Attendance</p>
    <p class="font-serif text-2xl text-beige-100 mt-1">
      <?= $totalSeatsConfirmed > 0 ? round(($totalSeatsAttended / $totalSeatsConfirmed) * 100) . '%' : '—' ?>
    </p>
    <p class="text-[10px] text-beige-100/45 mt-0.5"><?= $totalSeatsAttended ?> / <?= $totalSeatsConfirmed ?> seats</p>
  </div>
</div>

<!-- Table -->
<div class="mt-10 border border-white/5 rounded-2xl bg-navy-900/40 overflow-x-auto">
  <table class="w-full text-sm">
    <thead class="text-left text-beige-100/50 text-xs uppercase tracking-wider bg-navy-950/40">
      <tr>
        <th class="px-4 py-3">Session</th>
        <th class="text-right">Bkgs</th>
        <th class="text-right">Seats</th>
        <th class="text-right">Attend.</th>
        <th class="text-right">Gross</th>
        <th class="text-right">Discounts</th>
        <th class="text-right">Credit seats</th>
        <th class="text-right">Comfort / BYO</th>
        <th class="text-right">Referral</th>
        <th class="text-right"><?= e($companyLabel) ?></th>
      </tr>
    </thead>
    <tbody class="divide-y divide-white/5">
      <?php foreach ($sessions as $s):
        $gross    = (float) $s['gross_revenue'];
        $company  = round($gross * $companyPct / 100, 2);
        $attRate  = (int) $s['seats_confirmed'] > 0
            ? round(((int) $s['seats_attended'] / (int) $s['seats_confirmed']) * 100) : 0;
        $isRecurringTpl = in_array($s['recurrence'] ?? 'none', ['daily','weekly','monthly','custom'], true) && empty($s['parent_event_id']);
      ?>
        <tr>
          <td class="px-4 py-3">
            <p class="text-beige-100 <?= (int) $s['bookings_confirmed'] === 0 ? 'opacity-60' : '' ?>"><?= e($s['title']) ?><?php if ($isRecurringTpl): ?> <span class="text-[10px] text-beige-100/40 uppercase tracking-widest">· template</span><?php endif; ?></p>
            <p class="text-xs text-beige-100/45"><?= e(format_datetime($s['starts_at'], 'd M Y · g:i A')) ?></p>
          </td>
          <td class="text-right"><?= (int) $s['bookings_confirmed'] ?></td>
          <td class="text-right"><?= (int) $s['seats_confirmed'] ?> / <?= (int) $s['capacity'] ?></td>
          <td class="text-right"><?= $attRate ?>%</td>
          <td class="text-right"><?= e(format_money($gross)) ?></td>
          <td class="text-right text-beige-100/70"><?= e(format_money((float) $s['total_discounts'])) ?></td>
          <td class="text-right text-beige-100/70"><?= (int) $s['credit_seats'] ?></td>
          <td class="text-right text-beige-100/70"><?= (int) $s['pkg_a_seats'] ?> / <?= (int) $s['pkg_b_seats'] ?></td>
          <td class="text-right text-red-300/70"><?= e(format_money((float) $s['referral_owed'])) ?></td>
          <td class="text-right text-gold-400"><?= e(format_money($company)) ?></td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$sessions): ?>
        <tr><td colspan="10" class="px-4 py-6 text-beige-100/55">No events in this window.</td></tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>

<p class="mt-4 text-[11px] text-beige-100/45">
  Gross is the sum of <code>total_amount</code> for paid + attended bookings (post-discount, but before the referral / partner-split payouts). The <?= e($companyLabel) ?> figure = <?= e(rtrim(rtrim((string) $companyPct, '0'), '.')) ?>% of gross; the <?= e($partnerLabel) ?> figure is the complement. Referral rewards are shown separately as they're owed to individual members (not the partner).
</p>

<?php require __DIR__ . '/../includes/admin_layout_end.php'; ?>
