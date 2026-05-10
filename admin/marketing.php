<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_admin();
$pageTitle = 'Marketing';

$range = (int) input('range', 30);
if (!in_array($range, [7, 30, 90], true)) $range = 30;

$pdo = db();

/**
 * Helper: returns 0 instead of throwing when page_views isn't migrated yet.
 */
$safeFetch = function (string $sql, array $params = [], $default = 0) use ($pdo) {
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchColumn() ?: $default;
    } catch (Throwable $e) {
        return $default;
    }
};
$safeRows = function (string $sql, array $params = []) use ($pdo) {
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    } catch (Throwable $e) {
        return [];
    }
};

// ---------- KPIs ----------
$views   = (int) $safeFetch(
    "SELECT COUNT(*) FROM page_views
      WHERE is_bot = 0 AND created_at >= NOW() - INTERVAL :r DAY",
    [':r' => $range]
);
$visitors = (int) $safeFetch(
    "SELECT COUNT(DISTINCT session_token) FROM page_views
      WHERE is_bot = 0 AND created_at >= NOW() - INTERVAL :r DAY
        AND session_token IS NOT NULL",
    [':r' => $range]
);

$leadsContact   = (int) $safeFetch(
    "SELECT COUNT(*) FROM contact_leads     WHERE created_at >= NOW() - INTERVAL :r DAY",
    [':r' => $range]
);
$leadsCorporate = (int) $safeFetch(
    "SELECT COUNT(*) FROM corporate_inquiries WHERE created_at >= NOW() - INTERVAL :r DAY",
    [':r' => $range]
);
$leadsTotal = $leadsContact + $leadsCorporate;

$registrations = (int) $safeFetch(
    "SELECT COUNT(*) FROM users WHERE role_id = 3 AND created_at >= NOW() - INTERVAL :r DAY",
    [':r' => $range]
);
$trialSignups = (int) $safeFetch(
    "SELECT COUNT(*) FROM users
      WHERE role_id = 3 AND trial_ends_at IS NOT NULL
        AND created_at >= NOW() - INTERVAL :r DAY",
    [':r' => $range]
);
$paidBookings = (int) $safeFetch(
    "SELECT COUNT(*) FROM event_bookings
      WHERE status IN ('paid','attended') AND created_at >= NOW() - INTERVAL :r DAY",
    [':r' => $range]
);
$revenue = (float) $safeFetch(
    "SELECT COALESCE(SUM(amount), 0) FROM payments
      WHERE status = 'paid' AND paid_at IS NOT NULL
        AND paid_at >= NOW() - INTERVAL :r DAY",
    [':r' => $range],
    0.0
);

$referralSignups = (int) $safeFetch(
    "SELECT COUNT(*) FROM referrals WHERE signed_up_at >= NOW() - INTERVAL :r DAY",
    [':r' => $range]
);
$topReferrers = $safeRows(
    "SELECT u.id, u.full_name, u.email, COUNT(r.id) AS total
       FROM referrals r
       JOIN users u ON u.id = r.referrer_user_id
      WHERE r.signed_up_at >= NOW() - INTERVAL :r DAY
      GROUP BY u.id
      ORDER BY total DESC
      LIMIT 8",
    [':r' => $range]
);

// Funnel rates
$signupRate  = $visitors > 0 ? ($registrations / $visitors) * 100 : 0;
$bookingRate = $registrations > 0 ? ($paidBookings / $registrations) * 100 : 0;

// ---------- Top pages ----------
$topPages = $safeRows(
    "SELECT path, COUNT(*) AS views, COUNT(DISTINCT session_token) AS visitors
       FROM page_views
      WHERE is_bot = 0 AND created_at >= NOW() - INTERVAL :r DAY
      GROUP BY path
      ORDER BY views DESC
      LIMIT 10",
    [':r' => $range]
);

// ---------- Top referrers ----------
$topRefs = $safeRows(
    "SELECT referrer_host AS host, COUNT(*) AS views, COUNT(DISTINCT session_token) AS visitors
       FROM page_views
      WHERE is_bot = 0 AND referrer_host IS NOT NULL AND referrer_host != ''
        AND created_at >= NOW() - INTERVAL :r DAY
      GROUP BY referrer_host
      ORDER BY views DESC
      LIMIT 10",
    [':r' => $range]
);

