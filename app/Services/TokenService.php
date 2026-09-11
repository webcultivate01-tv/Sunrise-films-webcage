<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use App\Core\Request;
use App\Models\AuthToken;
use App\Models\User;

/**
 * Issues and verifies authentication tokens (spec s13, s14).
 *
 * A token is `<selector>.<validator>`:
 *   - the selector is an indexed lookup key, so verification is a single
 *     indexed read rather than a scan over every row;
 *   - only an HMAC-SHA256 of the validator is stored, keyed with APP_KEY, so
 *     a leaked `auth_tokens` table cannot be replayed as a login.
 * Comparison is constant time.
 */
final class TokenService
{
    /**
     * Issue a token for a user and return the plaintext value. This is the only
     * moment the plaintext exists — it is handed to the client and forgotten.
     */
    public static function issue(User $user, Request $request): string
    {
        $selector  = bin2hex(random_bytes(16));
        $validator = bin2hex(random_bytes(32));

        $expiresAt = date(
            'Y-m-d H:i:s',
            time() + (max(1, (int) Config::get('auth.token_lifetime', 480)) * 60),
        );

        AuthToken::create(
            userId:        $user->id,
            role:          $user->role,
            selector:      $selector,
            validatorHash: self::hashValidator($validator),
            expiresAt:     $expiresAt,
            ip:            $request->ip(),
            userAgent:     $request->userAgent(),
        );

        return $selector . '.' . $validator;
    }

    /**
     * Resolve a plaintext token to its user, or null when the token is
     * malformed, unknown, revoked, expired, or belongs to an account that is
     * no longer active.
     */
    public static function resolve(?string $token): ?User
    {
        $parts = self::split($token);

        if ($parts === null) {
            return null;
        }

        [$selector, $validator] = $parts;

        $row = AuthToken::findLive($selector);

        if ($row === null) {
            return null;
        }

        if (!hash_equals((string) $row['validator_hash'], self::hashValidator($validator))) {
            // A valid selector with a bad validator means the token was
            // tampered with: kill the token rather than let it be brute forced.
            AuthToken::revokeBySelector($selector);

            return null;
        }

        $user = User::findById((int) $row['user_id']);

        if ($user === null || !$user->isActive()) {
            return null;
        }

        // The role is pinned at issue time; a role change invalidates the token.
        if ($user->role !== (string) $row['role']) {
            AuthToken::revokeBySelector($selector);

            return null;
        }

        AuthToken::touch((int) $row['id']);

        return $user;
    }

    public static function revoke(?string $token): void
    {
        $parts = self::split($token);

        if ($parts === null) {
            return;
        }

        AuthToken::revokeBySelector($parts[0]);
    }

    public static function revokeAllForUser(int $userId): int
    {
        return AuthToken::revokeAllForUser($userId);
    }

    /**
     * @return array{0:string,1:string}|null
     */
    private static function split(?string $token): ?array
    {
        if ($token === null || !str_contains($token, '.')) {
            return null;
        }

        [$selector, $validator] = explode('.', $token, 2);

        if (strlen($selector) !== 32 || strlen($validator) !== 64) {
            return null;
        }

        if (ctype_xdigit($selector) === false || ctype_xdigit($validator) === false) {
            return null;
        }

        return [$selector, $validator];
    }

    private static function hashValidator(string $validator): string
    {
        return hash_hmac('sha256', $validator, (string) Config::get('app.key', ''));
    }
}
