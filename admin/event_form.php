<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_staff_or_admin();

$id = (int) input('id', 0);
$event = ['id' => 0, 'slug' => '', 'title' => '', 'subtitle' => '', 'description' => '',
          'cover_image' => '', 'location' => '', 'starts_at' => '', 'ends_at' => '',
          'capacity' => 30, 'price_public' => 0, 'price_member' => 0,
          'facilitator' => '', 'category' => '', 'status' => 'draft',
          'recurrence' => 'none', 'recurrence_until' => '', 'recurrence_days' => '', 'custom_dates' => '',
          'time_slots' => '',
          'package_a_label' => '', 'package_a_perks' => '',
          'package_b_label' => '', 'package_b_perks' => '',
          'package_b_enabled' => 1,
          'package_a_humans' => 1, 'package_a_pets' => 2,
          'package_b_humans' => 1, 'package_b_pets' => 1,
          'intake_type' => 'none',
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
        'recurrence'      => in_array(input('recurrence'), ['none','daily','weekly','monthly','custom'], true) ? input('recurrence') : 'none',
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
        // Parse the comma-separated date list the Alpine chip picker
        // POSTs. Kept only for recurrence='custom' — other modes ignore
        // it. Whitespace/duplicate/malformed entries are dropped so a
        // stray paste from a spreadsheet doesn't poison the row.
        'custom_dates' => (function () {
            if ((string) input('recurrence', 'none') !== 'custom') return null;
            $raw = (string) input('custom_dates', '');
            $dates = [];
            foreach (preg_split('/[\s,;]+/', $raw) ?: [] as $d) {
                $d = trim($d);
                if ($d === '') continue;
                if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) && strtotime($d) !== false) {
                    $dates[$d] = true;
                }
            }
            $sorted = array_keys($dates);
            sort($sorted);
            return $sorted ? implode(',', $sorted) : null;
        })(),
        // Additional time-of-day slots on each occurrence date, HH:MM
        // CSV. The primary starts_at time is implicit; this list is
        // extras. Empty = single-slot event (existing behaviour).
        'time_slots' => (function () {
            $raw = (string) input('time_slots', '');
            $out = [];
            foreach (preg_split('/[\s,;]+/', $raw) ?: [] as $t) {
                $t = trim($t);
                if ($t === '') continue;
                if (!preg_match('/^(\d{1,2}):(\d{2})$/', $t, $m)) continue;
                $h = (int) $m[1]; $mi = (int) $m[2];
                if ($h < 0 || $h > 23 || $mi < 0 || $mi > 59) continue;
                $out[sprintf('%02d:%02d', $h, $mi)] = true;
            }
            $sorted = array_keys($out);
            sort($sorted);
            return $sorted ? implode(',', $sorted) : null;
        })(),
        'package_a_label' => trim((string) input('package_a_label', '')),
        'package_a_perks' => trim((string) input('package_a_perks', '')),
        'package_b_label' => trim((string) input('package_b_label', '')),
        'package_b_perks' => trim((string) input('package_b_perks', '')),
        'package_b_enabled' => !empty($_POST['package_b_enabled']) ? 1 : 0,
        // Intake composition — how many humans + pets per package tier.
        // Clamped 0..8 so a stray large number can't blow up the form.
        'package_a_humans' => max(0, min(8, (int) input('package_a_humans', 1))),
        'package_a_pets'   => max(0, min(8, (int) input('package_a_pets',   2))),
        'package_b_humans' => max(0, min(8, (int) input('package_b_humans', 1))),
        'package_b_pets'   => max(0, min(8, (int) input('package_b_pets',   1))),
        'intake_type'      => in_array(input('intake_type'), ['none','pet'], true) ? input('intake_type') : 'none',
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

    // Multi-slot support requires the event to run as a template so
    // each slot can materialise its own bookable child. When an admin
    // sets extra same-day slots on a plain one-off event (recurrence
    // 'none'), transparently promote it to recurrence='custom' with
    // just the starts_at date — that way the expander emits every
    // slot and the resolver can materialise a child per slot without
    // needing a special one-off + multi-slot code path.
    if ($event['recurrence'] === 'none' && !empty($event['time_slots']) && !empty($event['starts_at'])) {
        $event['recurrence']   = 'custom';
        $event['custom_dates'] = date('Y-m-d', strtotime((string) $event['starts_at']));
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
                 recurrence=:rec, recurrence_until=:ru, recurrence_days=:rd, custom_dates=:cd, time_slots=:ts,
                 package_a_label=:pal, package_a_perks=:pap,
                 package_b_label=:pbl, package_b_perks=:pbp,
                 package_b_enabled=:pbe,
                 package_a_humans=:pah, package_a_pets=:pap2,
                 package_b_humans=:pbh, package_b_pets=:pbp2,
                 intake_type=:itype,
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
                ':cd'  => $event['custom_dates'] ?: null,
                ':ts'  => $event['time_slots'] ?: null,
                ':pal' => $event['package_a_label'] ?: null,
                ':pap' => $event['package_a_perks'] ?: null,
                ':pbl' => $event['package_b_label'] ?: null,
                ':pbp' => $event['package_b_perks'] ?: null,
                ':pbe' => (int) $event['package_b_enabled'],
                ':pah'  => (int) $event['package_a_humans'],
                ':pap2' => (int) $event['package_a_pets'],
                ':pbh'  => (int) $event['package_b_humans'],
                ':pbp2' => (int) $event['package_b_pets'],
                ':itype' => (string) $event['intake_type'],
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
                        c.package_b_enabled = :pbe,
                        c.package_a_humans = :pah, c.package_a_pets = :pap2,
                        c.package_b_humans = :pbh, c.package_b_pets = :pbp2,
                        c.intake_type = :itype
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
                ':pah'  => (int) $event['package_a_humans'],
                ':pap2' => (int) $event['package_a_pets'],
                ':pbh'  => (int) $event['package_b_humans'],
                ':pbp2' => (int) $event['package_b_pets'],
                ':itype' => (string) $event['intake_type'],
                ':id' => $id,
            ]);
        } else {
            $stmt = db()->prepare(
                "INSERT INTO events (slug, title, subtitle, description, cover_image, location, starts_at, ends_at,
                                     capacity, price_public, price_member, facilitator, category, status,
                                     recurrence, recurrence_until, recurrence_days, custom_dates, time_slots,
                                     package_a_label, package_a_perks, package_b_label, package_b_perks,
                                     package_b_enabled,
                                     package_a_humans, package_a_pets, package_b_humans, package_b_pets,
                                     intake_type,
                                     experience_id, referral_reward_amount,
                                     audience, credit_eligible, created_by)
                 VALUES (:slug, :t, :st, :d, :ci, :l, :s, :e, :c, :pp, :pm, :f, :cat, :status,
                         :rec, :ru, :rd, :cd, :ts, :pal, :pap, :pbl, :pbp, :pbe,
                         :pah, :pap2, :pbh, :pbp2,
                         :itype,
                         :xid, :rra, :aud, :cel, :uid)"
            );
            $stmt->execute([
                ':slug' => $slug, ':t' => $event['title'], ':st' => $event['subtitle'],
                ':d' => $event['description'], ':ci' => $event['cover_image'], ':l' => $event['location'],
                ':s' => $event['starts_at'], ':e' => $event['ends_at'],
                ':c' => $event['capacity'], ':pp' => $event['price_public'], ':pm' => $event['price_member'],
                ':f' => $event['facilitator'], ':cat' => $event['category'], ':status' => $event['status'],
                ':rec' => $event['recurrence'], ':ru' => $event['recurrence_until'],
                ':rd'  => $event['recurrence_days'] ?: null,
                ':cd'  => $event['custom_dates'] ?: null,
                ':ts'  => $event['time_slots'] ?: null,
                ':pal' => $event['package_a_label'] ?: null,
                ':pap' => $event['package_a_perks'] ?: null,
                ':pbl' => $event['package_b_label'] ?: null,
                ':pbp' => $event['package_b_perks'] ?: null,
                ':pbe' => (int) $event['package_b_enabled'],
                ':pah'  => (int) $event['package_a_humans'],
                ':pap2' => (int) $event['package_a_pets'],
                ':pbh'  => (int) $event['package_b_humans'],
                ':pbp2' => (int) $event['package_b_pets'],
                ':itype' => (string) $event['intake_type'],
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

        // Persist the dynamic package list. The Alpine repeater POSTs
        // packages[i][id|label|price|perks|humans|pets|status]; the
        // helper deletes rows not in the incoming set, updates existing
        // ones, and inserts new ones. Legacy events.package_a/b_*
        // columns get auto-synced from the first two active packages
        // so pages still reading the old columns render correctly.
        if (array_key_exists('packages', $_POST) && is_array($_POST['packages'])) {
            $incoming = [];
            foreach ((array) $_POST['packages'] as $row) {
                if (!is_array($row)) continue;
                $incoming[] = [
                    'id'     => (int) ($row['id']     ?? 0),
                    'label'  => (string) ($row['label']  ?? ''),
                    'price'  => (float) ($row['price']  ?? 0),
                    'perks'  => (string) ($row['perks']  ?? ''),
                    'humans' => (int) ($row['humans'] ?? 1),
                    'pets'   => (int) ($row['pets']   ?? 0),
                    'status' => (string) ($row['status'] ?? 'active'),
                ];
            }
            try {
                event_packages_save((int) $id, $incoming);
            } catch (Throwable $e) {
                $errors[] = 'Could not save packages: ' . $e->getMessage();
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

    <!-- Additional same-day time slots. Applies to recurring templates
         only (each candidate date generates one occurrence per slot
         time). Leave empty for a single-slot event — the primary
         starts_at time is always included implicitly. -->
    <div class="block sm:col-span-2" x-data="{
           picker: '',
           slots: (<?= htmlspecialchars(json_encode(array_values(array_filter(array_map('trim',
                     preg_split('/[\s,;]+/', (string) ($event['time_slots'] ?? '')))
                   , fn($t) => (bool) preg_match('/^\d{1,2}:\d{2}$/', $t)))), ENT_QUOTES, 'UTF-8') ?>),
           add() {
             if (!/^\d{1,2}:\d{2}$/.test(this.picker)) return;
             const [h, m] = this.picker.split(':').map(n => parseInt(n, 10));
             if (h < 0 || h > 23 || m < 0 || m > 59) { this.picker = ''; return; }
             const t = String(h).padStart(2,'0') + ':' + String(m).padStart(2,'0');
             if (!this.slots.includes(t)) { this.slots.push(t); this.slots.sort(); }
             this.picker = '';
           },
           remove(t) { this.slots = this.slots.filter(x => x !== t); },
           fmt(t) {
             const [h, m] = t.split(':').map(n => parseInt(n, 10));
             const ampm = h >= 12 ? 'PM' : 'AM';
             const hh = ((h + 11) % 12) + 1;
             return hh + ':' + String(m).padStart(2, '0') + ' ' + ampm;
           }
         }">
      <span class="text-xs uppercase tracking-widest text-beige-100/60">Extra same-day slots <span class="text-beige-100/30">(optional)</span></span>
      <p class="text-[11px] text-beige-100/40 mt-1">Each date runs at the primary "Starts at" time above plus every extra time listed here. Duration is inherited from starts/ends above. Use for a 3pm + 6pm double-header on the same day — works on both one-off and recurring events.</p>
      <div class="mt-2 flex flex-wrap gap-2 items-center">
        <input type="time" x-model="picker"
               class="rounded-2xl bg-navy-900 border border-white/5 px-4 py-2.5 text-sm">
        <button type="button" @click="add()"
                class="px-4 py-2 rounded-full bg-gold-500 text-navy-950 font-medium hover:bg-gold-400 transition text-sm">
          + Add slot
        </button>
      </div>
      <div class="mt-3 flex flex-wrap gap-2">
        <template x-for="t in slots" :key="t">
          <span class="inline-flex items-center gap-2 px-3 py-1.5 rounded-full border border-gold-500/40 bg-gold-500/10 text-gold-400 text-xs">
            <span x-text="fmt(t)"></span>
            <button type="button" @click="remove(t)" class="text-gold-400/70 hover:text-gold-300" aria-label="Remove">×</button>
          </span>
        </template>
        <span x-show="!slots.length" class="text-[11px] text-beige-100/40 italic">Single slot — event runs once per date at the primary time.</span>
      </div>
      <input type="hidden" name="time_slots" :value="slots.join(',')">
    </div>
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
    <!-- Prices moved into the Booking packages repeater below. The
         legacy events.price_public / price_member columns are kept in
         sync automatically from the first two active packages on save. -->
    <input type="hidden" name="price_public" value="<?= e((string)$event['price_public']) ?>">
    <input type="hidden" name="price_member" value="<?= e((string)$event['price_member']) ?>">
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
          <option value="custom">Custom dates (pick each one manually)</option>
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

      <!-- Custom dates picker — visible only when the recurrence mode is
           "custom". Each date the admin picks becomes a chip; removing
           a chip drops the date. On save, the list is stringified into
           events.custom_dates as a comma-separated CSV. -->
      <div x-show="rec === 'custom'" x-cloak class="mt-4 rounded-2xl border border-white/10 bg-navy-950/40 p-4"
           x-data="{
             picker: '',
             dates: (<?= htmlspecialchars(json_encode(array_values(array_filter(array_map('trim',
                       preg_split('/[\s,;]+/', (string) ($event['custom_dates'] ?? '')))
                     , fn($d) => (bool) preg_match('/^\d{4}-\d{2}-\d{2}$/', $d)))), ENT_QUOTES, 'UTF-8') ?>),
             add() {
               if (!/^\d{4}-\d{2}-\d{2}$/.test(this.picker)) return;
               if (this.dates.includes(this.picker)) { this.picker = ''; return; }
               this.dates.push(this.picker);
               this.dates.sort();
               this.picker = '';
             },
             remove(d) { this.dates = this.dates.filter(x => x !== d); },
             fmt(d) {
               const t = new Date(d + 'T00:00:00');
               return t.toLocaleDateString('en-GB', { weekday: 'short', day: 'numeric', month: 'short', year: 'numeric' });
             }
           }">
        <p class="text-[11px] uppercase tracking-widest text-beige-100/55">Custom dates</p>
        <p class="text-[11px] text-beige-100/40 mt-1">Pick each date the session runs on. The list is stored as the exact set of occurrences — no pattern derivation.</p>

        <div class="mt-3 flex flex-wrap gap-2 items-center">
          <input type="date" x-model="picker"
                 class="rounded-2xl bg-navy-900 border border-white/5 px-4 py-2.5 text-sm">
          <button type="button" @click="add()"
                  class="px-4 py-2 rounded-full bg-gold-500 text-navy-950 font-medium hover:bg-gold-400 transition text-sm">
            + Add date
          </button>
        </div>

        <!-- Chip list of picked dates. The hidden input carries the
             CSV so the standard form POST works — no JS on the server side. -->
        <div class="mt-3 flex flex-wrap gap-2">
          <template x-for="d in dates" :key="d">
            <span class="inline-flex items-center gap-2 px-3 py-1.5 rounded-full border border-gold-500/40 bg-gold-500/10 text-gold-400 text-xs">
              <span x-text="fmt(d)"></span>
              <button type="button" @click="remove(d)" class="text-gold-400/70 hover:text-gold-300" aria-label="Remove">×</button>
            </span>
          </template>
          <span x-show="!dates.length" class="text-[11px] text-beige-100/40 italic">No dates yet — pick one above.</span>
        </div>

        <input type="hidden" name="custom_dates" :value="dates.join(',')">
      </div>

      <div x-show="rec !== 'none' && rec !== 'custom'" x-cloak class="mt-4">
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

  <?php
    // Load current packages (or a starter pair for a brand-new event
    // so the admin has something to edit rather than an empty screen).
    $currentPackages = $id > 0 ? event_packages_load($id) : [];
    if (!$currentPackages) {
        $currentPackages = [
            ['id' => 0, 'label' => 'Comfort', 'price' => (float) ($event['price_public'] ?? 0),
             'perks' => (string) ($event['package_a_perks'] ?? ''),
             'humans' => (int) ($event['package_a_humans'] ?? 1),
             'pets'   => (int) ($event['package_a_pets']   ?? 0),
             'status' => 'active'],
        ];
    }
  ?>
  <section class="border-t border-white/5 pt-6 space-y-5"
           x-data='{ packages: <?= htmlspecialchars(json_encode(array_map(fn($p) => [
             "id"     => (int) ($p["id"] ?? 0),
             "label"  => (string) $p["label"],
             "price"  => (float) $p["price"],
             "perks"  => (string) ($p["perks"] ?? ""),
             "humans" => (int) $p["humans"],
             "pets"   => (int) $p["pets"],
             "status" => (string) ($p["status"] ?? "active"),
           ], $currentPackages), JSON_UNESCAPED_UNICODE), ENT_QUOTES, "UTF-8") ?>,
             add() {
               this.packages.push({ id: 0, label: "", price: 0, perks: "", humans: 1, pets: 0, status: "active" });
             },
             remove(i) {
               if (!confirm("Remove this package?")) return;
               this.packages.splice(i, 1);
             },
             move(i, dir) {
               const j = i + dir;
               if (j < 0 || j >= this.packages.length) return;
               [this.packages[i], this.packages[j]] = [this.packages[j], this.packages[i]];
             }
           }'>
    <div class="flex items-start justify-between gap-4 flex-wrap">
      <div>
        <h2 class="font-serif text-xl text-gold-400">Booking packages</h2>
        <p class="text-[11px] text-beige-100/45 mt-1">Every bookable tier for this event. Label + price + perks + how many humans and pets the tier expects at booking. Add as many as you need — Adult, Adult + Pet, Two Adults, Couples, whatever fits the workshop.</p>
      </div>
      <button type="button" @click="add()" class="px-4 py-2 rounded-full bg-gold-500 text-navy-950 hover:bg-gold-400 transition text-sm">+ Add package</button>
    </div>

    <div class="space-y-4">
      <template x-for="(pkg, i) in packages" :key="i">
        <div class="rounded-2xl border border-white/10 bg-navy-950/40 p-5 space-y-4">
          <div class="flex items-center justify-between gap-3">
            <p class="text-[11px] uppercase tracking-widest text-gold-400/85">
              Package <span x-text="i + 1"></span>
              <span x-show="pkg.status === 'disabled'" class="ml-2 text-beige-100/40 normal-case">(disabled — hidden from booking page)</span>
            </p>
            <div class="flex items-center gap-2 text-xs">
              <button type="button" @click="move(i, -1)" :disabled="i === 0" class="px-2 py-1 rounded-full border border-white/10 text-beige-100/60 hover:text-gold-400 disabled:opacity-30 disabled:cursor-not-allowed">↑</button>
              <button type="button" @click="move(i, 1)" :disabled="i === packages.length - 1" class="px-2 py-1 rounded-full border border-white/10 text-beige-100/60 hover:text-gold-400 disabled:opacity-30 disabled:cursor-not-allowed">↓</button>
              <button type="button" @click="pkg.status = pkg.status === 'active' ? 'disabled' : 'active'"
                      class="px-3 py-1 rounded-full border border-white/10 text-beige-100/60 hover:text-gold-400">
                <span x-text="pkg.status === 'active' ? 'Disable' : 'Enable'"></span>
              </button>
              <button type="button" @click="remove(i)" class="px-3 py-1 rounded-full border border-red-500/30 text-red-300/80 hover:bg-red-500/10">Remove</button>
            </div>
          </div>

          <input type="hidden" :name="`packages[${i}][id]`"     :value="pkg.id">
          <input type="hidden" :name="`packages[${i}][status]`" :value="pkg.status">

          <div class="grid sm:grid-cols-3 gap-3">
            <label class="block sm:col-span-2">
              <span class="text-[10px] uppercase tracking-widest text-beige-100/50">Label</span>
              <input :name="`packages[${i}][label]`" x-model="pkg.label" placeholder="e.g. Adult · Comfort"
                     class="mt-1 w-full rounded-2xl bg-navy-900 border border-white/5 px-4 py-2.5">
            </label>
            <label class="block">
              <span class="text-[10px] uppercase tracking-widest text-beige-100/50">Price (MYR)</span>
              <input :name="`packages[${i}][price]`" x-model.number="pkg.price" type="number" step="0.01" min="0"
                     class="mt-1 w-full rounded-2xl bg-navy-900 border border-white/5 px-4 py-2.5">
            </label>
            <label class="block sm:col-span-3">
              <span class="text-[10px] uppercase tracking-widest text-beige-100/50">Perks (one per line)</span>
              <textarea :name="`packages[${i}][perks]`" x-model="pkg.perks" rows="4"
                        placeholder="Welcome drink&#10;Yoga mat provided&#10;Full sound healing experience"
                        class="mt-1 w-full rounded-2xl bg-navy-900 border border-white/5 px-4 py-2.5 text-sm"></textarea>
            </label>
            <label class="block">
              <span class="text-[10px] uppercase tracking-widest text-beige-100/50">Humans on this tier</span>
              <input :name="`packages[${i}][humans]`" x-model.number="pkg.humans" type="number" min="0" max="8"
                     class="mt-1 w-full rounded-2xl bg-navy-900 border border-white/5 px-4 py-2.5">
              <span class="text-[11px] text-beige-100/40 mt-1 block">Incl. primary attendee.</span>
            </label>
            <label class="block">
              <span class="text-[10px] uppercase tracking-widest text-beige-100/50">Pets on this tier</span>
              <input :name="`packages[${i}][pets]`" x-model.number="pkg.pets" type="number" min="0" max="8"
                     class="mt-1 w-full rounded-2xl bg-navy-900 border border-white/5 px-4 py-2.5">
            </label>
          </div>
        </div>
      </template>
      <p x-show="!packages.length" class="text-[11px] text-beige-100/45 italic">No packages defined — click "+ Add package" to create one.</p>
    </div>
  </section>

  <!-- Intake — extra info collected at booking. When set to "pet",
       every package's humans + pets counts (edited in the Booking
       packages section above) decide the shape of the form the
       customer sees. -->
  <section class="border-t border-white/5 pt-6 space-y-5">
    <div>
      <h2 class="font-serif text-xl text-gold-400">Intake questions</h2>
      <p class="text-[11px] text-beige-100/45 mt-1">Extra info collected at booking. Turn on the pet workshop mode when the session includes pets — the humans + pets numbers on each package (above) then decide how many attendee blocks appear.</p>
    </div>

    <label class="block">
      <span class="text-xs uppercase tracking-widest text-beige-100/60">Intake type</span>
      <select name="intake_type" class="mt-2 w-full rounded-2xl bg-navy-900 border border-white/5 px-4 py-3">
        <option value="none" <?= ($event['intake_type'] ?? 'none') === 'none' ? 'selected' : '' ?>>None — booking form has no extra questions</option>
        <option value="pet"  <?= ($event['intake_type'] ?? 'none') === 'pet'  ? 'selected' : '' ?>>Pet workshop — collect pawrent + per-package humans &amp; pets</option>
      </select>
    </label>
  </section>

  <div class="flex gap-3">
    <button class="px-6 py-3 rounded-full bg-gold-500 text-navy-950 hover:bg-gold-400 transition">Save</button>
    <a href="<?= url('/admin/events.php') ?>" class="px-6 py-3 rounded-full border border-white/10 text-beige-100/70 hover:border-white/20">Cancel</a>
  </div>
</form>
<?php require __DIR__ . '/../includes/admin_layout_end.php'; ?>
