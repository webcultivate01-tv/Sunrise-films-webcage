-- ---------------------------------------------------------------------------
-- LOGIN_ATTEMPTS TABLE
-- Counts sign-in and forgot-password tries so the app can slow down anyone
-- guessing passwords.
-- ---------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS login_attempts (
    id            BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    attempt_key   VARCHAR(190) NOT NULL,
    ip_address    VARCHAR(45),
    attempted_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    INDEX (attempt_key, attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
