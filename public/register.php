<?php
require_once __DIR__ . '/../includes/bootstrap.php';

// If the visitor arrived via /public/register.php?ref=ABCD12, store it.
capture_referral_cookie();

$refCode    = get_referral_cookie();
$refUserId  = referrer_id_for_code($refCode);
$refName    = '';
if ($refUserId) {
    $stmt = db()->prepare('SELECT full_name FROM users WHERE id = :id');
    $stmt->execute([':id' => $refUserId]);
    $refName = (string) ($stmt->fetchColumn() ?: '');
}

$isTrial   = (!empty(input('trial')) || $refUserId) && (bool) setting('trial_enabled', true);
$trialDays = (int) setting('trial_duration_days', 7);
$pageTitle = $refUserId ? 'A friend has invited you' : ($isTrial ? 'Begin your free trial' : 'Begin your journey');
$errors = [];

if (is_post()) {
    csrf_verify();
    $name  = format_name((string) input('full_name', ''));
    $email = filter_var(input('email', ''), FILTER_VALIDATE_EMAIL);
    $phone = normalize_phone((string) input('phone', ''));
    $pass  = (string) input('password', '');
    $confirm = (string) input('password_confirm', '');

    if ($name === '')                    { $errors[] = 'Please share your full name.'; }
    if (!$email)                         { $errors[] = 'Please provide a valid email.'; }
    if (strlen($pass) < 8)               { $errors[] = 'Password must be at least 8 characters.'; }
    if ($pass !== $confirm)              { $errors[] = 'Passwords do not match.'; }

    if (!$errors) {
        $exists = db()->prepare('SELECT id FROM users WHERE email = :e LIMIT 1');
        $exists->execute([':e' => $email]);
        if ($exists->fetch()) {
            $errors[] = 'An account with this email already exists. Please sign in.';
        }
    }

    if (!$errors) {
        if (!throttle('register:' . client_ip(), 5, 600)) {
            $errors[] = 'Too many sign-ups from this network. Please try again shortly.';
        }
    }

    if (!$errors) {
        $userId = register_user($name, $email, $pass, $phone ?: null, $isTrial ? $trialDays : null);

        // Credit a referrer if the new user arrived via a share link.
        apply_referral_on_signup($userId, $refCode);

        // Issue an email verification token (one-time, 7-day window).
        $verifyToken = generate_token(32);
        db()->prepare(
            "INSERT INTO user_tokens (user_id, purpose, token_hash, expires_at)
             VALUES (:u, 'email_verify', :h, :e)"
        )->execute([
            ':u' => $userId,
            ':h' => hash('sha256', $verifyToken),
            ':e' => (new DateTimeImmutable('+7 days'))->format('Y-m-d H:i:s'),
        ]);

        send_mail($email, $name, 'Welcome to SoundHeal', 'welcome', ['full_name' => $name]);
        send_mail($email, $name, 'Confirm your email', 'verify_email', [
            'verify_url' => url('/public/verify_email.php?token=' . $verifyToken),
        ]);

        attempt_login($email, $pass);
        flash('welcome',
            $isTrial
                ? 'Your ' . $trialDays . '-day free trial is open. Welcome.'
                : 'Welcome to ' . config('app.name') . '. Your sanctuary is ready — please confirm your email when you have a moment.',
            'success');
        redirect($isTrial ? '/member/content.php' : '/member/dashboard.php');
    }
}

