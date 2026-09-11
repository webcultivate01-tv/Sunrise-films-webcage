<?php

declare(strict_types=1);

use App\Core\Config;
use App\Core\Csrf;

/**
 * Escape for HTML output. Every dynamic value in a view goes through this.
 */
function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * Hidden CSRF input for a form.
 */
function csrf_field(): string
{
    return Csrf::field();
}

/**
 * Repopulate a form field after a failed submission.
 *
 * @param array<string, string> $old
 */
function old(array $old, string $field, string $default = ''): string
{
    return e($old[$field] ?? $default);
}

/**
 * The validation message for one field, if there is one.
 *
 * @param array<string, string> $errors
 */
function field_error(array $errors, string $field): string
{
    if (!isset($errors[$field])) {
        return '';
    }

    return '<p class="mt-1.5 text-sm text-red-600">' . e($errors[$field]) . '</p>';
}

/**
 * Tailwind classes for an input, reddened when the field has an error.
 *
 * @param array<string, string> $errors
 */
function input_classes(array $errors, string $field): string
{
    $base = 'block w-full rounded-lg border px-3.5 py-2.5 text-ink placeholder:text-slate-400 '
        . 'focus:outline-none focus:ring-2 focus:ring-offset-0 transition';

    return isset($errors[$field])
        ? $base . ' border-red-400 bg-red-50 focus:border-red-500 focus:ring-red-200'
        : $base . ' border-slate-300 bg-white focus:border-brand-500 focus:ring-brand-200';
}

/**
 * Badge classes for an account status.
 */
function status_badge(string $status): string
{
    return match ($status) {
        'active'    => 'bg-emerald-50 text-emerald-700 ring-emerald-600/20',
        'inactive'  => 'bg-slate-100 text-slate-600 ring-slate-500/20',
        'suspended' => 'bg-red-50 text-red-700 ring-red-600/20',
        default     => 'bg-slate-100 text-slate-600 ring-slate-500/20',
    };
}

function config(string $key, mixed $default = null): mixed
{
    return Config::get($key, $default);
}

/**
 * Human readable date for the panels.
 */
function pretty_date(?string $value, string $fallback = 'Never'): string
{
    if ($value === null || $value === '') {
        return $fallback;
    }

    $timestamp = strtotime($value);

    return $timestamp === false ? $fallback : date('j M Y, g:i a', $timestamp);
}
