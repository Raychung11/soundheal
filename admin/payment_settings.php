<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_admin();
$pageTitle = 'Payment settings';

$message = null;
$messageType = 'info';

if (is_post()) {
    csrf_verify();

    if (input('action') === 'test') {
        // Test the API key by hitting Billplz GET /collections/{id}
        $cfg = payment_config();
        if ($cfg['api_key'] === '' || $cfg['collection_id'] === '') {
            $message = 'Add your API key and Collection ID before testing.';
            $messageType = 'error';
        } else {
            $ch = curl_init($cfg['api_base'] . '/collections/' . rawurlencode($cfg['collection_id']));
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_USERPWD        => $cfg['api_key'] . ':',
                CURLOPT_TIMEOUT        => 15,
            ]);
            $response = curl_exec($ch);
            $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($http >= 200 && $http < 300) {
                $coll = json_decode((string) $response, true);
                $title = $coll['title'] ?? $coll['id'] ?? 'OK';
                $message = 'Connected to Billplz ' . ($cfg['sandbox'] ? 'sandbox' : 'live') . ' — collection "' . $title . '" is live.';
                $messageType = 'success';
            } elseif ($http === 401) {
                $mode = $cfg['sandbox'] ? 'SANDBOX (billplz-sandbox.com)' : 'LIVE (billplz.com)';
                $other = $cfg['sandbox'] ? 'live'    : 'sandbox';
                $otherUrl = $cfg['sandbox'] ? 'https://www.billplz.com' : 'https://www.billplz-sandbox.com';
                $message = 'Billplz rejected the credentials (401). You are currently testing against ' . $mode . '. '
                         . 'The most common cause is a key/environment mismatch: '
                         . 'a key generated at ' . $otherUrl . ' will only work after you '
                         . ($cfg['sandbox'] ? 'untick' : 'tick')
                         . ' Sandbox mode and use a Collection ID from the ' . $other . ' environment too. '
                         . 'Also check there is no whitespace around the key.';
                $messageType = 'error';
            } elseif ($http === 404) {
                $message = 'Billplz returned 404 — the API key authenticated, but Collection ID "' . $cfg['collection_id']
                         . '" was not found in ' . ($cfg['sandbox'] ? 'sandbox' : 'live') . '. '
                         . 'Either re-create the collection in this environment or switch the Sandbox toggle to match.';
                $messageType = 'error';
            } else {
                $message = 'Billplz returned HTTP ' . $http . '. Double-check the API key and Collection ID. Response: '
                         . substr((string) $response, 0, 240);
                $messageType = 'error';
            }
            audit_log('payment.test', 'site_settings', null, ['http' => $http]);
        }
    } else {
        // Save form values.
        set_setting('billplz_sandbox',       !empty($_POST['billplz_sandbox']) ? '1' : '0', 'bool');
        set_setting('billplz_api_key',       trim((string) input('billplz_api_key', '')),       'string');
        set_setting('billplz_collection_id', trim((string) input('billplz_collection_id', '')), 'string');
        set_setting('billplz_x_signature',   trim((string) input('billplz_x_signature', '')),   'string');
        set_setting('billplz_redirect_url',  trim((string) input('billplz_redirect_url', '')),  'string');

        audit_log('payment_settings.update', 'site_settings', null);
        flash('payment', 'Payment settings saved.', 'success');
        redirect('/admin/payment_settings.php');
    }
}

$cfg = payment_config();
$configured = $cfg['api_key'] !== '' && $cfg['collection_id'] !== '';

// Recent payment activity for the status panel.
$recent = [];
try {
    $recent = db()->query(
        "SELECT id, purpose, reference_id, gateway_bill_id, amount, currency, status, created_at, paid_at
         FROM payments
         ORDER BY created_at DESC
         LIMIT 10"
    )->fetchAll();
} catch (Throwable $e) { /* table missing — ignore */ }

require __DIR__ . '/../includes/admin_layout.php';
?>

