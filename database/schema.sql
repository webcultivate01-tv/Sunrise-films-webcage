-- ---------------------------------------------------------------------------
-- Sunrise Films - authentication schema
-- Run with:  mysql -u root -p < database/schema.sql
-- ---------------------------------------------------------------------------

CREATE DATABASE IF NOT EXISTS `sunrise_films`
    DEFAULT CHARACTER SET utf8mb4
    DEFAULT COLLATE utf8mb4_unicode_ci;

USE `sunrise_films`;

-- ---------------------------------------------------------------------------
-- users
-- One row per system user. There is no public registration: every row is
-- created by an Admin (managers) or by a Manager (employees), except the
-- bootstrap Admin seeded below.
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `users` (
    `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name`           VARCHAR(120) NOT NULL,
    `email`          VARCHAR(190) NOT NULL,
    `phone`          VARCHAR(30)  NULL DEFAULT NULL,
    -- Filename only (e.g. `ab12...f9.jpg`), relative to public/uploads. NULL
    -- until the user sets one from their profile page.
    `photo`          VARCHAR(255) NULL DEFAULT NULL,
    `password_hash`  VARCHAR(255) NOT NULL,
    `role`           ENUM('admin','manager','employee') NOT NULL,
    `status`         ENUM('active','inactive','suspended') NOT NULL DEFAULT 'active',
    -- The user who created this account: Admin for managers, Manager for
    -- employees. NULL for the bootstrap Admin.
    `created_by`     INT UNSIGNED NULL DEFAULT NULL,
    `last_login_at`  DATETIME NULL DEFAULT NULL,
    `created_at`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_users_email` (`email`),
    KEY `idx_users_role_status` (`role`, `status`),
    KEY `idx_users_created_by` (`created_by`),
    CONSTRAINT `fk_users_created_by`
        FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- auth_tokens
-- Token-based authentication (spec s13). Tokens use a split selector/validator
-- design: the selector is the lookup key, only a SHA-256 hash of the validator
-- is stored, so a database leak cannot be replayed as a login.
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `auth_tokens` (
    `id`              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id`         INT UNSIGNED NOT NULL,
    `role`            ENUM('admin','manager','employee') NOT NULL,
    `selector`        CHAR(32) NOT NULL,
    `validator_hash`  CHAR(64) NOT NULL,
    `ip_address`      VARCHAR(45)  NULL DEFAULT NULL,
    `user_agent`      VARCHAR(255) NULL DEFAULT NULL,
    `expires_at`      DATETIME NOT NULL,
    `revoked_at`      DATETIME NULL DEFAULT NULL,
    `last_used_at`    DATETIME NULL DEFAULT NULL,
    `created_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_auth_tokens_selector` (`selector`),
    KEY `idx_auth_tokens_user` (`user_id`),
    KEY `idx_auth_tokens_expires` (`expires_at`),
    CONSTRAINT `fk_auth_tokens_user`
        FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- password_resets
-- Forgot Password authorisation (spec s8). Same selector/validator design as
-- auth tokens; single use, time limited.
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `password_resets` (
    `id`              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id`         INT UNSIGNED NOT NULL,
    `selector`        CHAR(32) NOT NULL,
    `validator_hash`  CHAR(64) NOT NULL,
    `requested_ip`    VARCHAR(45) NULL DEFAULT NULL,
    `expires_at`      DATETIME NOT NULL,
    `used_at`         DATETIME NULL DEFAULT NULL,
    `created_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_password_resets_selector` (`selector`),
    KEY `idx_password_resets_user` (`user_id`),
    CONSTRAINT `fk_password_resets_user`
        FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------------
-- login_attempts
-- Throttling for the login and forgot-password forms.
-- ---------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `login_attempts` (
    `id`           BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `attempt_key`  VARCHAR(190) NOT NULL,
    `ip_address`   VARCHAR(45) NULL DEFAULT NULL,
    `attempted_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_login_attempts_key_time` (`attempt_key`, `attempted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
