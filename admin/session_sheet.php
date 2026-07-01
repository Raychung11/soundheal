<?php
/**
 * Session prep sheet — printable / exportable per-session view.
 *
 *   /admin/session_sheet.php?event_id=<events.id>
 *   /admin/session_sheet.php?event_id=<events.id>&export=csv
 *
 *   Everything front-of-house needs before the session starts,
 *   on one page: attendee list, package chosen (so ops knows how
 *   many mats/blankets to lay out), pet intake, medical notes,
 *   payment status. Print-friendly styling; a CSV button hands
 *   the same rows to a spreadsheet.
 */
require_once __DIR__ . '/../includes/bootstrap.php';
require_staff_or_admin();

$eventId = (int) input('event_id', 0);
if ($eventId <= 0) {
    http_response_code(400);
    exit('Missing event_id.');
}

$evStmt = db()->prepare("SELECT * FROM events WHERE id = :id LIMIT 1");
$evStmt->execute([':id' => $eventId]);
$event = $evStmt->fetch();
if (!$event) {
    http_response_code(404);
    exit('Event not found.');
}

// If admin opened the sheet for a recurring template directly, tell
// them so — the actual bookings live on child instances.
$isRecurringTemplate = ($event['recurrence'] ?? 'none') === 'daily' && empty($event['parent_event_id']);

$bookingsStmt = db()->prepare(
    "SELECT b.id, b.booking_ref, b.status, b.quantity, b.package,
            b.paid_with_credit, b.unit_price, b.total_amount,
            b.intake_data, b.created_at,
            u.full_name, u.email, u.phone
       FROM event_bookings b
       JOIN users u ON u.id = b.user_id
      WHERE b.event_id = :e
        AND b.status IN ('pending','paid','attended')
      ORDER BY b.status DESC, b.created_at ASC"
);
$bookingsStmt->execute([':e' => $eventId]);
$bookings = $bookingsStmt->fetchAll();

$comfortLabel = trim((string) ($event['package_a_label'] ?? '')) ?: 'Comfort';
$byoLabel     = trim((string) ($event['package_b_label'] ?? '')) ?: 'BYO Zen';
$packageName  = static function (?string $pkg) use ($comfortLabel, $byoLabel): string {
    return match ($pkg) {
        'comfort' => $comfortLabel,
        'byo'     => $byoLabel,
        default   => $pkg ?: 'Standard',
    };
};

$totalSeats = 0; $paidSeats = 0; $pendingSeats = 0;
foreach ($bookings as $b) {
    $totalSeats += (int) $b['quantity'];
    if (in_array($b['status'], ['paid','attended'], true)) $paidSeats += (int) $b['quantity'];
    else $pendingSeats += (int) $b['quantity'];
}

// CSV export
if ((string) input('export', '') === 'csv') {
    $slug = preg_replace('/[^A-Za-z0-9]+/', '-', strtolower((string) $event['title']));
    $filename = 'session-' . trim((string) $slug, '-') . '-' . date('Y-m-d', strtotime((string) $event['starts_at'])) . '.csv';
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, [
        'Booking ref', 'Name', 'Email', 'Phone', 'Package', 'Seats', 'Status',
        'Paid with credit', 'Amount', 'Pet(s)', 'Pet notes',
    ]);
    foreach ($bookings as $b) {
        $intake = $b['intake_data'] ? (json_decode((string) $b['intake_data'], true) ?: []) : [];
        $petsLine  = [];
        $petsNotes = [];
        foreach (($intake['pets'] ?? []) as $pet) {
            $head = trim(($pet['name'] ?? '') . ' (' . ($pet['breed'] ?? '—') . ')');
            $petsLine[] = $head;
            $note = [];
            if (!empty($pet['age']))       $note[] = 'age ' . $pet['age'];
            if (!empty($pet['neutered']))  $note[] = 'neutered:' . $pet['neutered'];
            if (!empty($pet['character'])) $note[] = 'char:' . implode('/', (array) $pet['character']);
            if (!empty($pet['medical']))   $note[] = 'medical: ' . $pet['medical'];
            $petsNotes[] = $head . ' — ' . implode('; ', $note);
        }
        fputcsv($out, [
            $b['booking_ref'],
            $b['full_name'],
            $b['email'],
            $b['phone'] ?? '',
            $packageName($b['package'] ?? null),
            (int) $b['quantity'],
            $b['status'],
            !empty($b['paid_with_credit']) ? 'yes' : 'no',
            number_format((float) $b['total_amount'], 2),
            implode('; ', $petsLine),
            implode(' | ', $petsNotes),
        ]);
    }
    fclose($out);
    audit_log('session_sheet.export', 'events', $eventId);
    exit;
}

