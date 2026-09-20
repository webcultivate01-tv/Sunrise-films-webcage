-- ---------------------------------------------------------------------------
-- TASKS TABLE
-- Task Management. Every task belongs to one project and is assigned to one
-- employee at a time. When an employee exits a task it is not deleted - its
-- status becomes 'exited' and it can be reassigned, which creates a brand new
-- task row (linked back via parent_task_id) so the original assignment's
-- history is never overwritten.
-- ---------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS tasks (
    id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    project_id     INT UNSIGNED NOT NULL,
    employee_id    INT UNSIGNED NOT NULL,
    title          VARCHAR(150) NOT NULL,
    description    TEXT NOT NULL,
    start_date     DATE NOT NULL,
    end_date       DATE NOT NULL,
    priority       ENUM('high', 'medium', 'low') NOT NULL DEFAULT 'medium',
    amount         DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    status         ENUM('assigned', 'accepted', 'in_progress', 'completed', 'exited', 'reassigned')
                   NOT NULL DEFAULT 'assigned',
    progress       TINYINT UNSIGNED NOT NULL DEFAULT 0,
    parent_task_id INT UNSIGNED DEFAULT NULL,
    accepted_at    DATETIME DEFAULT NULL,
    completed_at   DATETIME DEFAULT NULL,
    exited_at      DATETIME DEFAULT NULL,
    created_by     INT UNSIGNED,
    created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX (project_id),
    INDEX (employee_id),
    INDEX (status),
    INDEX (priority),
    INDEX (created_by),
    INDEX (parent_task_id),
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE RESTRICT,
    FOREIGN KEY (employee_id) REFERENCES users(id) ON DELETE RESTRICT,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (parent_task_id) REFERENCES tasks(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
