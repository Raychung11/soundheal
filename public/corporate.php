<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$pageTitle = 'Corporate wellness';
$pageDescription = 'Sound healing and breathwork for teams — on-site or in-studio wellness rituals for offsites, quarters and ongoing employee wellbeing.';

$packages = db()->query(
    "SELECT id, slug, name, tagline, description, seat_count, session_count, price, image
       FROM corporate_packages
      WHERE status = 'active'
      ORDER BY sort_order ASC, id ASC"
)->fetchAll();

$errors = [];
$done   = false;

if (is_post()) {
    csrf_verify();

    $company = trim((string) input('company_name', ''));
    $name    = trim((string) input('contact_name', ''));
    $email   = filter_var(trim((string) input('contact_email', '')), FILTER_VALIDATE_EMAIL);
    $phone   = trim((string) input('contact_phone', ''));
    $team    = trim((string) input('team_size', ''));
    $message = trim((string) input('message', ''));
    $pkgId   = (int) input('package_id', 0);

    if ($company === '')        $errors[] = 'Please share your company name.';
    if ($name === '')           $errors[] = 'Please share your name.';
    if (!$email)                $errors[] = 'Please share a valid email.';
    if (mb_strlen($message) > 2000) $errors[] = 'Please keep your message under 2000 characters.';

    if (!$errors && !throttle('corporate:' . client_ip(), 5, 900)) {
        $errors[] = 'You have submitted a lot recently. Please try again in a few minutes.';
    }

    if (!$errors) {
        // Look up the human-readable package name for the "interest"
        // field so admin/corporate_leads.php shows the ask at a glance
        // even without joining corporate_packages.
        $pkgName = null;
        if ($pkgId > 0) {
            $p = db()->prepare("SELECT name FROM corporate_packages WHERE id = :id AND status = 'active' LIMIT 1");
            $p->execute([':id' => $pkgId]);
            $pkgName = $p->fetchColumn() ?: null;
        }
        db()->prepare(
            "INSERT INTO corporate_inquiries
                (company_name, contact_name, contact_email, contact_phone,
                 team_size, interest, message, package_id, status)
             VALUES (:c, :n, :e, :p, :t, :i, :m, :pid, 'new')"
        )->execute([
            ':c' => $company, ':n' => $name, ':e' => $email,
            ':p' => $phone ?: null, ':t' => $team ?: null,
            ':i' => $pkgName, ':m' => $message ?: null,
            ':pid' => $pkgId > 0 ? $pkgId : null,
        ]);
        audit_log('corporate.inquiry', 'corporate_inquiries', (int) db()->lastInsertId(), ['package_id' => $pkgId]);
        $done = true;
    }
}

$focusPkgId = (int) input('package_id', 0);

require __DIR__ . '/../includes/header.php';
?>

<section class="relative overflow-hidden border-b border-white/5">
  <div class="max-w-5xl mx-auto px-6 py-20 md:py-28">
    <p class="text-gold-400/80 tracking-[0.4em] uppercase text-[11px]">For teams</p>
    <h1 class="font-serif text-5xl md:text-6xl text-beige-100 mt-6 leading-tight">A quieter room, brought to your team.</h1>
    <p class="mt-6 max-w-2xl text-beige-100/70 leading-[1.85] font-light">Sound healing and breathwork held on-site (at your office or a chosen venue) or in-studio — for team offsites, quarter starts, post-launch decompression, and ongoing monthly wellbeing rituals.</p>
  </div>
</section>

<?php if ($packages): ?>
<section class="max-w-6xl mx-auto px-6 py-16">
  <h2 class="font-serif text-3xl md:text-4xl text-beige-100">Packages</h2>
  <p class="mt-3 text-beige-100/60 text-sm max-w-xl">Tap "Enquire" on a package below — we'll follow up within one working day to shape it around your team.</p>

  <div class="mt-10 grid md:grid-cols-2 gap-6">
    <?php foreach ($packages as $pkg): ?>
      <article id="pkg-<?= (int) $pkg['id'] ?>" class="rounded-3xl border border-white/5 bg-navy-900/40 hover:border-gold-500/30 transition overflow-hidden flex flex-col">
        <?php if (!empty($pkg['image'])):
          $img = media_src((string) $pkg['image']);
        ?>
          <div class="relative aspect-[16/9] overflow-hidden">
            <img src="<?= e($img) ?>" alt="<?= e($pkg['name']) ?>" class="w-full h-full object-cover" loading="lazy">
            <div class="absolute inset-0 bg-gradient-to-t from-navy-950/80 to-transparent"></div>
          </div>
        <?php endif; ?>
        <div class="p-6 sm:p-8 flex-1 flex flex-col">
          <h3 class="font-serif text-2xl text-gold-400"><?= e($pkg['name']) ?></h3>
          <?php if (!empty($pkg['tagline'])): ?>
            <p class="mt-2 text-sm text-beige-100/75"><?= e($pkg['tagline']) ?></p>
          <?php endif; ?>
          <div class="mt-4 flex flex-wrap gap-x-5 gap-y-1 text-xs text-beige-100/55">
            <?php if (!empty($pkg['seat_count'])): ?><span>Up to <?= (int) $pkg['seat_count'] ?> pax</span><?php endif; ?>
            <?php if (!empty($pkg['session_count'])): ?><span>· <?= (int) $pkg['session_count'] ?> session<?= (int) $pkg['session_count'] === 1 ? '' : 's' ?></span><?php endif; ?>
          </div>
          <?php if (!empty($pkg['description'])): ?>
            <div class="mt-5 text-beige-100/70 text-sm space-y-3">
              <?= render_rich_text((string) $pkg['description']) ?>
            </div>
          <?php endif; ?>
          <div class="mt-auto pt-6 flex items-end justify-between gap-4 border-t border-white/5 mt-6">
            <div>
              <p class="text-[10px] uppercase tracking-widest text-beige-100/50">From</p>
              <p class="font-serif text-2xl text-gold-400 mt-1">
                <?= $pkg['price'] !== null ? e(format_money((float) $pkg['price'])) : 'On request' ?>
              </p>
            </div>
            <a href="#inquire"
               onclick="document.getElementById('inquiry_package_id').value='<?= (int) $pkg['id'] ?>'"
               class="px-6 py-3 rounded-full bg-gold-500 text-navy-950 font-medium hover:bg-gold-400 transition text-sm whitespace-nowrap">Enquire →</a>
          </div>
        </div>
      </article>
    <?php endforeach; ?>
  </div>
