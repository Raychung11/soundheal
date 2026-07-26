<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_admin();
$pageTitle = 'Gift vouchers';

$errors = [];
$flashResendMsg = null;

if (is_post()) {
    csrf_verify();
    $action = (string) input('action');

    if ($action === 'issue') {
        $amount     = max(0.0, (float) input('amount', 0));
        $recipient  = trim((string) input('recipient_name', ''));
        $email      = trim((string) input('recipient_email', ''));
        $message    = trim((string) input('message', ''));
        $expiresRaw = trim((string) input('expires_at', ''));
        $notes      = trim((string) input('notes', ''));
        $sendEmail  = !empty($_POST['send_email']);

        if ($amount <= 0) $errors[] = 'Amount must be greater than zero.';
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Recipient email doesn\'t look valid.';
        }
        $expiresAt = null;
        if ($expiresRaw !== '') {
            $ts = strtotime($expiresRaw);
            if ($ts === false) $errors[] = 'Invalid expiry date.';
            else $expiresAt = date('Y-m-d H:i:s', $ts);
        }

        if (!$errors) {
            $code = generate_gift_voucher_code();
            db()->prepare(
                "INSERT INTO gift_vouchers
                    (code, amount, currency, recipient_name, recipient_email, message,
                     expires_at, notes, created_by)
                 VALUES (:c, :a, 'MYR', :rn, :re, :m, :ex, :n, :u)"
            )->execute([
                ':c'  => $code,
                ':a'  => $amount,
                ':rn' => $recipient ?: null,
                ':re' => $email ?: null,
                ':m'  => $message ?: null,
                ':ex' => $expiresAt,
                ':n'  => $notes ?: null,
                ':u'  => current_user_id(),
            ]);
            $newId = (int) db()->lastInsertId();
            audit_log('gift_voucher.issue', 'gift_vouchers', $newId, ['amount' => $amount]);

            if ($sendEmail && $email !== '') {
                send_mail(
                    $email,
                    $recipient ?: 'friend',
                    'A gift for you · ' . brand_name(),
                    'gift_voucher_issued',
                    [
                        'recipient_name'  => $recipient ?: 'friend',
                        'amount_display'  => format_money($amount),
                        'code'            => $code,
                        'message'         => $message,
                        'expires_display' => $expiresAt ? format_datetime($expiresAt, 'd M Y') : '',
                        'events_url'      => rtrim((string) config('app.url'), '/') . '/public/events.php',
                    ]
                );
            }
            flash('gv', 'Issued voucher ' . $code . '. Share link ready below.', 'success');
            // Jump straight to the share panel for this new voucher —
            // "one click, get the share link" instead of fishing through
            // the list.
            redirect('/admin/voucher_share.php?code=' . rawurlencode($code));
        }
    } elseif ($action === 'revoke') {
        $id = (int) input('id', 0);
        db()->prepare("UPDATE gift_vouchers SET status = 'revoked' WHERE id = :id AND status = 'issued'")
            ->execute([':id' => $id]);
        audit_log('gift_voucher.revoke', 'gift_vouchers', $id);
        flash('gv', 'Voucher revoked.', 'info');
        redirect('/admin/gift_vouchers.php');
    } elseif ($action === 'resend') {
        $id = (int) input('id', 0);
        $r = db()->prepare("SELECT * FROM gift_vouchers WHERE id = :id LIMIT 1");
        $r->execute([':id' => $id]);
        $v = $r->fetch();
        if ($v && !empty($v['recipient_email'])) {
            send_mail(
                (string) $v['recipient_email'],
                (string) ($v['recipient_name'] ?? 'friend'),
                'A gift for you · ' . brand_name(),
                'gift_voucher_issued',
                [
                    'recipient_name'  => $v['recipient_name'] ?: 'friend',
                    'amount_display'  => format_money((float) $v['amount']),
                    'code'            => $v['code'],
                    'message'         => $v['message'],
                    'expires_display' => $v['expires_at'] ? format_datetime($v['expires_at'], 'd M Y') : '',
                    'events_url'      => rtrim((string) config('app.url'), '/') . '/public/events.php',
                ]
            );
            audit_log('gift_voucher.resend', 'gift_vouchers', (int) $v['id']);
            flash('gv', 'Voucher email resent.', 'success');
        } else {
            flash('gv', 'No recipient email on file for this voucher.', 'error');
        }
        redirect('/admin/gift_vouchers.php');
    }
}

