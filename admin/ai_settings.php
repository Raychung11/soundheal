<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_admin();
$pageTitle = 'Aria · AI settings';

$message = null;
$messageType = 'info';

if (is_post()) {
    csrf_verify();

    if (input('action') === 'test') {
        // Ping OpenAI with a tiny "hello" request to confirm key + model.
        $cfg = ai_config();
        if (($cfg['openai']['api_key'] ?? '') === '') {
            $message = 'Add your OpenAI API key before testing.';
            $messageType = 'error';
        } else {
            $payload = json_encode([
                'model'    => $cfg['openai']['model'],
                'messages' => [
                    ['role' => 'system', 'content' => 'Reply with the single word: ready.'],
                    ['role' => 'user',   'content' => 'Are you there?'],
                ],
                'max_tokens'  => 10,
                'temperature' => 0,
            ]);
            $ch = curl_init($cfg['openai']['base_url'] . '/chat/completions');
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST           => true,
                CURLOPT_POSTFIELDS     => $payload,
                CURLOPT_HTTPHEADER     => [
                    'Content-Type: application/json',
                    'Authorization: Bearer ' . $cfg['openai']['api_key'],
                ],
                CURLOPT_TIMEOUT        => 15,
            ]);
            $response = curl_exec($ch);
            $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($http >= 200 && $http < 300) {
                $data = json_decode((string) $response, true);
                $text = trim((string) ($data['choices'][0]['message']['content'] ?? '(no text)'));
                $message = 'Connected — model "' . $cfg['openai']['model'] . '" replied: ' . $text;
                $messageType = 'success';
            } elseif ($http === 401) {
                $message = 'OpenAI rejected the credentials (401). Double-check the API key (no whitespace, starts with "sk-").';
                $messageType = 'error';
            } elseif ($http === 404) {
                $message = 'OpenAI returned 404 — usually means the model name "' . $cfg['openai']['model'] . '" is not available for this key. Try "gpt-4o-mini" or another model you have access to.';
                $messageType = 'error';
            } else {
                $message = 'OpenAI returned HTTP ' . $http . '. Response: ' . substr((string) $response, 0, 240);
                $messageType = 'error';
            }
            audit_log('ai.test', 'site_settings', null, ['http' => $http]);
        }
    } else {
        set_setting('ai_persona_name',           trim((string) input('ai_persona_name', '')),         'string');
        set_setting('ai_persona_role',           trim((string) input('ai_persona_role', '')),         'string');
        set_setting('ai_system_prompt',          (string) input('ai_system_prompt', ''),              'string');
        set_setting('ai_model',                  trim((string) input('ai_model', '')),                'string');
        set_setting('ai_temperature',            trim((string) input('ai_temperature', '0.6')),       'string');
        set_setting('ai_openai_api_key',         trim((string) input('ai_openai_api_key', '')),       'string');
        set_setting('ai_include_live_offerings', !empty($_POST['ai_include_live_offerings']) ? '1' : '0', 'bool');

        audit_log('ai_settings.update', 'site_settings', null);
        flash('ai', 'Aria settings saved.', 'success');
        redirect('/admin/ai_settings.php');
    }
}

$cfg = ai_config();
$configured = ($cfg['openai']['api_key'] ?? '') !== '';
$promptPreview = aria_system_prompt();

require __DIR__ . '/../includes/admin_layout.php';
?>

<div class="flex items-center justify-between gap-4 flex-wrap">
  <div>
    <h1 class="font-serif text-3xl text-beige-100">Aria · AI settings</h1>
    <p class="text-beige-100/60 mt-1 text-sm">Edit her name, voice, model, and API access. Live offerings (active experiences + class packs) are injected automatically.</p>
  </div>
  <span class="text-xs uppercase tracking-widest px-3 py-1.5 rounded-full <?= $configured
      ? 'bg-gold-500/20 text-gold-400 border border-gold-500/40'
      : 'border border-red-400/40 text-red-300/80' ?>">
    <?= $configured ? 'API connected' : 'No API key' ?>
  </span>
</div>

<?php if ($f = flash('ai')): ?>
  <div class="mt-6 border rounded-2xl px-5 py-4 text-sm <?= ($f['type'] ?? 'info') === 'success' ? 'border-gold-500/40 bg-gold-500/5 text-gold-400' : 'border-white/10 bg-navy-900/40 text-beige-100/85' ?>"><?= e($f['message'] ?? '') ?></div>
<?php endif; ?>

<?php if ($message !== null): ?>
  <div class="mt-6 border rounded-2xl px-5 py-4 text-sm <?= $messageType === 'success' ? 'border-gold-500/40 bg-gold-500/5 text-gold-400'
                                                                  : ($messageType === 'error' ? 'border-red-400/40 bg-red-500/5 text-red-200'
                                                                  : 'border-white/10 bg-navy-900/40 text-beige-100/85') ?>">
    <?= e($message) ?>
  </div>
<?php endif; ?>

