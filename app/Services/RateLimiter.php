<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\LoginAttempt;
use App\Models\Setting;

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
        return LoginAttempt::countSince($key, self::lockoutMinutes()) >= Setting::current()->loginMaxAttempts;
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
        return Setting::current()->loginLockoutMinutes;
    }

    public static function lockoutMessage(): string
    {
        return sprintf(
            'Too many failed attempts. Please try again in %d minutes.',
            self::lockoutMinutes(),
        );
    }
}