// Summary
$summary = db()->query(
    "SELECT
        COALESCE(SUM(CASE WHEN status='issued'   THEN amount END), 0) AS outstanding,
        COALESCE(SUM(CASE WHEN status='redeemed' THEN amount END), 0) AS redeemed_total,
        COUNT(CASE WHEN status='issued'   THEN 1 END) AS outstanding_count,
        COUNT(CASE WHEN status='redeemed' THEN 1 END) AS redeemed_count
      FROM gift_vouchers"
)->fetch() ?: [];

$vouchers = db()->query(
    "SELECT v.*, b.booking_ref, u.full_name AS redeemer_name, creator.full_name AS creator_name
       FROM gift_vouchers v
       LEFT JOIN event_bookings b ON b.id = v.redeemed_by_booking_id
       LEFT JOIN users u        ON u.id = b.user_id
       LEFT JOIN users creator  ON creator.id = v.created_by
      ORDER BY v.id DESC LIMIT 200"
)->fetchAll();

require __DIR__ . '/../includes/admin_layout.php';
?>

<div class="flex items-center justify-between gap-4 flex-wrap">
  <div>
    <h1 class="font-serif text-3xl text-beige-100">Gift vouchers</h1>
    <p class="text-beige-100/60 mt-1 text-sm">Fixed-MYR vouchers redeemable at booking. Codes are single-use and issued individually here.</p>
  </div>
</div>

<div class="mt-8 grid sm:grid-cols-2 lg:grid-cols-4 gap-3">
  <div class="border border-white/5 rounded-2xl p-4 bg-navy-900/40">
    <p class="text-[11px] uppercase tracking-widest text-beige-100/50">Outstanding</p>
    <p class="font-serif text-2xl text-gold-400 mt-1"><?= e(format_money((float) ($summary['outstanding'] ?? 0))) ?></p>
    <p class="text-[10px] text-beige-100/45 mt-0.5"><?= (int) ($summary['outstanding_count'] ?? 0) ?> issued, unredeemed</p>
  </div>
  <div class="border border-white/5 rounded-2xl p-4 bg-navy-900/40">
    <p class="text-[11px] uppercase tracking-widest text-beige-100/50">Redeemed</p>
    <p class="font-serif text-2xl text-beige-100 mt-1"><?= e(format_money((float) ($summary['redeemed_total'] ?? 0))) ?></p>
    <p class="text-[10px] text-beige-100/45 mt-0.5"><?= (int) ($summary['redeemed_count'] ?? 0) ?> gifts used</p>
  </div>
</div>

<?php foreach ($errors as $err): ?>
  <p class="mt-4 text-red-300/80 text-sm"><?= e($err) ?></p>
<?php endforeach; ?>

<form method="post" class="mt-8 border border-white/5 rounded-3xl p-6 bg-navy-900/40 space-y-5">
  <?= csrf_field() ?>
  <input type="hidden" name="action" value="issue">
  <h2 class="font-serif text-2xl text-gold-400">Issue a new voucher</h2>
  <p class="text-[11px] text-beige-100/45">A random code is generated and stored. If you tick "Email the recipient", we'll send them the code straight away.</p>

  <div class="grid sm:grid-cols-2 gap-4">
    <label class="block">
      <span class="text-xs uppercase tracking-widest text-beige-100/60">Amount · MYR</span>
      <input name="amount" type="number" step="0.01" min="0" required
             class="mt-2 w-full rounded-2xl bg-navy-950 border border-white/5 px-4 py-3">
    </label>
    <label class="block">
      <span class="text-xs uppercase tracking-widest text-beige-100/60">Expires at <span class="text-beige-100/30">(optional)</span></span>
      <input name="expires_at" type="datetime-local"
             class="mt-2 w-full rounded-2xl bg-navy-950 border border-white/5 px-4 py-3">
    </label>
    <label class="block">
      <span class="text-xs uppercase tracking-widest text-beige-100/60">Recipient name <span class="text-beige-100/30">(optional)</span></span>
      <input name="recipient_name" maxlength="150"
             class="mt-2 w-full rounded-2xl bg-navy-950 border border-white/5 px-4 py-3">
    </label>
    <label class="block">
      <span class="text-xs uppercase tracking-widest text-beige-100/60">Recipient email <span class="text-beige-100/30">(optional)</span></span>
      <input name="recipient_email" type="email"
             class="mt-2 w-full rounded-2xl bg-navy-950 border border-white/5 px-4 py-3">
    </label>
    <label class="block sm:col-span-2">
      <span class="text-xs uppercase tracking-widest text-beige-100/60">Personal message <span class="text-beige-100/30">(optional)</span></span>
      <textarea name="message" rows="3"
                class="mt-2 w-full rounded-2xl bg-navy-950 border border-white/5 px-4 py-3"></textarea>
    </label>
    <label class="block sm:col-span-2">
      <span class="text-xs uppercase tracking-widest text-beige-100/60">Internal notes <span class="text-beige-100/30">(not shown to recipient)</span></span>
      <input name="notes" maxlength="255"
             class="mt-2 w-full rounded-2xl bg-navy-950 border border-white/5 px-4 py-3">
    </label>
  </div>

  <label class="flex items-center gap-2 text-sm text-beige-100/75">
    <input type="checkbox" name="send_email" value="1" checked class="accent-gold-500">
    Email the recipient now (only if recipient email is filled in)
  </label>

  <div class="flex gap-3">
    <button class="px-6 py-3 rounded-full bg-gold-500 text-navy-950 hover:bg-gold-400 transition">Issue voucher</button>
  </div>
