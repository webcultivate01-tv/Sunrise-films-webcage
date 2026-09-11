<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/**
 * Persistence for issued authentication tokens. The plaintext token never
 * reaches this table: only the selector and a hash of the validator do.
 */
final class AuthToken
{
    public static function create(
        int $userId,
        string $role,
        string $selector,
        string $validatorHash,
        string $expiresAt,
        string $ip,
        string $userAgent,
    ): int {
        Database::statement(
            'INSERT INTO auth_tokens (user_id, role, selector, validator_hash, ip_address, user_agent, expires_at)
             VALUES (?, ?, ?, ?, ?, ?, ?)',
            [$userId, $role, $selector, $validatorHash, $ip, $userAgent, $expiresAt],
        );

        return Database::lastInsertId();
    }

    /**
     * Look up a live (not revoked, not expired) token by its selector.
     *
     * @return array<string, mixed>|null
     */
    public static function findLive(string $selector): ?array
    {
        return Database::selectOne(
            'SELECT * FROM auth_tokens
             WHERE selector = ? AND revoked_at IS NULL AND expires_at > NOW()
             LIMIT 1',
            [$selector],
        );
    }

    public static function touch(int $id): void
    {
        Database::statement('UPDATE auth_tokens SET last_used_at = NOW() WHERE id = ?', [$id]);
    }

    public static function revokeBySelector(string $selector): void
    {
        Database::statement(
            'UPDATE auth_tokens SET revoked_at = NOW() WHERE selector = ? AND revoked_at IS NULL',
            [$selector],
        );
    }

    /**
     * Used after a password change or a status change, so every device the
     * account was signed in on loses access immediately.
     */
    public static function revokeAllForUser(int $userId): int
    {
        return Database::statement(
            'UPDATE auth_tokens SET revoked_at = NOW() WHERE user_id = ? AND revoked_at IS NULL',
            [$userId],
        );
    }

    public static function activeCountForUser(int $userId): int
    {
        $row = Database::selectOne(
            'SELECT COUNT(*) AS total FROM auth_tokens
             WHERE user_id = ? AND revoked_at IS NULL AND expires_at > NOW()',
            [$userId],
        );

        return (int) ($row['total'] ?? 0);
    }

    /**
     * Housekeeping: drop rows that can no longer authenticate anybody.
     */
    public static function purgeExpired(): int
    {
        return Database::statement(
            'DELETE FROM auth_tokens
             WHERE expires_at < (NOW() - INTERVAL 7 DAY)
                OR (revoked_at IS NOT NULL AND revoked_at < (NOW() - INTERVAL 7 DAY))',
        );
    }
}
