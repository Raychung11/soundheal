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
    $name  = trim((string) input('full_name', ''));
    $email = filter_var(input('email', ''), FILTER_VALIDATE_EMAIL);
    $phone = trim((string) input('phone', ''));
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

  <form method="post" class="mt-10 space-y-5">
    <?= csrf_field() ?>
    <?php if ($isTrial): ?><input type="hidden" name="trial" value="1"><?php endif; ?>
    <label class="block">
      <span class="text-xs uppercase tracking-widest text-beige-100/60">Full name</span>
      <input name="full_name" required class="mt-2 w-full rounded-2xl bg-navy-900 border border-white/5 px-4 py-3 focus:border-gold-500/50 focus:outline-none">
    </label>
    <label class="block">
      <span class="text-xs uppercase tracking-widest text-beige-100/60">Email</span>
      <input name="email" type="email" required class="mt-2 w-full rounded-2xl bg-navy-900 border border-white/5 px-4 py-3 focus:border-gold-500/50 focus:outline-none">
    </label>
    <label class="block">
      <span class="text-xs uppercase tracking-widest text-beige-100/60">Phone (optional)</span>
      <input name="phone" class="mt-2 w-full rounded-2xl bg-navy-900 border border-white/5 px-4 py-3 focus:border-gold-500/50 focus:outline-none">
    </label>
    <label class="block">
      <span class="text-xs uppercase tracking-widest text-beige-100/60">Password</span>
      <input name="password" type="password" minlength="8" required class="mt-2 w-full rounded-2xl bg-navy-900 border border-white/5 px-4 py-3 focus:border-gold-500/50 focus:outline-none">
    </label>
    <label class="block">
      <span class="text-xs uppercase tracking-widest text-beige-100/60">Confirm password</span>
      <input name="password_confirm" type="password" minlength="8" required class="mt-2 w-full rounded-2xl bg-navy-900 border border-white/5 px-4 py-3 focus:border-gold-500/50 focus:outline-none">
    </label>
    <button class="w-full px-5 py-3 rounded-full bg-gold-500 text-navy-950 font-medium hover:bg-gold-400 transition">Create account</button>
  </form>

  <p class="mt-8 text-center text-sm text-beige-100/60">Already with us? <a href="<?= url('/public/login.php') ?>" class="text-gold-400 hover:text-gold-300">Sign in</a></p>
</section>
<?php require __DIR__ . '/../includes/footer.php'; ?>
