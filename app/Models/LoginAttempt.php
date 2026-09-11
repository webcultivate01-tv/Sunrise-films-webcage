<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

final class LoginAttempt
{
    public static function record(string $key, string $ip): void
    {
        Database::statement(
            'INSERT INTO login_attempts (attempt_key, ip_address) VALUES (?, ?)',
            [$key, $ip],
        );
    }

    public static function countSince(string $key, int $minutes): int
    {
        // The window is interpolated rather than bound: it is an internal
        // integer from config, and MySQL will not take a placeholder for the
        // quantity in an INTERVAL expression on every version.
        $window = max(1, $minutes);

        $row = Database::selectOne(
            'SELECT COUNT(*) AS total FROM login_attempts
             WHERE attempt_key = ? AND attempted_at > (NOW() - INTERVAL ' . $window . ' MINUTE)',
            [$key],
        );

        return (int) ($row['total'] ?? 0);
    }

    public static function clear(string $key): void
    {
        Database::statement('DELETE FROM login_attempts WHERE attempt_key = ?', [$key]);
    }

    public static function purgeOld(): int
    {
        return Database::statement(
            'DELETE FROM login_attempts WHERE attempted_at < (NOW() - INTERVAL 1 DAY)',
        );
    }
}
