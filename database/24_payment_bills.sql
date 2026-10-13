-- ---------------------------------------------------------------------------
-- 24 PAYMENT BILLS - the frozen receipt for every payment
--
-- A bill is generated once, at the moment a payment is recorded (Payment
-- Management's Record Payment, or the advance / full payment collected while
-- adding a work in Work Management), and from then on it is the record: every
-- figure and name printed on it is copied in here and never read back from
-- projects / photographers / settings again. Editing the project's total,
-- renaming the photographer or changing the company profile later does not
-- alter a bill that has already been issued, and there is no update path in
-- the application - a correction is a new payment with its own new bill.
--
-- Existing payments are backfilled below from what is on file today, with
-- "collected to date" worked out as the running total up to that payment.
--
-- Safe to run more than once.
-- ---------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS payment_bills (
    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    payment_id          INT UNSIGNED NOT NULL,
    reference           VARCHAR(20) NOT NULL,
    bill_date           DATE NOT NULL,

    photographer_name   VARCHAR(150) NULL DEFAULT NULL,
    photographer_phone  VARCHAR(40) NULL DEFAULT NULL,
    photographer_email  VARCHAR(190) NULL DEFAULT NULL,
    customer_name       VARCHAR(150) NULL DEFAULT NULL,

    payment_type        VARCHAR(20) NOT NULL,
    payment_method      VARCHAR(20) NOT NULL,
    amount              DECIMAL(12,2) NOT NULL,
    project_total       DECIMAL(12,2) NOT NULL,
    collected_to_date   DECIMAL(12,2) NOT NULL,
    outstanding         DECIMAL(12,2) NOT NULL,
    notes               VARCHAR(500) NULL DEFAULT NULL,

    company_name        VARCHAR(150) NOT NULL,
    company_address     VARCHAR(500) NOT NULL DEFAULT '',
    company_phone       VARCHAR(60) NOT NULL DEFAULT '',
    company_email       VARCHAR(190) NOT NULL DEFAULT '',
    company_website     VARCHAR(190) NOT NULL DEFAULT '',

    created_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    UNIQUE KEY uq_payment_bills_payment (payment_id),
    FOREIGN KEY (payment_id) REFERENCES payments(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Backfill: one bill for every payment that does not have one yet.
INSERT INTO payment_bills (
    payment_id, reference, bill_date,
    photographer_name, photographer_phone, photographer_email, customer_name,
    payment_type, payment_method, amount, project_total, collected_to_date, outstanding, notes,
    company_name, company_address, company_phone, company_email, company_website
)
SELECT
    t.id,
    CONCAT('PAY-', LPAD(t.id, 4, '0')),
    t.payment_date,
    c.name, c.phone, c.email, proj.customer_name,
    t.payment_type, t.payment_method, t.amount, proj.total_payment,
    t.running_total,
    GREATEST(proj.total_payment - t.running_total, 0),
    t.notes,
    COALESCE(s.company_name, 'Sunrise Films'),
    COALESCE(s.company_address, ''), COALESCE(s.company_phone, ''),
    COALESCE(s.company_email, ''), COALESCE(s.company_website, '')
  FROM (
        SELECT pay.*,
               SUM(pay.amount) OVER (PARTITION BY pay.project_id ORDER BY pay.payment_date, pay.id) AS running_total
          FROM payments pay
       ) t
  JOIN projects proj ON proj.id = t.project_id
  LEFT JOIN photographers c ON c.id = proj.photographer_id
  LEFT JOIN settings s ON s.id = 1
 WHERE NOT EXISTS (SELECT 1 FROM payment_bills b WHERE b.payment_id = t.id);
