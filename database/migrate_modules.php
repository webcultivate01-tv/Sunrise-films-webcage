<?php

declare(strict_types=1);

/**
 * Brings an existing install up to date with every module added after the
 * original schema: the `address`/`temporary_address` columns on `users`, and
 * the `photographers`, `projects`, `tasks`, `task_descriptions`,
 * `task_salary_credits`, `salary_settlements`, `payments` and `settings`
 * tables.
 *
 * Safe to run more than once.
 *
 * Usage:  php database/migrate_modules.php
 */

use App\Core\Config;
use App\Core\Database;

define('BASE_PATH', dirname(__DIR__));

require BASE_PATH . '/app/bootstrap.php';

try {
    Database::connection();
} catch (Throwable $e) {
    fwrite(STDERR, $e->getMessage() . PHP_EOL);
    exit(1);
}

// --- users.address ---------------------------------------------------------
$column = Database::selectOne(
    "SELECT COLUMN_NAME FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'address'",
);

if ($column !== null) {
    echo '`users`.`address` already exists - skipped.' . PHP_EOL;
} else {
    Database::statement('ALTER TABLE users ADD COLUMN address VARCHAR(500) NULL DEFAULT NULL AFTER phone');

    echo 'Added `address` to `users`.' . PHP_EOL;
}

// --- users.temporary_address -------------------------------------------------
$column = Database::selectOne(
    "SELECT COLUMN_NAME FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'temporary_address'",
);

if ($column !== null) {
    echo '`users`.`temporary_address` already exists - skipped.' . PHP_EOL;
} else {
    Database::statement('ALTER TABLE users ADD COLUMN temporary_address VARCHAR(500) NULL DEFAULT NULL AFTER address');

    echo 'Added `temporary_address` to `users`.' . PHP_EOL;
}

// --- photographers -------------------------------------------------------------
$table = Database::selectOne(
    "SELECT TABLE_NAME FROM information_schema.TABLES
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'photographers'",
);

if ($table !== null) {
    echo '`photographers` already exists - skipped.' . PHP_EOL;
} else {
    Database::statement(
        "CREATE TABLE `photographers` (
            `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `name`        VARCHAR(120) NOT NULL,
            `email`       VARCHAR(190) NOT NULL,
            `phone`       VARCHAR(30)  NOT NULL,
            `address`     VARCHAR(500) NOT NULL,
            `status`      ENUM('active','inactive') NOT NULL DEFAULT 'active',
            `created_by`  INT UNSIGNED NULL DEFAULT NULL,
            `created_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uq_photographers_email` (`email`),
            KEY `idx_photographers_status` (`status`),
            KEY `idx_photographers_created_by` (`created_by`),
            CONSTRAINT `fk_photographers_created_by`
                FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    );

    echo 'Created `photographers`.' . PHP_EOL;
}

// --- projects ----------------------------------------------------------------
$table = Database::selectOne(
    "SELECT TABLE_NAME FROM information_schema.TABLES
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'projects'",
);

if ($table !== null) {
    echo '`projects` already exists - skipped.' . PHP_EOL;
} else {
    Database::statement(
        "CREATE TABLE `projects` (
            `id`               INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `photographer_id`  INT UNSIGNED NOT NULL,
            `customer_name`    VARCHAR(150) NOT NULL,
            `description`      TEXT NOT NULL,
            `folder_name`      VARCHAR(190) NOT NULL,
            `deadline`         DATE NOT NULL,
            `total_payment`    DECIMAL(12,2) NOT NULL DEFAULT 0.00,
            `status`           ENUM('pending','in_progress','completed','cancelled') NOT NULL DEFAULT 'pending',
            `created_by`       INT UNSIGNED NULL DEFAULT NULL,
            `created_at`       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at`       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `idx_projects_photographer` (`photographer_id`),
            KEY `idx_projects_customer_name` (`customer_name`),
            KEY `idx_projects_status` (`status`),
            KEY `idx_projects_created_by` (`created_by`),
            CONSTRAINT `fk_projects_photographer`
                FOREIGN KEY (`photographer_id`) REFERENCES `photographers` (`id`) ON DELETE RESTRICT,
            CONSTRAINT `fk_projects_created_by`
                FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    );

    echo 'Created `projects`.' . PHP_EOL;
}

