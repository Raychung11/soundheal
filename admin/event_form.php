<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_staff_or_admin();

$id = (int) input('id', 0);
$event = ['id' => 0, 'slug' => '', 'title' => '', 'subtitle' => '', 'description' => '',
          'cover_image' => '', 'location' => '', 'starts_at' => '', 'ends_at' => '',
          'capacity' => 30, 'price_public' => 0, 'price_member' => 0,
          'facilitator' => '', 'category' => '', 'status' => 'draft',
          'recurrence' => 'none', 'recurrence_until' => '', 'recurrence_days' => '',
          'package_a_label' => '', 'package_a_perks' => '',
          'package_b_label' => '', 'package_b_perks' => '',
          'package_b_enabled' => 1,
          'experience_id' => null,
          'referral_reward_amount' => '',
          'audience' => 'public',
          'credit_eligible' => 1];

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
        'recurrence'      => in_array(input('recurrence'), ['none','daily','weekly','monthly'], true) ? input('recurrence') : 'none',
        'recurrence_until' => trim((string) input('recurrence_until', '')) ?: null,
        'recurrence_days' => (function () {
            $mode = (string) input('recurrence', 'none');
            if ($mode === 'weekly') {
                $days = array_values(array_unique(array_filter(array_map('intval',
                    (array) ($_POST['recurrence_days'] ?? [])),
                    fn($n) => $n >= 0 && $n <= 6)));
                sort($days);
                return $days ? implode(',', $days) : null;
            }
            if ($mode === 'monthly') {
                $ord  = (string) input('recurrence_monthly_ordinal', '1');
                $dow  = (string) input('recurrence_monthly_dow', 'SUN');
                if (!in_array($ord, ['1','2','3','4','5','L'], true))                       $ord = '1';
                if (!in_array($dow, ['SUN','MON','TUE','WED','THU','FRI','SAT'], true))     $dow = 'SUN';
                return $ord . $dow;
            }
            return null;
        })(),
        'package_a_label' => trim((string) input('package_a_label', '')),
        'package_a_perks' => trim((string) input('package_a_perks', '')),
        'package_b_label' => trim((string) input('package_b_label', '')),
        'package_b_perks' => trim((string) input('package_b_perks', '')),
        'package_b_enabled' => !empty($_POST['package_b_enabled']) ? 1 : 0,
        'experience_id'   => ($v = (int) input('experience_id', 0)) > 0 ? $v : null,
        'referral_reward_amount' => (($ra = trim((string) input('referral_reward_amount', ''))) !== '' && (float) $ra > 0) ? (float) $ra : null,
        'audience'        => in_array(input('audience'), ['public','private'], true) ? input('audience') : 'public',
        'credit_eligible' => !empty($_POST['credit_eligible']) ? 1 : 0,
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
                 recurrence=:rec, recurrence_until=:ru, recurrence_days=:rd,
                 package_a_label=:pal, package_a_perks=:pap,
                 package_b_label=:pbl, package_b_perks=:pbp,
                 package_b_enabled=:pbe,
                 experience_id=:xid,
                 referral_reward_amount=:rra,
                 audience=:aud, credit_eligible=:cel
                 WHERE id=:id"
            );
            $stmt->execute([
                ':t' => $event['title'], ':st' => $event['subtitle'], ':d' => $event['description'],
                ':ci' => $event['cover_image'], ':l' => $event['location'],
                ':s' => $event['starts_at'], ':e' => $event['ends_at'],
                ':c' => $event['capacity'], ':pp' => $event['price_public'], ':pm' => $event['price_member'],
                ':f' => $event['facilitator'], ':cat' => $event['category'], ':status' => $event['status'],
                ':rec' => $event['recurrence'], ':ru' => $event['recurrence_until'],
                ':rd'  => $event['recurrence_days'] ?: null,
                ':pal' => $event['package_a_label'] ?: null,
                ':pap' => $event['package_a_perks'] ?: null,
                ':pbl' => $event['package_b_label'] ?: null,
                ':pbp' => $event['package_b_perks'] ?: null,
                ':pbe' => (int) $event['package_b_enabled'],
                ':xid' => $event['experience_id'],
                ':rra' => $event['referral_reward_amount'],
                ':aud' => $event['audience'],
                ':cel' => (int) $event['credit_eligible'],
                ':id' => $id,
            ]);
            audit_log('event.update', 'events', $id);

            // Cascade config-level changes down to unbooked child
            // instances of this template. Without this, turning off
            // Package B (or editing packages / audience / credits) on
            // the template wouldn't take effect on already-materialised
            // occurrences — the booking page books against the child
            // row, not the template. Children with any live booking
            // are left alone so we don't retroactively change what a
            // member already paid for.
            db()->prepare(
                "UPDATE events c
                    LEFT JOIN event_bookings b
                      ON b.event_id = c.id
                     AND b.status IN ('pending','paid','attended')
                    SET c.title = :t, c.subtitle = :st, c.description = :d,
                        c.cover_image = :ci, c.location = :l,
                        c.capacity = :c, c.price_public = :pp, c.price_member = :pm,
                        c.facilitator = :f, c.category = :cat, c.status = :status,
                        c.experience_id = :xid, c.audience = :aud, c.credit_eligible = :cel,
                        c.referral_reward_amount = :rra,
                        c.package_a_label = :pal, c.package_a_perks = :pap,
                        c.package_b_label = :pbl, c.package_b_perks = :pbp,
                        c.package_b_enabled = :pbe
                  WHERE c.parent_event_id = :id
                    AND b.id IS NULL"
            )->execute([
                ':t' => $event['title'], ':st' => $event['subtitle'], ':d' => $event['description'],
                ':ci' => $event['cover_image'], ':l' => $event['location'],
                ':c' => $event['capacity'], ':pp' => $event['price_public'], ':pm' => $event['price_member'],
                ':f' => $event['facilitator'], ':cat' => $event['category'], ':status' => $event['status'],
                ':xid' => $event['experience_id'],
                ':aud' => $event['audience'],
                ':cel' => (int) $event['credit_eligible'],
                ':rra' => $event['referral_reward_amount'],
                ':pal' => $event['package_a_label'] ?: null,
                ':pap' => $event['package_a_perks'] ?: null,
                ':pbl' => $event['package_b_label'] ?: null,
                ':pbp' => $event['package_b_perks'] ?: null,
                ':pbe' => (int) $event['package_b_enabled'],
                ':id' => $id,
            ]);
        } else {
            $stmt = db()->prepare(
                "INSERT INTO events (slug, title, subtitle, description, cover_image, location, starts_at, ends_at,
                                     capacity, price_public, price_member, facilitator, category, status,
                                     recurrence, recurrence_until, recurrence_days,
                                     package_a_label, package_a_perks, package_b_label, package_b_perks,
                                     package_b_enabled,
                                     experience_id, referral_reward_amount,
                                     audience, credit_eligible, created_by)
                 VALUES (:slug, :t, :st, :d, :ci, :l, :s, :e, :c, :pp, :pm, :f, :cat, :status,
                         :rec, :ru, :rd, :pal, :pap, :pbl, :pbp, :pbe, :xid, :rra, :aud, :cel, :uid)"
            );
            $stmt->execute([
                ':slug' => $slug, ':t' => $event['title'], ':st' => $event['subtitle'],
                ':d' => $event['description'], ':ci' => $event['cover_image'], ':l' => $event['location'],
                ':s' => $event['starts_at'], ':e' => $event['ends_at'],
                ':c' => $event['capacity'], ':pp' => $event['price_public'], ':pm' => $event['price_member'],
                ':f' => $event['facilitator'], ':cat' => $event['category'], ':status' => $event['status'],
                ':rec' => $event['recurrence'], ':ru' => $event['recurrence_until'],
                ':rd'  => $event['recurrence_days'] ?: null,
                ':pal' => $event['package_a_label'] ?: null,
                ':pap' => $event['package_a_perks'] ?: null,
                ':pbl' => $event['package_b_label'] ?: null,
                ':pbp' => $event['package_b_perks'] ?: null,
                ':pbe' => (int) $event['package_b_enabled'],
                ':xid' => $event['experience_id'],
                ':rra' => $event['referral_reward_amount'],
                ':aud' => $event['audience'],
                ':cel' => (int) $event['credit_eligible'],
                ':uid' => current_user_id(),
            ]);
            $id = (int) db()->lastInsertId();
            audit_log('event.create', 'events', $id);
        }
        // Sync skip dates (recurrence exceptions). Simple replace-all —
        // parse the textarea, keep only valid YYYY-MM-DD dates, then
        // wipe + reinsert. Only touched when the input was submitted so
        // absent field never destroys existing exceptions unexpectedly.
        if (array_key_exists('recurrence_exceptions', $_POST)) {
            $raw   = (string) input('recurrence_exceptions', '');
            $lines = preg_split('/[\r\n,;]+/', $raw) ?: [];
            $dates = [];
            foreach ($lines as $line) {
                $line = trim($line);
                if ($line === '') continue;
                if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $line) && strtotime($line) !== false) {
                    $dates[$line] = true; // dedupe
                }
            }
            db()->prepare("DELETE FROM event_recurrence_exceptions WHERE event_id = :id")
                ->execute([':id' => $id]);
            if ($dates) {
                $ins = db()->prepare("INSERT IGNORE INTO event_recurrence_exceptions (event_id, exception_date) VALUES (:e, :d)");
                foreach (array_keys($dates) as $d) {
                    $ins->execute([':e' => $id, ':d' => $d]);
                }
            }
        }

        flash('event', 'Saved.', 'success');
        redirect('/admin/events.php');
    }
}

