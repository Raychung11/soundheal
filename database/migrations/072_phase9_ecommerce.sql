-- =====================================================
-- Phase 9: Ecommerce — products + orders + order items.
--
-- Most products are pre-order; the schema treats stock and pre-order
-- as orthogonal flags so a live-stock item ("we ship next week") and
-- a preorder item ("ships in Sept") coexist.
--
-- Payments reuse the existing Billplz pipeline; the payments.purpose
-- ENUM gains 'order' and settle_payment() flips orders.status via
-- includes/payments.php.
-- =====================================================

CREATE TABLE IF NOT EXISTS products (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    slug          VARCHAR(80)  NOT NULL,
    title         VARCHAR(180) NOT NULL,
    subtitle      VARCHAR(255) DEFAULT NULL,
    description   TEXT DEFAULT NULL,
    price         DECIMAL(10,2) NOT NULL DEFAULT 0,
    currency      VARCHAR(8) NOT NULL DEFAULT 'MYR',
    sku           VARCHAR(80) DEFAULT NULL,
    stock_qty     INT NOT NULL DEFAULT 0,
    is_preorder   TINYINT(1) NOT NULL DEFAULT 0,
    preorder_eta  VARCHAR(120) DEFAULT NULL,  -- free-text: "Ships mid-August"
    cover_image   VARCHAR(255) DEFAULT NULL,
    weight_grams  INT UNSIGNED DEFAULT NULL,   -- for future shipping calc
    category      VARCHAR(80) DEFAULT NULL,
    status        ENUM('draft','published','archived') NOT NULL DEFAULT 'draft',
    sort_order    INT NOT NULL DEFAULT 100,
    created_by    INT UNSIGNED DEFAULT NULL,
    created_at    DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at    DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_products_slug (slug),
    INDEX idx_products_status_sort (status, sort_order, id),
    INDEX idx_products_category (category, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS orders (
    id                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_ref         VARCHAR(40) NOT NULL,
    user_id           INT UNSIGNED NOT NULL,
    ship_to_snapshot  JSON DEFAULT NULL,
    subtotal          DECIMAL(10,2) NOT NULL DEFAULT 0,
    shipping          DECIMAL(10,2) NOT NULL DEFAULT 0,
    tax               DECIMAL(10,2) NOT NULL DEFAULT 0,
    total             DECIMAL(10,2) NOT NULL DEFAULT 0,
    currency          VARCHAR(8) NOT NULL DEFAULT 'MYR',
    -- Lifecycle:
    --   pending    → invoice sent, awaiting Billplz confirmation
    --   paid       → funds cleared, no preorder items
    --   preorder   → funds cleared, at least one preorder item, waiting
    --                on stock. Admin flips to packed once ready.
    --   packed     → picked and packed, waiting for courier
    --   shipped    → tracking number stamped, courier collected
    --   delivered  → confirmed received
    --   cancelled  → cancelled before payment
    --   refunded   → refunded after payment
    status            ENUM('pending','paid','preorder','packed','shipped','delivered','cancelled','refunded') NOT NULL DEFAULT 'pending',
    has_preorder      TINYINT(1) NOT NULL DEFAULT 0,
    notes             TEXT DEFAULT NULL,
    tracking_courier  VARCHAR(80) DEFAULT NULL,
    tracking_number   VARCHAR(120) DEFAULT NULL,
    payment_id        INT UNSIGNED DEFAULT NULL,
    paid_at           DATETIME DEFAULT NULL,
    shipped_at        DATETIME DEFAULT NULL,
    delivered_at      DATETIME DEFAULT NULL,
    created_at        DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at        DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_orders_ref (order_ref),
    INDEX idx_orders_user (user_id, created_at),
    INDEX idx_orders_status (status, created_at),
    CONSTRAINT fk_orders_user FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS order_items (
    id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_id       INT UNSIGNED NOT NULL,
    product_id     INT UNSIGNED DEFAULT NULL,   -- SET NULL on product delete
    title_snapshot VARCHAR(255) NOT NULL,
    unit_price     DECIMAL(10,2) NOT NULL DEFAULT 0,
    quantity       INT NOT NULL DEFAULT 1,
    amount         DECIMAL(10,2) NOT NULL DEFAULT 0,
    is_preorder    TINYINT(1) NOT NULL DEFAULT 0,
    preorder_eta   VARCHAR(120) DEFAULT NULL,
    created_at     DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_order_items_order (order_id),
    INDEX idx_order_items_product (product_id),
    CONSTRAINT fk_order_items_order   FOREIGN KEY (order_id)   REFERENCES orders(id)   ON DELETE CASCADE,
    CONSTRAINT fk_order_items_product FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Extend the payments purpose so settle_payment() can dispatch on
-- 'order' and flip the matching orders row.
ALTER TABLE payments
    MODIFY COLUMN purpose ENUM('membership','booking','class_pack','order','other') NOT NULL DEFAULT 'booking';

-- Extend the invoices purpose to match, so issue_invoice() can log
-- shop orders alongside bookings and manual invoices.
ALTER TABLE invoices
    MODIFY COLUMN purpose ENUM('booking','membership','other','manual','order') NOT NULL DEFAULT 'booking';
