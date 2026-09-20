-- ---------------------------------------------------------------------------
-- PROJECTS TABLE
-- Work Management. Every project belongs to one photographer and names that
-- photographer's own client in `customer_name` - the people the shoot is
-- actually for. That is what identifies a project; there is no separate
-- project name. `folder_name` is just a text label (e.g. a shared-drive
-- folder name) - the app does not store or manage any files itself.
-- ---------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS projects (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    photographer_id INT UNSIGNED NOT NULL,
    customer_name   VARCHAR(150) NOT NULL,
    description     TEXT NOT NULL,
    folder_name     VARCHAR(190) NOT NULL,
    deadline        DATE NOT NULL,
    total_payment   DECIMAL(12,2) NOT NULL DEFAULT 0.00,
    status          ENUM('pending', 'in_progress', 'completed', 'cancelled') NOT NULL DEFAULT 'pending',
    created_by      INT UNSIGNED,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX (photographer_id),
    INDEX (customer_name),
    INDEX (status),
    INDEX (created_by),
    FOREIGN KEY (photographer_id) REFERENCES photographers(id) ON DELETE RESTRICT,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