// Existing skip dates (recurrence exceptions) for prefilling the textarea.
$existingExceptions = [];
if ($id > 0) {
    $exStmt = db()->prepare(
        "SELECT exception_date FROM event_recurrence_exceptions
          WHERE event_id = :id ORDER BY exception_date ASC"
    );
    $exStmt->execute([':id' => $id]);
    $existingExceptions = array_column($exStmt->fetchAll(), 'exception_date');
}

// Existing monthly pattern → prefill ordinal + dow selects.
$monthlyOrdinal = '1';
$monthlyDow     = 'SUN';
if (($event['recurrence'] ?? 'none') === 'monthly') {
    $raw = strtoupper((string) ($event['recurrence_days'] ?? ''));
    if (preg_match('/^([1-5L])(SUN|MON|TUE|WED|THU|FRI|SAT)$/', $raw, $mm)) {
        $monthlyOrdinal = $mm[1];
        $monthlyDow     = $mm[2];
    }
}

require __DIR__ . '/../includes/admin_layout.php';
?>
<h1 class="font-serif text-3xl text-beige-100"><?= $id ? 'Edit session' : 'New session' ?></h1>

<?php if ($id && !empty($event['parent_event_id'])): ?>
  <!-- Editing a CHILD instance. Changes here only affect this one
       date, not the template or the sibling occurrences. Loudly warn
       so admins stop chasing "my Package B toggle didn't take effect
       across all dates" bugs. -->
  <div class="mt-4 border border-red-500/40 bg-red-500/10 rounded-2xl p-4 text-sm">
    <p class="text-red-200 font-medium">You are editing one occurrence (#<?= (int) $id ?>) of a recurring template (#<?= (int) $event['parent_event_id'] ?>).</p>
    <p class="text-red-200/85 mt-1 text-xs">Changes here only touch this specific date. To change every occurrence at once — Package B, prices, labels, experience link, status — edit the template instead.</p>
    <p class="mt-2 flex gap-3 text-xs">
      <a href="<?= url('/admin/event_form.php?id=' . (int) $event['parent_event_id']) ?>"
         class="px-3 py-1.5 rounded-full bg-gold-500 text-navy-950 font-medium hover:bg-gold-400">Edit template #<?= (int) $event['parent_event_id'] ?> →</a>
      <a href="<?= url('/admin/event_debug.php?id=' . (int) $event['parent_event_id']) ?>"
         class="px-3 py-1.5 rounded-full border border-red-500/40 text-red-200 hover:bg-red-500/10">Debug view</a>
    </p>
  </div>
<?php endif; ?>

<?php foreach ($errors as $err): ?>
  <p class="mt-3 text-red-300/80"><?= e($err) ?></p>
<?php endforeach; ?>

<form method="post" enctype="multipart/form-data" class="mt-8 space-y-5 max-w-3xl"
      x-data="{ pkgB: <?= (int) ($event['package_b_enabled'] ?? 1) === 1 ? 'true' : 'false' ?> }">
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
      <span class="text-xs uppercase tracking-widest text-beige-100/60">Referral reward · MYR <span class="text-beige-100/30">(optional)</span></span>
      <input name="referral_reward_amount" type="number" step="0.01" min="0"
             placeholder="Leave blank for default"
             value="<?= e((string) ($event['referral_reward_amount'] ?? '')) ?>"
             class="mt-2 w-full rounded-2xl bg-navy-900 border border-white/5 px-4 py-3">
      <span class="text-[11px] text-beige-100/40 mt-1 block">Cash to the referrer when a friend attends this session. Blank uses the site default (<?= e(format_money((float) setting('referral_event_reward_default', 50.00))) ?>).</span>
    </label>
    <label class="block">
      <span class="text-xs uppercase tracking-widest text-beige-100/60">Primary price · Comfort (MYR)</span>
      <input name="price_public" type="number" step="0.01" value="<?= e((string)$event['price_public']) ?>" class="mt-2 w-full rounded-2xl bg-navy-900 border border-white/5 px-4 py-3">
      <span class="text-[11px] text-beige-100/40 mt-1 block">Charged for the Comfort (Package A) tier. Every event has this price.</span>
    </label>
    <!-- Second-tier price shown only when Package B is enabled below.
         Hidden fully so admins running a single-price event aren't
         puzzled by a stray "Member price" field. -->
    <label class="block" x-show="pkgB" x-cloak>
      <span class="text-xs uppercase tracking-widest text-beige-100/60">Second-tier price · BYO (MYR)</span>
      <input name="price_member" type="number" step="0.01" value="<?= e((string)$event['price_member']) ?>" class="mt-2 w-full rounded-2xl bg-navy-900 border border-white/5 px-4 py-3">
      <span class="text-[11px] text-beige-100/40 mt-1 block">Only used when the Bring-Your-Own-Zen tier is offered — toggled in the Booking packages section below.</span>
    </label>
    <div class="block" x-show="!pkgB" x-cloak>
      <span class="text-xs uppercase tracking-widest text-beige-100/60">Second-tier price</span>
      <p class="mt-2 rounded-2xl border border-dashed border-white/10 bg-navy-950/40 px-4 py-3 text-sm text-beige-100/50">
        Not offered for this event. Turn on the second tier in "Booking packages" below to set a price.
      </p>
    </div>
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
    <div class="block sm:col-span-2" x-data="{ rec: '<?= e((string) ($event['recurrence'] ?? 'none')) ?>' }">
      <label class="block">
        <span class="text-xs uppercase tracking-widest text-beige-100/60">Recurrence</span>
        <select name="recurrence" x-model="rec" class="mt-2 w-full rounded-2xl bg-navy-900 border border-white/5 px-4 py-3">
          <option value="none">One-off session (pick a specific date)</option>
          <option value="daily">Every day at the "Starts at" time</option>
          <option value="weekly">Every week on selected days</option>
          <option value="monthly">Monthly on the Nth weekday</option>
        </select>
        <span class="text-[11px] text-beige-100/40 mt-1 block">Recurring templates auto-show as bookable cards on the calendar (next 60 days) — a concrete event materialises per date when someone reserves.</span>
      </label>

      <div x-show="rec === 'weekly'" x-cloak class="mt-4 rounded-2xl border border-white/10 bg-navy-950/40 p-4">
        <p class="text-[11px] uppercase tracking-widest text-beige-100/55">Days of the week</p>
        <div class="mt-2 flex flex-wrap gap-2">
          <?php
            $selectedDays = array_map('intval', array_filter(
              explode(',', (string) ($event['recurrence_days'] ?? ''))
            , fn($v) => $v !== ''));
            foreach ([['0','Sun'],['1','Mon'],['2','Tue'],['3','Wed'],['4','Thu'],['5','Fri'],['6','Sat']] as [$v, $l]):
              $checked = in_array((int) $v, $selectedDays, true);
          ?>
            <label class="cursor-pointer">
              <input type="checkbox" name="recurrence_days[]" value="<?= $v ?>" <?= $checked ? 'checked' : '' ?> class="peer sr-only">
              <span class="px-3 py-1.5 rounded-full text-xs border border-white/10 bg-navy-950 text-beige-100/70 hover:border-gold-500/40 peer-checked:border-gold-500/50 peer-checked:bg-gold-500/15 peer-checked:text-gold-400 transition"><?= e($l) ?></span>
            </label>
          <?php endforeach; ?>
        </div>
        <span class="text-[11px] text-beige-100/40 mt-2 block">Session generates on each ticked day at the "Starts at" time.</span>
      </div>

      <div x-show="rec === 'monthly'" x-cloak class="mt-4 rounded-2xl border border-white/10 bg-navy-950/40 p-4">
        <p class="text-[11px] uppercase tracking-widest text-beige-100/55 mb-3">Nth weekday</p>
        <div class="grid sm:grid-cols-2 gap-3">
          <label class="block">
            <span class="text-[10px] uppercase tracking-widest text-beige-100/50">Ordinal</span>
            <select name="recurrence_monthly_ordinal" class="mt-1 w-full rounded-2xl bg-navy-900 border border-white/5 px-4 py-2.5 text-sm">
              <?php foreach (['1'=>'First','2'=>'Second','3'=>'Third','4'=>'Fourth','5'=>'Fifth','L'=>'Last'] as $v => $lbl): ?>
                <option value="<?= e($v) ?>" <?= $monthlyOrdinal === $v ? 'selected' : '' ?>><?= e($lbl) ?></option>
              <?php endforeach; ?>
            </select>
          </label>
          <label class="block">
            <span class="text-[10px] uppercase tracking-widest text-beige-100/50">Weekday</span>
            <select name="recurrence_monthly_dow" class="mt-1 w-full rounded-2xl bg-navy-900 border border-white/5 px-4 py-2.5 text-sm">
              <?php foreach (['SUN'=>'Sunday','MON'=>'Monday','TUE'=>'Tuesday','WED'=>'Wednesday','THU'=>'Thursday','FRI'=>'Friday','SAT'=>'Saturday'] as $v => $lbl): ?>
                <option value="<?= e($v) ?>" <?= $monthlyDow === $v ? 'selected' : '' ?>><?= e($lbl) ?></option>
              <?php endforeach; ?>
            </select>
          </label>
        </div>
        <span class="text-[11px] text-beige-100/40 mt-2 block">e.g. "First Sunday" or "Last Friday" — the session generates once each month on that day at the "Starts at" time.</span>
      </div>

      <div x-show="rec !== 'none'" x-cloak class="mt-4">
        <label class="block">
          <span class="text-xs uppercase tracking-widest text-beige-100/60">Skip these dates <span class="text-beige-100/30">(one YYYY-MM-DD per line)</span></span>
          <textarea name="recurrence_exceptions" rows="3"
                    placeholder="2026-08-31&#10;2026-12-25"
                    class="mt-2 w-full rounded-2xl bg-navy-900 border border-white/5 px-4 py-3 font-mono text-sm"><?= e(implode("\n", $existingExceptions)) ?></textarea>
          <span class="text-[11px] text-beige-100/40 mt-1 block">Public holidays, facilitator away, venue booked — dates listed here disappear from the calendar and can't be booked. Existing bookings on skipped dates aren't touched.</span>
        </label>
      </div>
    </div>
    <label class="block">
      <span class="text-xs uppercase tracking-widest text-beige-100/60">Recurs until <span class="text-beige-100/30">(optional)</span></span>
      <input name="recurrence_until" type="date" value="<?= e((string) ($event['recurrence_until'] ?? '')) ?>" class="mt-2 w-full rounded-2xl bg-navy-900 border border-white/5 px-4 py-3">
      <span class="text-[11px] text-beige-100/40 mt-1 block">Leave blank for indefinite. Only used when recurrence is Daily.</span>
    </label>
    <label class="block">
      <span class="text-xs uppercase tracking-widest text-beige-100/60">Audience</span>
      <select name="audience" class="mt-2 w-full rounded-2xl bg-navy-900 border border-white/5 px-4 py-3">
        <option value="public"  <?= ($event['audience'] ?? 'public') === 'public'  ? 'selected' : '' ?>>Public — appears on the calendar</option>
        <option value="private" <?= ($event['audience'] ?? 'public') === 'private' ? 'selected' : '' ?>>Private — hidden from public browse (direct link only)</option>
      </select>
      <span class="text-[11px] text-beige-100/40 mt-1 block">Private sessions still open via their direct URL so you can share an invite with specific customers.</span>
    </label>
    <label class="flex items-start gap-3 rounded-2xl border border-white/5 bg-navy-900/40 p-4">
      <input type="checkbox" name="credit_eligible" value="1" <?= !empty($event['credit_eligible']) || !isset($event['credit_eligible']) ? 'checked' : '' ?> class="mt-1 accent-gold-500">
      <span>
        <span class="text-sm text-beige-100">Class-pack credits can book this session</span>
        <span class="block text-[11px] text-beige-100/45 mt-1">Uncheck for premium / group / partner sessions that must be paid — the "Use 1 credit" option is hidden on the booking form.</span>
      </span>
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

        <!-- Toggle bound to shared Alpine state so the "Second-tier price"
             field above and the label/perks inputs below react together.
             Default on for existing events so they don't change silently. -->
        <label class="flex items-start gap-3 rounded-2xl border border-gold-500/20 bg-gold-500/5 px-4 py-3 cursor-pointer">
          <input type="checkbox" name="package_b_enabled" value="1"
                 x-model="pkgB"
                 class="mt-1 accent-gold-500">
          <span>
            <span class="text-sm text-beige-100">Offer this package on the booking page</span>
            <span class="block text-[11px] text-beige-100/50 mt-1">Turn off for events that only ship one tier — e.g. a single-price workshop or a private corporate session. Comfort will fill the whole card row and the second-tier price above will be hidden.</span>
          </span>
        </label>

        <div x-show="pkgB" x-cloak class="space-y-3">
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
    </div>
  </section>

  <div class="flex gap-3">
    <button class="px-6 py-3 rounded-full bg-gold-500 text-navy-950 hover:bg-gold-400 transition">Save</button>
    <a href="<?= url('/admin/events.php') ?>" class="px-6 py-3 rounded-full border border-white/10 text-beige-100/70 hover:border-white/20">Cancel</a>
  </div>
</form>
<?php require __DIR__ . '/../includes/admin_layout_end.php'; ?>
