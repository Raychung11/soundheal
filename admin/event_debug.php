<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_admin();
$pageTitle = 'Event debug';

/**
 * Diagnostic dump for a single event and its recurring children.
 *
 *   /admin/event_debug.php?id=9
 *
 * Shows the raw DB state so we can see whether template edits are
 * cascading, whether child instances inherited config correctly, and
 * which fields are out of sync. Read-only — never mutates anything.
 */

$id = (int) input('id', 0);
if ($id <= 0) {
    $eventsList = db()->query(
        "SELECT id, title, starts_at, status, recurrence, parent_event_id
           FROM events ORDER BY id DESC LIMIT 30"
    )->fetchAll();
    require __DIR__ . '/../includes/admin_layout.php';
    ?>
    <h1 class="font-serif text-3xl text-beige-100">Event debug</h1>
    <p class="text-beige-100/60 mt-2">Pick an event to inspect its raw DB state and any recurring children.</p>

    <ul class="mt-6 space-y-2">
      <?php foreach ($eventsList as $ev): ?>
        <li>
          <a href="<?= url('/admin/event_debug.php?id=' . (int) $ev['id']) ?>"
             class="text-gold-400 hover:text-gold-300">
            #<?= (int) $ev['id'] ?> · <?= e($ev['title']) ?>
            <span class="text-xs text-beige-100/50">
              · <?= e((string) ($ev['recurrence'] ?? 'none')) ?>
              <?php if (!empty($ev['parent_event_id'])): ?>
                · child of #<?= (int) $ev['parent_event_id'] ?>
              <?php endif; ?>
              · <?= e((string) $ev['status']) ?>
              · <?= e((string) $ev['starts_at']) ?>
            </span>
          </a>
        </li>
      <?php endforeach; ?>
    </ul>
    <?php
    require __DIR__ . '/../includes/admin_layout_end.php';
    exit;
}

$stmt = db()->prepare("SELECT * FROM events WHERE id = :id LIMIT 1");
$stmt->execute([':id' => $id]);
$event = $stmt->fetch();
if (!$event) {
    require __DIR__ . '/../includes/admin_layout.php';
    echo '<p class="text-red-300/80">No event with id ' . (int) $id . '</p>';
    require __DIR__ . '/../includes/admin_layout_end.php';
    exit;
}

// Walk up to the template if we were handed a child, so the diff view
// always shows the parent as the reference row.
$templateId = !empty($event['parent_event_id']) ? (int) $event['parent_event_id'] : $id;
if ($templateId !== $id) {
    $stmt->execute([':id' => $templateId]);
    $template = $stmt->fetch() ?: $event;
} else {
    $template = $event;
}

// All children of this template.
$children = [];
if (in_array($template['recurrence'] ?? 'none', ['daily','weekly','monthly'], true)) {
    $cStmt = db()->prepare(
        "SELECT * FROM events WHERE parent_event_id = :pid ORDER BY starts_at ASC"
    );
    $cStmt->execute([':pid' => (int) $template['id']]);
    $children = $cStmt->fetchAll();
}

// Bookings on the template + each child, so we can see where seats
// actually landed.
$bookStmt = db()->prepare(
    "SELECT id, booking_ref, user_id, quantity, package, status, unit_price, total_amount, created_at
       FROM event_bookings WHERE event_id = :eid ORDER BY id DESC LIMIT 20"
);

$bookStmt->execute([':eid' => (int) $template['id']]);
$templateBookings = $bookStmt->fetchAll();
$childBookings = [];
foreach ($children as $c) {
    $bookStmt->execute([':eid' => (int) $c['id']]);
    $childBookings[(int) $c['id']] = $bookStmt->fetchAll();
}

// Recurrence exceptions (skip dates).
$exStmt = db()->prepare("SELECT exception_date FROM event_recurrence_exceptions WHERE event_id = :id ORDER BY exception_date");
$exStmt->execute([':id' => (int) $template['id']]);
$exceptions = array_column($exStmt->fetchAll(), 'exception_date');

// Which fields we care about for the config-drift check. These are the
// per-event settings that the child MUST inherit from the template for
// the booking page to render correctly.
$configFields = [
    'title', 'subtitle', 'location', 'facilitator', 'category',
    'capacity', 'price_public', 'price_member', 'status', 'audience',
    'experience_id', 'credit_eligible', 'referral_reward_amount',
    'package_a_label', 'package_a_perks',
    'package_b_label', 'package_b_perks', 'package_b_enabled',
    'intake_type',
];

