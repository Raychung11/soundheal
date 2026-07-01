<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_login();
$user = current_user();
$pageTitle = 'Reserve';

$eventId = (int) input('event_id', 0);
$bookDate = trim((string) input('date', ''));
$stmt = db()->prepare("SELECT * FROM events WHERE id = :id AND status = 'published' LIMIT 1");
$stmt->execute([':id' => $eventId]);
$event = $stmt->fetch();
if (!$event) {
    flash('booking', 'That session is no longer available.', 'error');
    redirect('/public/events.php');
}

// Recurring template: materialise the concrete child event for the picked
// date and book against it. Without a date param, bounce them back to the
// calendar so they pick one.
if (($event['recurrence'] ?? 'none') === 'daily') {
    if ($bookDate === '' || !function_exists('find_or_create_recurring_instance')) {
        flash('booking', 'Please choose a date for this session.', 'error');
        redirect('/public/events.php');
    }
    $child = find_or_create_recurring_instance((int) $event['id'], $bookDate);
    if (!$child) {
        flash('booking', 'That date is no longer available.', 'error');
        redirect('/public/events.php');
    }
    $event = $child;
    $eventId = (int) $child['id'];
}

// Package pricing — two amenity tiers anyone can pick at booking. The
// event row's price_public / price_member columns are the two prices;
// the labels and perk bullets fall back to the site-wide defaults
// (Comfort / BYO Zen) unless the event overrides them via the
// package_a_* / package_b_* columns (special workshops, etc.).
$comfortPrice = (float) $event['price_public'];
$byoPrice     = (float) $event['price_member'];

$defaultComfortPerks = ['Welcome drink', 'Yoga mat provided', 'Cozy blanket provided', 'Full sound healing experience'];
$defaultByoPerks     = ['Full sound healing experience', 'Bring your own mat and blanket'];

$comfortName  = trim((string) ($event['package_a_label'] ?? '')) ?: 'Comfort';
$byoName      = trim((string) ($event['package_b_label'] ?? '')) ?: 'Bring-Your-Own-Zen';
$comfortPerks = array_values(array_filter(array_map('trim',
    preg_split('/\r?\n/', (string) ($event['package_a_perks'] ?? '')))));
if (!$comfortPerks) $comfortPerks = $defaultComfortPerks;
$byoPerks = array_values(array_filter(array_map('trim',
    preg_split('/\r?\n/', (string) ($event['package_b_perks'] ?? '')))));
if (!$byoPerks) $byoPerks = $defaultByoPerks;

// Credit balance — lets the member pay with a pack credit instead of cash.
$creditBalance = credit_balance_for((int) $user['id']);

$errors = [];

