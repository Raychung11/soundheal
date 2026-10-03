<?php
/**
 * Mobile bottom tab bar — an app-style fixed nav for small screens only.
 *
 *   Rendered from includes/footer.php. Hidden on md+ (the top navbar
 *   takes over there). Context-aware:
 *     - logged in  → Home · Sessions · Credits · Bookings · Account
 *     - guest      → Home · Sessions · Membership · Sign in
 *
 *   A page can opt out with $skipMobileNav = true before the header
 *   include (e.g. full-screen flows).
 */

if (isset($skipMobileNav) && $skipMobileNav) return;

$mn_script = (string) ($_SERVER['SCRIPT_NAME'] ?? '');
if (str_contains($mn_script, '/admin/')) return;

$mn_user = current_user();

// Live credits balance for the Credits tab badge (members only).
$mn_creditsBadge = 0;
if ($mn_user && function_exists('credit_balance_for')) {
    $mn_creditsBadge = (int) credit_balance_for((int) $mn_user['id']);
}

if ($mn_user) {
    $mn_tabs = [
        ['Home',     '/member/dashboard.php',   'home'],
        ['Sessions', '/public/events.php',      'calendar'],
        ['Credits',  '/member/my_credits.php',  'spark'],
        ['Bookings', '/member/my_bookings.php', 'ticket'],
        ['Account',  '/member/profile.php',     'user'],
    ];
} else {
    $mn_tabs = [
        ['Home',       '/public/index.php',       'home'],
        ['Sessions',   '/public/events.php',      'calendar'],
        ['Membership', '/public/membership.php',  'spark'],
        ['Sign in',    '/public/login.php',       'user'],
    ];
}

$mn_icon = static function (string $name): string {
    $paths = [
        'home'     => '<path d="M3 10.5 12 4l9 6.5"/><path d="M5 9.5V20h14V9.5"/>',
        'calendar' => '<rect x="3.5" y="5" width="17" height="15" rx="2"/><path d="M3.5 9.5h17M8 3.5v3M16 3.5v3"/>',
        'spark'    => '<path d="M12 3.5 13.8 9 19.5 10 13.8 11 12 16.5 10.2 11 4.5 10 10.2 9 12 3.5Z"/>',
        'ticket'   => '<path d="M4 8a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2 2 2 0 0 0 0 4 2 2 0 0 1-2 2H6a2 2 0 0 1-2-2 2 2 0 0 0 0-4Z"/><path d="M14 6.5v11"/>',
        'user'     => '<circle cx="12" cy="8.5" r="3.5"/><path d="M5.5 19.5a6.5 6.5 0 0 1 13 0"/>',
    ];
    return $paths[$name] ?? $paths['home'];
};
?>
<nav class="md:hidden fixed inset-x-0 bottom-0 z-40 bg-navy-950/95 backdrop-blur border-t border-white/10"
     style="padding-bottom: env(safe-area-inset-bottom);" aria-label="Primary">
  <div class="flex">
    <?php foreach ($mn_tabs as [$mn_label, $mn_path, $mn_ic]):
      $mn_active = $mn_script === $mn_path || str_ends_with($mn_script, $mn_path);
    ?>
      <a href="<?= url($mn_path) ?>"
         class="flex-1 flex flex-col items-center gap-1 py-2 text-[10px] tracking-wide transition
                <?= $mn_active ? 'text-gold-400' : 'text-beige-100/55 hover:text-gold-400' ?>"
         <?= $mn_active ? 'aria-current="page"' : '' ?>>
        <span class="relative flex h-8 w-14 items-center justify-center rounded-full transition <?= $mn_active ? 'bg-gold-500/15' : '' ?>">
          <svg class="h-[22px] w-[22px]" viewBox="0 0 24 24" fill="none" stroke="currentColor"
               stroke-width="<?= $mn_active ? '1.85' : '1.55' ?>" stroke-linecap="round" stroke-linejoin="round"><?= $mn_icon($mn_ic) ?></svg>
          <?php if ($mn_label === 'Credits' && $mn_creditsBadge > 0): ?>
            <span class="absolute top-0 right-2 min-w-[16px] h-[16px] px-1 rounded-full bg-gold-500 text-navy-950 text-[10px] font-semibold leading-[16px] text-center"><?= $mn_creditsBadge ?></span>
          <?php endif; ?>
        </span>
        <span><?= e($mn_label) ?></span>
      </a>
    <?php endforeach; ?>
  </div>
</nav>
<!-- Spacer so the fixed bar never hides the last of the footer on mobile -->
<div class="md:hidden h-16" aria-hidden="true"></div>