require __DIR__ . '/../includes/admin_layout.php';

function fmt_val($v): string {
    if ($v === null) return '(NULL)';
    if ($v === '')   return '(empty)';
    if (is_string($v) && strlen($v) > 80) return substr($v, 0, 77) . '…';
    return (string) $v;
}
?>

<div class="flex items-center justify-between gap-3 flex-wrap">
  <h1 class="font-serif text-3xl text-beige-100">Event debug · #<?= (int) $template['id'] ?></h1>
  <div class="flex gap-3">
    <a href="<?= url('/admin/event_debug.php') ?>" class="text-xs text-beige-100/60 hover:text-gold-400">← back to list</a>
    <a href="<?= url('/admin/event_form.php?id=' . (int) $template['id']) ?>" class="text-xs text-gold-400 hover:text-gold-300">Edit template →</a>
  </div>
</div>

<!-- Template card -->
<section class="mt-6 border border-gold-500/30 rounded-2xl bg-navy-900/40 p-5">
  <p class="text-[10px] uppercase tracking-widest text-gold-400/80">Template</p>
  <p class="font-serif text-xl text-beige-100 mt-1"><?= e((string) $template['title']) ?></p>
  <p class="text-xs text-beige-100/50 mt-1">
    id=<?= (int) $template['id'] ?> ·
    recurrence=<?= e((string) ($template['recurrence'] ?? 'none')) ?>
    <?php if (!empty($template['recurrence_days'])): ?>
      · days=<?= e((string) $template['recurrence_days']) ?>
    <?php endif; ?>
    <?php if (!empty($template['recurrence_until'])): ?>
      · until <?= e((string) $template['recurrence_until']) ?>
    <?php endif; ?>
    · starts_at=<?= e((string) $template['starts_at']) ?>
    · status=<?= e((string) $template['status']) ?>
  </p>

  <div class="mt-4 grid sm:grid-cols-2 gap-x-6 gap-y-1 text-xs">
    <?php foreach ($configFields as $f): ?>
      <div class="flex justify-between border-b border-white/5 py-1">
        <span class="text-beige-100/50 font-mono"><?= e($f) ?></span>
        <span class="text-beige-100/90 font-mono"><?= e(fmt_val($template[$f] ?? null)) ?></span>
      </div>
    <?php endforeach; ?>
  </div>

  <?php if ($exceptions): ?>
    <p class="mt-4 text-xs text-beige-100/60">
      Skip dates:
      <?php foreach ($exceptions as $d): ?>
        <span class="ml-2 inline-block px-2 py-0.5 rounded bg-white/5 font-mono text-beige-100/80"><?= e($d) ?></span>
      <?php endforeach; ?>
    </p>
  <?php endif; ?>

  <?php if ($templateBookings): ?>
    <p class="mt-4 text-xs text-beige-100/60">
      <?= count($templateBookings) ?> booking(s) on the TEMPLATE row itself
      — usually a symptom of the pre-fix bug where weekly/monthly bookings
      piled onto the template. Move these to child instances if any.
    </p>
  <?php endif; ?>
</section>

