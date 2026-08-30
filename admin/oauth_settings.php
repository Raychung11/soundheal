<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_admin();
$pageTitle = 'Sign-in providers';

if (is_post()) {
    csrf_verify();
    set_setting('oauth_google_enabled',       !empty($_POST['oauth_google_enabled']) ? '1' : '0', 'bool');
    set_setting('oauth_google_client_id',     trim((string) input('oauth_google_client_id', '')),     'text');
    set_setting('oauth_google_client_secret', trim((string) input('oauth_google_client_secret', '')), 'text');
    audit_log('oauth_settings.update', 'site_settings', null);
    flash('oauth', 'Sign-in provider settings saved.', 'success');
    redirect('/admin/oauth_settings.php');
}

$cfg        = oauth_google_config();
$configured = $cfg['client_id'] !== '' && $cfg['client_secret'] !== '';
$ready      = oauth_google_ready();

// Simple activity — how many members currently have a Google link.
$linkedCount = 0;
try {
    $linkedCount = (int) db()->query("SELECT COUNT(*) FROM users WHERE google_sub IS NOT NULL")->fetchColumn();
} catch (Throwable $e) { /* pre-migration — ignore */ }

require __DIR__ . '/../includes/admin_layout.php';
?>

<div class="flex items-center justify-between gap-4 flex-wrap">
  <div>
    <h1 class="font-serif text-3xl text-beige-100">Sign-in providers</h1>
    <p class="text-beige-100/60 mt-1 text-sm">Let members sign in with their existing accounts instead of a new password.</p>
  </div>
  <span class="text-xs uppercase tracking-widest px-3 py-1.5 rounded-full <?= $ready ? 'bg-gold-500/20 text-gold-400 border border-gold-500/40' : ($configured ? 'border border-gold-500/40 text-gold-400' : 'border border-white/10 text-beige-100/60') ?>">
    <?php if ($ready): ?>Live on login screen<?php elseif ($configured): ?>Configured · not enabled<?php else: ?>Not configured<?php endif; ?>
  </span>
</div>

<?php if ($f = flash('oauth')): ?>
  <div class="mt-6 border rounded-2xl px-5 py-4 text-sm <?= ($f['type'] ?? 'info') === 'success' ? 'border-gold-500/40 bg-gold-500/5 text-gold-400' : 'border-white/10 bg-navy-900/40 text-beige-100/85' ?>"><?= e($f['message'] ?? '') ?></div>
<?php endif; ?>

