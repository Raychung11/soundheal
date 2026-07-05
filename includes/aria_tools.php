<?php
declare(strict_types=1);

/**
 * Aria's toolbox.
 *
 *   When OpenAI tool calling is enabled, the model can call any of the
 *   functions defined in aria_tools_schema(). The server validates the
 *   arguments, runs a scoped query, and returns a JSON payload.
 *
 *   Personalised tools require a logged-in user; the dispatcher refuses
 *   them otherwise. All queries are scoped to the supplied $userId so
 *   the model can never read another member's data even if it asks.
 *
 *   Adding a new tool: append a schema entry + a `case` to the
 *   dispatcher. Keep results small (the result is sent back to OpenAI
 *   as plain JSON — every byte costs tokens).
 */

if (!function_exists('aria_tools_schema')) {

    function aria_tools_schema(): array
    {
        return [
            // ---------- Public lookups ----------
            [
                'type' => 'function',
                'function' => [
                    'name' => 'list_upcoming_events',
                    'description' => 'Returns the next published in-person sessions. Use whenever a guest asks "what is happening", "what is on this week", "next event", etc.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'limit' => ['type' => 'integer', 'description' => '1-10', 'minimum' => 1, 'maximum' => 10],
                        ],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'search_events',
                    'description' => 'Search published sessions by free-text keyword and/or date range. Useful when a guest names a specific date or experience type.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'keyword' => ['type' => 'string', 'description' => 'Matches title, subtitle, description, location, facilitator.'],
                            'after'   => ['type' => 'string', 'description' => 'ISO 8601 date or datetime (sessions starting on or after this).'],
                            'before'  => ['type' => 'string', 'description' => 'ISO 8601 date or datetime (sessions starting on or before this).'],
                            'limit'   => ['type' => 'integer', 'minimum' => 1, 'maximum' => 10],
                        ],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'get_pack_pricing',
                    'description' => 'Returns the active class packs (bundle name, paid + bonus credits, total credits, price in MYR, validity in days, buy URL).',
                    'parameters' => ['type' => 'object', 'properties' => (object) []],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'get_business_info',
                    'description' => 'Returns the studio basics: brand, legal name, address, phone, email, business hours, a one-line refund-policy summary.',
                    'parameters' => ['type' => 'object', 'properties' => (object) []],
                ],
            ],

            // ---------- Personalised (logged-in member) ----------
            [
                'type' => 'function',
                'function' => [
                    'name' => 'get_my_credits',
                    'description' => 'Returns the logged-in member\'s class-pack credit balance and a per-pack breakdown (credits remaining, expiry).',
                    'parameters' => ['type' => 'object', 'properties' => (object) []],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'get_my_bookings',
                    'description' => 'Returns the logged-in member\'s bookings. scope=upcoming (default) | past | all.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'scope' => ['type' => 'string', 'enum' => ['upcoming', 'past', 'all']],
                            'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 10],
                        ],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'get_my_invoices',
                    'description' => 'Returns the member\'s recent invoices and receipts (doc_number, total, status, type).',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'limit' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 10],
                        ],
                    ],
                ],
            ],

            // ---------- Actions ----------
            [
                'type' => 'function',
                'function' => [
                    'name' => 'get_booking_link',
                    'description' => 'Returns the URL where the guest can reserve a seat for a specific event. Use after they say they want to book; let them click — never claim to have booked it yourself.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'event_id' => ['type' => 'integer', 'description' => 'The event id from list_upcoming_events / search_events.'],
                        ],
                        'required' => ['event_id'],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'submit_contact_lead',
                    'description' => 'Records a general contact/enquiry. Only call after confirming name, email, and message with the guest. Returns a confirmation id.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'name'    => ['type' => 'string'],
                            'email'   => ['type' => 'string', 'description' => 'Valid email.'],
                            'phone'   => ['type' => 'string'],
                            'subject' => ['type' => 'string'],
                            'message' => ['type' => 'string'],
                        ],
                        'required' => ['name', 'email', 'message'],
                    ],
                ],
            ],
            [
                'type' => 'function',
                'function' => [
                    'name' => 'submit_corporate_inquiry',
                    'description' => 'Records a corporate / on-site session enquiry. Confirm the details with the guest before calling. Returns a confirmation id.',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'company_name'  => ['type' => 'string'],
                            'contact_name'  => ['type' => 'string'],
                            'contact_email' => ['type' => 'string'],
                            'contact_phone' => ['type' => 'string'],
                            'team_size'     => ['type' => 'string'],
                            'interest'      => ['type' => 'string'],
                            'message'       => ['type' => 'string'],
                        ],
                        'required' => ['company_name', 'contact_name', 'contact_email'],
                    ],
                ],
            ],
        ];
    }

    /**
     * Dispatch a tool call. Returns a plain array (will be JSON-encoded
     * before being sent back to OpenAI).
     *
     * Personalised tools refuse to run without a logged-in user; the
     * "ok=false" shape lets the model report the limitation gracefully.
     */
    function aria_execute_tool(string $name, array $args, ?int $userId): array
    {
        try {
            return match ($name) {
                'list_upcoming_events'     => aria_tool_list_upcoming_events($args),
                'search_events'            => aria_tool_search_events($args),
                'get_pack_pricing'         => aria_tool_get_pack_pricing(),
                'get_business_info'        => aria_tool_get_business_info(),
                'get_my_credits'           => aria_tool_get_my_credits($userId),
                'get_my_bookings'          => aria_tool_get_my_bookings($userId, $args),
                'get_my_invoices'          => aria_tool_get_my_invoices($userId, $args),
                'get_booking_link'         => aria_tool_get_booking_link($args),
                'submit_contact_lead'      => aria_tool_submit_contact_lead($args),
                'submit_corporate_inquiry' => aria_tool_submit_corporate_inquiry($args),
                default                    => ['ok' => false, 'error' => 'Unknown tool: ' . $name],
            };
        } catch (Throwable $e) {
            error_log('[Aria tool] ' . $name . ': ' . $e->getMessage());
            return ['ok' => false, 'error' => 'Tool failed: ' . $e->getMessage()];
        }
    }

    // ============================================================
    // Tool handlers
    // ============================================================

    function aria_tool_list_upcoming_events(array $args): array
    {
        $limit = max(1, min(10, (int) ($args['limit'] ?? 5)));
        $stmt = db()->prepare(
            "SELECT e.id, e.title, e.subtitle, e.starts_at, e.ends_at, e.location, e.facilitator,
                    e.price_public, e.price_member, e.capacity,
                    (SELECT COALESCE(SUM(quantity), 0) FROM event_bookings
                       WHERE event_id = e.id AND status IN ('paid','attended','pending')) AS taken
               FROM events e
              WHERE e.status = 'published'
                AND e.starts_at >= NOW()
              ORDER BY e.starts_at ASC
              LIMIT {$limit}"
        );
        $stmt->execute();
        return ['ok' => true, 'events' => array_map('aria_shape_event', $stmt->fetchAll())];
    }

    function aria_tool_search_events(array $args): array
    {
        $where  = ["e.status = 'published'"];
        $params = [];

        if (!empty($args['keyword']) && is_string($args['keyword'])) {
            $where[] = '(e.title LIKE :kw OR e.subtitle LIKE :kw OR e.description LIKE :kw OR e.location LIKE :kw OR e.facilitator LIKE :kw)';
            $params[':kw'] = '%' . trim($args['keyword']) . '%';
        }
        if (!empty($args['after']) && ($ts = strtotime((string) $args['after']))) {
            $where[] = 'e.starts_at >= :after';
            $params[':after'] = date('Y-m-d H:i:s', $ts);
        }
        if (!empty($args['before']) && ($ts = strtotime((string) $args['before']))) {
            $where[] = 'e.starts_at <= :before';
            $params[':before'] = date('Y-m-d H:i:s', $ts);
        }
        if (empty($args['after']) && empty($args['before'])) {
            // Default: don't surface old events unless a date was given.
            $where[] = 'e.starts_at >= NOW()';
        }

        $limit = max(1, min(10, (int) ($args['limit'] ?? 5)));
        $sql = "SELECT e.id, e.title, e.subtitle, e.starts_at, e.ends_at, e.location, e.facilitator,
                       e.price_public, e.price_member, e.capacity,
                       (SELECT COALESCE(SUM(quantity), 0) FROM event_bookings
                          WHERE event_id = e.id AND status IN ('paid','attended','pending')) AS taken
                  FROM events e
                 WHERE " . implode(' AND ', $where) . "
                 ORDER BY e.starts_at ASC
                 LIMIT {$limit}";
        $stmt = db()->prepare($sql);
        $stmt->execute($params);
        return ['ok' => true, 'events' => array_map('aria_shape_event', $stmt->fetchAll())];
    }

    function aria_shape_event(array $row): array
    {
        $seatsLeft = max(0, (int) $row['capacity'] - (int) $row['taken']);
        return [
            'id'           => (int) $row['id'],
            'title'        => (string) $row['title'],
            'subtitle'     => (string) ($row['subtitle'] ?? ''),
            'starts_at'    => (string) $row['starts_at'],
            'starts_local' => format_datetime((string) $row['starts_at'], 'D, d M Y · g:i A'),
            'ends_at'      => (string) $row['ends_at'],
            'location'     => (string) ($row['location'] ?? ''),
            'facilitator'  => (string) ($row['facilitator'] ?? ''),
            'price_public' => 'RM ' . number_format((float) $row['price_public'], 2),
            'price_member' => 'RM ' . number_format((float) $row['price_member'], 2),
            'capacity'     => (int) $row['capacity'],
            'seats_left'   => $seatsLeft,
            'booking_url'  => url('/public/reserve.php?event_id=' . (int) $row['id']),
        ];
    }

    function aria_tool_get_pack_pricing(): array
    {
        $packs = function_exists('active_packs') ? active_packs() : [];
        $shaped = [];
        foreach ($packs as $p) {
            $total = (int) $p['paid_credits'] + (int) $p['bonus_credits'];
            $shaped[] = [
                'id'             => (int) $p['id'],
                'name'           => (string) $p['name'],
                'tagline'        => (string) ($p['tagline'] ?? ''),
                'paid_credits'   => (int) $p['paid_credits'],
                'bonus_credits'  => (int) $p['bonus_credits'],
                'total_credits'  => $total,
                'price'          => 'RM ' . number_format((float) $p['price'], 2),
                'validity_days'  => (int) $p['validity_days'],
                'one_credit_is'  => 'one offline class seat',
                'buy_url'        => url('/member/checkout_pack.php'),
            ];
        }
        return ['ok' => true, 'packs' => $shaped];
    }

    function aria_tool_get_business_info(): array
    {
        return [
            'ok' => true,
            'business' => [
                'brand'         => brand_name(),
                'legal_name'    => (string) setting('company_legal_name', ''),
                'registration'  => (string) setting('company_registration_no', ''),
                'address' => array_values(array_filter([
                    (string) setting('company_address_line1', ''),
                    (string) setting('company_address_line2', ''),
                    (string) setting('company_city', ''),
                    (string) setting('company_country', ''),
                ], fn ($v) => trim($v) !== '')),
                'phone'         => (string) setting('company_phone', ''),
                'email'         => (string) setting('company_email', ''),
                'business_hours'=> (string) setting('business_hours', ''),
                'instagram'     => (string) setting('company_social_instagram', ''),
                'whatsapp'      => (string) setting('company_social_whatsapp', ''),
                'refund_summary'=> 'Full refund if cancelled at least 24 hours before a session. Within 24 hours, the seat is held as a credit. Class-pack purchases are non-refundable but credits remain valid until expiry.',
                'urls' => [
                    'experiences' => url('/public/experiences.php'),
                    'events'      => url('/public/events.php'),
                    'packs'       => url('/member/checkout_pack.php'),
                    'contact'     => url('/public/contact.php'),
                    'terms'       => url('/public/terms.php'),
                    'privacy'     => url('/public/privacy.php'),
                    'refund'      => url('/public/refund.php'),
                ],
            ],
        ];
    }

    // ----- Personalised -----

    function aria_tool_get_my_credits(?int $userId): array
    {
        if (!$userId) return ['ok' => false, 'error' => 'Member must sign in to see credit balance.'];

        $balance = credit_balance_for($userId);

        $stmt = db()->prepare(
            "SELECT pack_snapshot, credits_remaining, credits_granted, expires_at, status
               FROM pack_purchases
              WHERE user_id = :u
                AND status IN ('active','expired')
              ORDER BY id DESC
              LIMIT 5"
        );
        $stmt->execute([':u' => $userId]);
        $packs = [];
        foreach ($stmt->fetchAll() as $row) {
            $snap = json_decode((string) ($row['pack_snapshot'] ?? '{}'), true) ?: [];
            $packs[] = [
                'pack_name'         => (string) ($snap['name'] ?? 'Class pack'),
                'credits_remaining' => (int) $row['credits_remaining'],
                'credits_granted'   => (int) $row['credits_granted'],
                'expires_at'        => $row['expires_at'] ? format_datetime((string) $row['expires_at'], 'd M Y') : null,
                'status'            => (string) $row['status'],
            ];
        }

        return ['ok' => true, 'balance' => $balance, 'packs' => $packs, 'credits_url' => url('/member/my_credits.php')];
    }

    function aria_tool_get_my_bookings(?int $userId, array $args): array
    {
        if (!$userId) return ['ok' => false, 'error' => 'Member must sign in to see bookings.'];

        $scope = $args['scope'] ?? 'upcoming';
        $limit = max(1, min(10, (int) ($args['limit'] ?? 5)));

        $where = ['b.user_id = :u'];
        if ($scope === 'upcoming') $where[] = "e.starts_at >= NOW() AND b.status IN ('paid','pending','attended')";
        elseif ($scope === 'past') $where[] = "e.starts_at < NOW()";
        $order = $scope === 'past' ? 'DESC' : 'ASC';

        $sql = "SELECT b.booking_ref, b.status, b.quantity, b.total_amount, b.paid_with_credit,
                       e.id AS event_id, e.title AS event_title, e.starts_at, e.location
                  FROM event_bookings b
                  JOIN events e ON e.id = b.event_id
                 WHERE " . implode(' AND ', $where) . "
                 ORDER BY e.starts_at {$order}
                 LIMIT {$limit}";
        $stmt = db()->prepare($sql);
        $stmt->execute([':u' => $userId]);

        $bookings = [];
        foreach ($stmt->fetchAll() as $row) {
            $bookings[] = [
                'booking_ref'      => (string) $row['booking_ref'],
                'event_id'         => (int) $row['event_id'],
                'event_title'      => (string) $row['event_title'],
                'starts_local'     => format_datetime((string) $row['starts_at'], 'D, d M Y · g:i A'),
                'location'         => (string) ($row['location'] ?? ''),
                'status'           => (string) $row['status'],
                'quantity'         => (int) $row['quantity'],
                'paid_with_credit' => (bool) $row['paid_with_credit'],
            ];
        }
        return ['ok' => true, 'scope' => $scope, 'bookings' => $bookings, 'bookings_url' => url('/member/my_bookings.php')];
    }

    function aria_tool_get_my_invoices(?int $userId, array $args): array
    {
        if (!$userId) return ['ok' => false, 'error' => 'Member must sign in to see invoices.'];

        $limit = max(1, min(10, (int) ($args['limit'] ?? 5)));
        $stmt = db()->prepare(
            "SELECT doc_number, doc_type, total, currency, status, purpose, issued_at, paid_at, access_token, id
               FROM invoices
              WHERE user_id = :u
              ORDER BY id DESC
              LIMIT {$limit}"
        );
        $stmt->execute([':u' => $userId]);

        $invoices = [];
        foreach ($stmt->fetchAll() as $row) {
            $invoices[] = [
                'doc_number' => (string) ($row['doc_number'] ?? ''),
                'doc_type'   => (string) $row['doc_type'],
                'purpose'    => (string) $row['purpose'],
                'total'      => 'RM ' . number_format((float) $row['total'], 2),
                'status'     => (string) $row['status'],
                'issued_at'  => $row['issued_at'] ? format_datetime((string) $row['issued_at'], 'd M Y') : null,
                'paid_at'    => $row['paid_at']   ? format_datetime((string) $row['paid_at'],   'd M Y') : null,
                'url'        => url('/member/document.php?id=' . (int) $row['id'] . '&t=' . urlencode((string) $row['access_token'])),
            ];
        }
        return ['ok' => true, 'invoices' => $invoices, 'invoices_url' => url('/member/invoices.php')];
    }

    // ----- Actions -----

    function aria_tool_get_booking_link(array $args): array
    {
        $eventId = (int) ($args['event_id'] ?? 0);
        if ($eventId <= 0) return ['ok' => false, 'error' => 'event_id is required.'];

        $stmt = db()->prepare("SELECT title, starts_at, location, status FROM events WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $eventId]);
        $event = $stmt->fetch();
        if (!$event || $event['status'] !== 'published') {
            return ['ok' => false, 'error' => 'That session is not available for booking.'];
        }
        return [
            'ok'           => true,
            'event_title'  => (string) $event['title'],
            'starts_local' => format_datetime((string) $event['starts_at'], 'D, d M Y · g:i A'),
            'location'     => (string) ($event['location'] ?? ''),
            'booking_url'  => url('/public/reserve.php?event_id=' . $eventId),
            'note'         => 'The guest must click the URL and confirm on the booking page. Do not claim to have booked it for them.',
        ];
    }

    function aria_tool_submit_contact_lead(array $args): array
    {
        $name    = trim((string) ($args['name'] ?? ''));
        $email   = trim((string) ($args['email'] ?? ''));
        $phone   = trim((string) ($args['phone'] ?? '')) ?: null;
        $subject = trim((string) ($args['subject'] ?? '')) ?: null;
        $message = trim((string) ($args['message'] ?? ''));

        if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || $message === '') {
            return ['ok' => false, 'error' => 'Need name, valid email, and message.'];
        }
        if (function_exists('throttle') && !throttle('aria:contact:' . md5($email), 3, 600)) {
            return ['ok' => false, 'error' => 'Too many submissions in a short window. Try again shortly.'];
        }

        db()->prepare(
            "INSERT INTO contact_leads (name, email, phone, subject, message, source)
             VALUES (:n, :e, :p, :s, :m, 'aria')"
        )->execute([
            ':n' => $name, ':e' => $email, ':p' => $phone, ':s' => $subject, ':m' => $message,
        ]);
        $id = (int) db()->lastInsertId();
        audit_log('aria.contact_lead', 'contact_leads', $id);
        return ['ok' => true, 'lead_id' => $id, 'message' => 'Contact enquiry recorded. The team will reply by email within 1–2 business days.'];
    }

    function aria_tool_submit_corporate_inquiry(array $args): array
    {
        $company = trim((string) ($args['company_name']  ?? ''));
        $cname   = trim((string) ($args['contact_name']  ?? ''));
        $cemail  = trim((string) ($args['contact_email'] ?? ''));
        $cphone  = trim((string) ($args['contact_phone'] ?? '')) ?: null;
        $size    = trim((string) ($args['team_size']     ?? '')) ?: null;
        $interest= trim((string) ($args['interest']      ?? '')) ?: null;
        $message = trim((string) ($args['message']       ?? '')) ?: null;

        if ($company === '' || $cname === '' || !filter_var($cemail, FILTER_VALIDATE_EMAIL)) {
            return ['ok' => false, 'error' => 'Need company name, contact name, and a valid contact email.'];
        }
        if (function_exists('throttle') && !throttle('aria:corp:' . md5($cemail), 3, 600)) {
            return ['ok' => false, 'error' => 'Too many submissions in a short window. Try again shortly.'];
        }

        db()->prepare(
            "INSERT INTO corporate_inquiries
                 (company_name, contact_name, contact_email, contact_phone, team_size, interest, message)
             VALUES (:co, :n, :e, :p, :t, :i, :m)"
        )->execute([
            ':co' => $company, ':n' => $cname, ':e' => $cemail, ':p' => $cphone,
            ':t' => $size, ':i' => $interest, ':m' => $message,
        ]);
        $id = (int) db()->lastInsertId();
        audit_log('aria.corporate_inquiry', 'corporate_inquiries', $id);
        return ['ok' => true, 'inquiry_id' => $id, 'message' => 'Corporate enquiry recorded. We will be in touch within 1–2 business days.'];
    }
}
