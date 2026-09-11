<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Collects field => message pairs. The first error wins per field, so the user
 * sees the most relevant message rather than a stack of them.
 */
final class Validator
{
    /** @var array<string, string> */
    private array $errors = [];

    public function add(string $field, string $message): void
    {
        if (!isset($this->errors[$field])) {
            $this->errors[$field] = $message;
        }
    }

    public function require(string $field, string $value, string $message): void
    {
        if ($value === '') {
            $this->add($field, $message);
        }
    }

    public function email(string $field, string $value, string $message = 'Please enter a valid email address.'): void
    {
        if ($value !== '' && filter_var($value, FILTER_VALIDATE_EMAIL) === false) {
            $this->add($field, $message);
        }
    }

    public function maxLength(string $field, string $value, int $max, string $message): void
    {
        if (mb_strlen($value) > $max) {
            $this->add($field, $message);
        }
    }

    public function fails(): bool
    {
        return $this->errors !== [];
    }

    public function passes(): bool
    {
        return $this->errors === [];
    }

    /**
     * @return array<string, string>
     */
    public function errors(): array
    {
        return $this->errors;
    }

    public function first(): ?string
    {
        foreach ($this->errors as $message) {
            return $message;
        }

        return null;
    }
}