$pageTitle = 'Session sheet';
require __DIR__ . '/../includes/admin_layout.php';
?>

<style>
  @media print {
    body { background: #ffffff !important; color: #111 !important; }
    .no-print { display: none !important; }
    .admin-shell, header, footer, aside { display: none !important; }
    main, section, div, table, tr, td, th { background: transparent !important; border-color: #ddd !important; color: #111 !important; }
    .sheet-attendee { break-inside: avoid; }
  }
</style>

<div class="flex items-start justify-between gap-4 flex-wrap">
  <div>
    <h1 class="font-serif text-3xl text-beige-100"><?= e($event['title']) ?></h1>
    <p class="text-beige-100/60 mt-1 text-sm">
      <?= e(format_datetime($event['starts_at'], 'l, d M Y · g:i A')) ?>
      <?php if (!empty($event['location'])): ?> · <?= e($event['location']) ?><?php endif; ?>
      <?php if (!empty($event['facilitator'])): ?> · with <?= e($event['facilitator']) ?><?php endif; ?>
    </p>
  </div>
  <div class="no-print flex items-center gap-2">
    <a href="<?= url('/admin/session_sheet.php?event_id=' . (int) $eventId . '&export=csv') ?>"
       class="text-xs px-4 py-1.5 rounded-full border border-white/10 text-beige-100/70 hover:border-gold-500/40 hover:text-gold-400 transition">Download CSV</a>
    <button onclick="window.print()"
            class="text-xs px-4 py-1.5 rounded-full bg-gold-500 text-navy-950 hover:bg-gold-400 transition">Print</button>
  </div>
</div>

<?php if ($isRecurringTemplate): ?>
  <p class="mt-4 text-sm text-beige-100/60 border border-white/10 rounded-2xl p-4 bg-navy-900/40">
    This is the recurring <em>template</em>. Concrete bookings live on child instances created per date — open the specific date's event row from Admin → Events to see that occurrence's sheet.
  </p>
<?php endif; ?>

<!-- Summary strip -->
<div class="mt-6 grid sm:grid-cols-4 gap-3">
  <div class="border border-white/5 rounded-2xl p-4 bg-navy-900/40">
    <p class="text-[11px] uppercase tracking-widest text-beige-100/50">Confirmed</p>
    <p class="font-serif text-2xl text-gold-400 mt-1"><?= $paidSeats ?> / <?= (int) $event['capacity'] ?></p>
  </div>
  <div class="border border-white/5 rounded-2xl p-4 bg-navy-900/40">
    <p class="text-[11px] uppercase tracking-widest text-beige-100/50">Pending</p>
    <p class="font-serif text-2xl text-beige-100 mt-1"><?= $pendingSeats ?></p>
  </div>
  <?php
    $comfortSeats = 0; $byoSeats = 0;
    foreach ($bookings as $b) {
        if ($b['status'] === 'pending') continue;
        $q = (int) $b['quantity'];
        if (($b['package'] ?? '') === 'comfort') $comfortSeats += $q;
        elseif (($b['package'] ?? '') === 'byo')  $byoSeats     += $q;
    }
  ?>
  <div class="border border-gold-500/25 rounded-2xl p-4 bg-gold-500/5">
    <p class="text-[11px] uppercase tracking-widest text-gold-400/80"><?= e($comfortLabel) ?></p>
    <p class="font-serif text-2xl text-gold-400 mt-1"><?= $comfortSeats ?></p>
  </div>
  <div class="border border-white/5 rounded-2xl p-4 bg-navy-900/40">
    <p class="text-[11px] uppercase tracking-widest text-beige-100/50"><?= e($byoLabel) ?></p>
    <p class="font-serif text-2xl text-beige-100 mt-1"><?= $byoSeats ?></p>
  </div>
</div>

<!-- Attendees -->
<h2 class="mt-10 font-serif text-2xl text-gold-400">Attendees</h2>
<?php if (!$bookings): ?>
  <p class="mt-3 text-beige-100/60 italic">No bookings yet.</p>
<?php else: ?>
  <div class="mt-4 space-y-3">
    <?php foreach ($bookings as $b):
      $intake = $b['intake_data'] ? (json_decode((string) $b['intake_data'], true) ?: []) : [];
      $pets   = $intake['pets'] ?? [];
      $paw    = $intake['pawrent'] ?? [];
    ?>
      <article class="sheet-attendee border border-white/5 rounded-2xl bg-navy-900/40 p-5">
        <div class="flex items-start justify-between gap-4 flex-wrap">
          <div class="min-w-0">
            <p class="font-serif text-lg text-beige-100"><?= e($b['full_name']) ?></p>
            <p class="text-xs text-beige-100/60"><?= e($b['email']) ?><?php if (!empty($b['phone'])): ?> · <?= e($b['phone']) ?><?php endif; ?></p>
            <?php if (!empty($paw['mobile']) && $paw['mobile'] !== ($b['phone'] ?? '')): ?>
              <p class="text-xs text-beige-100/50">Pawrent mobile: <?= e($paw['mobile']) ?></p>
            <?php endif; ?>
          </div>
          <div class="text-right text-sm">
            <p class="text-[10px] uppercase tracking-widest text-beige-100/50">Booking</p>
            <p class="font-mono text-beige-100/85 text-xs"><?= e($b['booking_ref']) ?></p>
            <div class="mt-1.5 flex items-center gap-2 justify-end text-xs">
              <span class="px-2 py-0.5 rounded-full <?= $b['status'] === 'paid' ? 'bg-gold-500/20 text-gold-400' : ($b['status'] === 'attended' ? 'bg-white/10 text-beige-100/80' : 'bg-white/5 text-beige-100/60') ?>"><?= e($b['status']) ?></span>
              <?php if (!empty($b['paid_with_credit'])): ?>
                <span class="px-2 py-0.5 rounded-full bg-white/5 text-beige-100/60 border border-white/10">credit</span>
              <?php endif; ?>
            </div>
          </div>
        </div>

        <div class="mt-3 grid sm:grid-cols-[auto_1fr] gap-x-5 gap-y-2 text-sm">
          <span class="text-[10px] uppercase tracking-widest text-beige-100/50">Package</span>
          <span class="text-beige-100/85">
            <?= e($packageName($b['package'] ?? null)) ?> · <?= (int) $b['quantity'] ?> seat<?= (int) $b['quantity'] === 1 ? '' : 's' ?>
          </span>
        </div>

        <?php if ($pets): ?>
          <div class="mt-4 border-t border-white/5 pt-3 space-y-2">
            <p class="text-[10px] uppercase tracking-widest text-gold-400/80">Pet(s)</p>
            <?php foreach ($pets as $pet): ?>
              <div class="text-sm text-beige-100/80">
                <p><strong class="text-beige-100"><?= e((string) ($pet['name'] ?? '—')) ?></strong>
                  <?php if (!empty($pet['breed'])): ?><span class="text-beige-100/55"> · <?= e((string) $pet['breed']) ?></span><?php endif; ?>
                  <?php if (!empty($pet['age'])): ?><span class="text-beige-100/55"> · <?= e((string) $pet['age']) ?></span><?php endif; ?>
                </p>
                <?php
                  $n = (string) ($pet['neutered'] ?? '');
                  $nLabel = $n === 'yes' ? 'Neutered/Spayed' : ($n === 'no' ? 'Not neutered' : ($n === 'na' ? 'Not disclosed' : ''));
                ?>
                <?php if ($nLabel !== '' || !empty($pet['character'])): ?>
                  <p class="text-xs text-beige-100/60">
                    <?= $nLabel !== '' ? e($nLabel) : '' ?>
                    <?php if (!empty($pet['character'])): ?>
                      <?= $nLabel !== '' ? '· ' : '' ?>Character: <?= e(implode(', ', (array) $pet['character'])) ?>
                    <?php endif; ?>
                  </p>
                <?php endif; ?>
                <?php if (!empty($pet['medical'])): ?>
                  <p class="text-xs text-beige-100/80 mt-1"><span class="text-red-300/70">Medical:</span> <?= e((string) $pet['medical']) ?></p>
                <?php endif; ?>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </article>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<?php require __DIR__ . '/../includes/admin_layout_end.php'; ?>
