<?php
declare(strict_types=1);

/**
 * Recurring-event helpers.
 *
 *   expand_event_occurrences() takes a list of raw event rows
 *   (templates + non-recurring) and returns a flat list of
 *   bookable occurrences for the next N days. Recurring 'daily'
 *   templates are virtually expanded; non-recurring events pass
 *   through. seats_taken is resolved per occurrence by looking up
 *   the concrete child event for that date (if one exists yet).
 *
 *   find_or_create_recurring_instance() is called from
 *   member/book_event.php to materialise a concrete child event
 *   row for the date the member booked.
 */

if (!function_exists('expand_event_occurrences')) {

    function expand_event_occurrences(array $rows, int $days = 14): array
    {
        $now      = time();
        $todayDay = strtotime(date('Y-m-d 00:00:00', $now));
        $out      = [];

        $childStmt = db()->prepare(
            "SELECT c.id,
                    (SELECT COALESCE(SUM(quantity),0) FROM event_bookings b
                       WHERE b.event_id = c.id
                         AND b.status IN ('pending','paid','attended')) AS seats_taken
               FROM events c
              WHERE c.parent_event_id = :pid AND DATE(c.starts_at) = :d
              LIMIT 1"
        );

        foreach ($rows as $e) {
            $recurrence = (string) ($e['recurrence'] ?? 'none');

            if ($recurrence !== 'daily') {
                if (strtotime((string) $e['starts_at']) >= $now) {
                    $e['_template_id']     = 0;
                    $e['_occurrence_date'] = '';
                    $e['_child_id']        = (int) $e['id'];
                    $out[] = $e;
                }
                continue;
            }

            $templateTs   = strtotime((string) $e['starts_at']);
            $templateTime = date('H:i:s', $templateTs);
            $tplStartDay  = strtotime(date('Y-m-d 00:00:00', $templateTs));
            $startDay     = max($todayDay, $tplStartDay);
            $untilTs      = !empty($e['recurrence_until'])
                ? strtotime($e['recurrence_until'] . ' 23:59:59')
                : null;

            for ($i = 0; $i < $days; $i++) {
                $occDay   = $startDay + $i * 86400;
                $occDate  = date('Y-m-d', $occDay);
                $occStart = $occDate . ' ' . $templateTime;
                $occTs    = strtotime($occStart);

                if ($occTs <= $now) continue;
                if ($untilTs !== null && $occTs > $untilTs) continue;

                $childStmt->execute([':pid' => $e['id'], ':d' => $occDate]);
                $child = $childStmt->fetch();

                $occ = $e;
                $occ['starts_at']        = $occStart;
                $occ['seats_taken']      = $child ? (int) $child['seats_taken'] : 0;
                $occ['_template_id']     = (int) $e['id'];
                $occ['_occurrence_date'] = $occDate;
                $occ['_child_id']        = $child ? (int) $child['id'] : 0;
                $out[] = $occ;
            }
        }

        usort($out, fn($a, $b) => strcmp((string) $a['starts_at'], (string) $b['starts_at']));
        return $out;
    }

    /**
     * Materialise a concrete child event row for a recurring
     * template on a specific date. Idempotent — returns the
     * existing child if one already exists.
     */
    function find_or_create_recurring_instance(int $templateId, string $date): ?array
    {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) return null;

        $tplStmt = db()->prepare("SELECT * FROM events WHERE id = :id LIMIT 1");
        $tplStmt->execute([':id' => $templateId]);
        $tpl = $tplStmt->fetch();
        if (!$tpl || ($tpl['recurrence'] ?? 'none') !== 'daily') return null;

        $childStmt = db()->prepare(
            "SELECT * FROM events
              WHERE parent_event_id = :pid AND DATE(starts_at) = :d LIMIT 1"
        );
        $childStmt->execute([':pid' => $templateId, ':d' => $date]);
        $existing = $childStmt->fetch();
        if ($existing) return $existing;

        // Honour the cutoff and never materialise a past instance.
        if (!empty($tpl['recurrence_until']) && $date > $tpl['recurrence_until']) return null;
        $startTime = date('H:i:s', strtotime((string) $tpl['starts_at']));
        $endTime   = date('H:i:s', strtotime((string) $tpl['ends_at']));
        $newStarts = $date . ' ' . $startTime;
        $newEnds   = $date . ' ' . $endTime;
        if (strtotime($newStarts) <= time()) return null;

        $childSlug = (string) $tpl['slug'] . '-' . $date;

        db()->prepare(
            "INSERT INTO events
                (slug, parent_event_id, title, subtitle, description, cover_image,
                 location, starts_at, ends_at, capacity, price_public, price_member,
                 facilitator, category, status, recurrence, recurrence_until, created_by)
             VALUES
                (:slug, :pid, :title, :subtitle, :description, :cover,
                 :location, :starts_at, :ends_at, :capacity, :pp, :pm,
                 :facilitator, :category, :status, 'none', NULL, :cb)"
        )->execute([
            ':slug'        => $childSlug,
            ':pid'         => $templateId,
            ':title'       => $tpl['title'],
            ':subtitle'    => $tpl['subtitle'],
            ':description' => $tpl['description'],
            ':cover'       => $tpl['cover_image'],
            ':location'    => $tpl['location'],
            ':starts_at'   => $newStarts,
            ':ends_at'     => $newEnds,
            ':capacity'    => $tpl['capacity'],
            ':pp'          => $tpl['price_public'],
            ':pm'          => $tpl['price_member'],
            ':facilitator' => $tpl['facilitator'],
            ':category'    => $tpl['category'],
            ':status'      => $tpl['status'],
            ':cb'          => $tpl['created_by'],
        ]);

        $childStmt->execute([':pid' => $templateId, ':d' => $date]);
        return $childStmt->fetch() ?: null;
    }
}