// --- projects.advance_payment (dropped - payments now live in Payment Management) --
$column = Database::selectOne(
    "SELECT COLUMN_NAME FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'projects' AND COLUMN_NAME = 'advance_payment'",
);

if ($column === null) {
    echo '`projects`.`advance_payment` already removed - skipped.' . PHP_EOL;
} else {
    Database::statement('ALTER TABLE projects DROP COLUMN advance_payment');

    echo 'Dropped `advance_payment` from `projects`.' . PHP_EOL;
}

// --- tasks -------------------------------------------------------------------
$table = Database::selectOne(
    "SELECT TABLE_NAME FROM information_schema.TABLES
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'tasks'",
);

if ($table !== null) {
    echo '`tasks` already exists - skipped.' . PHP_EOL;
} else {
    Database::statement(
        "CREATE TABLE `tasks` (
            `id`               INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `project_id`       INT UNSIGNED NOT NULL,
            `employee_id`      INT UNSIGNED NOT NULL,
            `title`            VARCHAR(150) NOT NULL,
            `description`      TEXT NOT NULL,
            `start_date`       DATE NOT NULL,
            `end_date`         DATE NOT NULL,
            `priority`         ENUM('high','medium','low') NOT NULL DEFAULT 'medium',
            `amount`           DECIMAL(12,2) NOT NULL DEFAULT 0.00,
            `status`           ENUM('assigned','accepted','in_progress','completed','exited','reassigned')
                               NOT NULL DEFAULT 'assigned',
            `progress`         TINYINT UNSIGNED NOT NULL DEFAULT 0,
            `parent_task_id`   INT UNSIGNED NULL DEFAULT NULL,
            `accepted_at`      DATETIME NULL DEFAULT NULL,
            `completed_at`     DATETIME NULL DEFAULT NULL,
            `exited_at`        DATETIME NULL DEFAULT NULL,
            `created_by`       INT UNSIGNED NULL DEFAULT NULL,
            `created_at`       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at`       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `idx_tasks_project` (`project_id`),
            KEY `idx_tasks_employee` (`employee_id`),
            KEY `idx_tasks_status` (`status`),
            KEY `idx_tasks_priority` (`priority`),
            KEY `idx_tasks_created_by` (`created_by`),
            KEY `idx_tasks_parent` (`parent_task_id`),
            CONSTRAINT `fk_tasks_project`
                FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE RESTRICT,
            CONSTRAINT `fk_tasks_employee`
                FOREIGN KEY (`employee_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT,
            CONSTRAINT `fk_tasks_created_by`
                FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
            CONSTRAINT `fk_tasks_parent`
                FOREIGN KEY (`parent_task_id`) REFERENCES `tasks` (`id`) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    );

    echo 'Created `tasks`.' . PHP_EOL;
}

// --- task_descriptions -------------------------------------------------------
$table = Database::selectOne(
    "SELECT TABLE_NAME FROM information_schema.TABLES
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'task_descriptions'",
);

