<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_admin();
$pageTitle = 'Legal pages';

$policies = [
    'terms'   => ['Terms of Service', '/public/terms.php'],
    'privacy' => ['Privacy Policy',   '/public/privacy.php'],
    'refund'  => ['Refund Policy',    '/public/refund.php'],
];

if (is_post()) {
    csrf_verify();
    $which = (string) input('which', '');
    if (!isset($policies[$which])) {
        flash('legal', 'Unknown policy.', 'error');
        redirect('/admin/legal_settings.php');
    }

    $oldBody = (string) setting('legal_' . $which . '_body', '');
    $newBody = (string) input('body', '');
    $title   = trim((string) input('title', $policies[$which][0]));
    $updated = trim((string) input('updated_at', ''));

    set_setting('legal_' . $which . '_title', $title, 'string');
    set_setting('legal_' . $which . '_body',  $newBody, 'text');

    // Bump the "last updated" date automatically when the body changed,
    // unless the admin typed one explicitly.
    if ($updated !== '') {
        set_setting('legal_' . $which . '_updated_at', $updated, 'string');
    } elseif ($oldBody !== $newBody) {
        set_setting('legal_' . $which . '_updated_at', date('Y-m-d'), 'string');
    }

    audit_log('legal.update', 'site_settings', null, ['policy' => $which]);
    flash('legal', $policies[$which][0] . ' saved.', 'success');
    redirect('/admin/legal_settings.php?which=' . $which);
}

$which = (string) input('which', 'terms');
if (!isset($policies[$which])) $which = 'terms';
$policyTitle    = (string) setting('legal_' . $which . '_title', $policies[$which][0]);
$policyUpdated  = (string) setting('legal_' . $which . '_updated_at', date('Y-m-d'));
$policyBody     = (string) setting('legal_' . $which . '_body', '');

require __DIR__ . '/../includes/admin_layout.php';
?>

<div class="flex items-center justify-between gap-4 flex-wrap">
  <div>
    <h1 class="font-serif text-3xl text-beige-100">Legal pages</h1>
    <p class="text-beige-100/60 mt-1 text-sm">Terms of Service · Privacy Policy · Refund Policy. Have these reviewed by qualified legal counsel before launch.</p>
  </div>
  <a href="<?= url($policies[$which][1]) ?>" target="_blank" class="text-sm text-gold-400 hover:text-gold-300">View live →</a>
</div>

<div class="mt-6 flex flex-wrap gap-2">
  <?php foreach ($policies as $key => [$label, $path]): ?>
    <a href="<?= url('/admin/legal_settings.php?which=' . $key) ?>"
       class="px-4 py-2 rounded-full text-sm <?= $which === $key ? 'bg-gold-500 text-navy-950' : 'border border-white/10 text-beige-100/70 hover:border-gold-500/40 hover:text-gold-400' ?> transition">
      <?= e($label) ?>
    </a>
  <?php endforeach; ?>
</div>

<form method="post" action="<?= url('/admin/legal_settings.php') ?>" class="mt-8 space-y-5 max-w-4xl border border-white/5 rounded-3xl p-6 bg-navy-900/40">
  <?= csrf_field() ?>
  <input type="hidden" name="which" value="<?= e($which) ?>">

  <div class="grid sm:grid-cols-[1fr_220px] gap-5">
    <label class="block">
      <span class="text-xs uppercase tracking-widest text-beige-100/60">Page title</span>
      <input name="title" value="<?= e($policyTitle) ?>" class="mt-2 w-full rounded-2xl bg-navy-950 border border-white/5 px-4 py-3 font-serif text-lg">
    </label>
    <label class="block">
      <span class="text-xs uppercase tracking-widest text-beige-100/60">Last updated</span>
      <input name="updated_at" type="date" value="<?= e($policyUpdated) ?>" class="mt-2 w-full rounded-2xl bg-navy-950 border border-white/5 px-4 py-3">
    </label>
  </div>

  <label class="block">
    <span class="text-xs uppercase tracking-widest text-beige-100/60">Body</span>
    <textarea name="body" rows="22" class="mt-2 w-full rounded-2xl bg-navy-950 border border-white/5 px-4 py-3 font-mono text-[13px] leading-relaxed"><?= e($policyBody) ?></textarea>
    <span class="text-[11px] text-beige-100/40 mt-2 block">
      HTML is allowed. Use <code class="text-gold-400/70">&lt;h2&gt;</code>, <code class="text-gold-400/70">&lt;p&gt;</code>, <code class="text-gold-400/70">&lt;ul&gt;&lt;li&gt;</code>, <code class="text-gold-400/70">&lt;a&gt;</code>, <code class="text-gold-400/70">&lt;strong&gt;</code>, <code class="text-gold-400/70">&lt;em&gt;</code>. The page styles them automatically.
    </span>
  </label>

  <div class="flex gap-3">
    <button class="px-6 py-3 rounded-full bg-gold-500 text-navy-950 hover:bg-gold-400 transition">Save policy</button>
    <a href="<?= url($policies[$which][1]) ?>" target="_blank" class="px-6 py-3 rounded-full border border-white/10 text-beige-100/70 hover:border-white/20">Preview</a>
  </div>
</form>

<?php require __DIR__ . '/../includes/admin_layout_end.php'; ?>
