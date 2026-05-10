<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_login();
$pageTitle = 'Profile';
$user = current_user();
$errors = [];
$saved = false;

if (is_post()) {
    csrf_verify();
    $name  = trim((string) input('full_name', ''));
    $phone = trim((string) input('phone', ''));
    $newPass = (string) input('new_password', '');
    $confirm = (string) input('confirm_password', '');

    if ($name === '') { $errors[] = 'Name is required.'; }
    if ($newPass !== '' && strlen($newPass) < 8) { $errors[] = 'New password must be at least 8 characters.'; }
    if ($newPass !== '' && $newPass !== $confirm) { $errors[] = 'Passwords do not match.'; }

    if (!$errors) {
        if ($newPass !== '') {
            $stmt = db()->prepare('UPDATE users SET full_name = :n, phone = :p, password_hash = :h WHERE id = :id');
            $stmt->execute([
                ':n' => $name, ':p' => $phone ?: null,
                ':h' => password_hash($newPass, PASSWORD_DEFAULT),
                ':id' => $user['id'],
            ]);
        } else {
            $stmt = db()->prepare('UPDATE users SET full_name = :n, phone = :p WHERE id = :id');
            $stmt->execute([':n' => $name, ':p' => $phone ?: null, ':id' => $user['id']]);
        }
        $_SESSION['user']['full_name'] = $name;
        audit_log('profile.update', 'users', $user['id']);
        $saved = true;
        $user = current_user();
    }
}

$row = db()->prepare('SELECT email, phone FROM users WHERE id = :id');
$row->execute([':id' => $user['id']]);
$details = $row->fetch();

require __DIR__ . '/../includes/header.php';
?>
<section class="max-w-2xl mx-auto px-6 py-16">
  <h1 class="font-serif text-4xl text-beige-100">Profile</h1>

  <?php if ($saved): ?><p class="mt-4 text-gold-400">Saved.</p><?php endif; ?>
  <?php foreach ($errors as $err): ?><p class="mt-3 text-red-300/80"><?= e($err) ?></p><?php endforeach; ?>

  <form method="post" class="mt-10 space-y-5">
    <?= csrf_field() ?>
    <label class="block">
      <span class="text-xs uppercase tracking-widest text-beige-100/60">Full name</span>
      <input name="full_name" required value="<?= e($user['full_name']) ?>" class="mt-2 w-full rounded-2xl bg-navy-900 border border-white/5 px-4 py-3 focus:border-gold-500/50 focus:outline-none">
    </label>
    <label class="block">
      <span class="text-xs uppercase tracking-widest text-beige-100/60">Email</span>
      <input value="<?= e($details['email']) ?>" disabled class="mt-2 w-full rounded-2xl bg-navy-950 border border-white/5 px-4 py-3 text-beige-100/60">
    </label>
    <label class="block">
      <span class="text-xs uppercase tracking-widest text-beige-100/60">Phone</span>
      <input name="phone" value="<?= e($details['phone'] ?? '') ?>" class="mt-2 w-full rounded-2xl bg-navy-900 border border-white/5 px-4 py-3 focus:border-gold-500/50 focus:outline-none">
    </label>

    <hr class="border-white/5">

    <label class="block">
      <span class="text-xs uppercase tracking-widest text-beige-100/60">New password (optional)</span>
      <input name="new_password" type="password" minlength="8" class="mt-2 w-full rounded-2xl bg-navy-900 border border-white/5 px-4 py-3 focus:border-gold-500/50 focus:outline-none">
    </label>
    <label class="block">
      <span class="text-xs uppercase tracking-widest text-beige-100/60">Confirm new password</span>
      <input name="confirm_password" type="password" minlength="8" class="mt-2 w-full rounded-2xl bg-navy-900 border border-white/5 px-4 py-3 focus:border-gold-500/50 focus:outline-none">
    </label>

    <button class="px-6 py-3 rounded-full bg-gold-500 text-navy-950 hover:bg-gold-400 transition">Save changes</button>
  </form>
</section>
<?php require __DIR__ . '/../includes/footer.php'; ?>