// ---------- UTM ----------
$utm = $safeRows(
    "SELECT COALESCE(utm_source,'(none)') AS source,
            COALESCE(utm_medium,'(none)') AS medium,
            COALESCE(utm_campaign,'(none)') AS campaign,
            COUNT(*) AS views,
            COUNT(DISTINCT session_token) AS visitors
       FROM page_views
      WHERE is_bot = 0
        AND (utm_source IS NOT NULL OR utm_medium IS NOT NULL OR utm_campaign IS NOT NULL)
        AND created_at >= NOW() - INTERVAL :r DAY
      GROUP BY source, medium, campaign
      ORDER BY views DESC
      LIMIT 10",
    [':r' => $range]
);

// ---------- Daily trend ----------
$daily = $safeRows(
    "SELECT DATE(created_at) AS d,
            COUNT(*) AS views,
            COUNT(DISTINCT session_token) AS visitors
       FROM page_views
      WHERE is_bot = 0 AND created_at >= NOW() - INTERVAL :r DAY
      GROUP BY DATE(created_at)
      ORDER BY d ASC",
    [':r' => $range]
);

// ---------- Recent leads (combined) ----------
$recentContacts = $safeRows(
    "SELECT 'contact' AS kind, name, email, subject AS topic, created_at, NULL AS company
       FROM contact_leads
      ORDER BY created_at DESC LIMIT 10"
);
$recentCorporate = $safeRows(
    "SELECT 'corporate' AS kind, contact_name AS name, contact_email AS email,
            interest AS topic, created_at, company_name AS company
       FROM corporate_inquiries
      ORDER BY created_at DESC LIMIT 10"
);
$recentLeads = array_merge($recentContacts, $recentCorporate);
usort($recentLeads, fn($a, $b) => strcmp($b['created_at'] ?? '', $a['created_at'] ?? ''));
$recentLeads = array_slice($recentLeads, 0, 12);

require __DIR__ . '/../includes/admin_layout.php';

$maxViews = 0;
foreach ($daily as $d) { $maxViews = max($maxViews, (int)$d['views']); }
?>

<div class="flex flex-wrap items-center justify-between gap-4">
  <div>
    <h1 class="font-serif text-3xl text-beige-100">Marketing</h1>
    <p class="text-beige-100/60 mt-1 text-sm">Views, leads and conversion funnel.</p>
  </div>
  <div class="flex items-center gap-2 text-sm">
    <?php foreach ([7, 30, 90] as $r): ?>
      <a href="<?= url('/admin/marketing.php?range=' . $r) ?>"
         class="px-3 py-1.5 rounded-full <?= $range === $r ? 'bg-gold-500 text-navy-950' : 'border border-white/10 text-beige-100/70 hover:border-gold-500/40 hover:text-gold-400' ?> transition">
        Last <?= $r ?> days
      </a>
    <?php endforeach; ?>
  </div>
</div>

<!-- KPIs -->
<div class="mt-8 grid sm:grid-cols-2 lg:grid-cols-4 gap-4">
  <?php
  $kpis = [
    ['Views',          number_format($views)],
    ['Unique visitors',number_format($visitors)],
    ['Leads',          number_format($leadsTotal),    'extra' => number_format($leadsContact) . ' contact · ' . number_format($leadsCorporate) . ' corporate'],
    ['Sign-ups',       number_format($registrations), 'extra' => number_format($trialSignups) . ' free trial'],
    ['Paid bookings',  number_format($paidBookings)],
    ['Revenue',        format_money($revenue)],
    ['Visitor → sign-up', number_format($signupRate, 1) . '%'],
    ['Sign-up → booking', number_format($bookingRate, 1) . '%'],
    ['Referral sign-ups', number_format($referralSignups)],
  ];
  foreach ($kpis as $k): ?>
    <div class="border border-white/5 rounded-2xl p-5 bg-navy-900/40">
      <p class="text-[11px] uppercase tracking-widest text-beige-100/50"><?= e($k[0]) ?></p>
      <p class="font-serif text-3xl text-gold-400 mt-2"><?= e($k[1]) ?></p>
      <?php if (!empty($k['extra'])): ?>
        <p class="text-xs text-beige-100/45 mt-1"><?= e($k['extra']) ?></p>
      <?php endif; ?>
    </div>
  <?php endforeach; ?>
