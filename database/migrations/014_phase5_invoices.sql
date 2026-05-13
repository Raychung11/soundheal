-- =====================================================
-- Phase 5 follow-up: invoices + receipts
-- One row per document. Each invoice/receipt snapshots the company +
-- customer details + line items at issue time, so reprinting later
-- yields the same document even after the company address / brand
-- has changed.
-- =====================================================

CREATE TABLE IF NOT EXISTS invoices (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    -- Document identity
    doc_type ENUM('invoice','receipt') NOT NULL,
    doc_number VARCHAR(40) DEFAULT NULL,           -- e.g. INV-2026-000123 / RCP-2026-000123
    access_token CHAR(40) NOT NULL UNIQUE,         -- so a link in email works without login

    -- What it bills
    user_id INT UNSIGNED NOT NULL,
    purpose ENUM('booking','membership','other') NOT NULL DEFAULT 'booking',
    reference_id INT UNSIGNED DEFAULT NULL,        -- event_bookings.id or memberships.id
    payment_id  INT UNSIGNED DEFAULT NULL,         -- payments.id once paid
    invoice_id  INT UNSIGNED DEFAULT NULL,         -- on receipts: link back to the invoice

    -- Amounts
    subtotal DECIMAL(10,2) NOT NULL DEFAULT 0,
    tax      DECIMAL(10,2) NOT NULL DEFAULT 0,
    total    DECIMAL(10,2) NOT NULL DEFAULT 0,
    currency VARCHAR(8)   NOT NULL DEFAULT 'MYR',

    -- Lifecycle
    status ENUM('due','paid','void','refunded') NOT NULL DEFAULT 'due',
    issued_at  DATETIME DEFAULT CURRENT_TIMESTAMP,
    paid_at    DATETIME DEFAULT NULL,

    -- Snapshots (JSON)
    line_items        JSON DEFAULT NULL,
    customer_snapshot JSON DEFAULT NULL,
    company_snapshot  JSON DEFAULT NULL,
    notes             TEXT DEFAULT NULL,

    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY uniq_doc_number (doc_number),
    INDEX idx_invoices_user (user_id),
    INDEX idx_invoices_purpose (purpose, reference_id),
    INDEX idx_invoices_status (status),
    INDEX idx_invoices_payment (payment_id),
    INDEX idx_invoices_invoice (invoice_id),
    CONSTRAINT fk_invoices_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