</section>
<?php endif; ?>

<section id="inquire" class="border-t border-white/5 bg-navy-950/40">
  <div class="max-w-3xl mx-auto px-6 py-20">
    <?php if ($done): ?>
      <div class="border border-gold-500/30 rounded-3xl p-10 bg-navy-900/50 text-center">
        <p class="font-serif text-3xl text-beige-100">Thank you.</p>
        <p class="mt-4 text-beige-100/70 leading-relaxed">We've received your enquiry and will reach out within one working day to shape something quiet for your team.</p>
        <a href="<?= url('/public/') ?>" class="inline-block mt-8 px-7 py-3 rounded-full bg-gold-500 text-navy-950 hover:bg-gold-400 transition">Back home</a>
      </div>
    <?php else: ?>
      <p class="text-gold-400/80 tracking-[0.3em] uppercase text-[11px]">Enquire</p>
      <h2 class="font-serif text-3xl md:text-4xl text-beige-100 mt-4">Tell us about your team.</h2>
      <p class="mt-4 text-beige-100/70 max-w-xl">A short note is enough — we'll reply with a proposal shaped around your context. Held in confidence.</p>

      <?php foreach ($errors as $err): ?>
        <p class="mt-4 text-red-300/80 text-sm"><?= e($err) ?></p>
      <?php endforeach; ?>

      <form method="post" class="mt-10 space-y-5">
        <?= csrf_field() ?>
        <input type="hidden" name="package_id" id="inquiry_package_id" value="<?= (int) $focusPkgId ?>">

        <div class="grid sm:grid-cols-2 gap-4">
          <label class="block">
            <span class="text-xs uppercase tracking-widest text-beige-100/60">Company</span>
            <input name="company_name" required maxlength="200" value="<?= e((string) input('company_name', '')) ?>"
                   class="mt-2 w-full rounded-2xl bg-navy-950 border border-white/5 px-4 py-3 focus:border-gold-500/50 focus:outline-none">
          </label>
          <label class="block">
            <span class="text-xs uppercase tracking-widest text-beige-100/60">Your name</span>
            <input name="contact_name" required maxlength="150" value="<?= e((string) input('contact_name', '')) ?>"
                   class="mt-2 w-full rounded-2xl bg-navy-950 border border-white/5 px-4 py-3 focus:border-gold-500/50 focus:outline-none">
          </label>
          <label class="block">
            <span class="text-xs uppercase tracking-widest text-beige-100/60">Email</span>
            <input name="contact_email" type="email" required value="<?= e((string) input('contact_email', '')) ?>"
                   class="mt-2 w-full rounded-2xl bg-navy-950 border border-white/5 px-4 py-3 focus:border-gold-500/50 focus:outline-none">
          </label>
          <label class="block">
            <span class="text-xs uppercase tracking-widest text-beige-100/60">Mobile <span class="text-beige-100/30">(optional)</span></span>
            <input name="contact_phone" type="tel" placeholder="+60…" value="<?= e((string) input('contact_phone', '')) ?>"
                   class="mt-2 w-full rounded-2xl bg-navy-950 border border-white/5 px-4 py-3 focus:border-gold-500/50 focus:outline-none">
          </label>
          <label class="block sm:col-span-2">
            <span class="text-xs uppercase tracking-widest text-beige-100/60">Team size <span class="text-beige-100/30">(rough estimate)</span></span>
            <input name="team_size" maxlength="50" placeholder="e.g. 12–20"
                   value="<?= e((string) input('team_size', '')) ?>"
                   class="mt-2 w-full rounded-2xl bg-navy-950 border border-white/5 px-4 py-3 focus:border-gold-500/50 focus:outline-none">
          </label>
          <label class="block sm:col-span-2">
            <span class="text-xs uppercase tracking-widest text-beige-100/60">Anything you'd like us to know</span>
            <textarea name="message" rows="4" maxlength="2000" placeholder="Context, timing, venue thoughts, or leave blank."
                      class="mt-2 w-full rounded-2xl bg-navy-950 border border-white/5 px-4 py-3 focus:border-gold-500/50 focus:outline-none"><?= e((string) input('message', '')) ?></textarea>
          </label>
        </div>

        <button class="w-full px-6 py-4 rounded-full bg-gold-500 text-navy-950 font-medium hover:bg-gold-400 transition">Send enquiry</button>
      </form>
    <?php endif; ?>
  </div>
</section>

<?php require __DIR__ . '/../includes/footer.php'; ?>
