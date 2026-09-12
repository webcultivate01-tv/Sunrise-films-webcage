-- ---------------------------------------------------------------------------
-- PAYMENTS TABLE
-- Payment Management. One row per payment received against a project - a
-- permanent transaction record that is never edited or overwritten (module
-- spec: "previous payment records should never be overwritten"). A project's
-- collected/outstanding figures and payment status are always recomputed
-- from these rows rather than stored anywhere, the same approach Monthly
-- Salary takes with task_salary_credits / salary_settlements.
-- ---------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS payments (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    project_id      INT UNSIGNED NOT NULL,
    amount          DECIMAL(12,2) NOT NULL,
    payment_type    ENUM('advance', 'milestone', 'partial', 'final', 'other') NOT NULL DEFAULT 'other',
    payment_method  ENUM('cash', 'bank_transfer', 'upi', 'cheque', 'card', 'other') NOT NULL DEFAULT 'other',
    reference_no    VARCHAR(60) NULL DEFAULT NULL,
    payment_date    DATE NOT NULL,
    notes           VARCHAR(500) NULL DEFAULT NULL,
    received_by     INT UNSIGNED NULL DEFAULT NULL,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    INDEX (project_id),
    INDEX (payment_type),
    INDEX (payment_method),
    INDEX (payment_date),
    INDEX (received_by),
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE RESTRICT,
    FOREIGN KEY (received_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
