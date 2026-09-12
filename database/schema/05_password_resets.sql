-- ---------------------------------------------------------------------------
-- PASSWORD_RESETS TABLE
-- One "forgot password" link at a time. Same idea as auth_tokens: a
-- selector to look the row up, and a hash to check the link is genuine.
-- ---------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS password_resets (
    id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id         INT UNSIGNED NOT NULL,
    selector        CHAR(32) NOT NULL UNIQUE,
    validator_hash  CHAR(64) NOT NULL,
    requested_ip    VARCHAR(45),
    expires_at      DATETIME NOT NULL,
    used_at         DATETIME,
    created_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    INDEX (user_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
