<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$pageTitle = 'Confirm email';
$result = null;

$token = (string) input('token', '');
if ($token !== '') {
    $hash = hash('sha256', $token);
    $row  = db()->prepare(
        "SELECT id, user_id, expires_at, used_at
         FROM user_tokens
         WHERE purpose = 'email_verify' AND token_hash = :h
         ORDER BY id DESC LIMIT 1"
    );
    $row->execute([':h' => $hash]);
    $record = $row->fetch();

    if (!$record || $record['used_at'] !== null || strtotime($record['expires_at']) < time()) {
        $result = ['ok' => false, 'message' => 'That link has expired or already been used.'];
    } else {
        db()->beginTransaction();
        try {
            db()->prepare('UPDATE users SET email_verified_at = NOW() WHERE id = :id')
                ->execute([':id' => $record['user_id']]);
            db()->prepare('UPDATE user_tokens SET used_at = NOW() WHERE id = :id')
                ->execute([':id' => $record['id']]);
            db()->commit();
            audit_log('email.verified', 'users', (int)$record['user_id']);
            $result = ['ok' => true, 'message' => 'Your email is confirmed. Thank you for taking that gentle step.'];
        } catch (Throwable $e) {
            db()->rollBack();
            $result = ['ok' => false, 'message' => 'We could not confirm right now. Please try again.'];
        }
    }
} else {
    $result = ['ok' => false, 'message' => 'No confirmation token provided.'];
}

require __DIR__ . '/../includes/header.php';
?>
<section class="max-w-md mx-auto px-6 py-24 text-center">
  <h1 class="font-serif text-4xl <?= $result['ok'] ? 'text-gold-400' : 'text-beige-100' ?>">
    <?= $result['ok'] ? 'Confirmed.' : 'Hmm.' ?>
  </h1>
  <p class="mt-4 text-beige-100/70"><?= e($result['message']) ?></p>
  <a href="<?= url('/public/login.php') ?>" class="inline-block mt-8 px-6 py-3 rounded-full bg-gold-500 text-navy-950 hover:bg-gold-400 transition">Continue</a>
</section>
<?php require __DIR__ . '/../includes/footer.php'; ?>