<div class="flex items-center justify-between gap-4 flex-wrap">
  <div>
    <h1 class="font-serif text-3xl text-beige-100">Payment settings · Billplz</h1>
    <p class="text-beige-100/60 mt-1 text-sm">Live keys, sandbox toggle, and webhook configuration.</p>
  </div>
  <span class="text-xs uppercase tracking-widest px-3 py-1.5 rounded-full <?= $configured
      ? ($cfg['sandbox'] ? 'border border-gold-500/40 text-gold-400' : 'bg-gold-500/20 text-gold-400 border border-gold-500/40')
      : 'border border-red-400/40 text-red-300/80' ?>">
    <?php if (!$configured): ?>
      Not configured
    <?php elseif ($cfg['sandbox']): ?>
      Sandbox mode
    <?php else: ?>
      Live mode
    <?php endif; ?>
  </span>
</div>

<?php if ($message !== null): ?>
  <div class="mt-6 border rounded-2xl px-5 py-4 text-sm <?= $messageType === 'success' ? 'border-gold-500/40 bg-gold-500/5 text-gold-400'
                                                                : ($messageType === 'error' ? 'border-red-400/40 bg-red-500/5 text-red-200'
                                                                : 'border-white/10 bg-navy-900/40 text-beige-100/85') ?>">
    <?= e($message) ?>
  </div>
<?php endif; ?>

<!-- Webhook URL panel -->
<div class="mt-8 border border-white/5 rounded-3xl bg-navy-900/40 p-6">
  <h2 class="font-serif text-2xl text-gold-400">Webhook URL</h2>
  <p class="text-sm text-beige-100/70 mt-2 leading-relaxed">In your Billplz dashboard → <em>Settings</em> → <em>Profile</em> → <em>Callback URL</em>, paste the URL below. This is where Billplz pings us when a payment is paid or failed.</p>
  <div class="mt-4 flex flex-wrap items-center gap-3">
    <code class="px-3 py-2 rounded-full bg-navy-950 border border-white/5 text-gold-400 text-sm break-all"><?= e($cfg['callback_url']) ?></code>
    <button type="button" class="text-xs text-beige-100/60 hover:text-gold-400 px-3 py-1.5 rounded-full border border-white/10"
            onclick="navigator.clipboard.writeText('<?= e($cfg['callback_url']) ?>'); this.textContent='Copied'; setTimeout(()=>this.textContent='Copy', 1800)">Copy</button>
  </div>
</div>

