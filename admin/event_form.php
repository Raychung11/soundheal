<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_staff_or_admin();

$id = (int) input('id', 0);
$event = ['id' => 0, 'slug' => '', 'title' => '', 'subtitle' => '', 'description' => '',
          'cover_image' => '', 'location' => '', 'starts_at' => '', 'ends_at' => '',
          'capacity' => 30, 'price_public' => 0, 'price_member' => 0,
          'facilitator' => '', 'category' => '', 'status' => 'draft',
          'recurrence' => 'none', 'recurrence_until' => '',
          'package_a_label' => '', 'package_a_perks' => '',
          'package_b_label' => '', 'package_b_perks' => '',
          'experience_id' => null];

$experienceOptions = db()->query(
    "SELECT id, title FROM experiences WHERE status = 'active' ORDER BY sort_order ASC, title ASC"
)->fetchAll();

if ($id) {
    $stmt = db()->prepare("SELECT * FROM events WHERE id = :id LIMIT 1");
    $stmt->execute([':id' => $id]);
    if ($row = $stmt->fetch()) {
        $event = $row;
    }
}
$pageTitle = $id ? 'Edit Event' : 'New Event';
$errors = [];

if (is_post()) {
    csrf_verify();
    $event = array_merge($event, [
        'title'        => trim((string)input('title', '')),
        'subtitle'     => trim((string)input('subtitle', '')),
        'description'  => trim((string)input('description', '')),
        'cover_image'  => trim((string)input('cover_image', '')),
        'location'     => trim((string)input('location', '')),
        'starts_at'    => input('starts_at', ''),
        'ends_at'      => input('ends_at', ''),
        'capacity'     => max(1, (int)input('capacity', 30)),
        'price_public' => (float)input('price_public', 0),
        'price_member' => (float)input('price_member', 0),
        'facilitator'  => trim((string)input('facilitator', '')),
        'category'     => trim((string)input('category', '')),
        'status'       => in_array(input('status'), ['draft','published','archived','cancelled'], true) ? input('status') : 'draft',
        'recurrence'      => in_array(input('recurrence'), ['none','daily'], true) ? input('recurrence') : 'none',
        'recurrence_until' => trim((string) input('recurrence_until', '')) ?: null,
        'package_a_label' => trim((string) input('package_a_label', '')),
        'package_a_perks' => trim((string) input('package_a_perks', '')),
        'package_b_label' => trim((string) input('package_b_label', '')),
        'package_b_perks' => trim((string) input('package_b_perks', '')),
        'experience_id'   => ($v = (int) input('experience_id', 0)) > 0 ? $v : null,
    ]);
    if ($event['title'] === '' || !$event['starts_at'] || !$event['ends_at']) {
        $errors[] = 'Title, start and end time are required.';
    }
    if ($event['recurrence_until'] !== null && !preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $event['recurrence_until'])) {
        $errors[] = '"Recurs until" must be a valid date (YYYY-MM-DD).';
    }

    // Optional cover image upload — replaces the URL field if a file is sent.
    if (!$errors) {
        try {
            $uploaded = handle_upload('cover_image_file', 'events');
            if ($uploaded) {
                if ($id && !empty($event['cover_image']) && str_starts_with((string) $event['cover_image'], '/uploads/')) {
                    delete_upload($event['cover_image']);
                }
                $event['cover_image'] = $uploaded;
            }
        } catch (RuntimeException $e) {
            $errors[] = $e->getMessage();
        }
    }

    if (!$errors) {
        $slug = $event['slug'] ?: slugify($event['title']);
        if ($id) {
            $stmt = db()->prepare(
                "UPDATE events SET title=:t, subtitle=:st, description=:d, cover_image=:ci,
                 location=:l, starts_at=:s, ends_at=:e, capacity=:c, price_public=:pp,
                 price_member=:pm, facilitator=:f, category=:cat, status=:status,
                 recurrence=:rec, recurrence_until=:ru,
                 package_a_label=:pal, package_a_perks=:pap,
                 package_b_label=:pbl, package_b_perks=:pbp,
                 experience_id=:xid
                 WHERE id=:id"
            );
            $stmt->execute([
                ':t' => $event['title'], ':st' => $event['subtitle'], ':d' => $event['description'],
                ':ci' => $event['cover_image'], ':l' => $event['location'],
                ':s' => $event['starts_at'], ':e' => $event['ends_at'],
                ':c' => $event['capacity'], ':pp' => $event['price_public'], ':pm' => $event['price_member'],
                ':f' => $event['facilitator'], ':cat' => $event['category'], ':status' => $event['status'],
                ':rec' => $event['recurrence'], ':ru' => $event['recurrence_until'],
                ':pal' => $event['package_a_label'] ?: null,
                ':pap' => $event['package_a_perks'] ?: null,
                ':pbl' => $event['package_b_label'] ?: null,
                ':pbp' => $event['package_b_perks'] ?: null,
                ':xid' => $event['experience_id'],
                ':id' => $id,
            ]);
            audit_log('event.update', 'events', $id);
        } else {
            $stmt = db()->prepare(
                "INSERT INTO events (slug, title, subtitle, description, cover_image, location, starts_at, ends_at,
                                     capacity, price_public, price_member, facilitator, category, status,
                                     recurrence, recurrence_until,
                                     package_a_label, package_a_perks, package_b_label, package_b_perks,
                                     experience_id, created_by)
                 VALUES (:slug, :t, :st, :d, :ci, :l, :s, :e, :c, :pp, :pm, :f, :cat, :status,
                         :rec, :ru, :pal, :pap, :pbl, :pbp, :xid, :uid)"
            );
            $stmt->execute([
                ':slug' => $slug, ':t' => $event['title'], ':st' => $event['subtitle'],
                ':d' => $event['description'], ':ci' => $event['cover_image'], ':l' => $event['location'],
                ':s' => $event['starts_at'], ':e' => $event['ends_at'],
                ':c' => $event['capacity'], ':pp' => $event['price_public'], ':pm' => $event['price_member'],
                ':f' => $event['facilitator'], ':cat' => $event['category'], ':status' => $event['status'],
                ':rec' => $event['recurrence'], ':ru' => $event['recurrence_until'],
                ':pal' => $event['package_a_label'] ?: null,
                ':pap' => $event['package_a_perks'] ?: null,
                ':pbl' => $event['package_b_label'] ?: null,
                ':pbp' => $event['package_b_perks'] ?: null,
                ':xid' => $event['experience_id'],
                ':uid' => current_user_id(),
            ]);
            $id = (int) db()->lastInsertId();
            audit_log('event.create', 'events', $id);
        }
        flash('event', 'Saved.', 'success');
        redirect('/admin/events.php');
    }
}