<!-- Children -->
<?php if ($children): ?>
  <h2 class="mt-8 font-serif text-2xl text-beige-100">Child instances (<?= count($children) ?>)</h2>
  <p class="text-xs text-beige-100/50 mt-1">
    Highlighted rows indicate a mismatch with the template — the child
    was materialised before the config was updated, or before the
    inheritance fix landed. Save the template again to re-cascade.
  </p>

  <div class="mt-4 space-y-4">
    <?php foreach ($children as $c):
      $mismatches = [];
      foreach ($configFields as $f) {
        // Normalize both sides so NULL vs '' and int-string vs int don't false-positive.
        $a = $template[$f] ?? null; $b = $c[$f] ?? null;
        if ((string) $a !== (string) $b) $mismatches[] = $f;
      }
      $bookings = $childBookings[(int) $c['id']] ?? [];
      $hasLiveBookings = false;
      foreach ($bookings as $bk) {
        if (in_array($bk['status'], ['pending','paid','attended'], true)) { $hasLiveBookings = true; break; }
      }
    ?>
      <div class="border <?= $mismatches ? 'border-red-500/30 bg-red-500/5' : 'border-white/5 bg-navy-900/40' ?> rounded-2xl p-4">
        <div class="flex items-start justify-between gap-3 flex-wrap">
          <div>
            <p class="text-sm text-beige-100">
              #<?= (int) $c['id'] ?> · <?= e((string) $c['starts_at']) ?>
              <?php if ($hasLiveBookings): ?>
                <span class="ml-2 text-[10px] uppercase tracking-widest px-2 py-0.5 rounded-full bg-gold-500/15 text-gold-400">live bookings</span>
              <?php endif; ?>
              <?php if ($mismatches): ?>
                <span class="ml-2 text-[10px] uppercase tracking-widest px-2 py-0.5 rounded-full bg-red-500/15 text-red-300"><?= count($mismatches) ?> mismatch<?= count($mismatches) === 1 ? '' : 'es' ?></span>
              <?php endif; ?>
            </p>
            <p class="text-[11px] text-beige-100/50 mt-0.5">status=<?= e((string) $c['status']) ?></p>
          </div>
          <a href="<?= url('/admin/session_sheet.php?event_id=' . (int) $c['id']) ?>"
             class="text-xs text-gold-400/80 hover:text-gold-300">Session sheet →</a>
        </div>

        <?php if ($mismatches): ?>
          <div class="mt-3 grid sm:grid-cols-2 gap-x-6 gap-y-1 text-[11px]">
            <?php foreach ($mismatches as $f): ?>
              <div class="flex justify-between border-b border-white/5 py-1">
                <span class="text-beige-100/60 font-mono"><?= e($f) ?></span>
                <span class="font-mono">
                  <span class="text-beige-100/40" title="template value"><?= e(fmt_val($template[$f] ?? null)) ?></span>
                  <span class="text-beige-100/30 mx-1">→</span>
                  <span class="text-red-300/80" title="child value"><?= e(fmt_val($c[$f] ?? null)) ?></span>
                </span>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>

        <?php if ($bookings): ?>
          <p class="mt-3 text-[11px] text-beige-100/50"><?= count($bookings) ?> booking(s) on this child</p>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>
  </div>
<?php elseif (in_array($template['recurrence'] ?? 'none', ['daily','weekly','monthly'], true)): ?>
  <p class="mt-8 text-beige-100/60">No child instances materialised yet — nobody has booked a specific date on this template.</p>
<?php endif; ?>

<!-- Public-side sanity check -->
<section class="mt-8 border border-white/5 rounded-2xl bg-navy-900/40 p-5">
  <h2 class="font-serif text-xl text-gold-400">Public URLs to check</h2>
  <ul class="mt-3 text-xs text-beige-100/70 space-y-1.5">
    <li>Booking flow (template): <a href="<?= url('/member/book_event.php?event_id=' . (int) $template['id']) ?>" target="_blank" class="text-gold-400 hover:text-gold-300 font-mono">/member/book_event.php?event_id=<?= (int) $template['id'] ?></a></li>
    <li>Booking flow (with a specific date): <span class="font-mono text-beige-100/50">/member/book_event.php?event_id=<?= (int) $template['id'] ?>&date=YYYY-MM-DD</span></li>
    <li>Public event page: <a href="<?= url('/public/event.php?id=' . (int) $template['id']) ?>" target="_blank" class="text-gold-400 hover:text-gold-300 font-mono">/public/event.php?id=<?= (int) $template['id'] ?></a></li>
    <?php if (!empty($template['experience_id'])):
      $xStmt = db()->prepare("SELECT slug FROM experiences WHERE id = :id");
      $xStmt->execute([':id' => (int) $template['experience_id']]);
      $xslug = (string) ($xStmt->fetchColumn() ?: '');
      if ($xslug !== ''): ?>
        <li>Experiences filter: <a href="<?= url('/public/events.php?experience=' . urlencode($xslug)) ?>" target="_blank" class="text-gold-400 hover:text-gold-300 font-mono">/public/events.php?experience=<?= e($xslug) ?></a></li>
      <?php endif;
    endif; ?>
  </ul>
</section>

<?php require __DIR__ . '/../includes/admin_layout_end.php'; ?>
