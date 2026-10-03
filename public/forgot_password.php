<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$pageTitle = 'Reset your password';
$sent = false;
$errors = [];

if (is_post()) {
    csrf_verify();
    $email = filter_var(input('email', ''), FILTER_VALIDATE_EMAIL);
    if (!$email) {
        $errors[] = 'Please enter a valid email.';
    } elseif (!throttle('forgot:' . client_ip(), 5, 600)) {
        $errors[] = 'Too many attempts. Please try again in a few minutes.';
    } else {
        $stmt = db()->prepare('SELECT id, full_name FROM users WHERE email = :e LIMIT 1');
        $stmt->execute([':e' => $email]);
        $user = $stmt->fetch();
        if ($user) {
            $token = generate_token(32);
            $hash  = hash('sha256', $token);
            $expires = (new DateTimeImmutable('+1 hour'))->format('Y-m-d H:i:s');
            db()->prepare(
                "INSERT INTO user_tokens (user_id, purpose, token_hash, expires_at)
                 VALUES (:u, 'password_reset', :h, :e)"
            )->execute([':u' => $user['id'], ':h' => $hash, ':e' => $expires]);
            $resetUrl = url('/public/reset_password.php?token=' . $token);
            send_mail($email, $user['full_name'], 'Reset your SoundHeal password',
                'password_reset', ['full_name' => $user['full_name'], 'reset_url' => $resetUrl]);
            audit_log('password.reset_requested', 'users', (int)$user['id']);
        }
        // Don't reveal whether the email exists.
        $sent = true;
    }
}

require __DIR__ . '/../includes/header.php';
?>
<section class="max-w-md mx-auto px-6 py-24">
  <p class="text-gold-400/80 tracking-[0.3em] uppercase text-xs text-center">Soft reset</p>
  <h1 class="font-serif text-4xl text-beige-100 mt-4 text-center">Forgotten your way back</h1>
  <p class="mt-3 text-center text-beige-100/60 text-sm">Tell us your email — we'll send a quiet link to reset your password.</p>

  <?php if ($sent): ?>
    <div class="mt-8 border border-gold-500/30 rounded-2xl p-6 bg-navy-900/40 text-center">
      <p class="font-serif text-xl text-gold-400">Check your inbox.</p>
      <p class="text-beige-100/70 mt-2 text-sm">If we found your account, a reset link is on its way. The link expires in 60 minutes.</p>
    </div>
  <?php else: ?>
    <?php foreach ($errors as $err): ?>
      <p class="mt-4 text-red-300/80 text-center"><?= e($err) ?></p>
    <?php endforeach; ?>
    <form method="post" class="mt-10 space-y-5">
      <?= csrf_field() ?>
      <label class="block">
        <span class="text-xs uppercase tracking-widest text-beige-100/60">Email</span>
        <input name="email" type="email" required autofocus class="mt-2 w-full rounded-2xl bg-navy-900 border border-white/5 px-4 py-3 focus:border-gold-500/50 focus:outline-none">
      </label>
      <button class="w-full px-5 py-3 rounded-full bg-gold-500 text-navy-950 font-medium hover:bg-gold-400 transition">Send reset link</button>
    </form>
  <?php endif; ?>
  <p class="mt-8 text-center text-sm text-beige-100/60"><a href="<?= url('/public/login.php') ?>" class="text-gold-400 hover:text-gold-300">Back to sign in</a></p>
</section>
<?php require __DIR__ . '/../includes/footer.php'; ?>