require __DIR__ . '/../includes/header.php';
?>
<section class="max-w-md mx-auto px-6 py-24">
  <?php if ($refUserId && $refName !== ''): ?>
    <div class="mb-8 border border-gold-500/30 rounded-3xl p-5 bg-navy-900/40 text-center">
      <p class="text-[11px] uppercase tracking-[0.3em] text-gold-400/80">A quiet invitation</p>
      <p class="mt-2 font-serif text-xl text-beige-100"><?= e(explode(' ', $refName)[0] ?? 'A friend') ?> invited you to SoundHeal.</p>
      <p class="text-xs text-beige-100/60 mt-2">You'll both receive an extra <?= (int) setting('referral_signup_trial_days', 7) ?> days of trial access when you sign up.</p>
    </div>
  <?php endif; ?>

  <?php if ($refUserId): ?>
    <p class="text-gold-400/80 tracking-[0.3em] uppercase text-xs text-center">Friend's invite · trial included</p>
    <h1 class="font-serif text-4xl text-beige-100 mt-4 text-center">Welcome, gently</h1>
  <?php elseif ($isTrial): ?>
    <p class="text-gold-400/80 tracking-[0.3em] uppercase text-xs text-center">Free trial · <?= (int) $trialDays ?> days</p>
    <h1 class="font-serif text-4xl text-beige-100 mt-4 text-center">A gentle first step</h1>
    <p class="mt-3 text-center text-beige-100/60 text-sm">No payment required. Your trial begins the moment you sign up.</p>
  <?php else: ?>
    <p class="text-gold-400/80 tracking-[0.3em] uppercase text-xs text-center">Create your sanctuary</p>
    <h1 class="font-serif text-4xl text-beige-100 mt-4 text-center">Begin gently</h1>
  <?php endif; ?>

  <?php foreach ($errors as $err): ?>
    <p class="mt-4 text-red-300/80 text-center"><?= e($err) ?></p>
  <?php endforeach; ?>

  <?php if (function_exists('oauth_google_ready') && oauth_google_ready()): ?>
    <a href="<?= url('/api/oauth_google_start.php') ?>"
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
      <span>or with email</span>
      <span class="flex-1 h-px bg-white/10"></span>
    </div>
  <?php endif; ?>

  <form method="post" class="<?= (function_exists('oauth_google_ready') && oauth_google_ready()) ? '' : 'mt-10 ' ?>space-y-5" x-data="{
      showPw: false,
      showConfirm: false,
      pw: '',
      confirm: '',
      get pwOk()    { return this.pw === '' || this.pw.length >= 8; },
      get pwMatch() { return this.confirm === '' || this.pw === this.confirm; }
    }">
    <?= csrf_field() ?>
    <?php if ($isTrial): ?><input type="hidden" name="trial" value="1"><?php endif; ?>
    <label class="block">
      <span class="text-xs uppercase tracking-widest text-beige-100/60">Full name</span>
      <input name="full_name" required
             x-data x-on:blur="$el.value = $el.value.trim().toLowerCase().replace(/\s+/g, ' ').replace(/(^|[\s'\-])\p{L}/gu, c => c.toUpperCase())"
             class="mt-2 w-full rounded-2xl bg-navy-900 border border-white/5 px-4 py-3 focus:border-gold-500/50 focus:outline-none"
             style="text-transform: capitalize;">
    </label>
    <label class="block">
      <span class="text-xs uppercase tracking-widest text-beige-100/60">Email</span>
      <input name="email" type="email" required class="mt-2 w-full rounded-2xl bg-navy-900 border border-white/5 px-4 py-3 focus:border-gold-500/50 focus:outline-none">
    </label>
    <label class="block">
      <span class="text-xs uppercase tracking-widest text-beige-100/60">Phone (optional)</span>
      <div class="mt-2 flex items-stretch rounded-2xl bg-navy-900 border border-white/5 focus-within:border-gold-500/50 transition overflow-hidden">
        <span class="px-3 flex items-center text-gold-400 text-sm bg-navy-950/60 border-r border-white/5">+60</span>
        <input name="phone" type="tel" inputmode="tel" autocomplete="tel-national"
               placeholder="12 345 6789"
               x-data x-on:input="$el.value = $el.value.replace(/[^\d+\s\-]/g, '')"
               x-on:blur="$el.value = $el.value.replace(/[^\d+]/g, '').replace(/^\+?60/, '').replace(/^0/, '')"
               class="flex-1 bg-transparent px-4 py-3 focus:outline-none">
      </div>
      <span class="text-[11px] text-beige-100/40 mt-1 block">Outside Malaysia? Start with <code class="text-gold-400/70">+</code> and your country code.</span>
    </label>
    <label class="block">
      <span class="text-xs uppercase tracking-widest text-beige-100/60">Password</span>
      <div class="relative mt-2">
        <input name="password" :type="showPw ? 'text' : 'password'" minlength="8" required
               autocomplete="new-password" x-model="pw"
               class="w-full rounded-2xl bg-navy-900 border px-4 py-3 pr-16 focus:outline-none transition"
               :class="pwOk ? 'border-white/5 focus:border-gold-500/50' : 'border-red-400/50'">
        <button type="button" @click="showPw = !showPw"
                class="absolute inset-y-0 right-0 px-4 text-beige-100/45 hover:text-gold-400 text-xs uppercase tracking-widest"
                :aria-label="showPw ? 'Hide password' : 'Show password'"
                x-text="showPw ? 'Hide' : 'Show'"></button>
      </div>
      <span class="mt-1 block text-[11px] text-red-300/70" x-show="!pwOk" x-cloak>At least 8 characters, gently.</span>
    </label>
    <label class="block">
      <span class="text-xs uppercase tracking-widest text-beige-100/60">Confirm password</span>
      <div class="relative mt-2">
        <input name="password_confirm" :type="showConfirm ? 'text' : 'password'" minlength="8" required
               autocomplete="new-password" x-model="confirm"
               class="w-full rounded-2xl bg-navy-900 border px-4 py-3 pr-16 focus:outline-none transition"
               :class="pwMatch ? 'border-white/5 focus:border-gold-500/50' : 'border-red-400/50'">
        <button type="button" @click="showConfirm = !showConfirm"
                class="absolute inset-y-0 right-0 px-4 text-beige-100/45 hover:text-gold-400 text-xs uppercase tracking-widest"
                :aria-label="showConfirm ? 'Hide password' : 'Show password'"
                x-text="showConfirm ? 'Hide' : 'Show'"></button>
      </div>
      <span class="mt-1 block text-[11px] text-red-300/70" x-show="!pwMatch" x-cloak>Both fields must match.</span>
    </label>
    <button class="w-full px-5 py-3 rounded-full bg-gold-500 text-navy-950 font-medium hover:bg-gold-400 transition">Create account</button>
  </form>

  <p class="mt-8 text-center text-sm text-beige-100/60">Already with us? <a href="<?= url('/public/login.php') ?>" class="text-gold-400 hover:text-gold-300">Sign in</a></p>
</section>
<?php require __DIR__ . '/../includes/footer.php'; ?>
