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

    /**
     * Compute the Nth (1..5) or 'L' (last) weekday of a given
     * calendar month. Returns YYYY-MM-DD or null if the ordinal
     * doesn't exist that month (e.g. no 5th Sunday).
     */
    function nth_weekday_of_month(int $year, int $month, $ordinal, int $dow): ?string
    {
        $firstTs   = mktime(0, 0, 0, $month, 1, $year);
        $daysCount = (int) date('t', $firstTs);
        $firstDow  = (int) date('w', $firstTs);
        if ($ordinal === 'L') {
            $lastTs  = mktime(0, 0, 0, $month, $daysCount, $year);
            $lastDow = (int) date('w', $lastTs);
            $day     = $daysCount - (($lastDow - $dow + 7) % 7);
        } else {
            $ord = (int) $ordinal;
            if ($ord < 1 || $ord > 5) return null;
            $day = 1 + (($dow - $firstDow + 7) % 7) + ($ord - 1) * 7;
            if ($day > $daysCount) return null;
        }
        return sprintf('%04d-%02d-%02d', $year, $month, $day);
    }

    /**
     * Parse a monthly pattern like "1SUN" or "LFRI" into
     * [ordinal, dow] or null on invalid input.
     */
    function parse_monthly_pattern(string $raw): ?array
    {
        $raw = strtoupper(trim($raw));
        if (!preg_match('/^([1-5L])(SUN|MON|TUE|WED|THU|FRI|SAT)$/', $raw, $m)) return null;
        $dowMap = ['SUN'=>0,'MON'=>1,'TUE'=>2,'WED'=>3,'THU'=>4,'FRI'=>5,'SAT'=>6];
        return [$m[1] === 'L' ? 'L' : (int) $m[1], $dowMap[$m[2]]];
    }

    /** Load skip dates for one template as a set of YYYY-MM-DD strings. */
    function load_event_exceptions(int $eventId): array
    {
        static $cache = [];
        if (isset($cache[$eventId])) return $cache[$eventId];
        $stmt = db()->prepare("SELECT exception_date FROM event_recurrence_exceptions WHERE event_id = :id");
        $stmt->execute([':id' => $eventId]);
        $dates = array_fill_keys(array_column($stmt->fetchAll(), 'exception_date'), true);
        return $cache[$eventId] = $dates;
    }

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

            if (!in_array($recurrence, ['daily','weekly','monthly','custom'], true)) {
                // One-off — pass through if in the future.
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
            $exceptions   = load_event_exceptions((int) $e['id']);

            // Build the list of candidate dates for this recurrence.
            $candidates = [];
            if ($recurrence === 'daily') {
                for ($i = 0; $i < $days; $i++) {
                    $candidates[] = date('Y-m-d', $startDay + $i * 86400);
                }
            } elseif ($recurrence === 'weekly') {
                $allowedDays = array_values(array_filter(array_map('intval',
                    explode(',', (string) ($e['recurrence_days'] ?? '')))
                    , fn($n) => $n >= 0 && $n <= 6));
                if (!$allowedDays) continue;
                for ($i = 0; $i < $days; $i++) {
                    $ts = $startDay + $i * 86400;
                    if (in_array((int) date('w', $ts), $allowedDays, true)) {
                        $candidates[] = date('Y-m-d', $ts);
                    }
                }
            } elseif ($recurrence === 'custom') {
                // Explicit list of YYYY-MM-DD dates. Each stands as its
                // own occurrence — no pattern derivation.
                $rawDates = array_filter(array_map('trim',
                    preg_split('/[\s,;]+/', (string) ($e['custom_dates'] ?? ''))));
                foreach ($rawDates as $d) {
                    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $d)) {
                        $candidates[] = $d;
                    }
                }
            } else { // monthly
                $parsed = parse_monthly_pattern((string) ($e['recurrence_days'] ?? ''));
                if (!$parsed) continue;
                [$ordinal, $dow] = $parsed;
                $windowEnd = $startDay + $days * 86400;
                $iter      = strtotime(date('Y-m-01', $startDay));
                while ($iter <= $windowEnd) {
                    $y = (int) date('Y', $iter);
                    $m = (int) date('n', $iter);
                    $d = nth_weekday_of_month($y, $m, $ordinal, $dow);
                    if ($d !== null && strtotime($d) >= $startDay) $candidates[] = $d;
                    $iter = strtotime('+1 month', $iter);
                }
            }

            foreach ($candidates as $occDate) {
                if (isset($exceptions[$occDate])) continue;
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
     * Human sentence for an event's schedule — used on the public
     * event page + experience cards when the event is a recurring
     * template rather than a specific occurrence.
     *
     *   describe_event_schedule($event) → "Daily at 7:00 PM"
     *                                    "Every Tue & Thu at 7:00 PM"
     *                                    formatted starts_at for one-offs
     */
    function describe_event_schedule(array $e): string
    {
        $rec  = (string) ($e['recurrence'] ?? 'none');
        $time = date('g:i A', strtotime((string) $e['starts_at']));
        if ($rec === 'daily')  return 'Daily at ' . $time;
        if ($rec === 'weekly') {
            $labels = ['Sun','Mon','Tue','Wed','Thu','Fri','Sat'];
            $days   = array_values(array_filter(array_map('intval',
                explode(',', (string) ($e['recurrence_days'] ?? '')))
                , fn($n) => $n >= 0 && $n <= 6));
            if (!$days) return 'Weekly · time TBA';
            sort($days);
            $names = array_map(fn($n) => $labels[$n], $days);
            $joined = count($names) === 1 ? $names[0]
                : (count($names) === 2 ? implode(' & ', $names)
                : implode(', ', array_slice($names, 0, -1)) . ' & ' . end($names));
            return 'Every ' . $joined . ' at ' . $time;
        }
        if ($rec === 'monthly') {
            $parsed = parse_monthly_pattern((string) ($e['recurrence_days'] ?? ''));
            if (!$parsed) return 'Monthly · date TBA';
            [$ord, $dow] = $parsed;
            $labels = ['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'];
            $ordName = $ord === 'L' ? 'Last' : [null,'First','Second','Third','Fourth','Fifth'][$ord];
            return $ordName . ' ' . $labels[$dow] . ' of every month at ' . $time;
        }
        if ($rec === 'custom') {
            $dates = array_values(array_filter(array_map('trim',
                preg_split('/[\s,;]+/', (string) ($e['custom_dates'] ?? '')))));
            $future = array_filter($dates, fn($d) => preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) && strtotime($d . ' 23:59:59') >= time());
            sort($future);
            if (!$future) return 'Selected dates · time TBA';
            $shown = array_slice($future, 0, 3);
            $labels = array_map(fn($d) => date('j M', strtotime($d)), $shown);
            $suffix = count($future) > 3 ? ' + ' . (count($future) - 3) . ' more' : '';
            return implode(', ', $labels) . $suffix . ' at ' . $time;
        }
        return format_datetime($e['starts_at'], 'l, d M Y · g:i A');
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
        if (!$tpl) return null;
        $rec = (string) ($tpl['recurrence'] ?? 'none');
        if (!in_array($rec, ['daily','weekly','monthly','custom'], true)) return null;

        // Reject any excepted date up-front — the admin explicitly
        // skipped it, so no booking should materialise on that day.
        $exceptions = load_event_exceptions((int) $tpl['id']);
        if (isset($exceptions[$date])) return null;

        // Weekly: date must land on an allowed day-of-week.
        if ($rec === 'weekly') {
            $allowed = array_values(array_filter(array_map('intval',
                explode(',', (string) ($tpl['recurrence_days'] ?? '')))
                , fn($n) => $n >= 0 && $n <= 6));
            $dow = (int) date('w', strtotime($date . ' 00:00:00'));
            if (!$allowed || !in_array($dow, $allowed, true)) return null;
        }
        // Monthly: date must equal the pattern's Nth-weekday for that month.
        if ($rec === 'monthly') {
            $parsed = parse_monthly_pattern((string) ($tpl['recurrence_days'] ?? ''));
            if (!$parsed) return null;
            [$ordinal, $dow] = $parsed;
            $y = (int) date('Y', strtotime($date));
            $m = (int) date('n', strtotime($date));
            $expected = nth_weekday_of_month($y, $m, $ordinal, $dow);
            if ($expected !== $date) return null;
        }
        // Custom: date must be in the explicit list.
        if ($rec === 'custom') {
            $customDates = array_filter(array_map('trim',
                preg_split('/[\s,;]+/', (string) ($tpl['custom_dates'] ?? ''))));
            if (!in_array($date, $customDates, true)) return null;
        }

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

        // Child instances need to inherit every per-event configuration
        // from the template — earlier versions of this helper only copied
        // the core fields, which silently dropped:
        //   - experience_id (breaks the "Reserve" link back from an
        //     experience card),
        //   - package_a/b_label / _perks (custom workshop tiers reverted
        //     to Comfort/BYO defaults),
        //   - package_b_enabled (admin turns off BYO on the template but
        //     the child still offers it),
        //   - audience (private templates leaked their children as
        //     public),
        //   - credit_eligible (paid-only templates let members redeem
        //     credits on child bookings),
        //   - referral_reward_amount (per-event override lost),
        //   - intake_type (pet workshops lost the pet intake form).
        $tplPkgBEnabled = array_key_exists('package_b_enabled', $tpl)
            ? (int) $tpl['package_b_enabled'] : 1;
        // Custom recurrence is a template-only concept — the child is
        // a concrete one-off, so custom_dates isn't propagated to it.

        db()->prepare(
            "INSERT INTO events
                (slug, parent_event_id, title, subtitle, description, cover_image,
                 location, starts_at, ends_at, capacity, price_public, price_member,
                 facilitator, category, status, recurrence, recurrence_until, created_by,
                 experience_id, audience, credit_eligible, referral_reward_amount,
                 package_a_label, package_a_perks,
                 package_b_label, package_b_perks, package_b_enabled,
                 intake_type)
             VALUES
                (:slug, :pid, :title, :subtitle, :description, :cover,
                 :location, :starts_at, :ends_at, :capacity, :pp, :pm,
                 :facilitator, :category, :status, 'none', NULL, :cb,
                 :xid, :aud, :cel, :rra,
                 :pal, :pap,
                 :pbl, :pbp, :pbe,
                 :itype)"
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
            ':xid'         => $tpl['experience_id'] ?? null,
            ':aud'         => $tpl['audience'] ?? 'public',
            ':cel'         => (int) ($tpl['credit_eligible'] ?? 1),
            ':rra'         => $tpl['referral_reward_amount'] ?? null,
            ':pal'         => $tpl['package_a_label'] ?? null,
            ':pap'         => $tpl['package_a_perks'] ?? null,
            ':pbl'         => $tpl['package_b_label'] ?? null,
            ':pbp'         => $tpl['package_b_perks'] ?? null,
            ':pbe'         => $tplPkgBEnabled,
            ':itype'       => $tpl['intake_type'] ?? 'none',
        ]);

        $childStmt->execute([':pid' => $templateId, ':d' => $date]);
        return $childStmt->fetch() ?: null;
    }
}