if ($table !== null) {
    echo '`task_descriptions` already exists - skipped.' . PHP_EOL;
} else {
    Database::statement(
        "CREATE TABLE `task_descriptions` (
            `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `task_id`    INT UNSIGNED NOT NULL,
            `body`       TEXT NOT NULL,
            `created_by` INT UNSIGNED NULL DEFAULT NULL,
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `idx_task_descriptions_task` (`task_id`),
            KEY `idx_task_descriptions_created_by` (`created_by`),
            CONSTRAINT `fk_task_descriptions_task`
                FOREIGN KEY (`task_id`) REFERENCES `tasks` (`id`) ON DELETE CASCADE,
            CONSTRAINT `fk_task_descriptions_created_by`
                FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    );

    // Every task that already exists gets its opening description as row one
    // of its thread, so the task pages have something to render from day one.
    Database::statement(
        'INSERT INTO task_descriptions (task_id, body, created_by, created_at)
         SELECT id, description, created_by, created_at FROM tasks ORDER BY id ASC',
    );

    echo 'Created `task_descriptions`, seeded from the description on every existing task.' . PHP_EOL;
}

// --- task_salary_credits ------------------------------------------------------
$table = Database::selectOne(
    "SELECT TABLE_NAME FROM information_schema.TABLES
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'task_salary_credits'",
);

if ($table !== null) {
    echo '`task_salary_credits` already exists - skipped.' . PHP_EOL;
} else {
    Database::statement(
        "CREATE TABLE `task_salary_credits` (
            `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `task_id`     INT UNSIGNED NOT NULL,
            `employee_id` INT UNSIGNED NOT NULL,
            `project_id`  INT UNSIGNED NOT NULL,
            `amount`      DECIMAL(12,2) NOT NULL,
            `credited_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uq_task_salary_credits_task` (`task_id`),
            KEY `idx_task_salary_credits_employee` (`employee_id`),
            CONSTRAINT `fk_task_salary_credits_task`
                FOREIGN KEY (`task_id`) REFERENCES `tasks` (`id`) ON DELETE CASCADE,
            CONSTRAINT `fk_task_salary_credits_employee`
                FOREIGN KEY (`employee_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
            CONSTRAINT `fk_task_salary_credits_project`
                FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    );

    echo 'Created `task_salary_credits`.' . PHP_EOL;
}

// --- salary_settlements --------------------------------------------------
$table = Database::selectOne(
    "SELECT TABLE_NAME FROM information_schema.TABLES
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'salary_settlements'",
);

if ($table !== null) {
    echo '`salary_settlements` already exists - skipped.' . PHP_EOL;
} else {
    Database::statement(
        "CREATE TABLE `salary_settlements` (
            `id`                       INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `employee_id`              INT UNSIGNED NOT NULL,
            `salary_month`             CHAR(7) NOT NULL,
            `amount`                   DECIMAL(12,2) NOT NULL,
            `month_earned`             DECIMAL(12,2) NOT NULL,
            `previous_settled`         DECIMAL(12,2) NOT NULL,
            `outstanding_after`        DECIMAL(12,2) NOT NULL,
            `reference_no`             VARCHAR(20) NULL DEFAULT NULL,
            `idempotency_key`          VARCHAR(64) NULL DEFAULT NULL,
            `employee_name_snapshot`   VARCHAR(120) NOT NULL,
            `employee_email_snapshot`  VARCHAR(190) NOT NULL,
            `employee_phone_snapshot`  VARCHAR(30) NULL DEFAULT NULL,
            `notes`                    VARCHAR(500) NULL DEFAULT NULL,
            `settled_by`               INT UNSIGNED NULL DEFAULT NULL,
            `settled_at`               DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uq_salary_settlements_reference` (`reference_no`),
            UNIQUE KEY `uq_salary_settlements_idempotency` (`idempotency_key`),
            KEY `idx_salary_settlements_employee` (`employee_id`),
            KEY `idx_salary_settlements_month` (`salary_month`),
            KEY `idx_salary_settlements_settled_by` (`settled_by`),
            CONSTRAINT `fk_salary_settlements_employee`
                FOREIGN KEY (`employee_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
            CONSTRAINT `fk_salary_settlements_settled_by`
                FOREIGN KEY (`settled_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    );

    echo 'Created `salary_settlements`.' . PHP_EOL;
}

// --- payments ----------------------------------------------------------------
$table = Database::selectOne(
    "SELECT TABLE_NAME FROM information_schema.TABLES
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'payments'",
);

if ($table !== null) {
    echo '`payments` already exists - skipped.' . PHP_EOL;

    // --- payments.payment_type: the 'full' option ---------------------------
    // Added with Work Management's "Advance payment / Full payment" choice, so
    // an install created before that still needs the enum widened.
    $column = Database::selectOne(
        "SELECT COLUMN_TYPE FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'payments' AND COLUMN_NAME = 'payment_type'",
    );

    if ($column !== null && !str_contains((string) $column['COLUMN_TYPE'], "'full'")) {
        Database::statement(
            "ALTER TABLE payments MODIFY COLUMN `payment_type`
             ENUM('advance','full','milestone','partial','final','other') NOT NULL DEFAULT 'other'",
        );

        echo "Added the 'full' option to `payments`.`payment_type`." . PHP_EOL;
    } else {
        echo "`payments`.`payment_type` already offers 'full' - skipped." . PHP_EOL;
    }
} else {
    Database::statement(
        "CREATE TABLE `payments` (
            `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `project_id`      INT UNSIGNED NOT NULL,
            `amount`          DECIMAL(12,2) NOT NULL,
            `payment_type`    ENUM('advance','full','milestone','partial','final','other') NOT NULL DEFAULT 'other',
            `payment_method`  ENUM('cash','bank_transfer','upi','cheque','card','other') NOT NULL DEFAULT 'other',
            `reference_no`    VARCHAR(60) NULL DEFAULT NULL,
            `payment_date`    DATE NOT NULL,
            `notes`           VARCHAR(500) NULL DEFAULT NULL,
            `received_by`     INT UNSIGNED NULL DEFAULT NULL,
            `created_at`      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `idx_payments_project` (`project_id`),
            KEY `idx_payments_type` (`payment_type`),
            KEY `idx_payments_method` (`payment_method`),
            KEY `idx_payments_date` (`payment_date`),
            KEY `idx_payments_received_by` (`received_by`),
            CONSTRAINT `fk_payments_project`
                FOREIGN KEY (`project_id`) REFERENCES `projects` (`id`) ON DELETE RESTRICT,
            CONSTRAINT `fk_payments_received_by`
                FOREIGN KEY (`received_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    );

    echo 'Created `payments`.' . PHP_EOL;
}

// --- settings --------------------------------------------------------------
$table = Database::selectOne(
    "SELECT TABLE_NAME FROM information_schema.TABLES
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'settings'",
);

if ($table !== null) {
    $hasWebsite = Database::selectOne(
        "SELECT COLUMN_NAME FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'settings' AND COLUMN_NAME = 'company_website'",
    );

    if ($hasWebsite === null) {
        Database::statement(
            "ALTER TABLE `settings` ADD COLUMN `company_website` VARCHAR(190) NOT NULL DEFAULT '' AFTER `company_email`",
        );
        echo '`settings` already exists - added `company_website` column.' . PHP_EOL;
    } else {
        echo '`settings` already exists - skipped.' . PHP_EOL;
    }
} else {
    Database::statement(
        "CREATE TABLE `settings` (
            `id`                             TINYINT UNSIGNED NOT NULL DEFAULT 1,
            `company_name`                   VARCHAR(150) NOT NULL DEFAULT 'Sunrise Films',
            `company_address`                VARCHAR(500) NOT NULL DEFAULT '',
            `company_phone`                  VARCHAR(30)  NOT NULL DEFAULT '',
            `company_email`                  VARCHAR(190) NOT NULL DEFAULT '',
            `company_website`                VARCHAR(190) NOT NULL DEFAULT '',
            `password_min_length`            TINYINT UNSIGNED NOT NULL DEFAULT 8,
            `login_max_attempts`             TINYINT UNSIGNED NOT NULL DEFAULT 5,
            `login_lockout_minutes`          SMALLINT UNSIGNED NOT NULL DEFAULT 15,
            `admin_forgot_password_enabled`  TINYINT(1) NOT NULL DEFAULT 0,
            `updated_by`                     INT UNSIGNED NULL DEFAULT NULL,
            `updated_at`                     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            CONSTRAINT `chk_settings_single_row` CHECK (`id` = 1),
            CONSTRAINT `fk_settings_updated_by`
                FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
    );

    // Seed the one row from whatever this install's .env already says, so
    // existing behaviour does not change the moment the table appears -
    // Admin Management only takes over from here on.
    Database::statement(
        'INSERT INTO settings (
            id, company_name, company_address, company_phone, company_email, company_website,
            password_min_length, login_max_attempts, login_lockout_minutes, admin_forgot_password_enabled
        ) VALUES (1, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
        [
            (string) Config::get('company.name', 'Sunrise Films'),
            (string) Config::get('company.address', ''),
            (string) Config::get('company.phone', ''),
            (string) Config::get('company.email', ''),
            (string) Config::get('company.website', ''),
            max(6, (int) Config::get('auth.password_min', 8)),
            max(1, (int) Config::get('auth.max_attempts', 5)),
            max(1, (int) Config::get('auth.lockout_minutes', 15)),
            Config::get('auth.admin_forgot_password') === true ? 1 : 0,
        ],
    );

    echo 'Created `settings`, seeded from the current .env values.' . PHP_EOL;
}

echo PHP_EOL . 'Schema is up to date.' . PHP_EOL;
