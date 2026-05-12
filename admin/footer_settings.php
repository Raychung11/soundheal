<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_admin();
$pageTitle = 'Footer builder';

$textKeys = [
    // Company
    'company_name'              => 'string',
    'company_legal_name'        => 'string',
    'company_registration_no'   => 'string',
    'company_tagline'           => 'string',
    'company_address_line1'     => 'string',
    'company_address_line2'     => 'string',
    'company_city'              => 'string',
    'company_country'           => 'string',
    'company_phone'             => 'string',
    'company_email'             => 'string',
    'company_billing_email'     => 'string',
    'company_privacy_email'     => 'string',
    // Socials
    'company_social_instagram'  => 'string',
    'company_social_facebook'   => 'string',
    'company_social_tiktok'     => 'string',
    'company_social_youtube'    => 'string',
    'company_social_whatsapp'   => 'string',
    // Footer micro-copy
    'footer_about_blurb'        => 'text',
];

if (is_post()) {
    csrf_verify();
    foreach ($textKeys as $k => $type) {
        if (array_key_exists($k, $_POST)) {
            set_setting($k, is_string($_POST[$k]) ? trim($_POST[$k]) : $_POST[$k], $type);
        }
    }
    set_setting('footer_show_company_block', !empty($_POST['footer_show_company_block']) ? '1' : '0', 'bool');
    set_setting('footer_show_policy_links',  !empty($_POST['footer_show_policy_links'])  ? '1' : '0', 'bool');

    audit_log('footer_settings.update', 'site_settings', null);
    flash('footer', 'Footer saved.', 'success');
    redirect('/admin/footer_settings.php');
}

require __DIR__ . '/../includes/admin_layout.php';

function text_field(string $key, string $label, string $placeholder = '', string $type = 'text'): void
{
    ?>
    <label class="block">
      <span class="text-xs uppercase tracking-widest text-beige-100/60"><?= e($label) ?></span>
      <input type="<?= e($type) ?>" name="<?= e($key) ?>"
             value="<?= e((string) setting($key, '')) ?>"
             placeholder="<?= e($placeholder) ?>"
             class="mt-2 w-full rounded-2xl bg-navy-950 border border-white/5 px-4 py-3">
    </label>
    <?php
}
?>

<div class="flex items-center justify-between gap-4 flex-wrap">
  <div>
    <h1 class="font-serif text-3xl text-beige-100">Footer builder</h1>
    <p class="text-beige-100/60 mt-1 text-sm">Company details, contact, socials and footer copy. Saved instantly to every page.</p>
  </div>
  <a href="<?= url('/public/index.php') ?>" target="_blank" class="text-sm text-gold-400 hover:text-gold-300">View live →</a>
</div>

<form method="post" action="<?= url('/admin/footer_settings.php') ?>" class="mt-8 space-y-8 max-w-5xl">
  <?= csrf_field() ?>

  <!-- Company identity -->
  <section class="border border-white/5 rounded-3xl p-6 bg-navy-900/40 space-y-5">
    <div class="flex items-center justify-between gap-4 flex-wrap">
      <h2 class="font-serif text-2xl text-gold-400">Company identity</h2>
      <label class="flex items-center gap-2 text-sm text-beige-100/70">
        <input type="checkbox" name="footer_show_company_block" value="1" <?= setting('footer_show_company_block', true) ? 'checked' : '' ?>>
        Show company block in footer
      </label>
    </div>

    <div class="grid sm:grid-cols-2 gap-5">
      <?php text_field('company_name',             'Brand name',          'SoundHeal'); ?>
      <?php text_field('company_tagline',          'Tagline',             'Wellness Operating System'); ?>
      <?php text_field('company_legal_name',       'Legal entity name',   'SoundHeal Wellness Sdn. Bhd.'); ?>
      <?php text_field('company_registration_no',  'Registration number', '202401012345 (1234567-X)'); ?>
    </div>

    <label class="block">
      <span class="text-xs uppercase tracking-widest text-beige-100/60">About blurb (small footer paragraph)</span>
      <textarea name="footer_about_blurb" rows="3" class="mt-2 w-full rounded-2xl bg-navy-950 border border-white/5 px-4 py-3"><?= e((string) setting('footer_about_blurb', '')) ?></textarea>
    </label>
  </section>

  <!-- Address + contact -->
  <section class="border border-white/5 rounded-3xl p-6 bg-navy-900/40 space-y-5">
    <h2 class="font-serif text-2xl text-gold-400">Address &amp; contact</h2>

    <div class="grid sm:grid-cols-2 gap-5">
      <?php text_field('company_address_line1', 'Address line 1', 'No. 12, Jalan Sanctuary'); ?>
      <?php text_field('company_address_line2', 'Address line 2', 'Bangsar'); ?>
      <?php text_field('company_city',          'City / postcode', 'Kuala Lumpur 59100'); ?>
      <?php text_field('company_country',       'Country',         'Malaysia'); ?>
      <?php text_field('company_phone',         'Phone',           '+60 3-1234 5678', 'tel'); ?>
      <?php text_field('company_email',         'General email',   'hello@soundheal.com', 'email'); ?>
      <?php text_field('company_billing_email', 'Billing email',   'billing@soundheal.com', 'email'); ?>
      <?php text_field('company_privacy_email', 'Privacy email',   'privacy@soundheal.com', 'email'); ?>
    </div>
  </section>

  <!-- Socials -->
  <section class="border border-white/5 rounded-3xl p-6 bg-navy-900/40 space-y-5">
    <h2 class="font-serif text-2xl text-gold-400">Social links</h2>
    <p class="text-xs text-beige-100/50">Leave any field blank to hide that icon. Use the full URL.</p>
    <div class="grid sm:grid-cols-2 gap-5">
      <?php text_field('company_social_instagram','Instagram URL',    'https://instagram.com/soundheal',    'url'); ?>
      <?php text_field('company_social_facebook', 'Facebook URL',     'https://facebook.com/soundheal',     'url'); ?>
      <?php text_field('company_social_tiktok',   'TikTok URL',       'https://tiktok.com/@soundheal',      'url'); ?>
      <?php text_field('company_social_youtube',  'YouTube URL',      'https://youtube.com/@soundheal',     'url'); ?>
      <?php text_field('company_social_whatsapp', 'WhatsApp link',    'https://wa.me/60123456789',          'url'); ?>
    </div>
  </section>

  <!-- Policy visibility -->
  <section class="border border-white/5 rounded-3xl p-6 bg-navy-900/40 space-y-5">
    <h2 class="font-serif text-2xl text-gold-400">Footer links</h2>
    <label class="flex items-center gap-2 text-sm text-beige-100/80">
      <input type="checkbox" name="footer_show_policy_links" value="1" <?= setting('footer_show_policy_links', true) ? 'checked' : '' ?>>
      Show Terms / Privacy / Refund links in the footer (recommended)
    </label>
    <p class="text-xs text-beige-100/50">Edit the policy bodies in <a href="<?= url('/admin/legal_settings.php') ?>" class="text-gold-400/80 hover:text-gold-300">Legal pages</a>.</p>
  </section>

  <div class="flex gap-3">
    <button class="px-6 py-3 rounded-full bg-gold-500 text-navy-950 hover:bg-gold-400 transition">Save footer</button>
    <a href="<?= url('/admin/dashboard.php') ?>" class="px-6 py-3 rounded-full border border-white/10 text-beige-100/70 hover:border-white/20">Done</a>
  </div>
</form>

<?php require __DIR__ . '/../includes/admin_layout_end.php'; ?>
