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

// Brand glyphs (single-path, 24x24, fill="currentColor") so the row
// reads as recognisable logos rather than text labels.
$socialIcons = [
    'Instagram' => '<path d="M7 2C4.24 2 2 4.24 2 7v10c0 2.76 2.24 5 5 5h10c2.76 0 5-2.24 5-5V7c0-2.76-2.24-5-5-5H7zm0 2h10c1.66 0 3 1.34 3 3v10c0 1.66-1.34 3-3 3H7c-1.66 0-3-1.34-3-3V7c0-1.66 1.34-3 3-3zm12 1.5c-.83 0-1.5.67-1.5 1.5s.67 1.5 1.5 1.5 1.5-.67 1.5-1.5-.67-1.5-1.5-1.5zM12 7a5 5 0 1 0 0 10 5 5 0 0 0 0-10zm0 2a3 3 0 1 1 0 6 3 3 0 0 1 0-6z"/>',
    'Facebook'  => '<path d="M22 12c0-5.52-4.48-10-10-10S2 6.48 2 12c0 4.84 3.44 8.87 8 9.8V15H8v-3h2V9.5C10 7.57 11.57 6 13.5 6H16v3h-2c-.55 0-1 .45-1 1v2h3v3h-3v6.95c5.05-.5 9-4.76 9-9.95z"/>',
    'TikTok'    => '<path d="M19.59 6.69a4.83 4.83 0 0 1-3.77-4.25V2h-3.45v13.67a2.89 2.89 0 0 1-5.2 1.74 2.89 2.89 0 0 1 2.31-4.64c.298-.001.595.045.88.135V9.4a6.33 6.33 0 0 0-1-.05A6.34 6.34 0 0 0 5.82 20.1a6.34 6.34 0 0 0 10.86-4.43v-7a8.16 8.16 0 0 0 4.77 1.52v-3.4a4.85 4.85 0 0 1-1.86-.1z"/>',
    'YouTube'   => '<path d="M23.5 6.2a3 3 0 0 0-2.1-2.1C19.5 3.6 12 3.6 12 3.6s-7.5 0-9.4.5A3 3 0 0 0 .5 6.2C0 8.1 0 12 0 12s0 3.9.5 5.8a3 3 0 0 0 2.1 2.1c1.9.5 9.4.5 9.4.5s7.5 0 9.4-.5a3 3 0 0 0 2.1-2.1c.5-1.9.5-5.8.5-5.8s0-3.9-.5-5.8zM9.6 15.6V8.4l6.2 3.6-6.2 3.6z"/>',
    'WhatsApp'  => '<path d="M.057 24l1.687-6.163a11.867 11.867 0 0 1-1.587-5.945C.16 5.335 5.495 0 12.05 0a11.817 11.817 0 0 1 8.413 3.488 11.824 11.824 0 0 1 3.48 8.413c-.003 6.557-5.338 11.892-11.893 11.892a11.9 11.9 0 0 1-5.688-1.448L.057 24zm6.597-3.807a9.9 9.9 0 0 0 5.034 1.378h.004c5.45 0 9.886-4.434 9.889-9.885a9.86 9.86 0 0 0-2.895-6.994 9.84 9.84 0 0 0-6.994-2.901c-5.452 0-9.887 4.434-9.889 9.884a9.84 9.84 0 0 0 1.515 5.26l.236.375-1 3.65 3.74-.981.36.214zm9.965-5.45c-.074-.124-.272-.198-.57-.347-.297-.149-1.758-.868-2.031-.967-.272-.099-.47-.149-.668.149-.198.298-.767.967-.94 1.166-.173.198-.347.223-.644.075-.297-.149-1.255-.463-2.39-1.475-.883-.788-1.48-1.762-1.653-2.06-.173-.297-.018-.458.13-.606.134-.133.297-.347.446-.52.149-.173.198-.298.298-.496.099-.198.05-.372-.025-.521-.075-.149-.668-1.611-.916-2.207-.241-.579-.487-.501-.668-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.298-1.04 1.016-1.04 2.479s1.064 2.875 1.213 3.073c.149.198 2.095 3.2 5.076 4.487.71.306 1.263.489 1.694.626.712.226 1.36.194 1.872.118.571-.085 1.758-.719 2.006-1.413.247-.694.247-1.289.173-1.413z"/>',
];

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
                 aria-label="<?= e($name) ?>" title="<?= e($name) ?>"
                 class="inline-flex h-10 w-10 items-center justify-center rounded-full border border-white/10 text-beige-100/75 hover:border-gold-500/40 hover:text-gold-400 transition">
                <?php if (isset($socialIcons[$name])): ?>
                  <svg viewBox="0 0 24 24" class="h-[18px] w-[18px]" fill="currentColor" aria-hidden="true">
                    <?= $socialIcons[$name] ?>
                  </svg>
                <?php else: ?>
                  <span class="text-xs px-1"><?= e($name) ?></span>
                <?php endif; ?>
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
<?php require __DIR__ . '/mobile_nav.php'; ?>
<?php require __DIR__ . '/aria_widget.php'; ?>
<script src="<?= asset('js/app.js') ?>"></script>
</body>
</html>
