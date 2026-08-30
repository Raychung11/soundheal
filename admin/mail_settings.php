<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_admin();
$pageTitle = 'Mail settings';

$message = null;
$messageType = 'info';

if (is_post()) {
    csrf_verify();
    $action = (string) input('action');

    if ($action === 'save') {
        $port = max(1, min(65535, (int) input('mail_port', 587)));
        $enc  = (string) input('mail_encryption', 'tls');
        if (!in_array($enc, ['tls','ssl','none'], true)) $enc = 'tls';

        set_setting('mail_driver',       'smtp',                                            'string');
        set_setting('mail_host',         trim((string) input('mail_host', '')),             'string');
        set_setting('mail_port',         (string) $port,                                     'int');
        set_setting('mail_username',     trim((string) input('mail_username', '')),         'string');
        // Only overwrite the password if a new one was typed in.
        $newPw = (string) input('mail_password', '');
        if ($newPw !== '') {
            set_setting('mail_password', $newPw, 'string');
        }
        set_setting('mail_encryption',   $enc,                                                'string');
        set_setting('mail_from_address', trim((string) input('mail_from_address', '')),     'string');
        set_setting('mail_from_name',    trim((string) input('mail_from_name', '')),        'string');

        audit_log('mail_settings.update', 'site_settings', null);
        flash('mail', 'Mail settings saved.', 'success');
        redirect('/admin/mail_settings.php');
    }

    if ($action === 'test') {
        $to     = trim((string) input('test_to', ''));
        $toName = trim((string) input('test_name', '')) ?: 'Test';
        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
            $message = 'Enter a valid recipient email.';
            $messageType = 'error';
        } else {
            $ok = send_mail($to, $toName, '[Test] mail from jaemie sound bath', 'welcome', [
                'full_name' => $toName,
            ]);
            if ($ok) {
                $message = 'Test email accepted by the SMTP server. Check the inbox for ' . $to . ' (and the spam folder).';
                $messageType = 'success';
            } else {
                $message = 'Send failed — see the most recent row in the log below for the exact error.';
                $messageType = 'error';
            }
        }
    }
}

$cfg = mail_settings();
$autoload = SH_ROOT . '/vendor/autoload.php';
$phpmailer = file_exists($autoload);
$activeDriver = $phpmailer
    ? 'PHPMailer (SMTP)'
    : (($cfg['host'] !== '' && $cfg['username'] !== '' && $cfg['password'] !== '')
        ? 'Built-in SMTP'
        : 'PHP mail() — unreliable on shared hosting');

// Last 20 send attempts (table may not exist pre-migration).
$log = [];
try {
    $log = db()->query("SELECT * FROM mail_log ORDER BY id DESC LIMIT 20")->fetchAll();
} catch (Throwable $e) { /* no-op */ }

require __DIR__ . '/../includes/admin_layout.php';
?>

<div class="flex items-center justify-between gap-4 flex-wrap">
  <div>
    <h1 class="font-serif text-3xl text-beige-100">Mail settings</h1>
    <p class="text-beige-100/60 mt-1 text-sm">Outgoing email used for welcome, password reset, booking confirmation and admin notifications.</p>
  </div>
  <span class="text-xs uppercase tracking-widest px-3 py-1.5 rounded-full <?= $phpmailer || ($cfg['host'] !== '' && $cfg['username'] !== '') ? 'border border-gold-500/40 text-gold-400' : 'border border-red-400/40 text-red-300/80' ?>">
    <?= e($activeDriver) ?>
  </span>
</div>

<?php if ($message !== null): ?>
  <div class="mt-6 border rounded-2xl px-5 py-4 text-sm <?= $messageType === 'success' ? 'border-gold-500/40 bg-gold-500/5 text-gold-400'
                                                                : ($messageType === 'error' ? 'border-red-400/40 bg-red-500/5 text-red-200'
                                                                : 'border-white/10 bg-navy-900/40 text-beige-100/85') ?>">
    <?= e($message) ?>
  </div>
<?php endif; ?>

