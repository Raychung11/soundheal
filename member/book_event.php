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

// Waiver / health disclosure — show the acknowledgement panel if the
// member hasn't accepted yet OR the admin has updated the waiver body
// since their last acceptance.
$waiverBody      = trim((string) setting('legal_waiver_body', ''));
$waiverUpdatedAt = trim((string) setting('legal_waiver_updated_at', ''));
$acceptedAt      = trim((string) ($user['waiver_accepted_at'] ?? ''));
$needsWaiver     = $waiverBody !== '' && (
    $acceptedAt === '' ||
    ($waiverUpdatedAt !== '' && substr($acceptedAt, 0, 10) < $waiverUpdatedAt)
);

$errors = [];

if (is_post()) {
    csrf_verify();
    $package = (string) input('package', 'comfort');
    if (!in_array($package, ['comfort', 'byo'], true)) {
        $package = 'comfort';
    }
    // Server-side guard mirroring the UI: credits can only pay for
    // events the admin has flagged as credit_eligible.
    $eventCreditsAllowed = !array_key_exists('credit_eligible', $event) || (int) $event['credit_eligible'] === 1;
    $useCredit = !empty($_POST['use_credit']) && $creditBalance > 0 && $eventCreditsAllowed;

    // Waiver acceptance + health disclosure.
    $waiverAcceptedNow = !empty($_POST['waiver_accepted']);
    if ($needsWaiver && !$waiverAcceptedNow) {
        $errors[] = 'Please read and tick the session waiver to continue.';
    }
    $healthDisclosure = trim((string) input('health_disclosure', ''));
    if (mb_strlen($healthDisclosure) > 2000) {
        $errors[] = 'Please keep the health note under 2000 characters.';
    }
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
        $subtotal = $effectiveUnit * $qty;

        // Promo / gift code — validated on the current subtotal. Not
        // available on credit-paid bookings. Gift-voucher codes take
        // precedence (they're personal / already paid for); promo
        // codes are the fallback.
        $codeInput       = trim((string) input('promo_code', ''));
        $promoCodeStored = null;
        $giftVoucherId   = null;
        $discountAmount  = 0.0;
        if ($codeInput !== '' && !$useCredit) {
            $vg = validate_gift_voucher($codeInput, $subtotal);
            if ($vg['ok']) {
                $discountAmount = (float) $vg['discount'];
                $giftVoucherId  = (int) $vg['voucher']['id'];
            } else {
                $vp = validate_promo_code($codeInput, $subtotal);
                if ($vp['ok']) {
                    $discountAmount  = (float) $vp['discount'];
                    $promoCodeStored = strtoupper($codeInput);
                } else {
                    // Show the voucher-specific error if the code
                    // looks like a voucher (SH- prefix); otherwise
                    // the promo error is more helpful.
                    $err = str_starts_with(strtoupper($codeInput), 'SH-') ? $vg['error'] : $vp['error'];
                    throw new RuntimeException($err);
                }
            }
        }
        $total = max(0.0, round($subtotal - $discountAmount, 2));

        $ins = db()->prepare(
            "INSERT INTO event_bookings (booking_ref, user_id, event_id, quantity, unit_price, total_amount, status, paid_with_credit, package, intake_data, health_disclosure)
             VALUES (:ref, :u, :e, :q, :up, :tot, :status, :pwc, :pkg, :intake, :health)"
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
            ':health' => $healthDisclosure !== '' ? $healthDisclosure : null,
        ]);
        $bookingId = (int) db()->lastInsertId();

        // Consume the code atomically. Both paths guard against a
        // race (voucher redeemed by a parallel booking, or promo
        // hitting its cap) and roll the whole booking back cleanly.
        if ($giftVoucherId !== null) {
            if (!redeem_gift_voucher($giftVoucherId, $bookingId, $discountAmount)) {
                throw new RuntimeException('That gift voucher was just used elsewhere. Please try again.');
            }
        } elseif ($promoCodeStored !== null) {
            if (!record_promo_use($bookingId, $promoCodeStored, $discountAmount)) {
                throw new RuntimeException('That promo code was just claimed by someone else. Please try again.');
            }
        }

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

        // Stamp the member's waiver acceptance once the booking is
        // in — the checkbox was validated above.
        if ($needsWaiver && $waiverAcceptedNow) {
            db()->prepare("UPDATE users SET waiver_accepted_at = NOW() WHERE id = :id")
                ->execute([':id' => (int) $user['id']]);
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

        // Partner (cafe / business) attribution — separate ledger from the
        // member referral above, so a booking can have at most one partner
        // and at most one member referrer. Cookie set by /public/p.php.
        if (function_exists('attribute_partner_booking')) {
            attribute_partner_booking($bookingId);
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

    <?php
      // credit_eligible defaults to 1 for pre-migration rows.
      $creditsAllowed = !array_key_exists('credit_eligible', $event) || (int) $event['credit_eligible'] === 1;
    ?>
    <?php if ($creditsAllowed && $creditBalance > 0): ?>
      <label class="flex items-start gap-3 border border-gold-500/30 rounded-2xl p-5 bg-gold-500/5 cursor-pointer">
        <input type="checkbox" name="use_credit" value="1" x-model="useCredit" class="mt-1 accent-gold-500">
        <span>
          <span class="text-beige-100">Use 1 credit instead of paying</span>
          <span class="block text-xs text-beige-100/60 mt-1">You hold <strong class="text-gold-400"><?= (int)$creditBalance ?> credit<?= $creditBalance === 1 ? '' : 's' ?></strong> · one credit = one seat. <a href="<?= url('/member/my_credits.php') ?>" class="text-gold-400 hover:text-gold-300 underline-offset-4 hover:underline">View balance</a></span>
        </span>
      </label>
    <?php elseif (!$creditsAllowed && $creditBalance > 0): ?>
      <p class="text-[11px] text-beige-100/45 italic px-2">Class-pack credits can't be used for this session — it must be paid.</p>
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

    <?php
      // Prefill the promo field from ?promo=CODE in the URL (referral /
      // marketing links) or from the last-submitted value on validation
      // error. Hidden when a credit is being redeemed.
      $promoPrefill = trim((string) input('promo_code', (string) input('promo', '')));
    ?>
    <!-- Optional health disclosure — always shown so front-of-house sees any allergies / conditions on the prep sheet. -->
    <label class="block">
      <span class="text-[11px] uppercase tracking-widest text-beige-100/60">Anything we should know? <span class="text-beige-100/35 lowercase tracking-normal">(optional — allergies, injuries, pregnancy, medication)</span></span>
      <textarea name="health_disclosure" rows="2" maxlength="2000" placeholder="Held in confidence."
                class="mt-2 w-full rounded-2xl bg-navy-950 border border-white/5 px-4 py-3 text-sm focus:border-gold-500/50 focus:outline-none"><?= e((string) input('health_disclosure', '')) ?></textarea>
    </label>

    <?php if ($needsWaiver): ?>
      <div class="border border-gold-500/25 rounded-2xl p-5 bg-gold-500/5 space-y-4">
        <p class="text-[10px] uppercase tracking-[0.3em] text-gold-400/80">Session waiver</p>
        <div class="text-xs text-beige-100/70 leading-relaxed max-h-40 overflow-y-auto pr-2 space-y-2">
          <?= $waiverBody /* admin-authored HTML, editable at /admin/legal_settings.php?which=waiver */ ?>
        </div>
        <label class="flex items-start gap-3 cursor-pointer">
          <input type="checkbox" name="waiver_accepted" value="1" <?= !empty($_POST['waiver_accepted']) ? 'checked' : '' ?> class="mt-1 accent-gold-500" required>
          <span class="text-sm text-beige-100">I've read and agree to the session waiver above.</span>
        </label>
        <p class="text-[11px] text-beige-100/45">
          <a href="<?= url('/public/waiver.php') ?>" target="_blank" class="text-gold-400/80 hover:text-gold-300 underline-offset-4 hover:underline">Open the full waiver in a new tab →</a>
        </p>
      </div>
    <?php endif; ?>

    <details class="rounded-2xl border border-white/5 bg-navy-900/40 p-4" <?= $promoPrefill !== '' ? 'open' : '' ?> x-show="!useCredit" x-cloak>
      <summary class="cursor-pointer text-sm text-beige-100/75 hover:text-gold-400 transition">
        Have a promo or gift code?
      </summary>
      <div class="mt-3 flex flex-col sm:flex-row gap-2">
        <input name="promo_code" placeholder="Enter code"
               value="<?= e($promoPrefill) ?>"
               maxlength="60"
               class="flex-1 rounded-full bg-navy-950 border border-white/10 px-4 py-2.5 text-sm uppercase tracking-widest focus:border-gold-500/50 focus:outline-none">
      </div>
      <p class="mt-2 text-[11px] text-beige-100/45">Applied at checkout. Codes can't stack with credit redemption.</p>
    </details>

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
