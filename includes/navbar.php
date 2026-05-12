<?php
$user = current_user();
$brandName    = (string) setting('company_name',    (string) config('app.name'));
$brandTagline = (string) setting('company_tagline', (string) config('app.tagline'));
$nav = [
    ['Experiences', '/public/experiences.php'],
    ['Sessions',    '/public/events.php'],
    ['Membership',  '/public/membership.php'],
    ['About',       '/public/about.php'],
    ['Contact',     '/public/contact.php'],
];
?>
<header class="border-b border-white/5 bg-navy-950/80 backdrop-blur sticky top-0 z-40">
  <div class="max-w-6xl mx-auto px-4 py-4 flex items-center justify-between" x-data="{ open: false }">
    <a href="<?= url('/public/index.php') ?>" class="flex items-center gap-2">
      <span class="font-serif text-2xl text-gold-400 tracking-wide"><?= e($brandName) ?></span>
      <?php if ($brandTagline !== ''): ?>
        <span class="hidden sm:inline text-xs uppercase tracking-[0.3em] text-beige-200/70"><?= e($brandTagline) ?></span>
      <?php endif; ?>
    </a>

    <nav class="hidden md:flex items-center gap-8 text-sm">
      <?php foreach ($nav as [$label, $path]): ?>
        <a href="<?= url($path) ?>" class="text-beige-100/80 hover:text-gold-400 transition"><?= e($label) ?></a>
      <?php endforeach; ?>
    </nav>

    <div class="hidden md:flex items-center gap-3">
      <?php if ($user): ?>
        <?php if ($user['role'] === 'admin' || $user['role'] === 'staff'): ?>
          <a href="<?= url('/admin/dashboard.php') ?>" class="text-sm text-gold-400 hover:text-gold-300">Admin</a>
        <?php endif; ?>
        <a href="<?= url('/member/content.php') ?>" class="text-sm text-beige-100/80 hover:text-gold-400">Library</a>
        <a href="<?= url('/member/refer.php') ?>" class="text-sm text-beige-100/80 hover:text-gold-400">Refer</a>
        <a href="<?= url('/member/dashboard.php') ?>" class="text-sm text-beige-100/90 hover:text-gold-400">My Sanctuary</a>
        <form method="post" action="<?= url('/public/logout.php') ?>" class="inline">
          <?= csrf_field() ?>
          <button class="text-sm text-beige-100/60 hover:text-gold-400" type="submit">Sign out</button>
        </form>
      <?php else: ?>
        <a href="<?= url('/public/login.php') ?>" class="text-sm text-beige-100/80 hover:text-gold-400">Sign in</a>
        <a href="<?= url('/public/register.php') ?>" class="px-4 py-2 rounded-full bg-gold-500 text-navy-950 text-sm font-medium hover:bg-gold-400 transition">Begin journey</a>
      <?php endif; ?>
    </div>

    <button class="md:hidden text-beige-100" @click="open = !open" aria-label="Menu">
      <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 6h16M4 12h16M4 18h16"/></svg>
    </button>

    <div x-show="open" x-transition class="md:hidden absolute top-full left-0 right-0 bg-navy-900 border-b border-white/5" style="display: none;">
      <div class="px-6 py-4 flex flex-col gap-4">
        <?php foreach ($nav as [$label, $path]): ?>
          <a href="<?= url($path) ?>" class="text-beige-100/90"><?= e($label) ?></a>
        <?php endforeach; ?>
        <?php if ($user): ?>
          <a href="<?= url('/member/dashboard.php') ?>" class="text-gold-400">My Sanctuary</a>
          <?php if (in_array($user['role'], ['admin','staff'], true)): ?>
            <a href="<?= url('/admin/dashboard.php') ?>" class="text-gold-400">Admin</a>
          <?php endif; ?>
          <form method="post" action="<?= url('/public/logout.php') ?>"><?= csrf_field() ?><button class="text-beige-100/70" type="submit">Sign out</button></form>
        <?php else: ?>
          <a href="<?= url('/public/login.php') ?>">Sign in</a>
          <a href="<?= url('/public/register.php') ?>" class="text-gold-400">Begin journey</a>
        <?php endif; ?>
      </div>
    </div>
  </div>
</header>
