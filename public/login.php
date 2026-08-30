<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$pageTitle = 'Sign in';
$error = null;

if (is_post()) {
    csrf_verify();
    $email = filter_var(input('email', ''), FILTER_VALIDATE_EMAIL);
    $pass  = (string) input('password', '');

    $throttleKey = 'login:' . client_ip() . ':' . strtolower((string) $email);
    if (!$email || $pass === '') {
        $error = 'Please enter a valid email and password.';
    } elseif (!throttle($throttleKey, 5, 300)) {
        $error = 'Too many attempts. Take a slow breath and try again in five minutes.';
        audit_log('login.throttled', 'users', null, ['email' => $email]);
    } elseif (!attempt_login($email, $pass)) {
        $error = 'Those details did not match. Please try again.';
        audit_log('login.fail', 'users', null, ['email' => $email]);
    } else {
        throttle_reset($throttleKey);
        $intended = $_SESSION['_intended'] ?? null;
        unset($_SESSION['_intended']);
        // Only honour a local path — never an absolute or protocol-relative
        // URL — so a crafted login link can't bounce users off-site.
        if (is_string($intended) && isset($intended[0]) && $intended[0] === '/'
            && !str_starts_with($intended, '//') && !str_starts_with($intended, '/\\')
        ) {
            redirect($intended);
        }
        redirect(has_role('admin','staff') ? '/admin/dashboard.php' : '/member/dashboard.php');
    }
}

