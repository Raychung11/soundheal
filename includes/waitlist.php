<?php
declare(strict_types=1);

/**
 * Waitlist helpers.
 *
 *   notify_next_waitlist_seat($eventId, $occurrenceDate) is called
 *   whenever a paid seat is freed (booking cancelled or refunded).
 *   It finds the oldest 'waiting' entry for that event/date, emails
 *   the "a seat just opened" invite, and flips their status to
 *   'notified'.
 *
 *   MVP: first-come-first-served on the invite email — no held
 *   window. Admin can re-invite the next entry manually if the
 *   first person doesn't book in time (via the Admin waitlist
 *   view, added later).
 */

if (!function_exists('notify_next_waitlist_seat')) {

    /** Resolve the parent event id + occurrence date for a booking row. */
    function waitlist_context_for_booking(int $bookingId): ?array
    {
        $stmt = db()->prepare(
            "SELECT b.event_id, e.parent_event_id, DATE(e.starts_at) AS occ_date, e.recurrence
               FROM event_bookings b
               JOIN events e ON e.id = b.event_id
              WHERE b.id = :id LIMIT 1"
        );
        $stmt->execute([':id' => $bookingId]);
        $row = $stmt->fetch();
        if (!$row) return null;

        // If the booking was against a concrete child of a recurring
        // template, invite the waitlist attached to the template + date.
        if (!empty($row['parent_event_id'])) {
            return ['event_id' => (int) $row['parent_event_id'], 'date' => (string) $row['occ_date']];
        }
        return ['event_id' => (int) $row['event_id'], 'date' => null];
    }

    function notify_next_waitlist_seat(int $eventId, ?string $occurrenceDate = null): bool
    {
        $stmt = db()->prepare(
            "SELECT id, email, full_name FROM event_waitlist
              WHERE event_id = :e
                AND ((:d IS NULL AND occurrence_date IS NULL) OR occurrence_date = :d2)
                AND status = 'waiting'
              ORDER BY created_at ASC LIMIT 1"
        );
        $stmt->execute([':e' => $eventId, ':d' => $occurrenceDate, ':d2' => $occurrenceDate]);
        $next = $stmt->fetch();
        if (!$next) return false;

        $ev = db()->prepare("SELECT title, starts_at, ends_at FROM events WHERE id = :id LIMIT 1");
        $ev->execute([':id' => $eventId]);
        $event = $ev->fetch();
        if (!$event) return false;

        $whenStr = $occurrenceDate
            ? format_datetime($occurrenceDate . ' ' . date('H:i:s', strtotime((string) $event['starts_at'])), 'l, d M Y · g:i A')
            : format_datetime($event['starts_at'], 'l, d M Y · g:i A');

        $base       = rtrim((string) config('app.url'), '/');
        $reserveUrl = $base . '/public/event.php?id=' . $eventId
                    . ($occurrenceDate ? '&date=' . urlencode($occurrenceDate) : '');
        $firstName  = trim(explode(' ', (string) $next['full_name'])[0]) ?: 'friend';

        $ok = send_mail(
            (string) $next['email'],
            (string) $next['full_name'],
            'A seat just opened · ' . $event['title'],
            'waitlist_seat_open',
            [
                'name'        => $firstName,
                'event_title' => $event['title'],
                'starts_at'   => $whenStr,
                'reserve_url' => $reserveUrl,
            ]
        );
        if ($ok) {
            db()->prepare(
                "UPDATE event_waitlist SET status = 'notified', notified_at = NOW()
                  WHERE id = :id"
            )->execute([':id' => (int) $next['id']]);
            audit_log('waitlist.notify', 'event_waitlist', (int) $next['id'], [
                'event_id' => $eventId, 'date' => $occurrenceDate,
            ]);
        }
        return $ok;
    }

    /**
     * Called from the cancel / refund handlers with the booking id.
     * Resolves the correct (event_id, occurrence_date) and invites
     * the next waitlist entry. Silent no-op if nothing waiting.
     */
    function notify_next_waitlist_for_booking(int $bookingId): void
    {
        $ctx = waitlist_context_for_booking($bookingId);
        if (!$ctx) return;
        notify_next_waitlist_seat($ctx['event_id'], $ctx['date']);
    }
}
