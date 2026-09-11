<?php

declare(strict_types=1);

namespace App\Core;

/**
 * A PHP session is used here for presentation state only — flash messages,
 * repopulated form input and the CSRF token. It deliberately never holds the
 * authenticated identity: per spec s13 the authenticated identity comes from
 * the authentication token alone, so clearing the session cannot log anybody
 * in or out.
 */
final class Session
{
    public static function start(bool $secure = false): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => '/',
            'domain'   => '',
            'secure'   => $secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);

        session_name('sf_state');
        session_start();
    }

    public static function put(string $key, mixed $value): void
    {
        $_SESSION[$key] = $value;
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return $_SESSION[$key] ?? $default;
    }

    public static function forget(string $key): void
    {
        unset($_SESSION[$key]);
    }

    /**
     * Read a value and remove it in the same breath.
     */
    public static function pull(string $key, mixed $default = null): mixed
    {
        $value = self::get($key, $default);
        self::forget($key);

        return $value;
    }

    public static function flash(string $type, string $message): void
    {
        $_SESSION['_flash'][$type] = $message;
    }

    /**
     * @return array<string, string>
     */
    public static function pullFlash(): array
    {
        /** @var array<string, string> $flash */
        $flash = self::pull('_flash', []);

        return $flash;
    }

    /**
     * @param array<string, string> $errors
     * @param array<string, string> $input
     */
    public static function flashErrors(array $errors, array $input = []): void
    {
        self::put('_errors', $errors);
        // Never repopulate a password field.
        unset($input['password'], $input['password_confirmation'], $input['new_password']);
        self::put('_old', $input);
    }

    /**
     * @return array<string, string>
     */
    public static function pullErrors(): array
    {
        /** @var array<string, string> $errors */
        $errors = self::pull('_errors', []);

        return $errors;
    }

    /**
     * @return array<string, string>
     */
    public static function pullOld(): array
    {
        /** @var array<string, string> $old */
        $old = self::pull('_old', []);

        return $old;
    }

    public static function regenerate(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
    }
}
