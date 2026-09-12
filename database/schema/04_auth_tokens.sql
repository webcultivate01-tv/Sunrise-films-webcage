-- ---------------------------------------------------------------------------
-- AUTH_TOKENS TABLE
-- Keeps people logged in between visits ("remember me"). We never store the
-- real token - only a hash of it - so a database leak alone cannot be used
-- to sign in as someone else.
-- ---------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS auth_tokens (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id         INT UNSIGNED NOT NULL,
    role            ENUM('admin', 'manager', 'employee') NOT NULL,
    selector        CHAR(32) NOT NULL UNIQUE,
    validator_hash  CHAR(64) NOT NULL,
    ip_address      VARCHAR(45),
    user_agent      VARCHAR(255),
    expires_at      DATETIME NOT NULL,
    revoked_at      DATETIME,
    last_used_at    DATETIME,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    INDEX (user_id),
    INDEX (expires_at),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