require __DIR__ . '/../includes/admin_layout.php';
?>
<h1 class="font-serif text-3xl text-beige-100"><?= $id ? 'Edit session' : 'New session' ?></h1>

<?php foreach ($errors as $err): ?>
  <p class="mt-3 text-red-300/80"><?= e($err) ?></p>
<?php endforeach; ?>

<form method="post" enctype="multipart/form-data" class="mt-8 space-y-5 max-w-3xl">
  <?= csrf_field() ?>
  <label class="block">
    <span class="text-xs uppercase tracking-widest text-beige-100/60">Title</span>
    <input name="title" required value="<?= e($event['title']) ?>" class="mt-2 w-full rounded-2xl bg-navy-900 border border-white/5 px-4 py-3">
  </label>
  <label class="block">
    <span class="text-xs uppercase tracking-widest text-beige-100/60">Subtitle</span>
    <input name="subtitle" value="<?= e($event['subtitle']) ?>" class="mt-2 w-full rounded-2xl bg-navy-900 border border-white/5 px-4 py-3">
  </label>
  <label class="block">
    <span class="text-xs uppercase tracking-widest text-beige-100/60">Description</span>
    <textarea name="description" rows="4" class="mt-2 w-full rounded-2xl bg-navy-900 border border-white/5 px-4 py-3"><?= e($event['description']) ?></textarea>
  </label>

  <div class="grid sm:grid-cols-2 gap-5">
    <label class="block">
      <span class="text-xs uppercase tracking-widest text-beige-100/60">Starts at</span>
      <input name="starts_at" type="datetime-local" required value="<?= e(str_replace(' ', 'T', substr((string)$event['starts_at'], 0, 16))) ?>" class="mt-2 w-full rounded-2xl bg-navy-900 border border-white/5 px-4 py-3">
    </label>
    <label class="block">
      <span class="text-xs uppercase tracking-widest text-beige-100/60">Ends at</span>
      <input name="ends_at" type="datetime-local" required value="<?= e(str_replace(' ', 'T', substr((string)$event['ends_at'], 0, 16))) ?>" class="mt-2 w-full rounded-2xl bg-navy-900 border border-white/5 px-4 py-3">
    </label>
    <label class="block">
      <span class="text-xs uppercase tracking-widest text-beige-100/60">Location</span>
      <input name="location" value="<?= e($event['location']) ?>" class="mt-2 w-full rounded-2xl bg-navy-900 border border-white/5 px-4 py-3">
    </label>
    <label class="block">
      <span class="text-xs uppercase tracking-widest text-beige-100/60">Facilitator</span>
      <input name="facilitator" value="<?= e($event['facilitator']) ?>" class="mt-2 w-full rounded-2xl bg-navy-900 border border-white/5 px-4 py-3">
    </label>
    <label class="block">
      <span class="text-xs uppercase tracking-widest text-beige-100/60">Capacity</span>
      <input name="capacity" type="number" min="1" value="<?= (int)$event['capacity'] ?>" class="mt-2 w-full rounded-2xl bg-navy-900 border border-white/5 px-4 py-3">
    </label>
    <label class="block">
      <span class="text-xs uppercase tracking-widest text-beige-100/60">Category</span>
      <input name="category" value="<?= e($event['category']) ?>" class="mt-2 w-full rounded-2xl bg-navy-900 border border-white/5 px-4 py-3">
    </label>
    <label class="block">
      <span class="text-xs uppercase tracking-widest text-beige-100/60">Experience <span class="text-beige-100/30">(optional)</span></span>
      <select name="experience_id" class="mt-2 w-full rounded-2xl bg-navy-900 border border-white/5 px-4 py-3">
        <option value="">— not linked —</option>
        <?php foreach ($experienceOptions as $xo): ?>
          <option value="<?= (int) $xo['id'] ?>" <?= ((int) ($event['experience_id'] ?? 0)) === (int) $xo['id'] ? 'selected' : '' ?>><?= e($xo['title']) ?></option>
        <?php endforeach; ?>
      </select>
      <span class="text-[11px] text-beige-100/40 mt-1 block">Links this event to an Experiences-page card so its "Reserve" button opens this session.</span>
    </label>
    <label class="block">
      <span class="text-xs uppercase tracking-widest text-beige-100/60">Public price (MYR)</span>
      <input name="price_public" type="number" step="0.01" value="<?= e((string)$event['price_public']) ?>" class="mt-2 w-full rounded-2xl bg-navy-900 border border-white/5 px-4 py-3">
    </label>
    <label class="block">
      <span class="text-xs uppercase tracking-widest text-beige-100/60">Member price (MYR)</span>
      <input name="price_member" type="number" step="0.01" value="<?= e((string)$event['price_member']) ?>" class="mt-2 w-full rounded-2xl bg-navy-900 border border-white/5 px-4 py-3">
    </label>
    <label class="block sm:col-span-2">
      <span class="text-xs uppercase tracking-widest text-beige-100/60">Cover image</span>
      <?php if (!empty($event['cover_image'])): ?>
        <img src="<?= e(str_starts_with((string)$event['cover_image'], '/') ? url($event['cover_image']) : $event['cover_image']) ?>" alt="" class="mt-2 h-32 w-auto rounded-xl object-cover border border-white/10">
      <?php endif; ?>
      <input type="file" name="cover_image_file" accept="image/jpeg,image/png,image/webp" class="mt-2 w-full text-sm text-beige-100/70 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:bg-gold-500/20 file:text-gold-400 hover:file:bg-gold-500/30">
      <input name="cover_image" type="hidden" value="<?= e($event['cover_image']) ?>">
      <span class="text-[11px] text-beige-100/40 mt-1 block">JPEG / PNG / WebP, up to 5 MB.</span>
    </label>
    <label class="block">
      <span class="text-xs uppercase tracking-widest text-beige-100/60">Status</span>
      <select name="status" class="mt-2 w-full rounded-2xl bg-navy-900 border border-white/5 px-4 py-3">
        <?php foreach (['draft','published','archived','cancelled'] as $opt): ?>
          <option value="<?= $opt ?>" <?= $event['status'] === $opt ? 'selected' : '' ?>><?= $opt ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <label class="block">
      <span class="text-xs uppercase tracking-widest text-beige-100/60">Recurrence</span>
      <select name="recurrence" class="mt-2 w-full rounded-2xl bg-navy-900 border border-white/5 px-4 py-3">
        <option value="none" <?= ($event['recurrence'] ?? 'none') === 'none' ? 'selected' : '' ?>>One-off session</option>
        <option value="daily" <?= ($event['recurrence'] ?? 'none') === 'daily' ? 'selected' : '' ?>>Every day (uses the time from “Starts at”)</option>
      </select>
      <span class="text-[11px] text-beige-100/40 mt-1 block">Daily sessions auto-show for the next 14 days; a concrete event is created for each date when someone books.</span>
    </label>
    <label class="block">
      <span class="text-xs uppercase tracking-widest text-beige-100/60">Recurs until <span class="text-beige-100/30">(optional)</span></span>
      <input name="recurrence_until" type="date" value="<?= e((string) ($event['recurrence_until'] ?? '')) ?>" class="mt-2 w-full rounded-2xl bg-navy-900 border border-white/5 px-4 py-3">
      <span class="text-[11px] text-beige-100/40 mt-1 block">Leave blank for indefinite. Only used when recurrence is Daily.</span>
    </label>
  </div>

  <section class="border-t border-white/5 pt-6 space-y-5">
    <div>
      <h2 class="font-serif text-xl text-gold-400">Booking packages</h2>
      <p class="text-[11px] text-beige-100/45 mt-1">Optional — override the default "Comfort" / "Bring-Your-Own-Zen" labels and perks for this event (e.g. special workshops with their own tiers). Leave blank to use the site defaults.</p>
    </div>

    <div class="grid sm:grid-cols-2 gap-5">
      <div class="space-y-3">
        <p class="text-[10px] uppercase tracking-widest text-beige-100/55">Package A · price <?= e(format_money((float) ($event['price_public'] ?? 0))) ?></p>
        <label class="block">
          <span class="text-xs uppercase tracking-widest text-beige-100/60">Label</span>
          <input name="package_a_label" placeholder="Comfort" value="<?= e((string) ($event['package_a_label'] ?? '')) ?>" class="mt-2 w-full rounded-2xl bg-navy-900 border border-white/5 px-4 py-3">
        </label>
        <label class="block">
          <span class="text-xs uppercase tracking-widest text-beige-100/60">Perks (one per line)</span>
          <textarea name="package_a_perks" rows="5" placeholder="Welcome drink&#10;Yoga mat provided&#10;Cozy blanket provided&#10;Full sound healing experience" class="mt-2 w-full rounded-2xl bg-navy-900 border border-white/5 px-4 py-3"><?= e((string) ($event['package_a_perks'] ?? '')) ?></textarea>
        </label>
      </div>

      <div class="space-y-3">
        <p class="text-[10px] uppercase tracking-widest text-beige-100/55">Package B · price <?= e(format_money((float) ($event['price_member'] ?? 0))) ?></p>
        <label class="block">
          <span class="text-xs uppercase tracking-widest text-beige-100/60">Label</span>
          <input name="package_b_label" placeholder="Bring-Your-Own-Zen" value="<?= e((string) ($event['package_b_label'] ?? '')) ?>" class="mt-2 w-full rounded-2xl bg-navy-900 border border-white/5 px-4 py-3">
        </label>
        <label class="block">
          <span class="text-xs uppercase tracking-widest text-beige-100/60">Perks (one per line)</span>
          <textarea name="package_b_perks" rows="5" placeholder="Full sound healing experience&#10;Bring your own mat and blanket" class="mt-2 w-full rounded-2xl bg-navy-900 border border-white/5 px-4 py-3"><?= e((string) ($event['package_b_perks'] ?? '')) ?></textarea>
        </label>
      </div>
    </div>
  </section>

  <div class="flex gap-3">
    <button class="px-6 py-3 rounded-full bg-gold-500 text-navy-950 hover:bg-gold-400 transition">Save</button>
    <a href="<?= url('/admin/events.php') ?>" class="px-6 py-3 rounded-full border border-white/10 text-beige-100/70 hover:border-white/20">Cancel</a>
  </div>
</form>
<?php require __DIR__ . '/../includes/admin_layout_end.php'; ?>
