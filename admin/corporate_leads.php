<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_admin();
$pageTitle = 'Corporate leads';

if (is_post()) {
    csrf_verify();
    $id = (int) input('id', 0);
    $status = input('status');
    if ($id && in_array($status, ['new','contacted','proposal_sent','won','lost'], true)) {
        db()->prepare("UPDATE corporate_inquiries SET status = :s, assigned_to = :u WHERE id = :id")
            ->execute([':s' => $status, ':u' => current_user_id(), ':id' => $id]);
        audit_log('corporate.update', 'corporate_inquiries', $id, ['status' => $status]);

        // When a lead flips to 'won' AND the inquiry had a package
        // attached AND we haven't already spawned an event, create a
        // draft/private event from the package details so bookings
        // can be tracked. Idempotent via spawned_event_id.
        if ($status === 'won') {
            $inq = db()->prepare(
                "SELECT ci.*, cp.name AS pkg_name, cp.tagline AS pkg_tagline,
                        cp.description AS pkg_description, cp.image AS pkg_image,
                        cp.seat_count AS pkg_seat_count
                   FROM corporate_inquiries ci
                   LEFT JOIN corporate_packages cp ON cp.id = ci.package_id
                  WHERE ci.id = :id LIMIT 1"
            );
            $inq->execute([':id' => $id]);
            $r = $inq->fetch();

            if ($r && (int) ($r['package_id'] ?? 0) > 0 && empty($r['spawned_event_id'])) {
                $company = trim((string) $r['company_name']);
                $title   = trim($company . ' · ' . (string) $r['pkg_name']);
                if ($title === '' || $title === '· ') $title = (string) $r['pkg_name'];
                $title   = mb_substr($title, 0, 200);

                // Capacity: package seat count → team-size digits → 20.
                $capacity = (int) ($r['pkg_seat_count'] ?? 0);
                if ($capacity <= 0 && preg_match('/(\d+)/', (string) ($r['team_size'] ?? ''), $m)) {
                    $capacity = (int) $m[1];
                }
                if ($capacity <= 0) $capacity = 20;

                // Placeholder date — admin edits before publishing.
                $startsAt = date('Y-m-d 18:00:00', strtotime('+14 days'));
                $endsAt   = date('Y-m-d 20:00:00', strtotime('+14 days'));

                // Slug — unique per inquiry so re-runs stay clean even
                // if two "wins" ever share a company + package name.
                $slugBase = strtolower(preg_replace('/[^a-z0-9]+/i', '-', $title) ?? '');
                $slug     = trim('corp-' . $id . '-' . $slugBase, '-');
                $slug     = substr($slug, 0, 180);

                try {
                    $ins = db()->prepare(
                        "INSERT INTO events
                            (slug, title, subtitle, description, cover_image,
                             starts_at, ends_at, capacity, price_public, price_member,
                             status, audience, credit_eligible,
                             category, created_by)
                         VALUES
                            (:slug, :title, :sub, :desc, :cover,
                             :s, :e, :cap, 0, 0,
                             'draft', 'private', 0,
                             'corporate', :cb)"
                    );
                    $ins->execute([
                        ':slug'  => $slug,
                        ':title' => $title,
                        ':sub'   => $r['pkg_tagline'] ?: null,
                        ':desc'  => $r['pkg_description'] ?: null,
                        ':cover' => $r['pkg_image'] ?: null,
                        ':s'     => $startsAt,
                        ':e'     => $endsAt,
                        ':cap'   => $capacity,
                        ':cb'    => current_user_id(),
                    ]);
                    $newEventId = (int) db()->lastInsertId();

                    db()->prepare("UPDATE corporate_inquiries SET spawned_event_id = :e WHERE id = :id")
                        ->execute([':e' => $newEventId, ':id' => $id]);

                    audit_log('corporate.event_spawned', 'events', $newEventId, ['inquiry_id' => $id]);
                    flash('corporate', 'Marked as won and created draft event #' . $newEventId . '. Edit it to set the date and publish.', 'success');
                } catch (Throwable $e) {
                    // Duplicate slug or DB oddity — surface but don't
                    // undo the status change; admin can retry.
                    error_log('[corporate_leads] spawn failed: ' . $e->getMessage());
                    flash('corporate', 'Marked as won, but could not auto-create the event: ' . $e->getMessage(), 'error');
                }
            }
        }
    }
    redirect('/admin/corporate_leads.php');
}