<form method="post" class="mt-8 space-y-6 max-w-3xl border border-white/5 rounded-3xl p-6 bg-navy-900/40">
  <?= csrf_field() ?>

  <h2 class="font-serif text-2xl text-gold-400">Persona</h2>
  <div class="grid sm:grid-cols-2 gap-4">
    <label class="block">
      <span class="text-xs uppercase tracking-widest text-beige-100/60">Concierge name</span>
      <input name="ai_persona_name" value="<?= e($cfg['persona']['name']) ?>" placeholder="Aria"
             class="mt-2 w-full rounded-2xl bg-navy-950 border border-white/5 px-4 py-3 focus:border-gold-500/50 focus:outline-none">
    </label>
    <label class="block">
      <span class="text-xs uppercase tracking-widest text-beige-100/60">Role / title</span>
      <input name="ai_persona_role" value="<?= e($cfg['persona']['role']) ?>" placeholder="Calm Wellness Concierge"
             class="mt-2 w-full rounded-2xl bg-navy-950 border border-white/5 px-4 py-3 focus:border-gold-500/50 focus:outline-none">
    </label>
  </div>

  <label class="block">
    <span class="text-xs uppercase tracking-widest text-beige-100/60">System prompt — voice &amp; rules</span>
    <textarea name="ai_system_prompt" rows="14"
              class="mt-2 w-full rounded-2xl bg-navy-950 border border-white/5 px-4 py-3 text-sm font-mono leading-relaxed focus:border-gold-500/50 focus:outline-none"><?= e((string) setting('ai_system_prompt', $cfg['persona']['system_prompt'])) ?></textarea>
    <span class="text-[11px] text-beige-100/40 mt-1 block">A brand preamble ("You are Aria, the Calm Wellness Concierge for <?= e(brand_name()) ?>.") is added automatically, so you can focus on voice and rules here.</span>
  </label>

  <label class="inline-flex items-center gap-3 px-4 py-3 rounded-2xl border border-white/10 text-sm text-beige-100/80">
    <input type="checkbox" name="ai_include_live_offerings" value="1"
           <?= !empty($cfg['include_live_offerings']) ? 'checked' : '' ?> class="accent-gold-500">
    Auto-include active experiences and class packs in her context
  </label>

  <h2 class="font-serif text-2xl text-gold-400 pt-4">OpenAI</h2>

  <label class="block">
    <span class="text-xs uppercase tracking-widest text-beige-100/60">API key</span>
    <input name="ai_openai_api_key" type="password" autocomplete="off" spellcheck="false"
           value="<?= e((string) setting('ai_openai_api_key', '')) ?>"
           placeholder="sk-..."
           class="mt-2 w-full rounded-2xl bg-navy-950 border border-white/5 px-4 py-3 font-mono text-sm">
    <span class="text-[11px] text-beige-100/40 mt-1 block">Stored in <code class="text-gold-400/70">site_settings</code>. Leave blank to fall back to the <code class="text-gold-400/70">OPENAI_API_KEY</code> env var.</span>
  </label>

  <div class="grid sm:grid-cols-2 gap-4">
    <label class="block">
      <span class="text-xs uppercase tracking-widest text-beige-100/60">Model</span>
      <input name="ai_model" value="<?= e((string) setting('ai_model', '')) ?>" placeholder="gpt-4o-mini"
             class="mt-2 w-full rounded-2xl bg-navy-950 border border-white/5 px-4 py-3 font-mono text-sm focus:border-gold-500/50 focus:outline-none">
      <span class="text-[11px] text-beige-100/40 mt-1 block">Currently active: <code class="text-gold-400/70"><?= e($cfg['openai']['model']) ?></code>. Common: <code>gpt-4o-mini</code>, <code>gpt-4o</code>.</span>
    </label>
    <label class="block">
      <span class="text-xs uppercase tracking-widest text-beige-100/60">Temperature (0.0 – 1.0)</span>
      <input name="ai_temperature" type="number" step="0.05" min="0" max="1"
             value="<?= e(number_format((float) $cfg['temperature'], 2)) ?>"
             class="mt-2 w-full rounded-2xl bg-navy-950 border border-white/5 px-4 py-3 focus:border-gold-500/50 focus:outline-none">
      <span class="text-[11px] text-beige-100/40 mt-1 block">Lower = steadier. Higher = more playful.</span>
    </label>
  </div>

  <div class="flex flex-wrap gap-3 pt-2">
    <button class="px-6 py-3 rounded-full bg-gold-500 text-navy-950 hover:bg-gold-400 transition">Save settings</button>
    <button type="submit" name="action" value="test"
            class="px-6 py-3 rounded-full border border-gold-500/40 text-gold-400 hover:bg-gold-500/10 transition">
      Test connection
    </button>
  </div>
</form>

<!-- Preview the resolved prompt Aria will use right now -->
<div class="mt-8 max-w-3xl border border-white/5 rounded-3xl p-6 bg-navy-900/40">
  <h2 class="font-serif text-2xl text-gold-400">Live prompt preview</h2>
  <p class="text-sm text-beige-100/60 mt-2">This is exactly what Aria sees on each call — your settings above plus the brand preamble and the live inventory.</p>
  <pre class="mt-4 rounded-2xl bg-navy-950/70 border border-white/5 p-5 text-xs leading-relaxed text-beige-100/80 whitespace-pre-wrap font-mono max-h-[60vh] overflow-y-auto"><?= e($promptPreview) ?></pre>
</div>

<?php require __DIR__ . '/../includes/admin_layout_end.php'; ?>
