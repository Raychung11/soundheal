<?php
/**
 * Admin layout helper. Renders sidebar + page header.
 * Include after bootstrap and require_staff_or_admin().
 */
$adminNav = [
    ['Dashboard',          '/admin/dashboard.php'],
    ['Home page',          '/admin/home_settings.php'],
    ['About page',         '/admin/about_settings.php'],
    ['Footer',             '/admin/footer_settings.php'],
    ['Legal pages',        '/admin/legal_settings.php'],
    ['Experiences',        '/admin/experiences.php'],
    ['Events',             '/admin/events.php'],
    ['Class packs',        '/admin/class_packs.php'],
    ['Bookings',           '/admin/bookings.php'],
    ['Members',            '/admin/members.php'],
    ['Payments',           '/admin/payments.php'],
    ['Invoices',           '/admin/invoices.php'],
    ['Payment settings',   '/admin/payment_settings.php'],
    ['Mail settings',      '/admin/mail_settings.php'],
    ['Aria (AI)',          '/admin/ai_settings.php'],
    ['Check-in',           '/admin/checkin.php'],
    ['Content',            '/admin/content.php'],
    ['Testimonials',       '/admin/testimonials.php'],
    ['Marketing',          '/admin/marketing.php'],
    ['Referral program',   '/admin/referral_settings.php'],
    ['Corporate leads',    '/admin/corporate_leads.php'],
    ['Reports',            '/admin/reports.php'],
];
$pageTitle = ($pageTitle ?? 'Admin') . ' · Admin';
$skipTracking = true;

$adminCurrent = 'Admin menu';
foreach ($adminNav as [$label, $path]) {
    if (($_SERVER['SCRIPT_NAME'] ?? '') === $path || str_ends_with($_SERVER['SCRIPT_NAME'] ?? '', $path)) {
        $adminCurrent = $label;
        break;
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

      <nav id="admin-nav" class="mt-3 flex-col" :class="navOpen ? 'flex' : 'hidden md:flex'">
        <?php foreach ($adminNav as [$label, $path]):
          $active = ($_SERVER['SCRIPT_NAME'] ?? '') === $path || str_ends_with($_SERVER['SCRIPT_NAME'] ?? '', $path);
        ?>
          <a href="<?= url($path) ?>" class="px-2 py-2 rounded-lg text-sm <?= $active ? 'text-gold-400 bg-gold-500/10' : 'text-beige-100/70 hover:text-gold-400' ?>"><?= e($label) ?></a>
        <?php endforeach; ?>
      </nav>
    </div>
  </aside>
  <div>
