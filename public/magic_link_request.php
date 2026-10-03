<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$pageTitle = 'Sign in with a link';

$sent  = false;
$error = null;

if (is_post()) {
    csrf_verify();
    $email  = (string) input('email', '');
    $result = magic_link_issue($email);
    if ($result['ok'] === false) {
        $error = $result['error'] ?? 'Please try again.';
    } else {
        // Same response whether or not the email exists — do NOT
        // disclose account existence here.
        $sent = true;
    }
}

require __DIR__ . '/../includes/header.php';
?>
<section class="max-w-md mx-auto px-6 py-24">
  <?php if ($sent): ?>
    <p class="text-gold-400/80 tracking-[0.3em] uppercase text-xs text-center">Check your inbox</p>
    <h1 class="font-serif text-4xl text-beige-100 mt-4 text-center">A link is on its way</h1>
    <p class="mt-5 text-center text-beige-100/70 leading-relaxed">
      If we have you on file — or you'd like to join us — tap the link we just emailed.
      It works once and expires in 30 minutes.
    </p>
    <div class="mt-10 border border-white/5 rounded-3xl p-6 bg-navy-900/40 text-sm text-beige-100/70 leading-relaxed">
      <p>Nothing arriving? A few quiet checks:</p>
      <ul class="mt-3 list-disc list-inside space-y-1">
        <li>Peek in your spam / promotions folder.</li>
        <li>Give it a minute — mail providers sometimes hold on to it.</li>
        <li>Re-check the address for typos, then <a href="<?= url('/public/magic_link_request.php') ?>" class="text-gold-400 hover:text-gold-300">try again</a>.</li>
      </ul>
    </div>
    <p class="mt-8 text-center text-sm text-beige-100/60">
      Prefer a password? <a href="<?= url('/public/login.php') ?>" class="text-gold-400 hover:text-gold-300">Sign in the usual way</a>.
    </p>
  <?php else: ?>
    <p class="text-gold-400/80 tracking-[0.3em] uppercase text-xs text-center">Sign in gently</p>
    <h1 class="font-serif text-4xl text-beige-100 mt-4 text-center">One tap, no password</h1>
    <p class="mt-3 text-center text-beige-100/65 text-sm">Type your email — we'll send a link that opens your sanctuary. Works for new members too.</p>

    <?php if ($error): ?>
      <p class="mt-6 text-red-300/80 text-center"><?= e($error) ?></p>
    <?php endif; ?>

    <form method="post" class="mt-10 space-y-5">
      <?= csrf_field() ?>
      <label class="block">
        <span class="text-xs uppercase tracking-widest text-beige-100/60">Email</span>
        <input name="email" type="email" required autofocus autocomplete="email"
               class="mt-2 w-full rounded-2xl bg-navy-900 border border-white/5 px-4 py-3 focus:border-gold-500/50 focus:outline-none">
      </label>
      <button class="w-full px-5 py-3 rounded-full bg-gold-500 text-navy-950 font-medium hover:bg-gold-400 transition">
        Send my sanctuary link
      </button>
    </form>

    <div class="my-6 flex items-center gap-3 text-[10px] uppercase tracking-[0.3em] text-beige-100/40">
      <span class="flex-1 h-px bg-white/10"></span>
      <span>or</span>
      <span class="flex-1 h-px bg-white/10"></span>
    </div>

    <div class="text-center text-sm text-beige-100/65 space-y-2">
      <?php if (function_exists('oauth_google_ready') && oauth_google_ready()): ?>
        <p><a href="<?= url('/api/oauth_google_start.php') ?>" class="text-gold-400 hover:text-gold-300">Continue with Google</a></p>
      <?php endif; ?>
      <p><a href="<?= url('/public/login.php') ?>" class="text-beige-100/70 hover:text-gold-400">Sign in with email &amp; password</a></p>
    </div>
  <?php endif; ?>
</section>
<?php require __DIR__ . '/../includes/footer.php'; ?>
