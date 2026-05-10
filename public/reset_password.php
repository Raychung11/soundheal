<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$pageTitle = 'Choose a new password';
$errors = [];
$ok = false;

$token = (string) input('token', '');
if ($token === '') {
    flash('reset', 'Reset link is invalid.', 'error');
    redirect('/public/login.php');
}

$hash = hash('sha256', $token);
$row  = db()->prepare(
    "SELECT t.id AS token_id, t.user_id, t.expires_at, t.used_at, u.full_name, u.email
     FROM user_tokens t
     JOIN users u ON u.id = t.user_id
     WHERE t.purpose = 'password_reset' AND t.token_hash = :h
     ORDER BY t.id DESC LIMIT 1"
);
$row->execute([':h' => $hash]);
$record = $row->fetch();

if (!$record || $record['used_at'] !== null || strtotime($record['expires_at']) < time()) {
    flash('reset', 'That reset link has expired.', 'error');
    redirect('/public/forgot_password.php');
}

if (is_post()) {
    csrf_verify();
    $pass = (string) input('password', '');
    $conf = (string) input('password_confirm', '');
    if (strlen($pass) < 8)        { $errors[] = 'Password must be at least 8 characters.'; }
    if ($pass !== $conf)          { $errors[] = 'Passwords do not match.'; }
    if (!$errors) {
        db()->beginTransaction();
        try {
            db()->prepare('UPDATE users SET password_hash = :h WHERE id = :id')
                ->execute([':h' => password_hash($pass, PASSWORD_DEFAULT), ':id' => $record['user_id']]);
            db()->prepare('UPDATE user_tokens SET used_at = NOW() WHERE id = :id')
                ->execute([':id' => $record['token_id']]);
            db()->commit();
            audit_log('password.reset', 'users', (int)$record['user_id']);
            $ok = true;
        } catch (Throwable $e) {
            db()->rollBack();
            $errors[] = 'Could not update password. Please try again.';
        }
    }
}

require __DIR__ . '/../includes/header.php';
?>
<section class="max-w-md mx-auto px-6 py-24">
  <p class="text-gold-400/80 tracking-[0.3em] uppercase text-xs text-center">New beginning</p>
  <h1 class="font-serif text-4xl text-beige-100 mt-4 text-center">Choose a new password</h1>

  <?php if ($ok): ?>
    <div class="mt-8 border border-gold-500/30 rounded-2xl p-6 bg-navy-900/40 text-center">
      <p class="font-serif text-xl text-gold-400">Saved with care.</p>
      <p class="text-beige-100/70 mt-2 text-sm">You may now sign in with your new password.</p>
      <a href="<?= url('/public/login.php') ?>" class="inline-block mt-6 px-6 py-3 rounded-full bg-gold-500 text-navy-950 hover:bg-gold-400 transition">Sign in</a>
    </div>
  <?php else: ?>
    <?php foreach ($errors as $err): ?>
      <p class="mt-4 text-red-300/80 text-center"><?= e($err) ?></p>
    <?php endforeach; ?>
    <form method="post" class="mt-10 space-y-5">
      <?= csrf_field() ?>
      <label class="block">
        <span class="text-xs uppercase tracking-widest text-beige-100/60">New password</span>
        <input name="password" type="password" minlength="8" required autofocus class="mt-2 w-full rounded-2xl bg-navy-900 border border-white/5 px-4 py-3 focus:border-gold-500/50 focus:outline-none">
      </label>
      <label class="block">
        <span class="text-xs uppercase tracking-widest text-beige-100/60">Confirm</span>
        <input name="password_confirm" type="password" minlength="8" required class="mt-2 w-full rounded-2xl bg-navy-900 border border-white/5 px-4 py-3 focus:border-gold-500/50 focus:outline-none">
      </label>
      <button class="w-full px-5 py-3 rounded-full bg-gold-500 text-navy-950 font-medium hover:bg-gold-400 transition">Set new password</button>
    </form>
  <?php endif; ?>
</section>
<?php require __DIR__ . '/../includes/footer.php'; ?>
