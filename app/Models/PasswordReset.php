<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

final class PasswordReset
{
    public static function create(
        int $userId,
        string $selector,
        string $validatorHash,
        string $expiresAt,
        string $ip,
    ): int {
        Database::statement(
            'INSERT INTO password_resets (user_id, selector, validator_hash, expires_at, requested_ip)
             VALUES (?, ?, ?, ?, ?)',
            [$userId, $selector, $validatorHash, $expiresAt, $ip],
        );

        return Database::lastInsertId();
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function findUsable(string $selector): ?array
    {
        return Database::selectOne(
            'SELECT * FROM password_resets
             WHERE selector = ? AND used_at IS NULL AND expires_at > NOW()
             LIMIT 1',
            [$selector],
        );
    }

    public static function markUsed(int $id): void
    {
        Database::statement('UPDATE password_resets SET used_at = NOW() WHERE id = ?', [$id]);
    }

    /**
     * Requesting a new link invalidates any outstanding one for that account.
     */
    public static function invalidateAllForUser(int $userId): void
    {
        Database::statement(
            'UPDATE password_resets SET used_at = NOW() WHERE user_id = ? AND used_at IS NULL',
            [$userId],
        );
    }

    public static function purgeExpired(): int
    {
        return Database::statement(
            'DELETE FROM password_resets WHERE expires_at < (NOW() - INTERVAL 1 DAY)',
        );
    }
}
