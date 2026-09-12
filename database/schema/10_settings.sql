-- ---------------------------------------------------------------------------
-- SETTINGS TABLE
-- One fixed row (id = 1) of system-level settings an Admin edits from
-- Admin Management: the company profile printed on bills, and the security
-- policy values that used to live only in .env. Defaults here match the ones
-- in config/config.php so a fresh install behaves the same either way.
-- ---------------------------------------------------------------------------

CREATE TABLE IF NOT EXISTS settings (
    id                             TINYINT UNSIGNED NOT NULL DEFAULT 1,
    company_name                   VARCHAR(150) NOT NULL DEFAULT 'Sunrise Films',
    company_address                VARCHAR(500) NOT NULL DEFAULT '',
    company_phone                  VARCHAR(30)  NOT NULL DEFAULT '',
    company_email                  VARCHAR(190) NOT NULL DEFAULT '',
    company_website                VARCHAR(190) NOT NULL DEFAULT '',
    password_min_length            TINYINT UNSIGNED NOT NULL DEFAULT 8,
    login_max_attempts             TINYINT UNSIGNED NOT NULL DEFAULT 5,
    login_lockout_minutes          SMALLINT UNSIGNED NOT NULL DEFAULT 15,
    admin_forgot_password_enabled  TINYINT(1) NOT NULL DEFAULT 0,
    updated_by                     INT UNSIGNED,
    updated_at                     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    CONSTRAINT chk_settings_single_row CHECK (id = 1),
    FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO settings (id) VALUES (1) ON DUPLICATE KEY UPDATE id = id;
