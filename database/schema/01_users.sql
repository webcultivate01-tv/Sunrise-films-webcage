-- ---------------------------------------------------------------------------
-- USERS TABLE
-- One row per person who can log in: admin, manager, or employee.
-- Nobody signs up on their own - an admin creates managers, a manager
-- creates employees. The very first admin comes from database/seed.php.
-- ---------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS users (
    id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name           VARCHAR(120) NOT NULL,
    email          VARCHAR(190) NOT NULL UNIQUE,
    phone          VARCHAR(30),
    address        VARCHAR(500),
    photo          VARCHAR(255),
    password_hash  VARCHAR(255) NOT NULL,
    role           ENUM('admin', 'manager', 'employee') NOT NULL,
    status         ENUM('active', 'inactive', 'suspended') NOT NULL DEFAULT 'active',
    created_by     INT UNSIGNED,
    last_login_at  DATETIME,
    created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX (role, status),
    INDEX (created_by),
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
