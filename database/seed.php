<?php

declare(strict_types=1);

/**
 * Seeds the bootstrap Admin account.
 *
 * There is no public registration (spec s3), so the very first account has to
 * come from here; every other account is created inside the application by the
 * role above it.
 *
 * Usage:  php database/seed.php
 */

use App\Core\Config;
use App\Core\Database;
use App\Models\User;
use App\Services\PasswordPolicy;

define('BASE_PATH', dirname(__DIR__));

require BASE_PATH . '/app/bootstrap.php';

$email    = 'admin@gmail.com';
$password = 'admin123';
$name     = 'System Administrator';

try {
    Database::connection();
} catch (Throwable $e) {
    fwrite(STDERR, $e->getMessage() . PHP_EOL);
    exit(1);
}

$existing = User::findByEmail($email);

if ($existing !== null) {
    // Re-running the seeder resets the bootstrap Admin back to a known state
    // rather than failing on the unique email.
    User::updatePassword($existing->id, PasswordPolicy::hash($password));
    User::updateStatus($existing->id, User::STATUS_ACTIVE);

    echo "Admin already existed - password reset to the documented default." . PHP_EOL;
} else {
    User::create(
        name:         $name,
        email:        $email,
        phone:        '',
        passwordHash: PasswordPolicy::hash($password),
        role:         User::ROLE_ADMIN,
        createdBy:    null,
        status:       User::STATUS_ACTIVE,
    );

    echo "Admin account created." . PHP_EOL;
}

echo PHP_EOL;
echo "  URL:      " . Config::get('app.url') . "/admin" . PHP_EOL;
echo "  Email:    " . $email . PHP_EOL;
echo "  Password: " . $password . PHP_EOL;
echo PHP_EOL;
echo "Change this password after the first sign-in." . PHP_EOL;
