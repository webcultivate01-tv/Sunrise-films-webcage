<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use App\Core\Request;
use App\Core\Session;
use App\Models\User;

/**
 * Credential checking, token issuing and the per-request identity.
 *
 * The authenticated identity is derived from the authentication token on every
 * request (spec s13) — nothing about who the user is lives in the PHP session.
 */
final class AuthService
{
    private static ?User $user = null;

    private static ?string $token = null;

    private static bool $resolved = false;

    /**
     * Verify credentials for one specific role.
     *
     * A user may only sign in through their own entry point (spec s2), so a
     * Manager posting to /admin/login fails exactly like a wrong password —
     * the response never reveals that the email exists under another role.
     */
    public static function attempt(Request $request, string $email, string $password, string $role): AuthResult
    {
        $key = RateLimiter::key('login:' . $role, $email, $request->ip());

        if (RateLimiter::tooManyAttempts($key)) {
            return AuthResult::failure(RateLimiter::lockoutMessage());
        }

        $user = User::findByEmail($email);

        // Hash a dummy value when the account is missing so a failed lookup and
        // a wrong password take a comparable amount of time.
        if ($user === null || $user->role !== $role) {
            PasswordPolicy::verify($password, '$2y$12$usesomesillystringfore7hnbRJHxXVLeakoG8K30M1XOmk.9KxGu');
            RateLimiter::hit($key, $request->ip());

            return AuthResult::failure('Invalid email or password.');
        }

        if (!PasswordPolicy::verify($password, $user->passwordHash)) {
            RateLimiter::hit($key, $request->ip());

            return AuthResult::failure('Invalid email or password.');
        }

        // Only active accounts may sign in (spec s17). This check comes after
        // the password check so account status is never leaked to a guess.
        if (!$user->isActive()) {
            RateLimiter::hit($key, $request->ip());

            return AuthResult::failure($user->statusMessage());
        }

        if (PasswordPolicy::needsRehash($user->passwordHash)) {
            User::updatePassword($user->id, PasswordPolicy::hash($password));
        }

        RateLimiter::clear($key);

        return AuthResult::success($user);
    }

    /**
     * Issue a token for an authenticated user and put it on the response.
     */
    public static function login(User $user, Request $request): string
    {
        $token = TokenService::issue($user, $request);

        self::setCookie($token, $request, time() + (max(1, (int) Config::get('auth.token_lifetime', 480)) * 60));

        User::touchLastLogin($user->id);

        // Fresh presentation state for the new sign-in.
        Session::regenerate();

        self::$user     = $user;
        self::$token    = $token;
        self::$resolved = true;

        return $token;
    }

    /**
     * Terminate authenticated access (spec s15): the stored token is revoked,
     * so the value the browser holds — or any copy of it — stops working.
     */
    public static function logout(Request $request): void
    {
        TokenService::revoke(self::currentToken($request));

        self::forgetCookie($request);

        self::$user     = null;
        self::$token    = null;
        self::$resolved = true;

        Session::regenerate();
    }

    /**
     * The user behind the token on this request, or null.
     */
    public static function resolve(Request $request): ?User
    {
        if (self::$resolved) {
            return self::$user;
        }

        self::$resolved = true;
        self::$token    = self::currentToken($request);
        self::$user     = TokenService::resolve(self::$token);

        // A token that no longer resolves should not keep being presented.
        if (self::$user === null && self::$token !== null) {
            self::forgetCookie($request);
        }

        return self::$user;
    }

    public static function user(): ?User
    {
        return self::$user;
    }

    public static function check(): bool
    {
        return self::$user instanceof User;
    }

    /**
     * The token travels in an HttpOnly cookie for browser panels, and may also
     * be sent as `Authorization: Bearer <token>` by non-browser clients.
     */
    private static function currentToken(Request $request): ?string
    {
        return $request->bearerToken() ?? $request->cookie(self::cookieName());
    }

    private static function cookieName(): string
    {
        return (string) Config::get('auth.cookie', 'sf_auth_token');
    }

    private static function setCookie(string $token, Request $request, int $expiresAt): void
    {
        setcookie(self::cookieName(), $token, [
            'expires'  => $expiresAt,
            'path'     => '/',
            'secure'   => $request->isSecure(),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }

    private static function forgetCookie(Request $request): void
    {
        if (headers_sent()) {
            return;
        }

        setcookie(self::cookieName(), '', [
            'expires'  => time() - 3600,
            'path'     => '/',
            'secure'   => $request->isSecure(),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }
}
