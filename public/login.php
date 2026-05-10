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
        if ($intended) {
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

  <form method="post" class="mt-10 space-y-5">
    <?= csrf_field() ?>
    <label class="block">
      <span class="text-xs uppercase tracking-widest text-beige-100/60">Email</span>
      <input name="email" type="email" required autofocus class="mt-2 w-full rounded-2xl bg-navy-900 border border-white/5 px-4 py-3 focus:border-gold-500/50 focus:outline-none">
    </label>
    <label class="block">
      <span class="text-xs uppercase tracking-widest text-beige-100/60">Password</span>
      <input name="password" type="password" required class="mt-2 w-full rounded-2xl bg-navy-900 border border-white/5 px-4 py-3 focus:border-gold-500/50 focus:outline-none">
    </label>
    <button class="w-full px-5 py-3 rounded-full bg-gold-500 text-navy-950 font-medium hover:bg-gold-400 transition">Enter</button>
  </form>

  <div class="mt-8 text-center text-sm text-beige-100/60 space-y-2">
    <p><a href="<?= url('/public/forgot_password.php') ?>" class="text-gold-400/80 hover:text-gold-300">Forgotten your password?</a></p>
    <p>New to <?= e(config('app.name')) ?>? <a href="<?= url('/public/register.php') ?>" class="text-gold-400 hover:text-gold-300">Create an account</a></p>
  </div>
</section>
<?php require __DIR__ . '/../includes/footer.php'; ?>
