<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Validator;
use App\Models\Setting;

/**
 * One password policy for Admin, Manager and Employee alike (spec s9), so
 * every place a password is set — creation, self-service reset, or a reset
 * performed by a superior — enforces exactly the same rules.
 */
final class PasswordPolicy
{
    public static function minLength(): int
    {
        return Setting::current()->passwordMinLength;
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

    /**
     * A temporary password for a newly created account. The Employee
     * Management form does not ask the creator for one (module spec s7), so
     * the system generates it, emails it with the welcome message and shows it
     * to the creator once.
     *
     * Always satisfies validate(): letters and digits only, from an alphabet
     * with no 0/O or 1/l/I, so it survives being read aloud or copied by hand.
     */
    public static function generate(): string
    {
        $letters = 'abcdefghjkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ';
        $digits  = '23456789';
        $length  = max(self::minLength(), 12);

        // Guarantee the letter and the digit the policy requires, then fill the
        // rest from both alphabets and shuffle so their positions are random.
        $characters = [
            $letters[random_int(0, strlen($letters) - 1)],
            $digits[random_int(0, strlen($digits) - 1)],
        ];

        $pool = $letters . $digits;

        for ($i = count($characters); $i < $length; $i++) {
            $characters[] = $pool[random_int(0, strlen($pool) - 1)];
        }

        for ($i = count($characters) - 1; $i > 0; $i--) {
            $j = random_int(0, $i);
            [$characters[$i], $characters[$j]] = [$characters[$j], $characters[$i]];
        }

        return implode('', $characters);
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