<div class="mt-8 grid lg:grid-cols-[1fr_320px] gap-8">
  <form method="post" class="border border-white/5 rounded-3xl bg-navy-900/40 p-6 space-y-6">
    <?= csrf_field() ?>

    <div class="flex items-start gap-4">
      <div class="w-12 h-12 rounded-2xl bg-white/5 flex items-center justify-center shrink-0">
        <!-- Google G mark, inline SVG to avoid external asset. -->
        <svg class="h-6 w-6" viewBox="0 0 48 48">
          <path fill="#4285F4" d="M45.12 24.5c0-1.56-.14-3.06-.4-4.5H24v8.51h11.84c-.51 2.75-2.06 5.08-4.39 6.64v5.52h7.11c4.16-3.83 6.56-9.47 6.56-16.17z"/>
          <path fill="#34A853" d="M24 46c5.94 0 10.92-1.97 14.56-5.33l-7.11-5.52c-1.97 1.32-4.49 2.1-7.45 2.1-5.73 0-10.58-3.87-12.31-9.07H4.34v5.7C7.96 41.07 15.4 46 24 46z"/>
          <path fill="#FBBC05" d="M11.69 28.18c-.44-1.32-.69-2.73-.69-4.18s.25-2.86.69-4.18v-5.7H4.34C2.86 17.09 2 20.45 2 24s.86 6.91 2.34 9.88l7.35-5.7z"/>
          <path fill="#EA4335" d="M24 10.75c3.23 0 6.13 1.11 8.42 3.29l6.31-6.31C34.91 4.18 29.93 2 24 2 15.4 2 7.96 6.93 4.34 14.12l7.35 5.7C13.42 14.62 18.27 10.75 24 10.75z"/>
        </svg>
      </div>
      <div class="flex-1">
        <h2 class="font-serif text-2xl text-gold-400">Google</h2>
        <p class="text-sm text-beige-100/60 mt-1">Members tap "Continue with Google" and land in their sanctuary. Existing accounts on the same email auto-link on first sign-in.</p>
      </div>
    </div>

    <label class="flex items-center gap-3 border border-white/5 rounded-2xl px-4 py-3 bg-navy-950/40">
      <input type="checkbox" name="oauth_google_enabled" value="1" <?= $cfg['enabled'] ? 'checked' : '' ?>
             class="w-4 h-4 rounded border-white/20 bg-navy-950">
      <span class="text-sm text-beige-100">Enable "Continue with Google" on the login and register pages</span>
    </label>

    <label class="block">
      <span class="text-xs uppercase tracking-widest text-beige-100/60">Client ID</span>
      <input name="oauth_google_client_id" value="<?= e((string)$cfg['client_id']) ?>"
             placeholder="123456-abc.apps.googleusercontent.com"
             class="mt-2 w-full rounded-2xl bg-navy-950 border border-white/5 px-4 py-3 font-mono text-sm focus:border-gold-500/50 focus:outline-none">
    </label>

    <label class="block">
      <span class="text-xs uppercase tracking-widest text-beige-100/60">Client secret</span>
      <input name="oauth_google_client_secret" type="password" value="<?= e((string)$cfg['client_secret']) ?>"
             placeholder="Paste the secret from your Google OAuth client"
             autocomplete="new-password"
             class="mt-2 w-full rounded-2xl bg-navy-950 border border-white/5 px-4 py-3 font-mono text-sm focus:border-gold-500/50 focus:outline-none">
      <span class="text-[11px] text-beige-100/45 mt-1 block">Stored on your site only — never shared with the browser.</span>
    </label>

    <button class="px-6 py-3 rounded-full bg-gold-500 text-navy-950 hover:bg-gold-400 transition text-sm font-medium">
      Save Google settings
    </button>
  </form>

  <aside class="space-y-6">
    <div class="border border-white/5 rounded-3xl bg-navy-900/40 p-6">
      <p class="text-xs uppercase tracking-widest text-beige-100/50">Your redirect URI</p>
      <p class="text-[11px] text-beige-100/45 mt-2">Paste this into your Google Cloud OAuth 2.0 Client — Authorised redirect URIs. It must match exactly, including the scheme.</p>
      <div class="mt-3 rounded-2xl bg-navy-950 border border-white/10 p-3 font-mono text-[12px] text-gold-400 break-all"
           x-data="{ copied: false }">
        <span id="redirectUri"><?= e($cfg['redirect_uri']) ?></span>
        <button type="button"
                @click="navigator.clipboard.writeText(document.getElementById('redirectUri').innerText); copied = true; setTimeout(() => copied = false, 1600)"
                class="mt-3 block text-[11px] uppercase tracking-widest text-beige-100/60 hover:text-gold-400">
          <span x-show="!copied">Copy</span>
          <span x-show="copied" x-cloak class="text-gold-400">Copied ✓</span>
        </button>
      </div>
    </div>

    <div class="border border-white/5 rounded-3xl bg-navy-900/40 p-6">
      <p class="text-xs uppercase tracking-widest text-beige-100/50">How to get credentials</p>
      <ol class="mt-3 text-sm text-beige-100/75 space-y-2 list-decimal list-inside leading-relaxed">
        <li>Open <a href="https://console.cloud.google.com/apis/credentials" target="_blank" class="text-gold-400 hover:text-gold-300">Google Cloud Console → Credentials</a>.</li>
        <li>Create OAuth client ID · type <em>Web application</em>.</li>
        <li>Paste the redirect URI above into <em>Authorised redirect URIs</em>.</li>
        <li>Copy the Client ID + Secret here and save.</li>
        <li>Tick <em>Enable</em> above to switch the button on.</li>
      </ol>
    </div>

    <div class="border border-white/5 rounded-3xl bg-navy-900/40 p-6">
      <p class="text-xs uppercase tracking-widest text-beige-100/50">Linked members</p>
      <p class="mt-2 font-serif text-3xl text-beige-100"><?= (int) $linkedCount ?></p>
      <p class="text-[11px] text-beige-100/45 mt-1">members currently signing in with Google.</p>
    </div>
  </aside>
</div>

<?php require __DIR__ . '/../includes/admin_layout_end.php'; ?>