<form method="post" action="<?= url('/admin/payment_settings.php') ?>" class="mt-8 space-y-6 max-w-3xl border border-white/5 rounded-3xl p-6 bg-navy-900/40">
  <?= csrf_field() ?>

  <div class="flex items-center justify-between gap-4 flex-wrap">
    <h2 class="font-serif text-2xl text-gold-400">Credentials</h2>
    <label class="inline-flex items-center gap-3 px-4 py-2 rounded-full border border-white/10 text-sm text-beige-100/80">
      <input type="checkbox" name="billplz_sandbox" value="1" <?= $cfg['sandbox'] ? 'checked' : '' ?> class="accent-gold-500">
      Sandbox mode
    </label>
  </div>

  <label class="block">
    <span class="text-xs uppercase tracking-widest text-beige-100/60">API key</span>
    <input name="billplz_api_key" type="text" autocomplete="off" spellcheck="false"
           value="<?= e($cfg['api_key']) ?>"
           placeholder="e.g. 1c1a8baa-1c1a-1c1a-1c1a-1c1a8baa1c1a"
           class="mt-2 w-full rounded-2xl bg-navy-950 border border-white/5 px-4 py-3 font-mono text-sm">
    <span class="text-[11px] text-beige-100/40 mt-1 block">Billplz dashboard → <em>Settings</em> → <em>Account Settings</em> → <em>API Key</em>.</span>
  </label>

  <label class="block">
    <span class="text-xs uppercase tracking-widest text-beige-100/60">Collection ID</span>
    <input name="billplz_collection_id" type="text" autocomplete="off" spellcheck="false"
           value="<?= e($cfg['collection_id']) ?>"
           placeholder="e.g. 8nqxxxxx"
           class="mt-2 w-full rounded-2xl bg-navy-950 border border-white/5 px-4 py-3 font-mono text-sm">
    <span class="text-[11px] text-beige-100/40 mt-1 block">Billplz dashboard → <em>Billing</em> → <em>Collections</em> → pick or create one (e.g. "jaemie sound bath bookings") and copy the ID from the URL or summary panel.</span>
  </label>

  <label class="block">
    <span class="text-xs uppercase tracking-widest text-beige-100/60">X-Signature key</span>
    <input name="billplz_x_signature" type="text" autocomplete="off" spellcheck="false"
           value="<?= e($cfg['x_signature']) ?>"
           placeholder="(optional but strongly recommended)"
           class="mt-2 w-full rounded-2xl bg-navy-950 border border-white/5 px-4 py-3 font-mono text-sm">
    <span class="text-[11px] text-beige-100/40 mt-1 block">Billplz dashboard → <em>Settings</em> → <em>Profile</em> → <em>X-Signature Key for Callback URL</em>. We use this to verify that incoming webhooks really come from Billplz.</span>
  </label>

  <label class="block">
    <span class="text-xs uppercase tracking-widest text-beige-100/60">Redirect URL after payment (optional)</span>
    <input name="billplz_redirect_url" type="url"
           value="<?= e(setting('billplz_redirect_url', '')) ?>"
           placeholder="<?= e(rtrim((string) config('app.url'), '/')) ?>/member/payment_thanks.php"
           class="mt-2 w-full rounded-2xl bg-navy-950 border border-white/5 px-4 py-3 text-sm">
    <span class="text-[11px] text-beige-100/40 mt-1 block">
      Where customers land after paying. Leave blank to use the default
      <code class="text-gold-400/70">/member/payment_thanks.php</code> page,
      which auto-detects paid / pending / failed states and shows the
      booking ticket or membership status.
    </span>
  </label>

  <div class="flex flex-wrap gap-3 pt-2">
    <button class="px-6 py-3 rounded-full bg-gold-500 text-navy-950 hover:bg-gold-400 transition">Save credentials</button>
    <button type="submit" name="action" value="test"
            class="px-6 py-3 rounded-full border border-gold-500/40 text-gold-400 hover:bg-gold-500/10 transition">
      Test connection
    </button>
    <a href="<?= url('/admin/payments.php') ?>" class="px-6 py-3 rounded-full border border-white/10 text-beige-100/70 hover:border-white/20">Payments ledger</a>
  </div>
</form>

<!-- Recent payments quick view -->
<div class="mt-8 border border-white/5 rounded-3xl bg-navy-900/40 p-6">
  <h2 class="font-serif text-2xl text-gold-400">Recent payment activity</h2>
  <?php if (!$recent): ?>
    <p class="text-sm text-beige-100/60 mt-3">No payments recorded yet. The first paid booking or membership will appear here.</p>
  <?php else: ?>
    <table class="mt-4 w-full text-sm">
      <thead class="text-left text-beige-100/50 text-xs uppercase tracking-wider">
        <tr><th class="py-2">When</th><th>Purpose</th><th>Bill ID</th><th>Amount</th><th>Status</th></tr>
      </thead>
      <tbody class="divide-y divide-white/5">
        <?php foreach ($recent as $p): ?>
          <tr>
            <td class="py-2 text-beige-100/70"><?= e(format_datetime($p['created_at'])) ?></td>
            <td><?= e($p['purpose']) ?> #<?= (int) $p['reference_id'] ?></td>
            <td class="font-mono text-xs text-beige-100/65"><?= e($p['gateway_bill_id'] ?? '—') ?></td>
            <td><?= e(format_money((float)$p['amount'], (string)$p['currency'])) ?></td>
            <td>
              <span class="text-xs px-2 py-1 rounded-full <?= $p['status'] === 'paid' ? 'bg-gold-500/20 text-gold-400' : 'bg-white/5 text-beige-100/65' ?>"><?= e($p['status']) ?></span>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>

<?php require __DIR__ . '/../includes/admin_layout_end.php'; ?>
