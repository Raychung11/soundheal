<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_staff_or_admin();
$pageTitle = 'Events';

if (is_post()) {
    csrf_verify();
    $action = input('action');
    if ($action === 'delete') {
        $id = (int) input('id');
        if ($id) {
            db()->prepare("UPDATE events SET status = 'archived' WHERE id = :id")->execute([':id' => $id]);
            audit_log('event.archive', 'events', $id);
        }
    }
    redirect('/admin/events.php');
}

// Match book_event.php's capacity check: pending bookings hold a seat
// until they pay or the hold times out, so they should count in the
// "seats taken" display too. Cancelled/refunded/no_show bookings free
// the seat and are excluded.
$events = db()->query(
    "SELECT e.*,
            x.title AS experience_title, x.slug AS experience_slug,
            (SELECT COALESCE(SUM(quantity), 0)
               FROM event_bookings
               WHERE event_id = e.id
                 AND status IN ('pending','paid','attended')) AS seats_taken,
            (SELECT COALESCE(SUM(quantity), 0)
               FROM event_bookings
               WHERE event_id = e.id
                 AND status IN ('paid','attended')) AS seats_paid
     FROM events e
     LEFT JOIN experiences x ON x.id = e.experience_id
     ORDER BY e.starts_at DESC LIMIT 100"
)->fetchAll();

require __DIR__ . '/../includes/admin_layout.php';
?>
<div class="flex justify-between items-center">
  <h1 class="font-serif text-3xl text-beige-100">Events</h1>
  <a href="<?= url('/admin/event_form.php') ?>" class="px-5 py-2 rounded-full bg-gold-500 text-navy-950 text-sm hover:bg-gold-400 transition">+ New session</a>
</div>

<div class="mt-8 border border-white/5 rounded-2xl bg-navy-900/40 overflow-hidden">
  <table class="w-full text-sm">
    <thead class="text-left text-beige-100/50 text-xs uppercase tracking-wider bg-navy-950/40">
      <tr>
        <th class="px-4 py-3">Title</th><th>Experience</th><th>When</th><th>Status</th><th>Seats</th><th></th>
      </tr>
    </thead>
    <tbody class="divide-y divide-white/5">
      <?php foreach ($events as $e): ?>
        <tr>
          <td class="px-4 py-3">
            <p class="text-beige-100"><?= e($e['title']) ?></p>
            <p class="text-xs text-beige-100/40"><?= e($e['location'] ?? '') ?></p>
          </td>
          <td>
            <?php if (!empty($e['experience_title'])): ?>
              <span class="text-xs px-2 py-1 rounded-full bg-gold-500/10 text-gold-400 border border-gold-500/25"><?= e($e['experience_title']) ?></span>
            <?php else: ?>
              <a href="<?= url('/admin/event_form.php?id=' . (int)$e['id']) ?>#experience_id"
                 class="text-xs text-beige-100/45 hover:text-gold-400 underline-offset-4 hover:underline">— link</a>
            <?php endif; ?>
          </td>
          <td><?= e(format_datetime($e['starts_at'])) ?></td>
          <td><span class="text-xs px-2 py-1 rounded-full <?= $e['status'] === 'published' ? 'bg-gold-500/20 text-gold-400' : 'bg-white/5 text-beige-100/60' ?>"><?= e($e['status']) ?></span></td>
          <td>
            <span class="text-beige-100"><?= (int)$e['seats_taken'] ?></span>
            <span class="text-beige-100/40">/ <?= (int)$e['capacity'] ?></span>
            <?php $pending = (int) $e['seats_taken'] - (int) $e['seats_paid']; if ($pending > 0): ?>
              <span class="ml-2 text-[10px] uppercase tracking-widest text-beige-100/50">
                <span class="text-gold-400/80"><?= (int)$e['seats_paid'] ?> paid</span> · <?= $pending ?> pending
              </span>
            <?php endif; ?>
          </td>
          <td class="text-right pr-4">
            <a href="<?= url('/admin/event_form.php?id=' . (int)$e['id']) ?>" class="text-gold-400 text-sm hover:text-gold-300">Edit</a>
            <form method="post" class="inline ml-3" onsubmit="return confirm('Archive this event?');">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="id" value="<?= (int)$e['id'] ?>">
              <button class="text-red-300/80 text-sm hover:text-red-300">Archive</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (!$events): ?>
        <tr><td colspan="6" class="px-4 py-6 text-beige-100/60">No events yet.</td></tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>
<?php require __DIR__ . '/../includes/admin_layout_end.php'; ?>
