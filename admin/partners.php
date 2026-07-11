<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_admin();
$pageTitle = 'Partners';

$errors = [];

if (is_post()) {
    csrf_verify();
    $action = (string) input('action');

    if ($action === 'save') {
        $id             = (int) input('id', 0);
        $name           = trim((string) input('name', ''));
        $slug           = strtolower(trim((string) input('slug', '')));
        $contactName    = trim((string) input('contact_name', ''));
        $contactEmail   = trim((string) input('contact_email', ''));
        $contactPhone   = trim((string) input('contact_phone', ''));
        $commissionType = in_array(input('commission_type'), ['fixed','percent'], true) ? input('commission_type') : 'fixed';
        $commissionRate = max(0.0, (float) input('commission_rate', 10.00));
        $firstPromo     = strtoupper(trim((string) input('first_visit_promo_code', '')));
        $landingPath    = trim((string) input('landing_path', '/public/events.php'));
        $status         = in_array(input('status'), ['active','inactive'], true) ? input('status') : 'active';
        $notes          = trim((string) input('notes', ''));
        $logoUrl        = trim((string) input('logo_url', ''));
        $coverImage     = trim((string) input('cover_image', ''));

        // Image uploads — either or both files are optional. The file
        // input wins when both a file and a URL are submitted; a URL
        // pasted alone is kept as-is (useful for hosted assets).
        try {
            if (($uploadedLogo = handle_upload('logo_file', 'partners')) !== null) {
                if ($logoUrl !== '' && str_starts_with($logoUrl, '/uploads/')) {
                    delete_upload($logoUrl);
                }
                $logoUrl = $uploadedLogo;
            }
            if (($uploadedCover = handle_upload('cover_file', 'partners')) !== null) {
                if ($coverImage !== '' && str_starts_with($coverImage, '/uploads/')) {
                    delete_upload($coverImage);
                }
                $coverImage = $uploadedCover;
            }
        } catch (RuntimeException $e) {
            $errors[] = $e->getMessage();
        }
        // Explicit remove checkboxes so admins can clear an image
        // without waiting for a save-with-new-file replacement.
        if (!empty($_POST['remove_logo']) && $logoUrl !== '') {
            if (str_starts_with($logoUrl, '/uploads/')) delete_upload($logoUrl);
            $logoUrl = '';
        }
        if (!empty($_POST['remove_cover']) && $coverImage !== '') {
            if (str_starts_with($coverImage, '/uploads/')) delete_upload($coverImage);
            $coverImage = '';
        }

        // Public-listing fields (surface on /public/partners.php).
        $showPublic  = !empty($_POST['show_on_public_page']) ? 1 : 0;
        $category    = trim((string) input('category', ''));
        $description = trim((string) input('description', ''));
        $websiteUrl  = trim((string) input('website_url', ''));
        $sortOrder   = (int) input('sort_order', 100);
        if ($websiteUrl !== '' && !preg_match('~^https?://~', $websiteUrl)) {
            $websiteUrl = 'https://' . $websiteUrl;
        }

        if ($name === '') $errors[] = 'Partner name is required.';
        if ($slug === '') $slug = generate_partner_slug($name);
        elseif (!preg_match('/^[a-z0-9\-]{2,80}$/', $slug)) $errors[] = 'Slug must be 2–80 chars, lowercase letters/numbers/hyphens.';
        if ($contactEmail !== '' && !filter_var($contactEmail, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Contact email doesn\'t look valid.';
        }
        if ($landingPath === '' || $landingPath[0] !== '/') {
            $errors[] = 'Landing path must start with "/" — e.g. /public/events.php or /public/experiences/human-pet-co-resonance-workshop.';
        }
        // If a promo code is set, make sure it exists.
        if ($firstPromo !== '') {
            $chk = db()->prepare("SELECT 1 FROM promo_codes WHERE code = :c LIMIT 1");
            $chk->execute([':c' => $firstPromo]);
            if (!$chk->fetchColumn()) {
                $errors[] = 'Promo code "' . $firstPromo . '" doesn\'t exist yet. Create it under Growth → Promo codes first.';
            }
        }

        if (!$errors) {
            try {
                if ($id > 0) {
                    db()->prepare(
                        "UPDATE partners
                            SET name = :name, slug = :slug, contact_name = :cn,
                                contact_email = :ce, contact_phone = :cp,
                                commission_type = :ct, commission_rate = :cr,
                                first_visit_promo_code = :fp, landing_path = :lp,
                                status = :status, notes = :notes,
                                logo_url = :logo, cover_image = :cov,
                                show_on_public_page = :spp, category = :cat,
                                description = :desc, website_url = :web,
                                sort_order = :so
                          WHERE id = :id"
                    )->execute([
                        ':name' => $name, ':slug' => $slug,
                        ':cn' => $contactName ?: null, ':ce' => $contactEmail ?: null, ':cp' => $contactPhone ?: null,
                        ':ct' => $commissionType, ':cr' => $commissionRate,
                        ':fp' => $firstPromo ?: null, ':lp' => $landingPath,
                        ':status' => $status, ':notes' => $notes ?: null,
                        ':logo' => $logoUrl ?: null,
                        ':cov'  => $coverImage ?: null,
                        ':spp'  => $showPublic,
                        ':cat'  => $category ?: null,
                        ':desc' => $description ?: null,
                        ':web'  => $websiteUrl ?: null,
                        ':so'   => $sortOrder,
                        ':id' => $id,
                    ]);
                    audit_log('partner.update', 'partners', $id);
                    flash('partner', 'Partner updated.', 'success');
                } else {
                    db()->prepare(
                        "INSERT INTO partners
                            (name, slug, contact_name, contact_email, contact_phone,
                             commission_type, commission_rate, first_visit_promo_code,
                             landing_path, status, notes, logo_url, cover_image,
                             show_on_public_page, category, description, website_url, sort_order,
                             created_by)
                         VALUES (:name, :slug, :cn, :ce, :cp, :ct, :cr, :fp, :lp, :status, :notes, :logo, :cov,
                                 :spp, :cat, :desc, :web, :so,
                                 :u)"
                    )->execute([
                        ':name' => $name, ':slug' => $slug,
                        ':cn' => $contactName ?: null, ':ce' => $contactEmail ?: null, ':cp' => $contactPhone ?: null,
                        ':ct' => $commissionType, ':cr' => $commissionRate,
                        ':fp' => $firstPromo ?: null, ':lp' => $landingPath,
                        ':status' => $status, ':notes' => $notes ?: null,
                        ':logo' => $logoUrl ?: null,
                        ':cov'  => $coverImage ?: null,
                        ':spp'  => $showPublic,
                        ':cat'  => $category ?: null,
                        ':desc' => $description ?: null,
                        ':web'  => $websiteUrl ?: null,
                        ':so'   => $sortOrder,
                        ':u' => current_user_id(),
                    ]);
                    $newId = (int) db()->lastInsertId();
                    audit_log('partner.create', 'partners', $newId, ['slug' => $slug]);
                    flash('partner', 'Partner created. Print the poster and hand it over.', 'success');
                    redirect('/admin/partners.php?edit=' . $newId);
                }
                redirect('/admin/partners.php');
            } catch (Throwable $e) {
                if (str_contains((string) $e->getMessage(), '1062')) {
                    $errors[] = 'That slug is already in use — pick another.';
                } else {
                    $errors[] = 'Could not save: ' . $e->getMessage();
                }
            }
        }
    } elseif ($action === 'toggle') {
        $id = (int) input('id', 0);
        db()->prepare("UPDATE partners SET status = IF(status='active','inactive','active') WHERE id = :id")
            ->execute([':id' => $id]);
        audit_log('partner.toggle', 'partners', $id);
        redirect('/admin/partners.php');
    } elseif ($action === 'settle_payout') {
        $partnerId = (int) input('partner_id', 0);
        $reference = trim((string) input('reference', ''));
        $res = settle_partner_referral_payout($partnerId, $reference, current_user_id());
        if ($res['ok']) {
            audit_log('partner.payout', 'partner_referral_payouts', (int) $res['payout_id'], [
                'partner_id' => $partnerId,
                'amount'     => $res['amount'],
                'count'      => $res['count'],
            ]);
            flash('partner', 'Recorded a payout of ' . format_money((float) $res['amount']) . ' across ' . (int) $res['count'] . ' reward(s).', 'success');
        } else {
            flash('partner', $res['message'] ?? 'Could not record payout.', 'error');
        }
        redirect('/admin/partners.php');
    }
}

$editingId = (int) input('edit', 0);
$editing = $editingId > 0 ? find_partner_by_id($editingId) : null;
$row = $editing ?: [
    'id' => 0, 'name' => '', 'slug' => '',
    'contact_name' => '', 'contact_email' => '', 'contact_phone' => '',
    'commission_type' => 'fixed',
    'commission_rate' => (float) setting('partner_default_commission', 10.00),
    'first_visit_promo_code' => '',
    'landing_path' => '/public/events.php',
    'status' => 'active', 'notes' => '', 'logo_url' => '', 'cover_image' => '',
    'show_on_public_page' => 0, 'category' => '', 'description' => '',
    'website_url' => '', 'sort_order' => 100,
    'scan_count' => 0, 'last_scan_at' => null,
];

$partners = db()->query(
    "SELECT p.*,
            COALESCE((SELECT SUM(amount) FROM partner_referrals r
                       WHERE r.partner_id = p.id AND r.status='earned' AND r.payout_status='unpaid'),0) AS unpaid_earned,
            COALESCE((SELECT SUM(amount) FROM partner_referrals r
                       WHERE r.partner_id = p.id AND r.status='pending'),0) AS pending_total,
            COALESCE((SELECT SUM(amount) FROM partner_referrals r
                       WHERE r.partner_id = p.id AND r.payout_status='paid'),0) AS paid_total,
            COALESCE((SELECT COUNT(*)   FROM partner_referrals r
                       WHERE r.partner_id = p.id),0) AS ref_count
       FROM partners p
      ORDER BY p.status ASC, p.name ASC"
)->fetchAll();

$summary = partner_summary();

$recentRewards = db()->query(
    "SELECT r.*, p.name AS partner_name, b.booking_ref, b.status AS booking_status,
            e.title AS event_title, u.full_name AS customer_name
       FROM partner_referrals r
       JOIN partners p ON p.id = r.partner_id
       JOIN event_bookings b ON b.id = r.booking_id
       JOIN events e ON e.id = b.event_id
       LEFT JOIN users u ON u.id = r.user_id
      ORDER BY r.id DESC LIMIT 40"
)->fetchAll();

$payouts = db()->query(
    "SELECT po.*, p.name AS partner_name, admin.full_name AS by_name
       FROM partner_referral_payouts po
       JOIN partners p ON p.id = po.partner_id
       LEFT JOIN users admin ON admin.id = po.paid_by
      ORDER BY po.id DESC LIMIT 20"
)->fetchAll();

require __DIR__ . '/../includes/admin_layout.php';
?>

<div class="flex items-center justify-between gap-4 flex-wrap">
  <div>
    <h1 class="font-serif text-3xl text-beige-100">Partners</h1>
    <p class="text-beige-100/60 mt-1 text-sm">Cafes and businesses that hand out a QR — each booking they refer earns a commission you pay out in batches.</p>
  </div>
  <span class="text-xs px-3 py-1.5 rounded-full bg-gold-500/20 text-gold-400">
    Default commission <?= e(format_money((float) setting('partner_default_commission', 10.00))) ?> · overrideable per partner
  </span>
</div>

<?php foreach ($errors as $err): ?>
  <p class="mt-4 text-red-300/80 text-sm"><?= e($err) ?></p>
<?php endforeach; ?>

<!-- Rollup cards mirroring referral rewards so the money story is
     familiar. Same four-panel layout. -->
<div class="mt-8 grid sm:grid-cols-2 lg:grid-cols-4 gap-4">
  <div class="border border-gold-500/30 rounded-2xl p-5 bg-gold-500/5">
    <p class="text-[11px] uppercase tracking-widest text-gold-400/80">Owed now (earned · unpaid)</p>
    <p class="font-serif text-2xl text-gold-400 mt-2"><?= e(format_money((float) $summary['unpaid_earned'])) ?></p>
    <p class="text-[11px] text-beige-100/45 mt-1"><?= (int) $summary['unpaid_count'] ?> reward(s)</p>
  </div>
  <div class="border border-white/5 rounded-2xl p-5 bg-navy-900/40">
    <p class="text-[11px] uppercase tracking-widest text-beige-100/50">Pending (awaiting attendance)</p>
    <p class="font-serif text-2xl text-beige-100 mt-2"><?= e(format_money((float) $summary['pending_total'])) ?></p>
  </div>
  <div class="border border-white/5 rounded-2xl p-5 bg-navy-900/40">
    <p class="text-[11px] uppercase tracking-widest text-beige-100/50">Paid out to date</p>
    <p class="font-serif text-2xl text-beige-100 mt-2"><?= e(format_money((float) $summary['paid_total'])) ?></p>
  </div>
  <div class="border border-white/5 rounded-2xl p-5 bg-navy-900/40">
    <p class="text-[11px] uppercase tracking-widest text-beige-100/50">Reversed (refunds)</p>
    <p class="font-serif text-2xl text-red-300/80 mt-2"><?= e(format_money((float) $summary['reversed_total'])) ?></p>
  </div>
</div>

<!-- Create / edit form -->
<form method="post" enctype="multipart/form-data" class="mt-10 border border-white/5 rounded-3xl p-6 bg-navy-900/40 space-y-5">
  <?= csrf_field() ?>
  <input type="hidden" name="action" value="save">
  <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">

  <div class="flex items-center justify-between gap-4 flex-wrap">
    <h2 class="font-serif text-2xl text-gold-400"><?= $editing ? 'Edit partner' : 'Add partner' ?></h2>
    <?php if ($editing): ?>
      <div class="flex items-center gap-2">
        <a href="<?= url('/admin/partner_poster.php?id=' . (int) $editing['id']) ?>" target="_blank"
           class="px-4 py-2 rounded-full bg-gold-500 text-navy-950 font-medium hover:bg-gold-400 transition text-sm">Open poster</a>
        <a href="<?= url('/admin/partners.php') ?>"
           class="px-4 py-2 rounded-full border border-white/10 text-beige-100/70 hover:border-gold-500/40 hover:text-gold-400 transition text-sm">Add another</a>
      </div>
    <?php endif; ?>
  </div>

  <div class="grid sm:grid-cols-2 gap-4">
    <label class="block">
      <span class="text-xs uppercase tracking-widest text-beige-100/60">Business name</span>
      <input name="name" required maxlength="180" value="<?= e((string) $row['name']) ?>"
             class="mt-2 w-full rounded-2xl bg-navy-950 border border-white/5 px-4 py-3">
    </label>
    <label class="block">
      <span class="text-xs uppercase tracking-widest text-beige-100/60">Slug <span class="text-beige-100/30">(auto if blank)</span></span>
      <input name="slug" maxlength="80" placeholder="cafe-mocha"
             value="<?= e((string) $row['slug']) ?>"
             class="mt-2 w-full rounded-2xl bg-navy-950 border border-white/5 px-4 py-3 font-mono">
      <?php if (!empty($row['slug'])): ?>
        <span class="text-[11px] text-beige-100/40 mt-1 block">
          QR points at <span class="text-gold-400/80"><?= e(rtrim((string) config('app.url'), '/') . '/public/p.php?s=' . (string) $row['slug']) ?></span>
        </span>
      <?php endif; ?>
    </label>

    <label class="block">
      <span class="text-xs uppercase tracking-widest text-beige-100/60">Contact name</span>
      <input name="contact_name" maxlength="160" value="<?= e((string) $row['contact_name']) ?>"
             class="mt-2 w-full rounded-2xl bg-navy-950 border border-white/5 px-4 py-3">
    </label>
    <label class="block">
      <span class="text-xs uppercase tracking-widest text-beige-100/60">Contact email</span>
      <input name="contact_email" type="email" maxlength="180" value="<?= e((string) $row['contact_email']) ?>"
             class="mt-2 w-full rounded-2xl bg-navy-950 border border-white/5 px-4 py-3">
    </label>
    <label class="block sm:col-span-2">
      <span class="text-xs uppercase tracking-widest text-beige-100/60">Contact phone / WhatsApp</span>
      <input name="contact_phone" maxlength="40" value="<?= e((string) $row['contact_phone']) ?>"
             class="mt-2 w-full rounded-2xl bg-navy-950 border border-white/5 px-4 py-3">
    </label>

    <label class="block">
      <span class="text-xs uppercase tracking-widest text-beige-100/60">Commission type</span>
      <select name="commission_type" class="mt-2 w-full rounded-2xl bg-navy-950 border border-white/5 px-4 py-3">
        <option value="fixed"   <?= ($row['commission_type'] ?? 'fixed') === 'fixed'   ? 'selected' : '' ?>>Fixed amount per booking (MYR)</option>
        <option value="percent" <?= ($row['commission_type'] ?? 'fixed') === 'percent' ? 'selected' : '' ?>>Percent of booking total (%)</option>
      </select>
    </label>
    <label class="block">
      <span class="text-xs uppercase tracking-widest text-beige-100/60">Commission rate</span>
      <input name="commission_rate" type="number" step="0.01" min="0" value="<?= e(number_format((float) $row['commission_rate'], 2, '.', '')) ?>"
             class="mt-2 w-full rounded-2xl bg-navy-950 border border-white/5 px-4 py-3">
      <span class="text-[11px] text-beige-100/40 mt-1 block">Earned only when the booking is attended; reversed on refund.</span>
    </label>

    <label class="block">
      <span class="text-xs uppercase tracking-widest text-beige-100/60">First-visit promo code <span class="text-beige-100/30">(optional)</span></span>
      <input name="first_visit_promo_code" maxlength="40" placeholder="CAFEMOCHA10"
             value="<?= e((string) $row['first_visit_promo_code']) ?>"
             class="mt-2 w-full rounded-2xl bg-navy-950 border border-white/5 px-4 py-3 font-mono uppercase">
      <span class="text-[11px] text-beige-100/40 mt-1 block">Printed on the poster so the visitor knows what to type at checkout.</span>
    </label>
    <label class="block">
      <span class="text-xs uppercase tracking-widest text-beige-100/60">Landing path</span>
      <input name="landing_path" maxlength="255" value="<?= e((string) $row['landing_path']) ?>"
             class="mt-2 w-full rounded-2xl bg-navy-950 border border-white/5 px-4 py-3 font-mono">
      <span class="text-[11px] text-beige-100/40 mt-1 block">Where the QR sends the visitor after we drop the cookie. Default sends them to the calendar.</span>
    </label>

    <label class="block">
      <span class="text-xs uppercase tracking-widest text-beige-100/60">Status</span>
      <select name="status" class="mt-2 w-full rounded-2xl bg-navy-950 border border-white/5 px-4 py-3">
        <option value="active"   <?= ($row['status'] ?? 'active') === 'active'   ? 'selected' : '' ?>>Active (QR works)</option>
        <option value="inactive" <?= ($row['status'] ?? 'active') === 'inactive' ? 'selected' : '' ?>>Inactive (QR silently redirects to calendar)</option>
      </select>
    </label>
    <label class="block sm:col-span-2">
      <span class="text-xs uppercase tracking-widest text-beige-100/60">Internal notes</span>
      <textarea name="notes" rows="3"
                class="mt-2 w-full rounded-2xl bg-navy-950 border border-white/5 px-4 py-3"><?= e((string) $row['notes']) ?></textarea>
    </label>
  </div>

  <!-- Public listing — when on, the partner appears as a card on
       /public/partners.php. Independent from the QR referral flow so
       a business can be either / both / neither. -->
  <div class="border-t border-white/5 pt-6 space-y-4"
       x-data="{ pub: <?= (int) ($row['show_on_public_page'] ?? 0) === 1 ? 'true' : 'false' ?> }">
    <div class="flex items-start justify-between gap-4 flex-wrap">
      <div>
        <h3 class="font-serif text-lg text-gold-400">Public listing</h3>
        <p class="text-[11px] text-beige-100/45 mt-1">Appears as a card on <a href="<?= url('/public/partners.php') ?>" target="_blank" class="text-gold-400/85 hover:text-gold-300">/public/partners.php</a>.</p>
      </div>
      <label class="flex items-center gap-2 cursor-pointer">
        <input type="checkbox" name="show_on_public_page" value="1" x-model="pub" class="accent-gold-500">
        <span class="text-sm text-beige-100">Show on the public partners page</span>
      </label>
    </div>

    <div x-show="pub" x-cloak class="space-y-4">
      <div class="grid sm:grid-cols-2 gap-4">
        <label class="block">
          <span class="text-xs uppercase tracking-widest text-beige-100/60">Category</span>
          <input name="category" list="partner-cat-list" maxlength="80"
                 value="<?= e((string) ($row['category'] ?? '')) ?>"
                 placeholder="e.g. Cafés · Wellness studios · Retreat centres"
                 class="mt-2 w-full rounded-2xl bg-navy-950 border border-white/5 px-4 py-3">
          <span class="text-[11px] text-beige-100/40 mt-1 block">Cards group under their category on the public page.</span>
        </label>
        <label class="block">
          <span class="text-xs uppercase tracking-widest text-beige-100/60">Sort order</span>
          <input name="sort_order" type="number" value="<?= (int) ($row['sort_order'] ?? 100) ?>"
                 class="mt-2 w-full rounded-2xl bg-navy-950 border border-white/5 px-4 py-3">
          <span class="text-[11px] text-beige-100/40 mt-1 block">Lower = appears first within its category.</span>
        </label>
        <label class="block sm:col-span-2">
          <span class="text-xs uppercase tracking-widest text-beige-100/60">Website URL</span>
          <input name="website_url" type="url" maxlength="255"
                 value="<?= e((string) ($row['website_url'] ?? '')) ?>"
                 placeholder="https://www.example.com"
                 class="mt-2 w-full rounded-2xl bg-navy-950 border border-white/5 px-4 py-3 font-mono text-sm">
        </label>
        <label class="block sm:col-span-2">
          <span class="text-xs uppercase tracking-widest text-beige-100/60">Description</span>
          <textarea name="description" rows="4"
                    placeholder="A short blurb shown under the partner name."
                    class="mt-2 w-full rounded-2xl bg-navy-950 border border-white/5 px-4 py-3"><?= e((string) ($row['description'] ?? '')) ?></textarea>
        </label>
        <!-- Cover photo — fills the top of the public partner card. -->
        <div class="block sm:col-span-2">
          <span class="text-xs uppercase tracking-widest text-beige-100/60">Cover photo <span class="text-beige-100/30">(top of the card · JPG / PNG / WebP, up to 5 MB)</span></span>
          <?php if (!empty($row['cover_image'])): ?>
            <div class="mt-2 flex items-center gap-4">
              <img src="<?= e(str_starts_with((string) $row['cover_image'], '/') ? url($row['cover_image']) : (string) $row['cover_image']) ?>"
                   alt="" class="h-24 w-40 rounded-xl object-cover border border-white/10">
              <label class="inline-flex items-center gap-2 text-xs text-red-300/80 hover:text-red-200">
                <input type="checkbox" name="remove_cover" value="1" class="accent-red-400">
                Remove this photo
              </label>
            </div>
          <?php endif; ?>
          <input type="file" name="cover_file" accept="image/jpeg,image/png,image/webp"
                 class="mt-2 w-full text-sm text-beige-100/70 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:bg-gold-500/20 file:text-gold-400 hover:file:bg-gold-500/30">
          <input type="hidden" name="cover_image" value="<?= e((string) ($row['cover_image'] ?? '')) ?>">
        </div>

        <!-- Logo — small mark used on the poster + optional card badge. -->
        <div class="block sm:col-span-2">
          <span class="text-xs uppercase tracking-widest text-beige-100/60">Logo <span class="text-beige-100/30">(small mark · used on the printed QR poster · JPG / PNG / WebP, up to 5 MB)</span></span>
          <?php if (!empty($row['logo_url'])): ?>
            <div class="mt-2 flex items-center gap-4">
              <img src="<?= e(str_starts_with((string) $row['logo_url'], '/') ? url($row['logo_url']) : (string) $row['logo_url']) ?>"
                   alt="" class="h-16 w-16 rounded-xl object-contain border border-white/10 bg-navy-950/50 p-1">
              <label class="inline-flex items-center gap-2 text-xs text-red-300/80 hover:text-red-200">
                <input type="checkbox" name="remove_logo" value="1" class="accent-red-400">
                Remove this logo
              </label>
            </div>
          <?php endif; ?>
          <input type="file" name="logo_file" accept="image/jpeg,image/png,image/webp"
                 class="mt-2 w-full text-sm text-beige-100/70 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:bg-gold-500/20 file:text-gold-400 hover:file:bg-gold-500/30">
          <span class="mt-2 block text-[11px] text-beige-100/40">Or paste a URL:</span>
          <input name="logo_url" type="url" maxlength="255"
                 value="<?= e((string) ($row['logo_url'] ?? '')) ?>"
                 placeholder="https://…/logo.png"
                 class="mt-1 w-full rounded-2xl bg-navy-950 border border-white/5 px-4 py-3 font-mono text-sm">
        </div>
      </div>
    </div>

    <datalist id="partner-cat-list">
      <?php
        $catSuggest = db()->query("SELECT DISTINCT category FROM partners WHERE category IS NOT NULL AND category <> '' ORDER BY category")->fetchAll(PDO::FETCH_COLUMN);
        foreach ($catSuggest as $c): ?>
        <option value="<?= e($c) ?>">
      <?php endforeach; ?>
    </datalist>
  </div>

  <div class="pt-2 flex items-center gap-3">
    <button class="px-6 py-3 rounded-full bg-gold-500 text-navy-950 font-medium hover:bg-gold-400 transition">
      <?= $editing ? 'Save changes' : 'Create partner' ?>
    </button>
    <?php if ($editing && (int) $row['scan_count'] > 0): ?>
      <span class="text-xs text-beige-100/45">
        QR scanned <?= (int) $row['scan_count'] ?> time<?= (int) $row['scan_count'] === 1 ? '' : 's' ?>
        <?php if (!empty($row['last_scan_at'])): ?>
          · last on <?= e(format_datetime($row['last_scan_at'], 'D, d M · g:i A')) ?>
        <?php endif; ?>
      </span>
    <?php endif; ?>
  </div>
</form>

<!-- Partner list -->
<div class="mt-10">
  <h2 class="font-serif text-2xl text-beige-100">All partners</h2>
  <?php if (!$partners): ?>
    <p class="mt-4 text-beige-100/60 italic">No partners yet. Add the first cafe or business above.</p>
  <?php else: ?>
    <div class="mt-4 overflow-x-auto border border-white/5 rounded-2xl">
      <table class="w-full text-sm">
        <thead class="text-[11px] uppercase tracking-widest text-beige-100/45 bg-navy-950/60">
          <tr>
            <th class="text-left px-4 py-3">Partner</th>
            <th class="text-left px-4 py-3">Commission</th>
            <th class="text-left px-4 py-3">Scans</th>
            <th class="text-right px-4 py-3">Owed</th>
            <th class="text-right px-4 py-3">Paid</th>
            <th class="text-right px-4 py-3"></th>
          </tr>
        </thead>
        <tbody class="divide-y divide-white/5">
          <?php foreach ($partners as $p): ?>
            <tr class="hover:bg-white/[0.02]">
              <td class="px-4 py-3">
                <p class="text-beige-100"><?= e($p['name']) ?>
                  <?php if ($p['status'] !== 'active'): ?>
                    <span class="ml-2 text-[10px] uppercase tracking-widest text-beige-100/40">inactive</span>
                  <?php endif; ?>
                </p>
                <p class="text-[11px] text-beige-100/45 font-mono">/p.php?s=<?= e($p['slug']) ?></p>
              </td>
              <td class="px-4 py-3 text-beige-100/70">
                <?= $p['commission_type'] === 'percent'
                    ? e(number_format((float) $p['commission_rate'], 2)) . '%'
                    : e(format_money((float) $p['commission_rate'])) ?>
              </td>
              <td class="px-4 py-3 text-beige-100/70">
                <?= (int) $p['scan_count'] ?>
                <?php if (!empty($p['last_scan_at'])): ?>
                  <span class="block text-[11px] text-beige-100/40"><?= e(format_datetime($p['last_scan_at'], 'D, d M')) ?></span>
                <?php endif; ?>
              </td>
              <td class="px-4 py-3 text-right text-gold-400"><?= e(format_money((float) $p['unpaid_earned'])) ?></td>
              <td class="px-4 py-3 text-right text-beige-100/60"><?= e(format_money((float) $p['paid_total'])) ?></td>
              <td class="px-4 py-3 text-right whitespace-nowrap">
                <a href="<?= url('/admin/partner_poster.php?id=' . (int) $p['id']) ?>" target="_blank"
                   class="text-xs text-beige-100/70 hover:text-gold-400 mr-3">Poster</a>
                <a href="<?= url('/admin/partners.php?edit=' . (int) $p['id']) ?>"
                   class="text-xs text-gold-400 hover:text-gold-300 mr-3">Edit</a>
                <?php if ((float) $p['unpaid_earned'] > 0): ?>
                  <button type="button" class="text-xs text-gold-400 hover:text-gold-300"
                          onclick="document.getElementById('settle-<?= (int) $p['id'] ?>').classList.toggle('hidden')">Settle</button>
                <?php endif; ?>
              </td>
            </tr>
            <?php if ((float) $p['unpaid_earned'] > 0): ?>
              <tr id="settle-<?= (int) $p['id'] ?>" class="hidden bg-navy-950/60">
                <td colspan="6" class="px-4 py-3">
                  <form method="post" class="flex items-center gap-3 flex-wrap">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="settle_payout">
                    <input type="hidden" name="partner_id" value="<?= (int) $p['id'] ?>">
                    <span class="text-xs text-beige-100/60">
                      Paying <?= e(format_money((float) $p['unpaid_earned'])) ?> to <?= e($p['name']) ?>.
                    </span>
                    <input name="reference" maxlength="160" placeholder="DuitNow ref (optional)"
                           class="rounded-full bg-navy-900 border border-white/10 px-4 py-2 text-xs w-64">
                    <button class="px-4 py-2 rounded-full bg-gold-500 text-navy-950 font-medium hover:bg-gold-400 transition text-xs">
                      Mark paid
                    </button>
                  </form>
                </td>
              </tr>
            <?php endif; ?>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<!-- Recent attributed bookings -->
<div class="mt-10">
  <h2 class="font-serif text-2xl text-beige-100">Recent referrals</h2>
  <?php if (!$recentRewards): ?>
    <p class="mt-4 text-beige-100/60 italic">No partner bookings yet. Once a visitor scans a QR and books, they'll show up here.</p>
  <?php else: ?>
    <div class="mt-4 overflow-x-auto border border-white/5 rounded-2xl">
      <table class="w-full text-sm">
        <thead class="text-[11px] uppercase tracking-widest text-beige-100/45 bg-navy-950/60">
          <tr>
            <th class="text-left px-4 py-3">Partner</th>
            <th class="text-left px-4 py-3">Booking</th>
            <th class="text-left px-4 py-3">Customer</th>
            <th class="text-left px-4 py-3">Session</th>
            <th class="text-left px-4 py-3">Status</th>
            <th class="text-right px-4 py-3">Amount</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-white/5">
          <?php foreach ($recentRewards as $r): ?>
            <tr class="hover:bg-white/[0.02]">
              <td class="px-4 py-3 text-beige-100/80"><?= e($r['partner_name']) ?></td>
              <td class="px-4 py-3 font-mono text-xs text-beige-100/70"><?= e((string) $r['booking_ref']) ?></td>
              <td class="px-4 py-3 text-beige-100/70"><?= e((string) ($r['customer_name'] ?? '—')) ?></td>
              <td class="px-4 py-3 text-beige-100/70"><?= e((string) $r['event_title']) ?></td>
              <td class="px-4 py-3">
                <?php
                  $chip = match ($r['status']) {
                      'earned'   => ['label' => $r['payout_status'] === 'paid' ? 'Paid' : 'Earned', 'cls' => 'text-gold-400 bg-gold-500/10 border-gold-500/30'],
                      'reversed' => ['label' => 'Reversed', 'cls' => 'text-red-300/80 bg-red-500/10 border-red-500/30'],
                      default    => ['label' => 'Pending',  'cls' => 'text-beige-100/70 bg-white/5 border-white/10'],
                  };
                ?>
                <span class="text-[10px] uppercase tracking-widest px-3 py-1 rounded-full border <?= e($chip['cls']) ?>"><?= e($chip['label']) ?></span>
              </td>
              <td class="px-4 py-3 text-right text-beige-100"><?= e(format_money((float) $r['amount'])) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<!-- Payout history -->
<?php if ($payouts): ?>
<div class="mt-10">
  <h2 class="font-serif text-2xl text-beige-100">Payout history</h2>
  <div class="mt-4 overflow-x-auto border border-white/5 rounded-2xl">
    <table class="w-full text-sm">
      <thead class="text-[11px] uppercase tracking-widest text-beige-100/45 bg-navy-950/60">
        <tr>
          <th class="text-left px-4 py-3">Paid at</th>
          <th class="text-left px-4 py-3">Partner</th>
          <th class="text-left px-4 py-3">Rewards</th>
          <th class="text-left px-4 py-3">Reference</th>
          <th class="text-left px-4 py-3">Paid by</th>
          <th class="text-right px-4 py-3">Amount</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-white/5">
        <?php foreach ($payouts as $po): ?>
          <tr class="hover:bg-white/[0.02]">
            <td class="px-4 py-3 text-beige-100/70"><?= e(format_datetime($po['paid_at'], 'D, d M · g:i A')) ?></td>
            <td class="px-4 py-3 text-beige-100/80"><?= e($po['partner_name']) ?></td>
            <td class="px-4 py-3 text-beige-100/60"><?= (int) $po['reward_count'] ?></td>
            <td class="px-4 py-3 font-mono text-xs text-beige-100/60"><?= e((string) ($po['reference'] ?? '')) ?></td>
            <td class="px-4 py-3 text-beige-100/60"><?= e((string) ($po['by_name'] ?? '—')) ?></td>
            <td class="px-4 py-3 text-right text-beige-100"><?= e(format_money((float) $po['amount'])) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>

<?php require __DIR__ . '/../includes/admin_layout_end.php'; ?>