<!-- What the system currently sees -->
<?php
$rows = [
    ['SMTP host',     $cfg['host'],         $cfg['host']         !== ''],
    ['Port',          (string) $cfg['port'],$cfg['port']         > 0],
    ['Encryption',    $cfg['encryption'],   $cfg['encryption']   !== ''],
    ['Username',      $cfg['username'],     $cfg['username']     !== ''],
    ['Password',      $cfg['password'] !== '' ? '✓ saved (' . strlen($cfg['password']) . ' chars)' : '— not set —', $cfg['password'] !== ''],
    ['From address',  $cfg['from_address'], $cfg['from_address'] !== ''],
    ['From name',     $cfg['from_name'],    $cfg['from_name']    !== ''],
];
$allSet = $cfg['host'] !== '' && $cfg['username'] !== '' && $cfg['password'] !== '';
?>
<div class="mt-6 border <?= $allSet ? 'border-white/5 bg-navy-900/40' : 'border-red-400/40 bg-red-500/5' ?> rounded-3xl p-6">
  <div class="flex items-center justify-between gap-3 flex-wrap">
    <h2 class="font-serif text-xl text-gold-400">What the system currently sees</h2>
    <span class="text-[11px] uppercase tracking-widest <?= $allSet ? 'text-gold-400/80' : 'text-red-300' ?>">
      <?= $allSet ? 'SMTP ready' : 'SMTP not yet configured' ?>
    </span>
  </div>
  <table class="mt-3 w-full text-sm">
    <tbody class="divide-y divide-white/5">
      <?php foreach ($rows as [$label, $val, $ok]): ?>
        <tr>
          <td class="py-2 text-beige-100/55 w-44"><?= e($label) ?></td>
          <td class="text-beige-100/90 font-mono text-xs break-all"><?= e($val !== '' ? $val : '— empty —') ?></td>
          <td class="text-right text-xs <?= $ok ? 'text-gold-400/80' : 'text-red-300' ?>">
            <?= $ok ? '✓' : 'missing' ?>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  <?php if (!$allSet): ?>
    <p class="mt-4 text-xs text-red-200/90 leading-relaxed">
      The system needs <strong>SMTP host</strong>, <strong>username</strong> and <strong>password</strong> before it can speak SMTP. Until those three are filled in, every outbound email falls through to PHP <code>mail()</code> which Hostinger silently drops.
    </p>
  <?php endif; ?>
</div>

