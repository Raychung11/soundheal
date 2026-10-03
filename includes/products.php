<?php
declare(strict_types=1);

/**
 * Ecommerce helpers — products, session cart, order creation.
 *
 * Cart lives in $_SESSION['cart'] as [productId => qty]. Guest browsing
 * is allowed, but checkout requires a logged-in user (same rule as
 * bookings). At checkout we snapshot title + unit price into
 * order_items so a later product price change doesn't retroactively
 * repricre a placed order.
 *
 * Pre-order handling: any item with is_preorder=1 flips the order's
 * has_preorder flag, and settle_payment() will land the order in the
 * 'preorder' status (funds cleared, waiting for stock) instead of
 * jumping straight to 'paid'. Admins move it forward from there.
 */

if (!function_exists('product_get_active')) {

    function product_get_active(int $id): ?array
    {
        $stmt = db()->prepare(
            "SELECT * FROM products WHERE id = :id AND status = 'published' LIMIT 1"
        );
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    function product_get_by_slug(string $slug): ?array
    {
        $stmt = db()->prepare(
            "SELECT * FROM products WHERE slug = :s AND status = 'published' LIMIT 1"
        );
        $stmt->execute([':s' => $slug]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    /**
     * List published products for the public shop grid. Optional
     * category filter matches on the free-text category column.
     */
    function products_list_published(?string $category = null): array
    {
        if ($category !== null && $category !== '') {
            $stmt = db()->prepare(
                "SELECT * FROM products
                  WHERE status = 'published' AND category = :c
                  ORDER BY sort_order ASC, id DESC"
            );
            $stmt->execute([':c' => $category]);
        } else {
            $stmt = db()->query(
                "SELECT * FROM products
                  WHERE status = 'published'
                  ORDER BY sort_order ASC, id DESC"
            );
        }
        return $stmt->fetchAll();
    }

    function product_categories_active(): array
    {
        $stmt = db()->query(
            "SELECT category, COUNT(*) AS n
               FROM products
              WHERE status = 'published' AND category IS NOT NULL AND category <> ''
              GROUP BY category
              ORDER BY category"
        );
        return $stmt->fetchAll();
    }

    // ---- Cart (session-backed) ----------------------------------------

    function cart_raw(): array
    {
        if (!isset($_SESSION['cart']) || !is_array($_SESSION['cart'])) {
            $_SESSION['cart'] = [];
        }
        return $_SESSION['cart'];
    }

    function cart_count(): int
    {
        $n = 0;
        foreach (cart_raw() as $qty) {
            $n += (int) $qty;
        }
        return $n;
    }

    function cart_add(int $productId, int $qty = 1): void
    {
        if ($qty < 1) return;
        $product = product_get_active($productId);
        if (!$product) return;

        cart_raw();
        $cur = (int) ($_SESSION['cart'][$productId] ?? 0);
        $target = $cur + $qty;

        // Live-stock item: cap at stock_qty. Preorder items are uncapped.
        if (!(int) $product['is_preorder']) {
            $target = min($target, max(0, (int) $product['stock_qty']));
        }
        $target = max(0, min(99, $target));

        if ($target === 0) {
            unset($_SESSION['cart'][$productId]);
        } else {
            $_SESSION['cart'][$productId] = $target;
        }
    }

    function cart_set(int $productId, int $qty): void
    {
        cart_raw();
        if ($qty < 1) {
            unset($_SESSION['cart'][$productId]);
            return;
        }
        $product = product_get_active($productId);
        if (!$product) {
            unset($_SESSION['cart'][$productId]);
            return;
        }
        if (!(int) $product['is_preorder']) {
            $qty = min($qty, max(0, (int) $product['stock_qty']));
        }
        $qty = max(0, min(99, $qty));
        if ($qty === 0) {
            unset($_SESSION['cart'][$productId]);
        } else {
            $_SESSION['cart'][$productId] = $qty;
        }
    }

    function cart_remove(int $productId): void
    {
        cart_raw();
        unset($_SESSION['cart'][$productId]);
    }

    function cart_clear(): void
    {
        $_SESSION['cart'] = [];
    }

    /**
     * Resolve the cart into fully-hydrated rows the checkout page can
     * render. Skips any product that is no longer published (drops it
     * silently from the session).
     *
     * Returns:
     *   [
     *     'items' => [ [product row + qty + line_total + is_preorder], ... ],
     *     'subtotal' => float,
     *     'has_preorder' => bool,
     *     'count' => int (total qty across all lines),
     *   ]
     */
    function cart_hydrate(): array
    {
        $out = ['items' => [], 'subtotal' => 0.0, 'has_preorder' => false, 'count' => 0];
        $raw = cart_raw();
        if (!$raw) return $out;

        $ids = array_map('intval', array_keys($raw));
        $ph  = implode(',', array_fill(0, count($ids), '?'));
        $stmt = db()->prepare(
            "SELECT * FROM products WHERE id IN ($ph) AND status = 'published'"
        );
        $stmt->execute($ids);
        $rows = $stmt->fetchAll();

        $byId = [];
        foreach ($rows as $r) $byId[(int) $r['id']] = $r;

        foreach ($raw as $pid => $qty) {
            $pid = (int) $pid;
            $qty = (int) $qty;
            if (!isset($byId[$pid])) {
                unset($_SESSION['cart'][$pid]);
                continue;
            }
            $p = $byId[$pid];
            if (!(int) $p['is_preorder']) {
                $qty = min($qty, max(0, (int) $p['stock_qty']));
                if ($qty === 0) {
                    unset($_SESSION['cart'][$pid]);
                    continue;
                }
                $_SESSION['cart'][$pid] = $qty;
            }
            $lineTotal = round((float) $p['price'] * $qty, 2);
            $out['items'][] = array_merge($p, [
                'qty'        => $qty,
                'line_total' => $lineTotal,
            ]);
            $out['subtotal'] += $lineTotal;
            $out['count']    += $qty;
            if ((int) $p['is_preorder']) {
                $out['has_preorder'] = true;
            }
        }
        $out['subtotal'] = round($out['subtotal'], 2);
        return $out;
    }

    // ---- Order creation -----------------------------------------------

    function _order_ref_generate(): string
    {
        return 'SH-ORD-' . strtoupper(bin2hex(random_bytes(4)));
    }

    /**
     * Create a pending order from the current cart. Runs inside a
     * transaction, snapshots title + unit price + preorder ETA onto
     * each order_items row so the invoice is stable even after the
     * product is edited or deleted.
     *
     * $shipTo shape (any subset ok — the whole thing is snapshotted):
     *   [
     *     'name' => 'Jane Doe',
     *     'phone' => '+60123456789',
     *     'address_line1' => '...',
     *     'address_line2' => '...',
     *     'city' => 'Kuala Lumpur',
     *     'postcode' => '50000',
     *     'country' => 'Malaysia',
     *     'notes' => 'gate code 1234',
     *   ]
     *
     * Returns the new order id, or 0 if the cart was empty / invalid.
     * Does NOT clear the cart — the caller does that only after a
     * successful payment redirect so a failed Billplz call still shows
     * the user their items.
     */
    function checkout_create_order(int $userId, array $shipTo): int
    {
        $cart = cart_hydrate();
        if (empty($cart['items'])) return 0;

        $ref = _order_ref_generate();
        $shipping = 0.0; // flat / TBD — admin invoices manually for now
        $tax      = 0.0;
        $subtotal = (float) $cart['subtotal'];
        $total    = round($subtotal + $shipping + $tax, 2);

        db()->beginTransaction();
        try {
            $ins = db()->prepare(
                "INSERT INTO orders
                    (order_ref, user_id, ship_to_snapshot,
                     subtotal, shipping, tax, total, currency,
                     status, has_preorder)
                 VALUES
                    (:ref, :u, :ship,
                     :sub, :sh, :tax, :tot, :cur,
                     'pending', :hp)"
            );
            $ins->execute([
                ':ref'  => $ref,
                ':u'    => $userId,
                ':ship' => json_encode($shipTo, JSON_UNESCAPED_UNICODE),
                ':sub'  => $subtotal,
                ':sh'   => $shipping,
                ':tax'  => $tax,
                ':tot'  => $total,
                ':cur'  => 'MYR',
                ':hp'   => $cart['has_preorder'] ? 1 : 0,
            ]);
            $orderId = (int) db()->lastInsertId();

            $item = db()->prepare(
                "INSERT INTO order_items
                    (order_id, product_id, title_snapshot, unit_price,
                     quantity, amount, is_preorder, preorder_eta)
                 VALUES
                    (:o, :pid, :t, :up, :q, :amt, :pre, :eta)"
            );
            foreach ($cart['items'] as $row) {
                $item->execute([
                    ':o'   => $orderId,
                    ':pid' => (int) $row['id'],
                    ':t'   => (string) $row['title'],
                    ':up'  => (float) $row['price'],
                    ':q'   => (int) $row['qty'],
                    ':amt' => (float) $row['line_total'],
                    ':pre' => (int) $row['is_preorder'] ? 1 : 0,
                    ':eta' => (int) $row['is_preorder'] ? ($row['preorder_eta'] ?? null) : null,
                ]);
            }

            db()->commit();
            if (function_exists('audit_log')) {
                audit_log('order.create', 'orders', $orderId, [
                    'ref' => $ref, 'total' => $total, 'preorder' => $cart['has_preorder'],
                ]);
            }
            return $orderId;
        } catch (Throwable $e) {
            db()->rollBack();
            error_log('[products] checkout_create_order failed: ' . $e->getMessage());
            return 0;
        }
    }

    /**
     * Fetch an order + its items (owner-scoped: caller passes user id
     * so a member can only see their own). Pass $userId = 0 for the
     * admin path.
     */
    function order_get(int $orderId, int $userId = 0): ?array
    {
        if ($userId > 0) {
            $stmt = db()->prepare("SELECT * FROM orders WHERE id = :id AND user_id = :u LIMIT 1");
            $stmt->execute([':id' => $orderId, ':u' => $userId]);
        } else {
            $stmt = db()->prepare("SELECT * FROM orders WHERE id = :id LIMIT 1");
            $stmt->execute([':id' => $orderId]);
        }
        $order = $stmt->fetch();
        if (!$order) return null;

        $items = db()->prepare("SELECT * FROM order_items WHERE order_id = :o ORDER BY id");
        $items->execute([':o' => $orderId]);
        $order['items'] = $items->fetchAll();
        return $order;
    }

    /**
     * Decrement stock for every non-preorder line on a paid order.
     * Called by settle_payment() when the order flips to paid/preorder,
     * so we only reserve stock for items we actually took money for.
     * Wrapped in a transaction; safe to call repeatedly (would double
     * up if not for the guard in settle_payment, which only invokes
     * this on the first settle).
     */
    function decrement_stock_for_order(int $orderId): void
    {
        $items = db()->prepare(
            "SELECT product_id, quantity, is_preorder FROM order_items WHERE order_id = :o"
        );
        $items->execute([':o' => $orderId]);
        $stmt = db()->prepare(
            "UPDATE products
                SET stock_qty = GREATEST(0, stock_qty - :q)
              WHERE id = :id"
        );
        foreach ($items->fetchAll() as $row) {
            if ((int) $row['is_preorder']) continue;
            if (!$row['product_id']) continue;
            $stmt->execute([':q' => (int) $row['quantity'], ':id' => (int) $row['product_id']]);
        }
    }

    /**
     * Line items for the order's invoice/receipt. Mirrors the shape
     * expected by issue_invoice() / build_booking_line_items().
     */
    function build_order_line_items(array $order): array
    {
        $items = $order['items'] ?? [];
        $out = [];
        foreach ($items as $it) {
            $out[] = [
                'description' => (string) $it['title_snapshot'],
                'subtext'     => (int) $it['is_preorder']
                    ? ('Pre-order · ' . ((string) ($it['preorder_eta'] ?? 'ships when available')))
                    : '',
                'quantity'    => (int) $it['quantity'],
                'unit_price'  => (float) $it['unit_price'],
                'amount'      => (float) $it['amount'],
            ];
        }
        return $out;
    }
}
