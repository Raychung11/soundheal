<?php
/**
 * Public waitlist join page.
 *
 *   /public/waitlist.php?event=<id>&date=YYYY-MM-DD
 *
 *   Anyone (guest or logged-in) can leave their name + email
 *   for a sold-out session. When admin/self-cancel frees a
 *   seat later, notify_next_waitlist_seat() emails the oldest
 *   waiting entry.
 */
require_once __DIR__ . '/../includes/bootstrap.php';

$eventId = (int) input('event', 0);
$date    = trim((string) input('date', ''));

$event = null;
if ($eventId > 0) {
    $stmt = db()->prepare("SELECT * FROM events WHERE id = :id AND status = 'published' LIMIT 1");
    $stmt->execute([':id' => $eventId]);
    $event = $stmt->fetch() ?: null;
}

if (!$event) {
    http_response_code(404);
    $pageTitle = 'Session not found';
    require __DIR__ . '/../includes/header.php';
    ?>
    <section class="max-w-xl mx-auto px-6 py-24 text-center">
      <h1 class="font-serif text-4xl text-beige-100">Session not found.</h1>
      <a href="<?= url('/public/events.php') ?>" class="inline-block mt-8 px-7 py-3 rounded-full bg-gold-500 text-navy-950 hover:bg-gold-400 transition">Upcoming sessions</a>
    </section>
    <?php
    require __DIR__ . '/../includes/footer.php';
    exit;
}

$isRecurring = in_array($event['recurrence'] ?? 'none', ['daily','weekly','monthly'], true);
$dateValid   = $isRecurring && $date !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date);
$occDate     = $dateValid ? $date : null;

// Prefill from logged-in member.
$user = current_user();
$errors = [];
$done   = false;

if (is_post()) {
    csrf_verify();
    $email    = filter_var(trim((string) input('email', '')), FILTER_VALIDATE_EMAIL);
    $fullName = trim((string) input('full_name', ''));
    $mobile   = trim((string) input('mobile', ''));

    if (!$email)               $errors[] = 'Please share a valid email.';
    if ($fullName === '')      $errors[] = 'Please share your name.';
    if (mb_strlen($fullName) > 190) $errors[] = 'Name is a little long.';

    if (!$errors && !throttle('waitlist:' . client_ip(), 5, 600)) {
        $errors[] = 'You have submitted a lot recently. Please try again in a moment.';
    }

    if (!$errors) {
        // UNIQUE (event_id, occurrence_date, email) → INSERT IGNORE
        // makes a re-submit a no-op (they're already on the list).
        db()->prepare(
            "INSERT IGNORE INTO event_waitlist
                (event_id, occurrence_date, user_id, email, full_name, mobile)
             VALUES (:e, :d, :u, :em, :n, :m)"
        )->execute([
            ':e'  => (int) $event['id'],
            ':d'  => $occDate,
            ':u'  => $user ? (int) $user['id'] : null,
            ':em' => $email,
            ':n'  => $fullName,
            ':m'  => $mobile !== '' ? $mobile : null,
        ]);
        audit_log('waitlist.join', 'events', (int) $event['id'], ['email' => $email]);
        $done = true;
    }
}

$pageTitle = 'Waitlist · ' . $event['title'];
require __DIR__ . '/../includes/header.php';

$displayWhen = $dateValid
    ? format_datetime($date . ' ' . date('H:i:s', strtotime((string) $event['starts_at'])), 'l, d M Y · g:i A')
    : format_datetime($event['starts_at'], 'l, d M Y · g:i A');
?>
<section class="max-w-xl mx-auto px-6 py-16">
  <p class="text-gold-400/80 tracking-[0.3em] uppercase text-[11px]">Waitlist</p>
  <h1 class="font-serif text-4xl text-beige-100 mt-4 leading-tight"><?= e($event['title']) ?></h1>
  <p class="mt-2 text-beige-100/70"><?= e($displayWhen) ?></p>

  <?php if ($done): ?>
    <div class="mt-10 border border-gold-500/30 rounded-3xl p-8 bg-navy-900/50 text-center">
      <p class="font-serif text-2xl text-beige-100">You're on the list.</p>
      <p class="mt-3 text-beige-100/70 leading-relaxed">If a seat opens, we'll email you first-come-first-served. No hurry, no pressure.</p>
      <a href="<?= url('/public/events.php') ?>" class="inline-block mt-8 px-6 py-3 rounded-full bg-gold-500 text-navy-950 hover:bg-gold-400 transition">Browse other sessions</a>
    </div>
  <?php else: ?>
    <p class="mt-5 text-beige-100/70 leading-relaxed">This session is fully held. Leave your details below and if someone cancels, we'll email you the moment a seat opens — first to book takes it.</p>

    <?php foreach ($errors as $err): ?>
      <p class="mt-4 text-red-300/80 text-sm"><?= e($err) ?></p>
    <?php endforeach; ?>

    <form method="post" class="mt-10 space-y-5">
      <?= csrf_field() ?>
      <label class="block">
        <span class="text-xs uppercase tracking-widest text-beige-100/60">Your name</span>
        <input name="full_name" required maxlength="190"
               value="<?= e((string) ($user['full_name'] ?? input('full_name', ''))) ?>"
               class="mt-2 w-full rounded-2xl bg-navy-950 border border-white/5 px-4 py-3 focus:border-gold-500/50 focus:outline-none">
      </label>
      <label class="block">
        <span class="text-xs uppercase tracking-widest text-beige-100/60">Email</span>
        <input name="email" type="email" required
               value="<?= e((string) ($user['email'] ?? input('email', ''))) ?>"
               class="mt-2 w-full rounded-2xl bg-navy-950 border border-white/5 px-4 py-3 focus:border-gold-500/50 focus:outline-none">
      </label>
      <label class="block">
        <span class="text-xs uppercase tracking-widest text-beige-100/60">Mobile <span class="text-beige-100/30">(optional)</span></span>
        <input name="mobile" type="tel" placeholder="+60…"
               value="<?= e((string) input('mobile', '')) ?>"
               class="mt-2 w-full rounded-2xl bg-navy-950 border border-white/5 px-4 py-3 focus:border-gold-500/50 focus:outline-none">
      </label>
      <button class="w-full px-6 py-4 rounded-full bg-gold-500 text-navy-950 font-medium hover:bg-gold-400 transition">Notify me if a seat opens</button>
      <p class="text-center text-[11px] text-beige-100/40">We only email you if a seat becomes available. No newsletters.</p>
    </form>
  <?php endif; ?>
</section>
<?php require __DIR__ . '/../includes/footer.php'; ?>