</div>

<!-- Daily trend -->
<div class="mt-10 border border-white/5 rounded-3xl bg-navy-900/40 p-6">
  <div class="flex items-end justify-between mb-5">
    <h2 class="font-serif text-xl text-gold-400">Daily traffic</h2>
    <p class="text-xs text-beige-100/50">Bots excluded</p>
  </div>
  <?php if (!$daily): ?>
    <p class="text-beige-100/60 text-sm">No views recorded yet. After a few visitors land on the public site, the bars will fill in.</p>
  <?php else: ?>
    <div class="flex items-end gap-1 h-40">
      <?php foreach ($daily as $d):
        $h = $maxViews > 0 ? max(2, (int) round((int)$d['views'] / $maxViews * 100)) : 2;
      ?>
        <div class="group flex-1 flex flex-col items-center justify-end" style="min-width:6px;">
          <div class="w-full rounded-t-md bg-gradient-to-t from-gold-500/30 to-gold-400 transition group-hover:from-gold-500/50"
               style="height: <?= $h ?>%;"
               title="<?= e($d['d']) ?> · <?= (int)$d['views'] ?> views, <?= (int)$d['visitors'] ?> visitors"></div>
        </div>
      <?php endforeach; ?>
    </div>
    <div class="flex justify-between text-[10px] text-beige-100/40 uppercase tracking-widest mt-3">
      <span><?= e($daily[0]['d'] ?? '') ?></span>
      <span><?= e(end($daily)['d'] ?? '') ?></span>
    </div>
  <?php endif; ?>
</div>

<!-- Top pages + referrers -->
<div class="mt-10 grid lg:grid-cols-2 gap-6">
  <div class="border border-white/5 rounded-3xl bg-navy-900/40 p-6">
    <h2 class="font-serif text-xl text-gold-400">Top pages</h2>
    <?php if (!$topPages): ?>
      <p class="text-sm text-beige-100/60 mt-3">No data yet.</p>
    <?php else: ?>
      <table class="mt-4 w-full text-sm">
        <thead class="text-left text-beige-100/50 text-xs uppercase tracking-wider">
          <tr><th class="py-2">Path</th><th class="text-right">Views</th><th class="text-right">Visitors</th></tr>
        </thead>
        <tbody class="divide-y divide-white/5">
          <?php foreach ($topPages as $p): ?>
            <tr>
              <td class="py-2 text-beige-100/85 break-all"><?= e($p['path']) ?></td>
              <td class="text-right"><?= number_format((int)$p['views']) ?></td>
              <td class="text-right text-beige-100/60"><?= number_format((int)$p['visitors']) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>

  <div class="border border-white/5 rounded-3xl bg-navy-900/40 p-6">
    <h2 class="font-serif text-xl text-gold-400">Top referrers</h2>
    <?php if (!$topRefs): ?>
      <p class="text-sm text-beige-100/60 mt-3">No external referrers yet.</p>
    <?php else: ?>
      <table class="mt-4 w-full text-sm">
        <thead class="text-left text-beige-100/50 text-xs uppercase tracking-wider">
          <tr><th class="py-2">Source</th><th class="text-right">Views</th><th class="text-right">Visitors</th></tr>
        </thead>
        <tbody class="divide-y divide-white/5">
          <?php foreach ($topRefs as $r): ?>
            <tr>
              <td class="py-2 text-beige-100/85"><?= e($r['host']) ?></td>
              <td class="text-right"><?= number_format((int)$r['views']) ?></td>
              <td class="text-right text-beige-100/60"><?= number_format((int)$r['visitors']) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>
</div>

