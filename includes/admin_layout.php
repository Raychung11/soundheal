<?php
/**
 * Admin layout helper. Renders sidebar + page header.
 * Include after bootstrap and require_staff_or_admin().
 */
// Sidebar sections. Keys are the visible group headers; the special
// '' key is rendered without a header so Dashboard sits alone at the
// top. Reordering here reorders the sidebar.
$adminNav = [
    '' => [
        ['Dashboard',          '/admin/dashboard.php'],
    ],
    'Programme' => [
        ['Experiences',        '/admin/experiences.php'],
        ['Events',             '/admin/events.php'],
        ['Class packs',        '/admin/class_packs.php'],
        ['Content',            '/admin/content.php'],
        ['Testimonials',       '/admin/testimonials.php'],
    ],
    'Operations' => [
        ['Bookings',           '/admin/bookings.php'],
        ['Check-in',           '/admin/checkin.php'],
        ['Members',            '/admin/members.php'],
        ['Marketing',          '/admin/marketing.php'],
    ],
    'Money' => [
        ['Payments',           '/admin/payments.php'],
        ['Invoices',           '/admin/invoices.php'],
        ['Revenue split',      '/admin/revenue_splits.php'],
        ['Referral rewards',   '/admin/referral_rewards.php'],
        ['Session P&L',        '/admin/session_pnl.php'],
        ['Reports',            '/admin/reports.php'],
    ],
    'Growth' => [
        ['Promo codes',        '/admin/promo_codes.php'],
        ['Gift vouchers',      '/admin/gift_vouchers.php'],
        ['Referral program',   '/admin/referral_settings.php'],
        ['Partners (QR)',      '/admin/partners.php'],
        ['Corporate packages', '/admin/corporate_packages.php'],
        ['Corporate leads',    '/admin/corporate_leads.php'],
    ],
    'Site pages' => [
        ['Home page',          '/admin/home_settings.php'],
        ['About page',         '/admin/about_settings.php'],
        ['Footer',             '/admin/footer_settings.php'],
        ['Legal pages',        '/admin/legal_settings.php'],
    ],
    'Settings' => [
        ['Payment settings',   '/admin/payment_settings.php'],
        ['Mail settings',      '/admin/mail_settings.php'],
        ['Aria (AI)',          '/admin/ai_settings.php'],
    ],
];
$pageTitle = ($pageTitle ?? 'Admin') . ' · Admin';
$skipTracking = true;

// Resolve the current page label (for the mobile-nav header).
$adminCurrent = 'Admin menu';
foreach ($adminNav as $items) {
    foreach ($items as [$label, $path]) {
        if (($_SERVER['SCRIPT_NAME'] ?? '') === $path || str_ends_with($_SERVER['SCRIPT_NAME'] ?? '', $path)) {
            $adminCurrent = $label;
            break 2;
        }
    }
}

require __DIR__ . '/header.php';
?>
<div class="max-w-7xl mx-auto px-4 py-8 grid md:grid-cols-[220px_1fr] gap-8">
  <aside class="md:sticky md:top-24 self-start" x-data="{ navOpen: false }">
    <div class="border border-white/5 rounded-2xl bg-navy-900/40 p-4">
      <!-- Mobile: collapsed hamburger showing the current page -->
      <button type="button" @click="navOpen = !navOpen"
              class="md:hidden w-full flex items-center justify-between gap-3 px-2 py-1.5 text-left"
              :aria-expanded="navOpen.toString()" aria-controls="admin-nav">
        <span class="flex flex-col">
          <span class="text-[10px] uppercase tracking-widest text-gold-400/80">Admin</span>
          <span class="text-sm text-beige-100 mt-0.5"><?= e($adminCurrent) ?></span>
        </span>
        <svg class="h-5 w-5 text-beige-100/70 transition-transform" :class="navOpen && 'rotate-90'"
             viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
          <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16"/>
        </svg>
      </button>

      <p class="hidden md:block text-xs uppercase tracking-widest text-gold-400/80 px-2">Admin</p>

      <nav id="admin-nav" class="mt-3 flex-col gap-1" :class="navOpen ? 'flex' : 'hidden md:flex'">
        <?php foreach ($adminNav as $group => $items): ?>
          <?php if ($group !== ''): ?>
            <p class="mt-4 first:mt-2 px-2 pb-1 text-[10px] uppercase tracking-[0.25em] text-beige-100/40"><?= e($group) ?></p>
          <?php endif; ?>
          <?php foreach ($items as [$label, $path]):
            $active = ($_SERVER['SCRIPT_NAME'] ?? '') === $path || str_ends_with($_SERVER['SCRIPT_NAME'] ?? '', $path);
          ?>
            <a href="<?= url($path) ?>" class="px-2 py-1.5 rounded-lg text-sm <?= $active ? 'text-gold-400 bg-gold-500/10' : 'text-beige-100/70 hover:text-gold-400' ?>"><?= e($label) ?></a>
          <?php endforeach; ?>
        <?php endforeach; ?>
      </nav>
    </div>
  </aside>
  <div>
