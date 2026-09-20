-- ---------------------------------------------------------------------------
-- TASK_DESCRIPTIONS TABLE
-- The running description thread on a task. Photographers send fresh
-- instructions for the same piece of work again and again, so an Admin or
-- Manager can append a new description at any time instead of overwriting
-- the old one: every round is kept, in order, with who sent it and when.
--
-- The task's opening description is row one of its own thread, so the whole
-- brief reads as a single list. `tasks.description` still holds that opening
-- text (search and the task forms use it) and is never rewritten.
-- ---------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS task_descriptions (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    task_id    INT UNSIGNED NOT NULL,
    body       TEXT NOT NULL,
    created_by INT UNSIGNED,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    INDEX (task_id),
    INDEX (created_by),
    FOREIGN KEY (task_id) REFERENCES tasks(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
