-- ---------------------------------------------------------------------------
-- TASK_SALARY_CREDITS TABLE
-- Task Management -> Monthly Salary. One row per completed task, recording
-- the amount that became salary-eligible for the employee who completed it.
-- The unique key on task_id is what makes crediting idempotent: a task can
-- only ever create one credit, no matter how many times "Mark as Completed"
-- is submitted (module spec s7, s17).
-- ---------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS task_salary_credits (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    task_id     INT UNSIGNED NOT NULL,
    employee_id INT UNSIGNED NOT NULL,
    project_id  INT UNSIGNED NOT NULL,
    amount      DECIMAL(12,2) NOT NULL,
    credited_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    UNIQUE KEY uq_task_salary_credits_task (task_id),
    INDEX (employee_id),
    FOREIGN KEY (task_id) REFERENCES tasks(id) ON DELETE CASCADE,
    FOREIGN KEY (employee_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