</form>

<h2 class="mt-12 font-serif text-2xl text-beige-100">All vouchers</h2>
<div class="mt-4 border border-white/5 rounded-2xl bg-navy-900/40 overflow-x-auto">
  <table class="w-full text-sm">
    <thead class="text-left text-beige-100/50 text-xs uppercase tracking-wider bg-navy-950/40">
      <tr>
        <th class="px-4 py-3">Code</th><th>Amount</th><th>Recipient</th><th>Status</th><th>Expires</th><th></th>
      </tr>
    </thead>
    <tbody class="divide-y divide-white/5">
      <?php foreach ($vouchers as $v): ?>
        <tr class="<?= in_array($v['status'], ['redeemed','revoked','expired'], true) ? 'opacity-60' : '' ?>">
          <td class="px-4 py-3">
            <p class="font-mono text-beige-100 tracking-widest"><?= e($v['code']) ?></p>
            <p class="text-[10px] text-beige-100/40 mt-0.5"><?= e(format_datetime($v['created_at'], 'd M Y')) ?><?php if (!empty($v['creator_name'])): ?> · <?= e($v['creator_name']) ?><?php endif; ?></p>
          </td>
          <td class="text-beige-100"><?= e(format_money((float) $v['amount'])) ?></td>
          <td class="text-beige-100/75">
            <?= e($v['recipient_name'] ?? '—') ?>
            <?php if (!empty($v['recipient_email'])): ?>
              <p class="text-xs text-beige-100/45"><?= e($v['recipient_email']) ?></p>
            <?php endif; ?>
            <?php if (!empty($v['redeemer_name'])): ?>
              <p class="text-[10px] text-gold-400/70 mt-0.5">redeemed by <?= e($v['redeemer_name']) ?> · <?= e($v['booking_ref']) ?></p>
            <?php endif; ?>
          </td>
          <td>
            <span class="text-xs px-2 py-1 rounded-full <?= match ($v['status']) {
              'issued'   => 'bg-gold-500/20 text-gold-400',
              'redeemed' => 'bg-white/10 text-beige-100/80',
              'revoked'  => 'bg-red-500/15 text-red-300/80',
              default    => 'bg-white/5 text-beige-100/60',
            } ?>"><?= e($v['status']) ?></span>
          </td>
          <td class="text-beige-100/60 text-xs"><?= $v['expires_at'] ? e(format_datetime($v['expires_at'], 'd M Y')) : '—' ?></td>
          <td class="text-right pr-4 whitespace-nowrap">
            <a href="<?= url('/admin/voucher_share.php?code=' . rawurlencode((string) $v['code'])) ?>"
               class="text-xs text-gold-400/85 hover:text-gold-300 mr-3">Share →</a>
            <?php if ($v['status'] === 'issued'): ?>
              <?php if (!empty($v['recipient_email'])): ?>
                <form method="post" class="inline">
                  <?= csrf_field() ?>
                  <input type="hidden" name="action" value="resend">
                  <input type="hidden" name="id" value="<?= (int) $v['id'] ?>">
                  <button class="text-xs text-gold-400 hover:text-gold-300">Resend email</button>
                </form>
              <?php endif; ?>
              <form method="post" class="inline ml-2" onsubmit="return confirm('Revoke this voucher? It will no longer be redeemable.');">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="revoke">
                <input type="hidden" name="id" value="<?= (int) $v['id'] ?>">
                <button class="text-xs text-red-300/80 hover:text-red-300">Revoke</button>
              </form>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$vouchers): ?>
        <tr><td colspan="6" class="px-4 py-6 text-beige-100/55">No vouchers yet.</td></tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>

<?php require __DIR__ . '/../includes/admin_layout_end.php'; ?>
