-- =====================================================
-- Phase 7: Auto revenue-split ledger (company / IT partner)
--
--   Every settled payment is automatically recorded as an 85/15
--   split (configurable) into revenue_splits. The split is an
--   accounting ledger — money still lands in the company account;
--   the admin tracks the partner's running balance and records
--   payouts in partner_payouts. Refunds reverse the matching split.
--
--   Splits are computed on the GROSS amount and only for payments
--   settled on/after revenue_split_start_date (the cutover), so
--   past revenue is never retroactively split.
-- =====================================================

CREATE TABLE IF NOT EXISTS revenue_splits (
    id                    INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    payment_id            INT UNSIGNED NOT NULL,
    purpose               VARCHAR(40)  NOT NULL DEFAULT 'other',
    basis                 ENUM('gross','net') NOT NULL DEFAULT 'gross',
    gross_amount          DECIMAL(10,2) NOT NULL,
    basis_amount          DECIMAL(10,2) NOT NULL,
    currency              VARCHAR(8)   NOT NULL DEFAULT 'MYR',
    company_pct           DECIMAL(5,2) NOT NULL,
    partner_pct           DECIMAL(5,2) NOT NULL,
    company_amount        DECIMAL(10,2) NOT NULL,
    partner_amount        DECIMAL(10,2) NOT NULL,
    status                ENUM('active','reversed') NOT NULL DEFAULT 'active',
    partner_payout_status ENUM('unpaid','paid') NOT NULL DEFAULT 'unpaid',
    partner_payout_id     INT UNSIGNED DEFAULT NULL,
    reversed_at           DATETIME DEFAULT NULL,
    note                  VARCHAR(255) DEFAULT NULL,
    created_at            DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_split_payment (payment_id),
    INDEX idx_split_status (status, partner_payout_status),
    INDEX idx_split_payout (partner_payout_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS partner_payouts (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    amount      DECIMAL(10,2) NOT NULL,
    currency    VARCHAR(8)   NOT NULL DEFAULT 'MYR',
    split_count INT UNSIGNED NOT NULL DEFAULT 0,
    reference   VARCHAR(160) DEFAULT NULL,
    paid_by     INT UNSIGNED DEFAULT NULL,
    created_at  DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Config (insert-only: admin edits persist across re-runs).
-- Start date defaults to the day this migration is applied — the cutover.
INSERT INTO site_settings (`key`, `value`, `value_type`) VALUES
    ('revenue_split_enabled',       '1',          'bool'),
    ('revenue_split_partner_pct',   '15',         'string'),
    ('revenue_split_partner_label', 'IT partner', 'string'),
    ('revenue_split_company_label', 'Company',    'string'),
    ('revenue_split_start_date',    DATE_FORMAT(NOW(), '%Y-%m-%d'), 'string')
ON DUPLICATE KEY UPDATE `key` = `key`;
