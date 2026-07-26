<?php
$user = current_user();
$brandName    = brand_name();
$brandTagline = (string) setting('company_tagline', (string) config('app.tagline'));
$nav = [
    ['Experiences', '/public/experiences.php'],
    ['Sessions',    '/public/events.php'],
    ['Shop',        '/public/shop.php'],
    ['Gallery',     '/public/gallery.php'],
    ['Partners',    '/public/partners.php'],
    ['Membership',  '/public/membership.php'],
    ['About',       '/public/about.php'],
    ['Contact',     '/public/contact.php'],
];
$cartQty = function_exists('cart_count') ? cart_count() : 0;
$firstName = $user ? trim(explode(' ', (string) $user['full_name'])[0]) : '';
?>
<header class="border-b border-white/5 bg-navy-950/80 backdrop-blur sticky top-0 z-40">
  <div class="max-w-7xl mx-auto px-4 py-4 flex items-center justify-between gap-4" x-data="{ open: false, account: false }">
    <!-- Brand -->
    <a href="<?= url('/public/index.php') ?>" class="flex items-center gap-3 min-w-0 shrink-0">
      <span class="font-serif text-2xl text-gold-400 tracking-wide whitespace-nowrap"><?= e($brandName) ?></span>
      <?php if ($brandTagline !== ''): ?>
        <span class="hidden 2xl:inline text-[10px] uppercase tracking-[0.3em] text-beige-200/60 whitespace-nowrap"><?= e($brandTagline) ?></span>
      <?php endif; ?>
    </a>

    <!-- Primary nav (lg and up) -->
    <nav class="hidden lg:flex items-center gap-5 xl:gap-7 text-sm">
      <?php foreach ($nav as [$label, $path]): ?>
        <a href="<?= url($path) ?>" class="text-beige-100/80 hover:text-gold-400 transition whitespace-nowrap"><?= e($label) ?></a>
      <?php endforeach; ?>
    </nav>

    <!-- Account / CTA (lg and up) -->
    <div class="hidden lg:flex items-center gap-2 shrink-0">
      <!-- Cart chip: always visible on desktop nav, becomes gold when non-empty -->
      <a href="<?= url('/public/cart.php') ?>" aria-label="Cart"
         class="relative p-2 rounded-full text-beige-100/70 hover:text-gold-400 hover:bg-white/5 transition">
        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
          <path d="M3 3h2l2.4 12.2A2 2 0 0 0 9.36 17H19a2 2 0 0 0 1.94-1.5L22.5 8H6"/>
          <circle cx="10" cy="20" r="1.2"/><circle cx="18" cy="20" r="1.2"/>
        </svg>
        <?php if ($cartQty > 0): ?>
          <span class="absolute -top-0.5 -right-0.5 min-w-[18px] h-[18px] px-1 rounded-full bg-gold-500 text-navy-950 text-[10px] font-medium flex items-center justify-center"><?= (int)$cartQty ?></span>
        <?php endif; ?>
      </a>
      <!-- Theme toggle — one-tap flip between dark and light. POSTs
           the opposite theme; the server sets sh-theme cookie and
           redirects back to the current URL so the next render picks
           it up. -->
      <?php
        $currentTheme = ($_COOKIE['sh-theme'] ?? '') !== ''
            ? (string) $_COOKIE['sh-theme']
            : (string) setting('site_theme', 'dark');
        if (!in_array($currentTheme, ['dark','light'], true)) $currentTheme = 'dark';
        $flipTo = $currentTheme === 'dark' ? 'light' : 'dark';
        $currentPath = (string) ($_SERVER['REQUEST_URI'] ?? '/');
      ?>
      <form method="post" action="<?= url('/public/theme.php') ?>" class="inline">
        <?= csrf_field() ?>
        <input type="hidden" name="theme" value="<?= e($flipTo) ?>">
        <input type="hidden" name="next"  value="<?= e($currentPath) ?>">
        <button type="submit" title="Switch to <?= e($flipTo) ?> theme"
                aria-label="Switch to <?= e($flipTo) ?> theme"
                class="p-2 rounded-full text-beige-100/70 hover:text-gold-400 hover:bg-white/5 transition">
          <?php if ($currentTheme === 'dark'): // show sun (offers switch to light) ?>
            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
              <circle cx="12" cy="12" r="4"/>
              <path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/>
            </svg>
          <?php else: // show moon (offers switch to dark) ?>
            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
              <path d="M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8z"/>
            </svg>
          <?php endif; ?>
        </button>
      </form>
      <?php if ($user): ?>
        <div class="relative" @click.outside="account = false">
          <button type="button" @click="account = !account"
                  class="flex items-center gap-2 px-3 py-1.5 rounded-full border border-white/10 text-sm text-beige-100/85 hover:border-gold-500/40 hover:text-gold-400 transition">
            <span class="h-6 w-6 rounded-full bg-gold-500/20 text-gold-400 flex items-center justify-center text-[11px] uppercase"><?= e(substr($firstName ?: 'A', 0, 1)) ?></span>
            <span class="whitespace-nowrap"><?= e($firstName ?: 'Account') ?></span>
            <svg class="h-3 w-3 text-beige-100/50" viewBox="0 0 12 12" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M3 4.5l3 3 3-3"/></svg>
          </button>

          <div x-show="account" x-cloak x-transition.opacity
               class="absolute right-0 mt-2 w-56 rounded-2xl border border-white/10 bg-navy-900/95 backdrop-blur p-2 shadow-[0_20px_60px_-20px_rgba(0,0,0,0.6)]">
            <a href="<?= url('/member/dashboard.php') ?>"      class="block px-3 py-2 rounded-lg text-sm text-beige-100/90 hover:bg-gold-500/10 hover:text-gold-400">My Sanctuary</a>
            <a href="<?= url('/member/content.php') ?>"        class="block px-3 py-2 rounded-lg text-sm text-beige-100/90 hover:bg-gold-500/10 hover:text-gold-400">Audio library</a>
            <a href="<?= url('/member/my_bookings.php') ?>"    class="block px-3 py-2 rounded-lg text-sm text-beige-100/90 hover:bg-gold-500/10 hover:text-gold-400">My bookings</a>
            <a href="<?= url('/member/my_orders.php') ?>"      class="block px-3 py-2 rounded-lg text-sm text-beige-100/90 hover:bg-gold-500/10 hover:text-gold-400">My orders</a>
            <a href="<?= url('/member/my_credits.php') ?>"     class="block px-3 py-2 rounded-lg text-sm text-beige-100/90 hover:bg-gold-500/10 hover:text-gold-400">Class packs</a>
            <a href="<?= url('/member/invoices.php') ?>"       class="block px-3 py-2 rounded-lg text-sm text-beige-100/90 hover:bg-gold-500/10 hover:text-gold-400">Invoices &amp; receipts</a>
            <a href="<?= url('/member/refer.php') ?>"          class="block px-3 py-2 rounded-lg text-sm text-beige-100/90 hover:bg-gold-500/10 hover:text-gold-400">Refer a friend</a>
            <a href="<?= url('/member/profile.php') ?>"        class="block px-3 py-2 rounded-lg text-sm text-beige-100/90 hover:bg-gold-500/10 hover:text-gold-400">Profile</a>
            <?php if (in_array($user['role'], ['admin','staff'], true)): ?>
              <div class="my-1 border-t border-white/5"></div>
              <a href="<?= url('/admin/dashboard.php') ?>" class="block px-3 py-2 rounded-lg text-sm text-gold-400 hover:bg-gold-500/10">Admin →</a>
            <?php endif; ?>
            <div class="my-1 border-t border-white/5"></div>
            <form method="post" action="<?= url('/public/logout.php') ?>">
              <?= csrf_field() ?>
              <button class="block w-full text-left px-3 py-2 rounded-lg text-sm text-beige-100/65 hover:bg-red-500/10 hover:text-red-300" type="submit">Sign out</button>
            </form>
          </div>
        </div>
      <?php else: ?>
        <a href="<?= url('/public/login.php') ?>"    class="text-sm text-beige-100/80 hover:text-gold-400 whitespace-nowrap px-2">Sign in</a>
        <a href="<?= url('/public/register.php') ?>" class="px-3.5 py-2 rounded-full bg-gold-500 text-navy-950 text-sm font-medium hover:bg-gold-400 transition whitespace-nowrap">Begin journey</a>
      <?php endif; ?>
    </div>

    <!-- Compact toggles for md / below-lg: keep the cart reachable and
         show the hamburger for the full nav. Hidden once the desktop
         row can fit at lg. -->
    <div class="flex lg:hidden items-center gap-1">
      <a href="<?= url('/public/cart.php') ?>" aria-label="Cart"
         class="relative p-2 rounded-full text-beige-100/80 hover:text-gold-400 hover:bg-white/5 transition">
        <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
          <path d="M3 3h2l2.4 12.2A2 2 0 0 0 9.36 17H19a2 2 0 0 0 1.94-1.5L22.5 8H6"/>
          <circle cx="10" cy="20" r="1.2"/><circle cx="18" cy="20" r="1.2"/>
        </svg>
        <?php if ($cartQty > 0): ?>
          <span class="absolute -top-0.5 -right-0.5 min-w-[18px] h-[18px] px-1 rounded-full bg-gold-500 text-navy-950 text-[10px] font-medium flex items-center justify-center"><?= (int)$cartQty ?></span>
        <?php endif; ?>
      </a>
      <button class="text-beige-100 -mr-2 p-2" @click="open = !open" aria-label="Menu">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 6h16M4 12h16M4 18h16"/></svg>
      </button>
    </div>

    <!-- Mobile menu -->
    <div x-show="open" x-cloak x-transition class="lg:hidden absolute top-full left-0 right-0 bg-navy-900 border-b border-white/5">
      <div class="px-6 py-5 flex flex-col gap-4">
        <?php foreach ($nav as [$label, $path]): ?>
          <a href="<?= url($path) ?>" class="text-beige-100/90"><?= e($label) ?></a>
        <?php endforeach; ?>
        <a href="<?= url('/public/cart.php') ?>" class="text-beige-100/90 flex items-center gap-2">
          Cart <?php if ($cartQty > 0): ?><span class="min-w-[18px] h-[18px] px-1 rounded-full bg-gold-500 text-navy-950 text-[10px] font-medium flex items-center justify-center"><?= (int)$cartQty ?></span><?php endif; ?>
        </a>
        <div class="border-t border-white/5 pt-4 flex flex-col gap-4">
          <?php if ($user): ?>
            <a href="<?= url('/member/dashboard.php') ?>"   class="text-gold-400">My Sanctuary</a>
            <a href="<?= url('/member/content.php') ?>"    class="text-beige-100/90">Audio library</a>
            <a href="<?= url('/member/my_bookings.php') ?>" class="text-beige-100/90">My bookings</a>
            <a href="<?= url('/member/my_orders.php') ?>"   class="text-beige-100/90">My orders</a>
            <a href="<?= url('/member/my_credits.php') ?>"  class="text-beige-100/90">Class packs</a>
            <a href="<?= url('/member/invoices.php') ?>"    class="text-beige-100/90">Invoices &amp; receipts</a>
            <a href="<?= url('/member/refer.php') ?>"      class="text-beige-100/90">Refer a friend</a>
            <a href="<?= url('/member/profile.php') ?>"    class="text-beige-100/90">Profile</a>
            <?php if (in_array($user['role'], ['admin','staff'], true)): ?>
              <a href="<?= url('/admin/dashboard.php') ?>" class="text-gold-400">Admin →</a>
            <?php endif; ?>
            <form method="post" action="<?= url('/public/logout.php') ?>">
              <?= csrf_field() ?>
              <button class="text-beige-100/65 text-left" type="submit">Sign out</button>
            </form>
          <?php else: ?>
            <a href="<?= url('/public/login.php') ?>"    class="text-beige-100/90">Sign in</a>
            <a href="<?= url('/public/register.php') ?>" class="text-gold-400">Begin journey</a>
          <?php endif; ?>

          <!-- Mobile theme toggle. Same POST as the desktop button. -->
          <form method="post" action="<?= url('/public/theme.php') ?>" class="pt-2 border-t border-white/5">
            <?= csrf_field() ?>
            <input type="hidden" name="theme" value="<?= e($flipTo) ?>">
            <input type="hidden" name="next"  value="<?= e($currentPath) ?>">
            <button type="submit" class="text-beige-100/85 text-left flex items-center gap-2">
              <?php if ($currentTheme === 'dark'): ?>
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/></svg>
                Switch to light theme
              <?php else: ?>
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8z"/></svg>
                Switch to dark theme
              <?php endif; ?>
            </button>
          </form>
        </div>
      </div>
    </div>
  </div>
</header>
