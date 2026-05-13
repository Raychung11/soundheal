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
    ['Events',             '/admin/events.php'],
    ['Bookings',           '/admin/bookings.php'],
    ['Members',            '/admin/members.php'],
    ['Payments',           '/admin/payments.php'],
    ['Payment settings',   '/admin/payment_settings.php'],
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
require __DIR__ . '/header.php';
?>
<div class="max-w-7xl mx-auto px-4 py-8 grid md:grid-cols-[220px_1fr] gap-8">
  <aside class="md:sticky md:top-24 self-start">
    <div class="border border-white/5 rounded-2xl bg-navy-900/40 p-4">
      <p class="text-xs uppercase tracking-widest text-gold-400/80 px-2">Admin</p>
      <nav class="mt-3 flex flex-col">
        <?php foreach ($adminNav as [$label, $path]):
          $active = ($_SERVER['SCRIPT_NAME'] ?? '') === $path || str_ends_with($_SERVER['SCRIPT_NAME'] ?? '', $path);
        ?>
          <a href="<?= url($path) ?>" class="px-2 py-2 rounded-lg text-sm <?= $active ? 'text-gold-400 bg-gold-500/10' : 'text-beige-100/70 hover:text-gold-400' ?>"><?= e($label) ?></a>
        <?php endforeach; ?>
      </nav>
    </div>
  </aside>
  <div>