$rows = db()->query(
    "SELECT ci.*, cp.name AS package_name,
            ev.title AS spawned_event_title, ev.starts_at AS spawned_event_starts_at, ev.status AS spawned_event_status
       FROM corporate_inquiries ci
       LEFT JOIN corporate_packages cp ON cp.id = ci.package_id
       LEFT JOIN events ev             ON ev.id = ci.spawned_event_id
      ORDER BY ci.created_at DESC LIMIT 200"
)->fetchAll();
require __DIR__ . '/../includes/admin_layout.php';
?>
<h1 class="font-serif text-3xl text-beige-100">Corporate inquiries</h1>

<div class="mt-6 space-y-4">
  <?php foreach ($rows as $r): ?>
    <article class="border border-white/5 rounded-2xl p-5 bg-navy-900/40">
      <div class="flex flex-wrap justify-between gap-3">
        <div>
          <p class="font-serif text-xl text-beige-100"><?= e($r['company_name']) ?></p>
          <p class="text-sm text-beige-100/60"><?= e($r['contact_name']) ?> · <?= e($r['contact_email']) ?> <?php if ($r['contact_phone']): ?>· <?= e($r['contact_phone']) ?><?php endif; ?></p>
          <p class="text-xs text-beige-100/40 mt-1"><?= e(format_datetime($r['created_at'])) ?> · Team size: <?= e($r['team_size'] ?? '—') ?></p>
          <?php if (!empty($r['package_name'])): ?>
            <p class="mt-2 inline-block text-[10px] uppercase tracking-widest px-3 py-1 rounded-full border border-gold-500/25 bg-gold-500/5 text-gold-400">
              Interested in: <?= e($r['package_name']) ?>
            </p>
          <?php endif; ?>
          <?php if (!empty($r['spawned_event_id'])): ?>
            <a href="<?= url('/admin/event_form.php?id=' . (int) $r['spawned_event_id']) ?>"
               class="mt-2 ml-1 inline-flex items-center gap-2 text-[10px] uppercase tracking-widest px-3 py-1 rounded-full border border-white/10 text-beige-100/75 hover:border-gold-500/40 hover:text-gold-400 transition">
              → <?= $r['spawned_event_status'] === 'published' ? 'Event' : 'Draft event' ?>
              <?php if (!empty($r['spawned_event_starts_at'])): ?>
                <span class="text-beige-100/50 normal-case tracking-normal"><?= e(format_datetime($r['spawned_event_starts_at'], 'd M Y')) ?></span>
              <?php endif; ?>
            </a>
          <?php endif; ?>
        </div>
        <form method="post" class="flex items-center gap-2">
          <?= csrf_field() ?>
          <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
          <select name="status" class="rounded-full bg-navy-950 border border-white/5 px-3 py-1 text-sm">
            <?php foreach (['new','contacted','proposal_sent','won','lost'] as $opt): ?>
              <option value="<?= $opt ?>" <?= $r['status'] === $opt ? 'selected' : '' ?>><?= $opt ?></option>
            <?php endforeach; ?>
          </select>
          <button class="text-sm text-gold-400">Update</button>
        </form>
      </div>
      <?php if ($r['message']): ?>
        <p class="mt-3 text-sm text-beige-100/80 leading-relaxed"><?= nl2br(e($r['message'])) ?></p>
      <?php endif; ?>
    </article>
  <?php endforeach; ?>
  <?php if (!$rows): ?>
    <p class="text-beige-100/60">No corporate inquiries yet.</p>
  <?php endif; ?>
</div>
<?php require __DIR__ . '/../includes/admin_layout_end.php'; ?>