require __DIR__ . '/../includes/header.php';
?>
<section class="max-w-md mx-auto px-6 py-24">
  <p class="text-gold-400/80 tracking-[0.3em] uppercase text-xs text-center">Sign in</p>
  <h1 class="font-serif text-4xl text-beige-100 mt-4 text-center">Welcome back</h1>
  <p class="mt-3 text-center text-beige-100/60 text-sm">Continue your wellness journey.</p>

  <?php if ($error): ?>
    <p class="mt-6 text-red-300/80 text-center"><?= e($error) ?></p>
  <?php endif; ?>

  <?php
    // Auth flashes routed here from the OAuth callback surface any
    // provider errors ("Google sign-in was cancelled", etc.) with the
    // same warm tone as the rest of the page.
    $authFlash = flash('auth');
  ?>
  <?php if ($authFlash): ?>
    <div class="mt-6 border rounded-2xl px-5 py-3 text-sm text-center
                <?= ($authFlash['type'] ?? 'info') === 'error'
                    ? 'border-red-400/40 bg-red-500/5 text-red-200'
                    : 'border-white/10 bg-navy-900/40 text-beige-100/85' ?>">
      <?= e($authFlash['message'] ?? '') ?>
    </div>
  <?php endif; ?>

  <?php if (function_exists('oauth_google_ready') && oauth_google_ready()):
    // Forward the visitor's intended landing page through the OAuth
    // start so a callback lands them back where they were headed.
    $intended = $_SESSION['_intended'] ?? '';
    $nextParam = (is_string($intended) && $intended !== '' && $intended[0] === '/'
                  && !str_starts_with($intended, '//')) ? ('?next=' . urlencode($intended)) : '';
  ?>
    <a href="<?= url('/api/oauth_google_start.php' . $nextParam) ?>"
       class="mt-10 flex items-center justify-center gap-3 w-full px-5 py-3 rounded-full border border-white/10 bg-white text-navy-950 hover:bg-white/95 transition text-sm font-medium">
      <svg class="h-5 w-5" viewBox="0 0 48 48">
        <path fill="#4285F4" d="M45.12 24.5c0-1.56-.14-3.06-.4-4.5H24v8.51h11.84c-.51 2.75-2.06 5.08-4.39 6.64v5.52h7.11c4.16-3.83 6.56-9.47 6.56-16.17z"/>
        <path fill="#34A853" d="M24 46c5.94 0 10.92-1.97 14.56-5.33l-7.11-5.52c-1.97 1.32-4.49 2.1-7.45 2.1-5.73 0-10.58-3.87-12.31-9.07H4.34v5.7C7.96 41.07 15.4 46 24 46z"/>
        <path fill="#FBBC05" d="M11.69 28.18c-.44-1.32-.69-2.73-.69-4.18s.25-2.86.69-4.18v-5.7H4.34C2.86 17.09 2 20.45 2 24s.86 6.91 2.34 9.88l7.35-5.7z"/>
        <path fill="#EA4335" d="M24 10.75c3.23 0 6.13 1.11 8.42 3.29l6.31-6.31C34.91 4.18 29.93 2 24 2 15.4 2 7.96 6.93 4.34 14.12l7.35 5.7C13.42 14.62 18.27 10.75 24 10.75z"/>
      </svg>
      Continue with Google
    </a>
    <div class="my-6 flex items-center gap-3 text-[10px] uppercase tracking-[0.3em] text-beige-100/40">
      <span class="flex-1 h-px bg-white/10"></span>
      <span>or</span>
      <span class="flex-1 h-px bg-white/10"></span>
    </div>
  <?php endif; ?>

  <!-- Magic-link primary CTA: no password, no signup form — one field
       and we email a sign-in link. Sits above the traditional
       email/password form so it's the visible default path. -->
  <a href="<?= url('/public/magic_link_request.php') ?>"
     class="<?= (function_exists('oauth_google_ready') && oauth_google_ready()) ? '' : 'mt-10 ' ?>flex items-center justify-center gap-2 w-full px-5 py-3 rounded-full bg-gold-500 text-navy-950 hover:bg-gold-400 transition text-sm font-medium">
    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round">
      <rect x="3" y="5" width="18" height="14" rx="2"/><path d="m4 7 8 6 8-6"/>
    </svg>
    Send me a sign-in link
  </a>

  <div class="my-6 flex items-center gap-3 text-[10px] uppercase tracking-[0.3em] text-beige-100/40">
    <span class="flex-1 h-px bg-white/10"></span>
    <span>or password</span>
    <span class="flex-1 h-px bg-white/10"></span>
  </div>

  <form method="post" class="space-y-5" x-data="{ show: false }">
    <?= csrf_field() ?>
    <label class="block">
      <span class="text-xs uppercase tracking-widest text-beige-100/60">Email</span>
      <input name="email" type="email" required autofocus autocomplete="email" class="mt-2 w-full rounded-2xl bg-navy-900 border border-white/5 px-4 py-3 focus:border-gold-500/50 focus:outline-none">
    </label>
    <label class="block">
      <span class="text-xs uppercase tracking-widest text-beige-100/60">Password</span>
      <div class="relative mt-2">
        <input name="password" :type="show ? 'text' : 'password'" required autocomplete="current-password"
               class="w-full rounded-2xl bg-navy-900 border border-white/5 px-4 py-3 pr-16 focus:border-gold-500/50 focus:outline-none">
        <button type="button" @click="show = !show"
                class="absolute inset-y-0 right-0 px-4 text-beige-100/45 hover:text-gold-400 text-xs uppercase tracking-widest"
                :aria-label="show ? 'Hide password' : 'Show password'"
                x-text="show ? 'Hide' : 'Show'"></button>
      </div>
    </label>
    <button class="w-full px-5 py-3 rounded-full bg-gold-500 text-navy-950 font-medium hover:bg-gold-400 transition">Enter</button>
  </form>

  <div class="mt-8 text-center text-sm text-beige-100/60 space-y-2">
    <p><a href="<?= url('/public/forgot_password.php') ?>" class="text-gold-400/80 hover:text-gold-300">Forgotten your password?</a></p>
    <p>New to <?= e(config('app.name')) ?>? <a href="<?= url('/public/register.php') ?>" class="text-gold-400 hover:text-gold-300">Create an account</a></p>
  </div>
</section>
<?php require __DIR__ . '/../includes/footer.php'; ?>
