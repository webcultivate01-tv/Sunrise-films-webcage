-- ---------------------------------------------------------------------------
-- CUSTOMERS TABLE
-- Customer Management. Added by an admin or a manager - customers never
-- register themselves. Every admin and manager can see every customer.
-- ---------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS customers (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name        VARCHAR(120) NOT NULL,
    email       VARCHAR(190) NOT NULL UNIQUE,
    phone       VARCHAR(30) NOT NULL,
    address     VARCHAR(500) NOT NULL,
    status      ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    created_by  INT UNSIGNED,
    created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX (status),
    INDEX (created_by),
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
