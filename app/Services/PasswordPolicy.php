<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use App\Core\Validator;

/**
 * One password policy for Admin, Manager and Employee alike (spec s9), so
 * every place a password is set — creation, self-service reset, or a reset
 * performed by a superior — enforces exactly the same rules.
 */
final class PasswordPolicy
{
    public static function minLength(): int
    {
        return max(6, (int) Config::get('auth.password_min', 8));
    }

    public static function description(): string
    {
        return sprintf(
            'At least %d characters, including one letter and one number.',
            self::minLength(),
        );
    }

    /**
     * Validate a new password and its confirmation.
     *
     * @param string $field        Name of the password field being validated.
     * @param string $confirmField Name of its confirmation field.
     */
    public static function validate(
        Validator $validator,
        string $password,
        string $confirmation,
        string $field = 'password',
        string $confirmField = 'password_confirmation',
    ): void {
        $min = self::minLength();

        if ($password === '') {
            $validator->add($field, 'Please enter a password.');

            return;
        }

        if (mb_strlen($password) < $min) {
            $validator->add($field, sprintf('Password must be at least %d characters long.', $min));
        } elseif (mb_strlen($password) > 200) {
            $validator->add($field, 'Password must be 200 characters or fewer.');
        } elseif (preg_match('/[A-Za-z]/', $password) !== 1 || preg_match('/\d/', $password) !== 1) {
            $validator->add($field, 'Password must contain at least one letter and one number.');
        }

        if ($confirmation === '') {
            $validator->add($confirmField, 'Please confirm the password.');

            return;
        }

        if (!hash_equals($password, $confirmation)) {
            $validator->add($confirmField, 'Passwords do not match.');
        }
    }

    public static function hash(string $password): string
    {
        // PASSWORD_DEFAULT lets PHP move to a stronger algorithm over time;
        // needsRehash() below keeps stored hashes in step with it.
        return password_hash($password, PASSWORD_DEFAULT);
    }

    public static function verify(string $password, string $hash): bool
    {
        return password_verify($password, $hash);
    }

    public static function needsRehash(string $hash): bool
    {
        return password_needs_rehash($hash, PASSWORD_DEFAULT);
    }
}