<!-- UTM table -->
<div class="mt-6 border border-white/5 rounded-3xl bg-navy-900/40 p-6">
  <div class="flex items-center justify-between gap-4 flex-wrap">
    <h2 class="font-serif text-xl text-gold-400">UTM campaigns</h2>
    <p class="text-xs text-beige-100/50">Tag any landing URL with <code class="text-gold-400/80">?utm_source=ig&amp;utm_medium=story&amp;utm_campaign=launch</code> to see it here.</p>
  </div>
  <?php if (!$utm): ?>
    <p class="text-sm text-beige-100/60 mt-3">No tagged links visited yet.</p>
  <?php else: ?>
    <table class="mt-4 w-full text-sm">
      <thead class="text-left text-beige-100/50 text-xs uppercase tracking-wider">
        <tr><th class="py-2">Source</th><th>Medium</th><th>Campaign</th><th class="text-right">Views</th><th class="text-right">Visitors</th></tr>
      </thead>
      <tbody class="divide-y divide-white/5">
        <?php foreach ($utm as $u): ?>
          <tr>
            <td class="py-2 text-beige-100/85"><?= e($u['source']) ?></td>
            <td><?= e($u['medium']) ?></td>
            <td><?= e($u['campaign']) ?></td>
            <td class="text-right"><?= number_format((int)$u['views']) ?></td>
            <td class="text-right text-beige-100/60"><?= number_format((int)$u['visitors']) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>

<!-- Recent leads -->
<div class="mt-6 border border-white/5 rounded-3xl bg-navy-900/40 p-6">
  <div class="flex items-center justify-between gap-4 flex-wrap">
    <h2 class="font-serif text-xl text-gold-400">Recent leads</h2>
    <a href="<?= url('/admin/corporate_leads.php') ?>" class="text-xs text-gold-400/80 hover:text-gold-300">Open corporate inbox →</a>
  </div>
  <?php if (!$recentLeads): ?>
    <p class="text-sm text-beige-100/60 mt-3">No leads recorded yet.</p>
  <?php else: ?>
    <table class="mt-4 w-full text-sm">
      <thead class="text-left text-beige-100/50 text-xs uppercase tracking-wider">
        <tr><th class="py-2">When</th><th>Kind</th><th>Name</th><th>Email</th><th>Topic</th></tr>
      </thead>
      <tbody class="divide-y divide-white/5">
        <?php foreach ($recentLeads as $l): ?>
          <tr>
            <td class="py-2 text-beige-100/70"><?= e(format_datetime($l['created_at'], 'd M · g:i A')) ?></td>
            <td>
              <span class="text-[10px] uppercase tracking-widest px-2 py-0.5 rounded-full <?= $l['kind'] === 'corporate' ? 'bg-gold-500/20 text-gold-400' : 'bg-white/5 text-beige-100/70' ?>"><?= e($l['kind']) ?></span>
            </td>
            <td class="text-beige-100/85">
              <?= e($l['name']) ?><?php if (!empty($l['company'])): ?> <span class="text-beige-100/50">· <?= e($l['company']) ?></span><?php endif; ?>
            </td>
            <td class="text-beige-100/70"><?= e($l['email']) ?></td>
            <td class="text-beige-100/55"><?= e($l['topic'] ?? '—') ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>

<!-- Top referring members -->
<div class="mt-6 border border-white/5 rounded-3xl bg-navy-900/40 p-6">
  <h2 class="font-serif text-xl text-gold-400">Top referring members</h2>
  <?php if (!$topReferrers): ?>
    <p class="text-sm text-beige-100/60 mt-3">No referral sign-ups in this window.</p>
  <?php else: ?>
    <table class="mt-4 w-full text-sm">
      <thead class="text-left text-beige-100/50 text-xs uppercase tracking-wider">
        <tr><th class="py-2">Member</th><th>Email</th><th class="text-right">Friends joined</th></tr>
      </thead>
      <tbody class="divide-y divide-white/5">
        <?php foreach ($topReferrers as $tr): ?>
          <tr>
            <td class="py-2 text-beige-100/85"><?= e($tr['full_name']) ?></td>
            <td class="text-beige-100/65"><?= e($tr['email']) ?></td>
            <td class="text-right font-serif text-gold-400"><?= number_format((int)$tr['total']) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>

<?php require __DIR__ . '/../includes/admin_layout_end.php'; ?>
