<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_login();
$user = current_user();
$pageTitle = 'Subscribe';

$planId = (int) input('plan', 0);
$stmt = db()->prepare("SELECT * FROM membership_plans WHERE id = :id AND is_active = 1 LIMIT 1");
$stmt->execute([':id' => $planId]);
$plan = $stmt->fetch();
if (!$plan) {
    flash('plan', 'That plan is no longer available.', 'error');
    redirect('/public/membership.php');
}

if (is_post()) {
    csrf_verify();
    db()->beginTransaction();
    try {
        $started = (new DateTimeImmutable('now'))->format('Y-m-d H:i:s');
        $expires = (new DateTimeImmutable('now'))->modify('+' . (int)$plan['duration_days'] . ' days')->format('Y-m-d H:i:s');

        $ins = db()->prepare(
            "INSERT INTO memberships (user_id, plan_id, started_at, expires_at, status, auto_renew)
             VALUES (:u, :p, :s, :e, 'pending', 0)"
        );
        $ins->execute([':u' => $user['id'], ':p' => $planId, ':s' => $started, ':e' => $expires]);
        $membershipId = (int) db()->lastInsertId();

        db()->commit();
        audit_log('membership.create', 'memberships', $membershipId, ['plan' => $plan['code']]);

        // Hand off to payment.
        redirect('/api/billplz_create.php?purpose=membership&ref=' . $membershipId);
    } catch (Throwable $e) {
        db()->rollBack();
        flash('subscribe', $e->getMessage(), 'error');
    }
}

require __DIR__ . '/../includes/header.php';
?>
<section class="max-w-xl mx-auto px-6 py-16">
  <p class="text-gold-400/80 tracking-[0.3em] uppercase text-xs">Membership</p>
  <h1 class="font-serif text-4xl text-beige-100 mt-4">Confirm <?= e($plan['name']) ?></h1>
  <p class="mt-3 text-beige-100/70"><?= e($plan['description']) ?></p>

  <div class="mt-8 border border-white/5 rounded-3xl p-6 bg-navy-900/40 flex justify-between items-center">
    <div>
      <p class="text-xs uppercase tracking-widest text-beige-100/50">Total</p>
      <p class="font-serif text-3xl text-gold-400 mt-1"><?= e(format_money((float)$plan['price'])) ?></p>
    </div>
    <p class="text-sm text-beige-100/60 capitalize"><?= e($plan['billing_cycle']) ?> · <?= (int)$plan['duration_days'] ?> days</p>
  </div>

  <form method="post" class="mt-8">
    <?= csrf_field() ?>
    <button class="w-full px-6 py-4 rounded-full bg-gold-500 text-navy-950 font-medium hover:bg-gold-400 transition">Continue to payment</button>
  </form>
</section>
<?php require __DIR__ . '/../includes/footer.php'; ?>
