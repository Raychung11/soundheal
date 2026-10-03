<?php
declare(strict_types=1);

/**
 * Event package helpers.
 *
 * Events now carry N bookable packages (event_packages table) instead
 * of a hardcoded A/B pair. This module reads and writes them, and
 * synchronises the legacy events.package_a/b_* columns from the
 * canonical event_packages rows so pages that still read the old
 * columns (calendar, JSON-LD, event summary card) keep working
 * without needing to be rewritten in the same pass.
 */

/**
 * Load active + disabled packages for an event, ordered by
 * sort_order. Returns an empty array when the event doesn't have any
 * (which after the 062 backfill only happens for events created
 * before the migration ran; the caller can fall back to reading the
 * legacy events.package_a/b_* columns in that case).
 */
function event_packages_load(int $eventId): array
{
    if ($eventId <= 0) return [];
    $stmt = db()->prepare(
        "SELECT * FROM event_packages
          WHERE event_id = :e
          ORDER BY sort_order ASC, id ASC"
    );
    $stmt->execute([':e' => $eventId]);
    return $stmt->fetchAll();
}

/**
 * Active-only variant for the public booking page.
 */
function event_packages_active(int $eventId): array
{
    return array_values(array_filter(event_packages_load($eventId),
        fn($p) => ($p['status'] ?? 'active') === 'active'));
}

/**
 * Replace an event's package set. $incoming is an ordered array of
 * ['id' => ?int, 'label' => str, 'price' => float, 'perks' => str,
 * 'humans' => int, 'pets' => int, 'status' => 'active|disabled'].
 *
 * Existing rows are updated in place when their id is present;
 * rows whose id isn't in $incoming are deleted (their bookings
 * survive via booking.package_id — the FK is set nullable at the
 * booking layer so deletion doesn't cascade).
 *
 * Wrapped in a transaction so a partial failure doesn't leave the
 * event with a half-mutated package list.
 */
function event_packages_save(int $eventId, array $incoming): void
{
    if ($eventId <= 0) return;

    $keepIds = [];
    foreach ($incoming as $row) {
        $id = (int) ($row['id'] ?? 0);
        if ($id > 0) $keepIds[] = $id;
    }

    db()->beginTransaction();
    try {
        // Delete rows that are no longer in the incoming set.
        if ($keepIds) {
            $ph = implode(',', array_fill(0, count($keepIds), '?'));
            $del = db()->prepare("DELETE FROM event_packages WHERE event_id = ? AND id NOT IN ($ph)");
            $del->execute(array_merge([$eventId], $keepIds));
        } else {
            db()->prepare("DELETE FROM event_packages WHERE event_id = :e")
                ->execute([':e' => $eventId]);
        }

        $sort = 10;
        foreach ($incoming as $row) {
            $label = trim((string) ($row['label'] ?? ''));
            if ($label === '') continue; // rows with an empty label are dropped
            $price  = max(0.0, (float) ($row['price']  ?? 0));
            $perks  = trim((string) ($row['perks']  ?? ''));
            $humans = max(0, min(8, (int) ($row['humans'] ?? 1)));
            $pets   = max(0, min(8, (int) ($row['pets']   ?? 0)));
            $status = in_array($row['status'] ?? 'active', ['active','disabled'], true) ? $row['status'] : 'active';
            $id     = (int) ($row['id'] ?? 0);

            if ($id > 0) {
                db()->prepare(
                    "UPDATE event_packages
                        SET label = :l, price = :p, perks = :pk,
                            humans = :h, pets = :pt, sort_order = :so, status = :st
                      WHERE id = :id AND event_id = :e"
                )->execute([
                    ':l' => $label, ':p' => $price, ':pk' => $perks ?: null,
                    ':h' => $humans, ':pt' => $pets, ':so' => $sort, ':st' => $status,
                    ':id' => $id, ':e' => $eventId,
                ]);
            } else {
                db()->prepare(
                    "INSERT INTO event_packages
                        (event_id, label, price, perks, humans, pets, sort_order, status)
                     VALUES (:e, :l, :p, :pk, :h, :pt, :so, :st)"
                )->execute([
                    ':e' => $eventId, ':l' => $label, ':p' => $price, ':pk' => $perks ?: null,
                    ':h' => $humans, ':pt' => $pets, ':so' => $sort, ':st' => $status,
                ]);
            }
            $sort += 10;
        }

        db()->commit();
    } catch (Throwable $e) {
        db()->rollBack();
        error_log('[event_packages] save failed: ' . $e->getMessage());
        throw $e;
    }

    // Mirror the first two active packages onto the legacy A/B
    // columns so pages that still read events.package_a_label /
    // price_public etc. show up-to-date info. Once every reader is
    // migrated to event_packages this mirror can go away.
    event_packages_sync_legacy_columns($eventId);
}

/**
 * Copy the first active package into price_public / package_a_* and
 * the second into price_member / package_b_* so legacy readers see
 * the current values. Idempotent — safe to call whenever packages
 * change, including from find_or_create_recurring_instance materialisation.
 */
function event_packages_sync_legacy_columns(int $eventId): void
{
    $active = event_packages_active($eventId);
    $a = $active[0] ?? null;
    $b = $active[1] ?? null;
    db()->prepare(
        "UPDATE events SET
            price_public       = COALESCE(:pp, price_public),
            price_member       = COALESCE(:pm, price_member),
            package_a_label    = :al,
            package_a_perks    = :ap,
            package_a_humans   = COALESCE(:ah, package_a_humans),
            package_a_pets     = COALESCE(:apt, package_a_pets),
            package_b_label    = :bl,
            package_b_perks    = :bp,
            package_b_humans   = COALESCE(:bh, package_b_humans),
            package_b_pets     = COALESCE(:bpt, package_b_pets),
            package_b_enabled  = :be
          WHERE id = :id"
    )->execute([
        ':pp'  => $a ? (float) $a['price'] : null,
        ':pm'  => $b ? (float) $b['price'] : null,
        ':al'  => $a['label'] ?? null,
        ':ap'  => $a['perks'] ?? null,
        ':ah'  => $a ? (int) $a['humans'] : null,
        ':apt' => $a ? (int) $a['pets']   : null,
        ':bl'  => $b['label'] ?? null,
        ':bp'  => $b['perks'] ?? null,
        ':bh'  => $b ? (int) $b['humans'] : null,
        ':bpt' => $b ? (int) $b['pets']   : null,
        ':be'  => $b ? 1 : 0,
        ':id'  => $eventId,
    ]);
}

/**
 * Copy a template's packages into a newly-materialised child. Called
 * from find_or_create_recurring_instance after the child row exists,
 * so bookings against the child pick up the same package options
 * (and the same package_ids can be referenced through the sync).
 */
function event_packages_clone(int $fromEventId, int $toEventId): void
{
    if ($fromEventId <= 0 || $toEventId <= 0 || $fromEventId === $toEventId) return;
    $rows = event_packages_load($fromEventId);
    if (!$rows) return;
    $ins = db()->prepare(
        "INSERT INTO event_packages
            (event_id, label, price, perks, humans, pets, sort_order, status)
         VALUES (:e, :l, :p, :pk, :h, :pt, :so, :st)"
    );
    foreach ($rows as $r) {
        $ins->execute([
            ':e'  => $toEventId,
            ':l'  => $r['label'],
            ':p'  => $r['price'],
            ':pk' => $r['perks'],
            ':h'  => $r['humans'],
            ':pt' => $r['pets'],
            ':so' => $r['sort_order'],
            ':st' => $r['status'],
        ]);
    }
    event_packages_sync_legacy_columns($toEventId);
}
