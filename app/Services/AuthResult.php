<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;

/**
 * Outcome of a login attempt. `field` says which input the message belongs to.
 */
final class AuthResult
{
    private function __construct(
        public readonly bool $succeeded,
        public readonly ?User $user = null,
        public readonly string $message = '',
        public readonly string $field = 'email',
    ) {
    }

    public static function success(User $user): self
    {
        return new self(true, $user);
    }

    public static function failure(string $message, string $field = 'email'): self
    {
        return new self(false, null, $message, $field);
    }
}