if (is_post()) {
    csrf_verify();
    $package = (string) input('package', 'comfort');
    if (!in_array($package, ['comfort', 'byo'], true)) {
        $package = 'comfort';
    }
    $useCredit = !empty($_POST['use_credit']) && $creditBalance > 0;
    $qty = $useCredit ? 1 : max(1, min(6, (int) input('quantity', 1)));
    $unitPrice = $package === 'byo' ? $byoPrice : $comfortPrice;
    $packageLabel = $package === 'byo' ? $byoName : $comfortName;

    // Per-event intake — currently only the pet workshop uses this.
    $intakeData = null;
    if (($event['intake_type'] ?? 'none') === 'pet') {
        $i = (array) ($_POST['intake'] ?? []);
        $intake = [
            'pawrent' => [
                'name'   => trim((string) ($i['pawrent_name'] ?? '')),
                'mobile' => trim((string) ($i['pawrent_mobile'] ?? '')),
                'email'  => trim((string) ($i['pawrent_email'] ?? '')),
            ],
            'pets' => [],
        ];
        if ($intake['pawrent']['name'] === '' || $intake['pawrent']['mobile'] === '' || $intake['pawrent']['email'] === '') {
            $errors[] = 'Please share your name, mobile and email so we can welcome you.';
        }
        $petsNeeded = $package === 'comfort' ? 2 : 1;
        for ($p = 1; $p <= $petsNeeded; $p++) {
            $pet = [
                'name'      => trim((string) ($i["pet_{$p}_name"] ?? '')),
                'breed'     => trim((string) ($i["pet_{$p}_breed"] ?? '')),
                'age'       => trim((string) ($i["pet_{$p}_age"] ?? '')),
                'neutered'  => trim((string) ($i["pet_{$p}_neutered"] ?? '')),
                'medical'   => trim((string) ($i["pet_{$p}_medical"] ?? '')),
                'character' => array_values(array_filter(array_map('strval', (array) ($i["pet_{$p}_character"] ?? [])))),
            ];
            if ($pet['name'] === '') {
                $errors[] = $petsNeeded > 1
                    ? "Please tell us pet #{$p}'s name."
                    : "Please tell us your pet's name.";
            }
            $intake['pets'][] = $pet;
        }
        if (!$errors) {
            $intakeData = json_encode($intake, JSON_UNESCAPED_UNICODE);
        }
    }
    if (!$errors) {
    db()->beginTransaction();
    try {
        $cap = db()->prepare(
            "SELECT capacity,
                    (SELECT COALESCE(SUM(quantity), 0)
                       FROM event_bookings
                      WHERE event_id = e.id
                        AND status IN ('paid','attended','pending')) AS taken
             FROM events e WHERE id = :id FOR UPDATE"
        );
        $cap->execute([':id' => $eventId]);
        $row = $cap->fetch();
        $remaining = (int)$row['capacity'] - (int)$row['taken'];
        if ($qty > $remaining) {
            throw new RuntimeException("Only {$remaining} seats remain for this session.");
        }

        $bookingRef = 'SH-' . strtoupper(bin2hex(random_bytes(4)));
        // When paying with a credit, the booking is effectively prepaid:
        // unit_price/total are recorded as 0 and the credit ledger is the source of truth.
        $effectiveUnit = $useCredit ? 0.0 : $unitPrice;
        $total = $effectiveUnit * $qty;

        $ins = db()->prepare(
            "INSERT INTO event_bookings (booking_ref, user_id, event_id, quantity, unit_price, total_amount, status, paid_with_credit, package, intake_data)
             VALUES (:ref, :u, :e, :q, :up, :tot, :status, :pwc, :pkg, :intake)"
        );
        $ins->execute([
            ':ref' => $bookingRef,
            ':u'   => $user['id'],
            ':e'   => $eventId,
            ':q'   => $qty,
            ':up'  => $effectiveUnit,
            ':tot' => $total,
            ':status' => ($useCredit || $effectiveUnit <= 0) ? 'paid' : 'pending',
            ':pwc' => $useCredit ? 1 : 0,
            ':pkg' => $package,
            ':intake' => $intakeData,
        ]);
        $bookingId = (int) db()->lastInsertId();

        // Burn the credit before issuing the ticket so we never double-spend.
        if ($useCredit) {
            if (!redeem_credit_for_booking((int) $user['id'], $bookingId)) {
                throw new RuntimeException('Could not redeem your credit. Please refresh and try again.');
            }
        }

        // Issue tickets immediately for free sessions and credit-paid sessions.
        if ($effectiveUnit <= 0 || $useCredit) {
            $tStmt = db()->prepare(
                "INSERT INTO tickets (booking_id, ticket_code, qr_token) VALUES (:b, :code, :token)"
            );
            for ($i = 0; $i < $qty; $i++) {
                $tStmt->execute([
                    ':b' => $bookingId,
                    ':code' => $bookingRef . '-' . ($i + 1),
                    ':token' => generate_token(24),
                ]);
            }
        }

        db()->commit();
        audit_log('booking.create', 'event_bookings', $bookingId, ['ref' => $bookingRef, 'total' => $total]);

        // If the visitor arrived via a ?ref=<code> link (cookie set by
        // capture_referral_cookie() in bootstrap), attribute the booking
        // and record a pending referral reward. Self-referrals are
        // guarded inside record_event_referral().
        if (function_exists('get_referral_cookie') && function_exists('record_event_referral')) {
            $refCode = get_referral_cookie();
            if ($refCode) {
                $referrerId = function_exists('referrer_id_for_code') ? referrer_id_for_code($refCode) : null;
                if ($referrerId) {
                    record_event_referral($bookingId, (int) $referrerId);
                }
            }
        }

        // Skip the invoice when the booking is fully paid with a credit —
        // the pack purchase already produced its own invoice/receipt.
        if (!$useCredit) {
            issue_invoice(
                (int) $user['id'],
                'booking',
                $bookingId,
                build_booking_line_items([
                    'event_title'  => $event['title'] . ' · ' . $packageLabel . ' package',
                    'event_id'     => $eventId,
                    'starts_at'    => $event['starts_at'],
                    'quantity'     => $qty,
                    'unit_price'   => $unitPrice,
                    'total_amount' => $total,
                ])
            );
        }

        if ($effectiveUnit <= 0 || $useCredit) {
            send_mail($user['email'], $user['full_name'], 'Your seat is held', 'booking_confirm', [
                'event_title' => $event['title'],
                'starts_at'   => format_datetime($event['starts_at']),
                'location'    => $event['location'] ?? 'Location TBA',
                'booking_ref' => $bookingRef,
            ]);
            $msg = $useCredit
                ? 'Seat held — 1 credit redeemed. We can\'t wait to welcome you.'
                : 'Your seat is held. We can\'t wait to welcome you.';
            flash('booking', $msg, 'success');
            redirect('/member/my_bookings.php');
        }

        // Otherwise hand off to payment.
        redirect('/member/checkout.php?booking=' . $bookingId);
    } catch (Throwable $e) {
        db()->rollBack();
        $errors[] = $e->getMessage();
    }
    } // end if (!$errors)
}

