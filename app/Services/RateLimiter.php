<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use App\Models\LoginAttempt;

/**
 * Throttles repeated failures against the login and forgot-password forms,
 * keyed on the submitted email plus the client IP.
 */
final class RateLimiter
{
    public static function key(string $scope, string $email, string $ip): string
    {
        return substr($scope . '|' . mb_strtolower($email) . '|' . $ip, 0, 190);
    }

    public static function tooManyAttempts(string $key): bool
    {
        $max = (int) Config::get('auth.max_attempts', 5);

        return LoginAttempt::countSince($key, self::lockoutMinutes()) >= $max;
    }

    public static function hit(string $key, string $ip): void
    {
        LoginAttempt::record($key, $ip);
    }

    public static function clear(string $key): void
    {
        LoginAttempt::clear($key);
    }

    public static function lockoutMinutes(): int
    {
        return max(1, (int) Config::get('auth.lockout_minutes', 15));
    }

    public static function lockoutMessage(): string
    {
        return sprintf(
            'Too many failed attempts. Please try again in %d minutes.',
            self::lockoutMinutes(),
        );
    }
}
