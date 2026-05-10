<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_login();
$pageTitle = 'My Bookings';
$user = current_user();

$stmt = db()->prepare(
    "SELECT b.*, e.title, e.starts_at, e.location,
            (SELECT COUNT(*) FROM tickets t WHERE t.booking_id = b.id AND t.status = 'valid') AS ticket_count
     FROM event_bookings b
     JOIN events e ON e.id = b.event_id
     WHERE b.user_id = :u
     ORDER BY e.starts_at DESC"
);
$stmt->execute([':u' => $user['id']]);
$bookings = $stmt->fetchAll();

require __DIR__ . '/../includes/header.php';
?>
<section class="max-w-5xl mx-auto px-6 py-16">
  <h1 class="font-serif text-4xl text-beige-100">My bookings</h1>
  <p class="mt-2 text-beige-100/60">Your sessions, tickets and history.</p>

  <?php if (!$bookings): ?>
    <div class="mt-12 border border-white/5 rounded-3xl p-12 text-center bg-navy-900/40">
      <p class="text-beige-100/70">You haven't reserved a session yet.</p>
      <a href="<?= url('/public/events.php') ?>" class="inline-block mt-6 px-6 py-3 rounded-full bg-gold-500 text-navy-950 hover:bg-gold-400 transition">Browse sessions</a>
    </div>
  <?php else: ?>
    <div class="mt-10 space-y-4">
      <?php foreach ($bookings as $b): ?>
        <div class="border border-white/5 rounded-3xl p-6 bg-navy-900/40 flex flex-col md:flex-row md:items-center gap-4 md:justify-between">
          <div>
            <p class="text-xs uppercase tracking-widest text-gold-400/80"><?= e(format_datetime($b['starts_at'], 'D, d M Y · g:i A')) ?></p>
            <p class="font-serif text-2xl text-beige-100 mt-1"><?= e($b['title']) ?></p>
            <p class="text-sm text-beige-100/50 mt-1">Ref: <?= e($b['booking_ref']) ?> · <?= e($b['location'] ?? 'Location TBA') ?></p>
          </div>
          <div class="flex items-center gap-4">
            <span class="text-xs px-3 py-1 rounded-full <?= $b['status'] === 'paid' ? 'bg-gold-500/20 text-gold-400' : 'bg-white/5 text-beige-100/60' ?>"><?= e($b['status']) ?></span>
            <?php if ((int)$b['ticket_count'] > 0): ?>
              <a href="<?= url('/member/my_tickets.php?booking=' . (int)$b['id']) ?>" class="text-sm text-gold-400 hover:text-gold-300">View ticket →</a>
            <?php endif; ?>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</section>
<?php require __DIR__ . '/../includes/footer.php'; ?>
