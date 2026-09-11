<?php

declare(strict_types=1);

/**
 * Adds the `photo` column to `users` for installs that ran schema.sql before
 * profile photos existed. Safe to run more than once.
 *
 * Usage:  php database/migrate_add_photo.php
 */

use App\Core\Database;

define('BASE_PATH', dirname(__DIR__));

require BASE_PATH . '/app/bootstrap.php';

try {
    Database::connection();
} catch (Throwable $e) {
    fwrite(STDERR, $e->getMessage() . PHP_EOL);
    exit(1);
}

$exists = Database::selectOne(
    "SELECT COLUMN_NAME FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'photo'",
);

if ($exists !== null) {
    echo '`photo` column already exists - nothing to do.' . PHP_EOL;
    exit(0);
}

Database::statement('ALTER TABLE users ADD COLUMN photo VARCHAR(255) NULL DEFAULT NULL AFTER phone');

echo 'Added `photo` column to `users`.' . PHP_EOL;