<form method="post" action="<?= url('/admin/mail_settings.php') ?>" class="mt-8 space-y-6 max-w-3xl border border-white/5 rounded-3xl p-6 bg-navy-900/40">
  <?= csrf_field() ?>
  <input type="hidden" name="action" value="save">

  <h2 class="font-serif text-2xl text-gold-400">SMTP</h2>

  <div class="grid sm:grid-cols-2 gap-5">
    <label class="block">
      <span class="text-xs uppercase tracking-widest text-beige-100/60">SMTP host</span>
      <input name="mail_host" value="<?= e($cfg['host']) ?>" placeholder="smtp.hostinger.com"
             class="mt-2 w-full rounded-2xl bg-navy-950 border border-white/5 px-4 py-3 font-mono text-sm">
    </label>
    <label class="block">
      <span class="text-xs uppercase tracking-widest text-beige-100/60">Port</span>
      <input name="mail_port" type="number" min="1" max="65535" value="<?= (int) $cfg['port'] ?>"
             class="mt-2 w-full rounded-2xl bg-navy-950 border border-white/5 px-4 py-3 font-mono text-sm">
      <span class="text-[11px] text-beige-100/40 mt-1 block">587 = STARTTLS · 465 = direct SSL · 25 = plain (rarely allowed).</span>
    </label>
    <label class="block">
      <span class="text-xs uppercase tracking-widest text-beige-100/60">Encryption</span>
      <select name="mail_encryption" class="mt-2 w-full rounded-2xl bg-navy-950 border border-white/5 px-4 py-3">
        <?php foreach (['tls' => 'STARTTLS (587)', 'ssl' => 'SSL (465)', 'none' => 'None'] as $v => $label): ?>
          <option value="<?= $v ?>" <?= $cfg['encryption'] === $v ? 'selected' : '' ?>><?= e($label) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <label class="block">
      <span class="text-xs uppercase tracking-widest text-beige-100/60">Username</span>
      <input name="mail_username" autocomplete="off" value="<?= e($cfg['username']) ?>"
             placeholder="hello@jaemiesoundbath.com"
             class="mt-2 w-full rounded-2xl bg-navy-950 border border-white/5 px-4 py-3 text-sm">
    </label>
    <label class="block sm:col-span-2" x-data="{ show: false }">
      <span class="text-xs uppercase tracking-widest text-beige-100/60">Password</span>
      <div class="relative mt-2">
        <input name="mail_password" :type="show ? 'text' : 'password'" autocomplete="new-password"
               placeholder="<?= $cfg['password'] !== '' ? '••••••••' . substr($cfg['password'], -2) : 'Enter the mailbox password' ?>"
               class="w-full rounded-2xl bg-navy-950 border border-white/5 px-4 py-3 pr-12 text-sm">
        <button type="button" @click="show = !show" class="absolute inset-y-0 right-0 px-4 text-beige-100/45 hover:text-gold-400 text-xs uppercase tracking-widest"
                x-text="show ? 'Hide' : 'Show'"></button>
      </div>
      <span class="text-[11px] text-beige-100/40 mt-1 block">Leave blank to keep the current password. <?= $cfg['password'] === '' ? '<span class="text-red-300/85">No password set yet.</span>' : '' ?></span>
    </label>
  </div>

  <hr class="border-white/5">

  <h2 class="font-serif text-2xl text-gold-400">From</h2>
  <div class="grid sm:grid-cols-2 gap-5">
    <label class="block">
      <span class="text-xs uppercase tracking-widest text-beige-100/60">From address</span>
      <input name="mail_from_address" type="email" value="<?= e($cfg['from_address']) ?>"
             placeholder="hello@jaemiesoundbath.com"
             class="mt-2 w-full rounded-2xl bg-navy-950 border border-white/5 px-4 py-3 text-sm">
    </label>
    <label class="block">
      <span class="text-xs uppercase tracking-widest text-beige-100/60">From name</span>
      <input name="mail_from_name" value="<?= e($cfg['from_name']) ?>"
             placeholder="jaemie sound bath"
             class="mt-2 w-full rounded-2xl bg-navy-950 border border-white/5 px-4 py-3 text-sm">
    </label>
  </div>

  <div class="flex gap-3">
    <button class="px-6 py-3 rounded-full bg-gold-500 text-navy-950 hover:bg-gold-400 transition">Save mail settings</button>
  </div>
</form>

<form method="post" action="<?= url('/admin/mail_settings.php') ?>" class="mt-6 max-w-3xl border border-white/5 rounded-3xl p-6 bg-navy-900/40 space-y-4">
  <?= csrf_field() ?>
  <input type="hidden" name="action" value="test">
  <h2 class="font-serif text-2xl text-gold-400">Send test email</h2>
  <p class="text-sm text-beige-100/65">Uses your saved SMTP settings to send a real welcome-style email. If it lands, your password-reset / booking-confirmation emails will too.</p>
  <div class="grid sm:grid-cols-[1fr_1fr_auto] gap-3">
    <input name="test_to"   type="email" required placeholder="you@example.com" class="rounded-full bg-navy-950 border border-white/5 px-4 py-3 text-sm">
    <input name="test_name" placeholder="Your name (optional)" class="rounded-full bg-navy-950 border border-white/5 px-4 py-3 text-sm">
    <button class="px-5 py-3 rounded-full border border-gold-500/40 text-gold-400 hover:bg-gold-500/10 transition text-sm">Send test</button>
  </div>
</form>

