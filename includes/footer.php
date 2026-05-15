<?php
$companyName    = brand_name();
$companyTagline = (string) setting('company_tagline',       (string) config('app.tagline'));
$companyLegal   = (string) setting('company_legal_name',    '');
$companyRegNo   = (string) setting('company_registration_no','');
$companyAddr1   = (string) setting('company_address_line1', '');
$companyAddr2   = (string) setting('company_address_line2', '');
$companyCity    = (string) setting('company_city',          '');
$companyCountry = (string) setting('company_country',       '');
$companyPhone   = (string) setting('company_phone',         '');
$companyEmail   = (string) setting('company_email',         '');
$footerBlurb    = (string) setting('footer_about_blurb',    $companyTagline . '. A sanctuary for sound, breath and stillness.');

$socials = array_filter([
    'Instagram' => (string) setting('company_social_instagram', ''),
    'Facebook'  => (string) setting('company_social_facebook',  ''),
    'TikTok'    => (string) setting('company_social_tiktok',    ''),
    'YouTube'   => (string) setting('company_social_youtube',   ''),
    'WhatsApp'  => (string) setting('company_social_whatsapp',  ''),
], fn($v) => trim((string)$v) !== '');

$showCompany = (bool) setting('footer_show_company_block', true);
$showPolicy  = (bool) setting('footer_show_policy_links',  true);
?>
</main>

<footer class="border-t border-white/5 bg-navy-950 mt-24">
  <div class="max-w-6xl mx-auto px-4 py-14 grid md:grid-cols-4 gap-10">

    <!-- Brand + blurb + socials -->
    <div class="md:col-span-2">
      <div class="font-serif text-2xl text-gold-400"><?= e($companyName) ?></div>
      <?php if ($companyTagline !== ''): ?>
        <div class="text-[11px] uppercase tracking-[0.3em] text-beige-200/60 mt-1"><?= e($companyTagline) ?></div>
      <?php endif; ?>
      <p class="text-sm text-beige-100/65 mt-4 leading-relaxed max-w-md"><?= e($footerBlurb) ?></p>

      <?php if ($socials): ?>
        <ul class="mt-5 flex flex-wrap items-center gap-2">
          <?php foreach ($socials as $name => $href): ?>
            <li>
              <a href="<?= e($href) ?>" target="_blank" rel="noopener"
                 class="inline-flex items-center gap-2 px-3 py-1.5 rounded-full border border-white/10 text-beige-100/75 hover:border-gold-500/40 hover:text-gold-400 text-xs transition">
                <?= e($name) ?>
              </a>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </div>

    <!-- Experience links -->
    <div>
      <h4 class="text-[11px] uppercase tracking-[0.3em] text-gold-400/80">Experience</h4>
      <ul class="mt-3 space-y-2 text-sm">
        <li><a class="hover:text-gold-400 text-beige-100/75" href="<?= url('/public/experiences.php') ?>">Experiences</a></li>
        <li><a class="hover:text-gold-400 text-beige-100/75" href="<?= url('/public/events.php') ?>">Upcoming sessions</a></li>
        <li><a class="hover:text-gold-400 text-beige-100/75" href="<?= url('/public/membership.php') ?>">Membership</a></li>
        <li><a class="hover:text-gold-400 text-beige-100/75" href="<?= url('/public/about.php') ?>">About</a></li>
        <li><a class="hover:text-gold-400 text-beige-100/75" href="<?= url('/public/contact.php') ?>">Contact</a></li>
      </ul>
    </div>

    <!-- Company block -->
    <div>
      <?php if ($showCompany && ($companyAddr1 || $companyPhone || $companyEmail)): ?>
        <h4 class="text-[11px] uppercase tracking-[0.3em] text-gold-400/80">Company</h4>
        <address class="not-italic mt-3 space-y-1 text-sm text-beige-100/70 leading-relaxed">
          <?php if ($companyLegal !== ''): ?><div class="text-beige-100/85"><?= e($companyLegal) ?></div><?php endif; ?>
          <?php if ($companyAddr1 !== ''): ?><div><?= e($companyAddr1) ?></div><?php endif; ?>
          <?php if ($companyAddr2 !== ''): ?><div><?= e($companyAddr2) ?></div><?php endif; ?>
          <?php if ($companyCity  !== ''): ?><div><?= e($companyCity) ?></div><?php endif; ?>
          <?php if ($companyCountry !== ''): ?><div><?= e($companyCountry) ?></div><?php endif; ?>
          <?php if ($companyPhone !== ''): ?>
            <div class="mt-2"><a href="tel:<?= e(preg_replace('/[^0-9+]/', '', $companyPhone)) ?>" class="hover:text-gold-400"><?= e($companyPhone) ?></a></div>
          <?php endif; ?>
          <?php if ($companyEmail !== ''): ?>
            <div><a href="mailto:<?= e($companyEmail) ?>" class="hover:text-gold-400"><?= e($companyEmail) ?></a></div>
          <?php endif; ?>
          <?php if ($companyRegNo !== ''): ?>
            <div class="mt-2 text-[11px] text-beige-100/50">Reg. <?= e($companyRegNo) ?></div>
          <?php endif; ?>
        </address>
      <?php else: ?>
        <h4 class="text-[11px] uppercase tracking-[0.3em] text-gold-400/80">Care</h4>
        <p class="text-xs text-beige-100/55 mt-3 leading-relaxed">Our offerings are not medical advice. Please consult qualified professionals for medical or mental-health concerns.</p>
      <?php endif; ?>
    </div>
  </div>

  <div class="border-t border-white/5">
    <div class="max-w-6xl mx-auto px-4 py-5 text-xs text-beige-100/45 flex flex-col sm:flex-row gap-3 sm:items-center sm:justify-between">
      <span>© <?= date('Y') ?> <?= e($companyLegal !== '' ? $companyLegal : $companyName) ?>. All rights reserved.</span>
      <?php if ($showPolicy): ?>
        <nav class="flex flex-wrap gap-x-5 gap-y-2">
          <a href="<?= url('/public/terms.php') ?>"   class="hover:text-gold-400">Terms</a>
          <a href="<?= url('/public/privacy.php') ?>" class="hover:text-gold-400">Privacy</a>
          <a href="<?= url('/public/refund.php') ?>"  class="hover:text-gold-400">Refund</a>
          <a href="<?= url('/public/contact.php') ?>" class="hover:text-gold-400">Contact</a>
        </nav>
      <?php endif; ?>
    </div>
  </div>
</footer>
<?php require __DIR__ . '/aria_widget.php'; ?>
<script src="<?= asset('js/app.js') ?>"></script>
</body>
</html>
