-- ---------------------------------------------------------------------------
-- SALARY_SETTLEMENTS TABLE
-- Monthly Salary spec: one row per confirmed settlement/payout, permanent and
-- never edited after creation - the amounts here are a frozen snapshot of the
-- employee's salary at the moment of settlement (spec s9, s18), so a bill
-- generated from this row never changes even if the employee's salary later
-- does. idempotency_key is what stops a duplicate click or a resubmitted
-- form from creating a second settlement for the same confirmation (spec s18).
-- ---------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS salary_settlements (
    id                       INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    employee_id              INT UNSIGNED NOT NULL,
    salary_month             CHAR(7) NOT NULL,
    amount                   DECIMAL(12,2) NOT NULL,
    month_earned             DECIMAL(12,2) NOT NULL,
    previous_settled         DECIMAL(12,2) NOT NULL,
    outstanding_after        DECIMAL(12,2) NOT NULL,
    reference_no             VARCHAR(20) NULL DEFAULT NULL,
    idempotency_key          VARCHAR(64) NULL DEFAULT NULL,
    employee_name_snapshot   VARCHAR(120) NOT NULL,
    employee_email_snapshot  VARCHAR(190) NOT NULL,
    employee_phone_snapshot  VARCHAR(30) NULL DEFAULT NULL,
    notes                    VARCHAR(500) NULL DEFAULT NULL,
    settled_by               INT UNSIGNED NULL DEFAULT NULL,
    settled_at               DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    UNIQUE KEY uq_salary_settlements_reference (reference_no),
    UNIQUE KEY uq_salary_settlements_idempotency (idempotency_key),
    INDEX (employee_id),
    INDEX (salary_month),
    INDEX (settled_by),
    FOREIGN KEY (employee_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (settled_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