<div class="mt-8 border border-white/5 rounded-3xl bg-navy-900/40 p-6">
  <h2 class="font-serif text-2xl text-gold-400">Recent send log</h2>
  <?php if (!$log): ?>
    <p class="text-sm text-beige-100/60 mt-3">No sends recorded yet. Run a test above to populate this log.</p>
  <?php else: ?>
    <table class="mt-4 w-full text-sm">
      <thead class="text-left text-beige-100/50 text-xs uppercase tracking-wider">
        <tr><th class="py-2">When</th><th>To</th><th>Subject</th><th>Driver</th><th>Status</th><th>Error</th></tr>
      </thead>
      <tbody class="divide-y divide-white/5">
        <?php foreach ($log as $l): ?>
          <tr>
            <td class="py-2 text-beige-100/70"><?= e(format_datetime($l['created_at'])) ?></td>
            <td class="text-beige-100/85"><?= e($l['to_email']) ?></td>
            <td><?= e($l['subject']) ?></td>
            <td class="text-beige-100/55"><?= e($l['driver']) ?></td>
            <td>
              <span class="text-xs px-2 py-1 rounded-full <?= $l['status'] === 'sent' ? 'bg-gold-500/20 text-gold-400' : 'bg-red-500/15 text-red-200' ?>">
                <?= e($l['status']) ?>
              </span>
            </td>
            <td class="text-red-200/85 text-xs"><?= e($l['error'] ? mb_strimwidth((string) $l['error'], 0, 200, '…') : '') ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>

<div class="mt-6 max-w-3xl border border-white/5 rounded-3xl p-6 bg-navy-900/40 text-sm text-beige-100/70 leading-relaxed">
  <h3 class="font-serif text-lg text-gold-400">Setting up email on Hostinger</h3>
  <ol class="mt-3 list-decimal pl-5 space-y-2">
    <li>In hPanel → <em>Email Accounts</em>, create a mailbox for <code class="text-gold-400/80">hello@jaemiesoundbath.com</code> (or any address you prefer).</li>
    <li>Use the new mailbox's full email + password as the SMTP credentials above. Host is usually <code class="text-gold-400/80">smtp.hostinger.com</code>, port <code class="text-gold-400/80">587</code> with <em>STARTTLS</em>.</li>
    <li>Set the <em>From address</em> to that same mailbox — most providers reject sends where the sender domain doesn't match the SMTP login.</li>
    <li>Click <strong>Send test</strong> and check the inbox.</li>
    <li>If the test arrives in spam, add <strong>SPF</strong> and <strong>DKIM</strong> DNS records for jaemiesoundbath.com — Hostinger's "Email Deliverability" page has one-click toggles.</li>
  </ol>
</div>

<?php
  $reminderToken = (string) setting('reminder_cron_token', '');
  $reminderUrl   = rtrim((string) config('app.url'), '/') . '/api/send_reminders.php?token=' . urlencode($reminderToken);
?>

<div class="mt-10 max-w-3xl border border-white/5 rounded-3xl p-6 bg-navy-900/40 space-y-3">
  <h2 class="font-serif text-2xl text-gold-400">Booking reminders · cron</h2>
  <p class="text-sm text-beige-100/70 leading-relaxed">
    Automated <em>"your session is tomorrow"</em> and <em>"starting in 2 hours"</em> emails go out when this
    endpoint is hit periodically. Add a cron in Hostinger hPanel → Advanced → Cron Jobs, every 30 minutes:
  </p>
  <?php if ($reminderToken === ''): ?>
    <p class="text-sm text-red-300/80">The reminder token isn't set yet — apply <code>database/migrations/044_phase7_booking_reminders.sql</code> first.</p>
  <?php else: ?>
    <label class="block text-sm">
      <span class="text-[11px] uppercase tracking-widest text-beige-100/60">Cron URL</span>
      <input type="text" readonly value="<?= e($reminderUrl) ?>"
             onclick="this.select()"
             class="mt-2 w-full rounded-2xl bg-navy-950 border border-white/5 px-4 py-3 font-mono text-xs text-beige-100/85">
    </label>
    <p class="text-xs text-beige-100/55">
      Hostinger cron command:
      <code class="block mt-2 p-3 rounded-xl bg-navy-950/70 border border-white/5 text-[11px] break-all">*/30 * * * * /usr/bin/curl -fsS "<?= e($reminderUrl) ?>" &gt;/dev/null 2&gt;&amp;1</code>
    </p>
    <a href="<?= e($reminderUrl) ?>" target="_blank" rel="noopener"
       class="inline-block mt-2 text-xs text-gold-400 hover:text-gold-300">Run once now →</a>
  <?php endif; ?>
</div>

<?php require __DIR__ . '/../includes/admin_layout_end.php'; ?>
