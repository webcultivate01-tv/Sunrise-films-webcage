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

    /**
     * A mobile or landline number: digits with the usual separators, long
     * enough to be real. Empty passes, so the caller decides whether the field
     * is required.
     */
    public function phone(
        string $field,
        string $value,
        string $message = 'Please enter a valid mobile number.',
    ): void {
        if ($value === '') {
            return;
        }

        // Count the digits separately so "+++" or "()()" cannot pass as a number.
        $digits = preg_replace('/\D/', '', $value) ?? '';

        if (preg_match('/^[0-9+\-\s()]{6,30}$/', $value) !== 1 || mb_strlen($digits) < 7) {
            $this->add($field, $message);
        }
    }

    /**
     * The submitted value has to be one the form actually offered. Used for
     * role and status selects, where a tampered <option> must not get through.
     *
     * @param list<string> $allowed
     */
    public function in(string $field, string $value, array $allowed, string $message): void
    {
        if (!in_array($value, $allowed, true)) {
            $this->add($field, $message);
        }
    }

    /**
     * A non-negative money amount, e.g. a payment field. Empty passes, so the
     * caller decides whether the field is required.
     */
    public function decimal(
        string $field,
        string $value,
        string $message = 'Please enter a valid amount.',
    ): void {
        if ($value === '') {
            return;
        }

        if (preg_match('/^\d+(\.\d{1,2})?$/', $value) !== 1) {
            $this->add($field, $message);
        }
    }

    /**
     * A whole number within [$min, $max], e.g. a policy setting. Empty passes,
     * so the caller decides whether the field is required.
     */
    public function integer(string $field, string $value, int $min, int $max, string $message): void
    {
        if ($value === '') {
            return;
        }

        if (preg_match('/^-?\d+$/', $value) !== 1 || (int) $value < $min || (int) $value > $max) {
            $this->add($field, $message);
        }
    }

    /**
     * A calendar date in Y-m-d, the format an <input type="date"> submits.
     * Empty passes, so the caller decides whether the field is required.
     */
    public function date(string $field, string $value, string $message = 'Please enter a valid date.'): void
    {
        if ($value === '') {
            return;
        }

        $date = \DateTime::createFromFormat('Y-m-d', $value);

        if ($date === false || $date->format('Y-m-d') !== $value) {
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