require __DIR__ . '/../includes/header.php';
?>
<?php
  // json_encode on a string produces "..." with double quotes. The outer
  // x-data attribute is also double-quoted, so raw output would close
  // the attribute early. htmlspecialchars keeps the JSON valid inside
  // the attribute value.
  $ax = static fn($v) => htmlspecialchars(json_encode($v), ENT_QUOTES, 'UTF-8');
?>
<div x-data="{
    useCredit: false,
    qty: 1,
    pkg: 'comfort',
    prices: { comfort: <?= $ax($comfortPrice) ?>, byo: <?= $ax($byoPrice) ?> },
    labels: { comfort: <?= $ax($comfortName) ?>, byo: <?= $ax($byoName) ?> },
    label() { return this.labels[this.pkg]; },
    unit()  { return this.prices[this.pkg]; },
    total() { return this.useCredit ? 0 : this.unit() * this.qty; }
  }">
<section class="max-w-2xl mx-auto px-6 py-16">
  <p class="text-gold-400/80 tracking-[0.3em] uppercase text-xs">Reserve</p>
  <h1 class="font-serif text-4xl text-beige-100 mt-4"><?= e($event['title']) ?></h1>
  <p class="mt-2 text-beige-100/60"><?= e(format_datetime($event['starts_at'], 'l, d M Y · g:i A')) ?> · <?= e($event['location'] ?? 'Location TBA') ?></p>

  <?php foreach ($errors as $err): ?>
    <p class="mt-4 text-red-300/80"><?= e($err) ?></p>
  <?php endforeach; ?>

  <form id="bookForm" method="post" class="mt-10 space-y-5">
    <?= csrf_field() ?>

    <p class="text-[10px] uppercase tracking-[0.3em] text-gold-400/80">Choose your package</p>

    <!-- Package A (price_public) -->
    <label class="block cursor-pointer">
      <input type="radio" name="package" value="comfort" x-model="pkg" class="sr-only">
      <div :class="pkg === 'comfort' ? 'border-gold-500/50 bg-gold-500/10 ring-1 ring-gold-500/30' : 'border-white/10 bg-navy-900/40 hover:border-gold-500/30'"
           class="rounded-2xl border p-5 transition">
        <div class="flex items-start justify-between gap-3">
          <p class="font-serif text-xl text-beige-100"><?= e($comfortName) ?></p>
          <span class="font-serif text-2xl text-gold-400 whitespace-nowrap"><?= e(format_money($comfortPrice)) ?></span>
        </div>
        <ul class="mt-3 space-y-1.5 text-sm text-beige-100/70">
          <?php foreach ($comfortPerks as $perk): ?>
            <li class="flex gap-2"><span class="text-gold-400">✦</span> <?= e($perk) ?></li>
          <?php endforeach; ?>
        </ul>
      </div>
    </label>

    <!-- Package B (price_member) -->
    <label class="block cursor-pointer">
      <input type="radio" name="package" value="byo" x-model="pkg" class="sr-only">
      <div :class="pkg === 'byo' ? 'border-gold-500/50 bg-gold-500/10 ring-1 ring-gold-500/30' : 'border-white/10 bg-navy-900/40 hover:border-gold-500/30'"
           class="rounded-2xl border p-5 transition">
        <div class="flex items-start justify-between gap-3">
          <p class="font-serif text-xl text-beige-100"><?= e($byoName) ?></p>
          <span class="font-serif text-2xl text-gold-400 whitespace-nowrap"><?= e(format_money($byoPrice)) ?></span>
        </div>
        <ul class="mt-3 space-y-1.5 text-sm text-beige-100/70">
          <?php foreach ($byoPerks as $perk): ?>
            <li class="flex gap-2"><span class="text-gold-400">✦</span> <?= e($perk) ?></li>
          <?php endforeach; ?>
        </ul>
      </div>
    </label>

    <?php if ($creditBalance > 0): ?>
      <label class="flex items-start gap-3 border border-gold-500/30 rounded-2xl p-5 bg-gold-500/5 cursor-pointer">
        <input type="checkbox" name="use_credit" value="1" x-model="useCredit" class="mt-1 accent-gold-500">
        <span>
          <span class="text-beige-100">Use 1 credit instead of paying</span>
          <span class="block text-xs text-beige-100/60 mt-1">You hold <strong class="text-gold-400"><?= (int)$creditBalance ?> credit<?= $creditBalance === 1 ? '' : 's' ?></strong> · one credit = one seat. <a href="<?= url('/member/my_credits.php') ?>" class="text-gold-400 hover:text-gold-300 underline-offset-4 hover:underline">View balance</a></span>
        </span>
      </label>
    <?php endif; ?>

    <?php if (($event['intake_type'] ?? 'none') === 'pet'):
      $charOptions = ['playful','friendly','calm','shy','anxious','aggressive'];
      $petFields = function (int $idx) use ($charOptions) {
        $name = $idx === 1 ? "your pet" : "pet #{$idx}";
        ?>
        <div class="grid sm:grid-cols-2 gap-3">
          <label class="block">
            <span class="text-[11px] uppercase tracking-widest text-beige-100/60">Pet name</span>
            <input name="intake[pet_<?= $idx ?>_name]" class="mt-1 w-full rounded-2xl bg-navy-950 border border-white/5 px-4 py-2.5 focus:border-gold-500/50 focus:outline-none">
          </label>
          <label class="block">
            <span class="text-[11px] uppercase tracking-widest text-beige-100/60">Breed</span>
            <input name="intake[pet_<?= $idx ?>_breed]" placeholder="e.g. Golden Retriever" class="mt-1 w-full rounded-2xl bg-navy-950 border border-white/5 px-4 py-2.5 focus:border-gold-500/50 focus:outline-none">
          </label>
          <label class="block">
            <span class="text-[11px] uppercase tracking-widest text-beige-100/60">Age</span>
            <input name="intake[pet_<?= $idx ?>_age]" placeholder="e.g. 4 years" class="mt-1 w-full rounded-2xl bg-navy-950 border border-white/5 px-4 py-2.5 focus:border-gold-500/50 focus:outline-none">
          </label>
          <label class="block">
            <span class="text-[11px] uppercase tracking-widest text-beige-100/60">Neutered / Spayed</span>
            <select name="intake[pet_<?= $idx ?>_neutered]" class="mt-1 w-full rounded-2xl bg-navy-950 border border-white/5 px-4 py-2.5 focus:border-gold-500/50 focus:outline-none">
              <option value="">—</option>
              <option value="yes">Yes</option>
              <option value="no">No</option>
              <option value="na">Prefer not to say</option>
            </select>
          </label>
          <label class="block sm:col-span-2">
            <span class="text-[11px] uppercase tracking-widest text-beige-100/60">Medical history <span class="text-beige-100/30">(allergies, conditions, meds — write “none” if so)</span></span>
            <textarea name="intake[pet_<?= $idx ?>_medical]" rows="2" placeholder="None" class="mt-1 w-full rounded-2xl bg-navy-950 border border-white/5 px-4 py-2.5 focus:border-gold-500/50 focus:outline-none"></textarea>
          </label>
          <div class="sm:col-span-2">
            <span class="text-[11px] uppercase tracking-widest text-beige-100/60 block">Character <span class="text-beige-100/30">(tick all that apply)</span></span>
            <div class="mt-2 flex flex-wrap gap-2">
              <?php foreach ($charOptions as $c): ?>
                <label class="cursor-pointer">
                  <input type="checkbox" name="intake[pet_<?= $idx ?>_character][]" value="<?= e($c) ?>" class="peer sr-only">
                  <span class="px-3 py-1.5 rounded-full text-xs border border-white/10 bg-navy-950 text-beige-100/70 capitalize hover:border-gold-500/40 peer-checked:border-gold-500/50 peer-checked:bg-gold-500/15 peer-checked:text-gold-400 transition"><?= e($c) ?></span>
                </label>
              <?php endforeach; ?>
            </div>
          </div>
        </div>
        <?php
      };
    ?>
    <div class="border border-gold-500/25 rounded-2xl p-5 bg-gold-500/5 space-y-5">
      <div>
        <p class="text-[10px] uppercase tracking-[0.3em] text-gold-400/80">Pawrent &amp; pet details</p>
        <p class="text-xs text-beige-100/55 mt-1">So we can welcome you and your fur companion properly.</p>
      </div>

      <div class="grid sm:grid-cols-2 gap-3">
        <label class="block">
          <span class="text-[11px] uppercase tracking-widest text-beige-100/60">Your name</span>
          <input name="intake[pawrent_name]" required value="<?= e((string) $user['full_name']) ?>" class="mt-1 w-full rounded-2xl bg-navy-950 border border-white/5 px-4 py-2.5 focus:border-gold-500/50 focus:outline-none">
        </label>
        <label class="block">
          <span class="text-[11px] uppercase tracking-widest text-beige-100/60">Mobile</span>
          <input name="intake[pawrent_mobile]" required type="tel" placeholder="+60…" class="mt-1 w-full rounded-2xl bg-navy-950 border border-white/5 px-4 py-2.5 focus:border-gold-500/50 focus:outline-none">
        </label>
        <label class="block sm:col-span-2">
          <span class="text-[11px] uppercase tracking-widest text-beige-100/60">Email</span>
          <input name="intake[pawrent_email]" required type="email" value="<?= e((string) $user['email']) ?>" class="mt-1 w-full rounded-2xl bg-navy-950 border border-white/5 px-4 py-2.5 focus:border-gold-500/50 focus:outline-none">
        </label>
      </div>

      <div class="border-t border-white/5 pt-4 space-y-3">
        <p class="text-[10px] uppercase tracking-[0.3em] text-gold-400/80">Pet 1</p>
        <?php $petFields(1); ?>
      </div>

      <div class="border-t border-white/5 pt-4 space-y-3" x-show="pkg === 'comfort'" x-cloak>
        <p class="text-[10px] uppercase tracking-[0.3em] text-gold-400/80">Pet 2 <span class="text-beige-100/40 normal-case tracking-normal">(shown for the 2-pet package)</span></p>
        <?php $petFields(2); ?>
      </div>
    </div>
    <?php endif; ?>

    <div class="border border-white/5 rounded-2xl p-5 bg-navy-900/40 flex justify-between items-center gap-4">
      <div class="min-w-0">
        <p class="text-[10px] uppercase tracking-widest text-beige-100/55">Total</p>
        <p class="font-serif text-2xl text-gold-400 mt-1" :class="useCredit ? 'opacity-60' : ''">
          <span x-show="!useCredit">RM <span x-text="total().toFixed(2)"></span></span>
          <span x-show="useCredit" x-cloak>1 credit</span>
        </p>
        <p class="text-[11px] text-beige-100/45 mt-0.5">
          <span x-text="label()"></span> · <span x-text="qty"></span> seat<span x-show="qty > 1" x-cloak>s</span>
        </p>
      </div>
      <label class="flex items-center gap-3 text-sm shrink-0" :class="useCredit ? 'opacity-50 pointer-events-none' : ''">
        <span class="text-beige-100/70">Seats</span>
        <select name="quantity" x-model.number="qty" class="rounded-full bg-navy-950 border border-white/5 px-4 py-2 focus:border-gold-500/50 focus:outline-none" :disabled="useCredit">
          <?php for ($i = 1; $i <= 6; $i++): ?><option><?= $i ?></option><?php endfor; ?>
        </select>
      </label>
    </div>

    <div class="hidden md:block">
      <button class="w-full px-6 py-4 rounded-full bg-gold-500 text-navy-950 font-medium hover:bg-gold-400 transition">
        <span x-show="!useCredit" x-text="unit() > 0 ? 'Continue to payment' : 'Confirm reservation'"></span>
        <span x-show="useCredit" x-cloak>Redeem 1 credit · confirm reservation</span>
      </button>
    </div>

    <?php if ($creditBalance === 0): ?>
      <p class="text-center text-xs text-beige-100/45">Want to save? <a href="<?= url('/member/checkout_pack.php') ?>" class="text-gold-400 hover:text-gold-300 underline-offset-4 hover:underline">Buy a class pack</a> and pay with credits next time.</p>
    <?php endif; ?>
  </form>
</section>

<!-- Mobile-only sticky reserve bar (inline so the package / qty / use-credit Alpine state stays in scope). -->
<div class="md:hidden fixed inset-x-0 z-40 bg-navy-950/95 backdrop-blur border-t border-white/10 px-4 py-3"
     style="bottom: calc(64px + env(safe-area-inset-bottom));">
  <p class="text-[11px] text-beige-100/55 text-center mb-2">
    <span x-show="useCredit" x-cloak>1 credit · 1 seat</span>
    <span x-show="!useCredit">
      <span x-text="label()"></span> · RM <span x-text="total().toFixed(2)"></span>
    </span>
  </p>
  <button type="submit" form="bookForm"
          class="w-full py-3.5 rounded-full bg-gold-500 text-navy-950 font-medium hover:bg-gold-400 transition">
    <span x-show="!useCredit" x-text="unit() > 0 ? 'Continue to payment' : 'Confirm reservation'"></span>
    <span x-show="useCredit" x-cloak>Redeem 1 credit · Confirm</span>
  </button>
</div>
<div class="md:hidden h-24" aria-hidden="true"></div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
