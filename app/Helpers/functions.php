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

/**
 * Badge classes for a user's role, so Managers and Employees are told apart at
 * a glance in the Employee Management list.
 */
function role_badge(string $role): string
{
    return match ($role) {
        'admin'    => 'bg-violet-50 text-violet-700 ring-violet-600/20',
        'manager'  => 'bg-brand-50 text-brand-700 ring-brand-600/20',
        'employee' => 'bg-sky-50 text-sky-700 ring-sky-600/20',
        default    => 'bg-slate-100 text-slate-600 ring-slate-500/20',
    };
}

function config(string $key, mixed $default = null): mixed
{
    return Config::get($key, $default);
}

/**
 * Badge classes for a project's progress status.
 */
function project_status_badge(string $status): string
{
    return match ($status) {
        'pending'     => 'bg-slate-100 text-slate-600 ring-slate-500/20',
        'in_progress' => 'bg-brand-50 text-brand-700 ring-brand-600/20',
        'completed'   => 'bg-emerald-50 text-emerald-700 ring-emerald-600/20',
        'cancelled'   => 'bg-red-50 text-red-700 ring-red-600/20',
        default       => 'bg-slate-100 text-slate-600 ring-slate-500/20',
    };
}

/**
 * A money amount as rupees, e.g. "1,20,000.00".
 */
function money(float $amount): string
{
    return '₹' . number_format($amount, 2);
}

/**
 * A rupee amount spelled out in words, Indian numbering (lakh/crore), for the
 * "Amount in Words" line on a printed invoice - e.g. 1250.50 becomes
 * "One Thousand Two Hundred Fifty Rupees and Fifty Paise Only".
 */
function amount_in_words(float $amount): string
{
    $amount = round(abs($amount), 2);
    $rupees = (int) floor($amount);
    $paise  = (int) round(($amount - $rupees) * 100);

    $words = number_to_indian_words($rupees) . ' Rupee' . ($rupees === 1 ? '' : 's');

    if ($paise > 0) {
        $words .= ' and ' . number_to_indian_words($paise) . ' Paise';
    }

    return $words . ' Only';
}

/**
 * Whole numbers only, split Indian-style into crore/lakh/thousand/hundred
 * groups rather than the international thousand/million grouping.
 */
function number_to_indian_words(int $number): string
{
    if ($number === 0) {
        return 'Zero';
    }

    $ones = [
        '', 'One', 'Two', 'Three', 'Four', 'Five', 'Six', 'Seven', 'Eight', 'Nine', 'Ten',
        'Eleven', 'Twelve', 'Thirteen', 'Fourteen', 'Fifteen', 'Sixteen', 'Seventeen', 'Eighteen', 'Nineteen',
    ];
    $tens = ['', '', 'Twenty', 'Thirty', 'Forty', 'Fifty', 'Sixty', 'Seventy', 'Eighty', 'Ninety'];

    $twoDigits = static function (int $n) use ($ones, $tens): string {
        if ($n < 20) {
            return $ones[$n];
        }

        $ten = intdiv($n, 10);
        $one = $n % 10;

        return trim($tens[$ten] . ($one > 0 ? ' ' . $ones[$one] : ''));
    };

    $threeDigits = static function (int $n) use ($ones, $twoDigits): string {
        $hundred = intdiv($n, 100);
        $rest    = $n % 100;
        $parts   = [];

        if ($hundred > 0) {
            $parts[] = $ones[$hundred] . ' Hundred';
        }

        if ($rest > 0) {
            $parts[] = $twoDigits($rest);
        }

        return implode(' ', $parts);
    };

    $crore    = intdiv($number, 10000000);
    $lakh     = intdiv($number % 10000000, 100000);
    $thousand = intdiv($number % 100000, 1000);
    $hundred  = $number % 1000;

    $parts = [];

    if ($crore > 0) {
        $parts[] = $threeDigits($crore) . ' Crore';
    }

    if ($lakh > 0) {
        $parts[] = $twoDigits($lakh) . ' Lakh';
    }

    if ($thousand > 0) {
        $parts[] = $twoDigits($thousand) . ' Thousand';
    }

    if ($hundred > 0) {
        $parts[] = $threeDigits($hundred);
    }

    return implode(' ', $parts);
}

/**
 * Human readable label for a project's progress status.
 */
function project_status_label(string $status): string
{
    return match ($status) {
        'pending'     => 'Pending',
        'in_progress' => 'In Progress',
        'completed'   => 'Completed',
        'cancelled'   => 'Cancelled',
        default       => ucfirst($status),
    };
}

/**
 * How a project is named wherever it has to be shown as one line - in a
 * dropdown, a report row or a filter caption.
 *
 * A project has no name column of its own: it is the shoot a photographer
 * gave us for one of their own customers, so it reads "customer - folder",
 * which is what makes two projects from the same photographer tellable apart.
 */
function project_title(\App\Models\Project $project): string
{
    $folder = trim($project->folderName);

    return $folder === '' ? $project->customerName : $project->customerName . ' - ' . $folder;
}

/**
 * Badge classes for a task's status.
 */
function task_status_badge(string $status): string
{
    return match ($status) {
        'assigned'    => 'bg-slate-100 text-slate-600 ring-slate-500/20',
        'accepted'    => 'bg-sky-50 text-sky-700 ring-sky-600/20',
        'in_progress' => 'bg-brand-50 text-brand-700 ring-brand-600/20',
        'completed'   => 'bg-emerald-50 text-emerald-700 ring-emerald-600/20',
        'exited'      => 'bg-red-50 text-red-700 ring-red-600/20',
        'reassigned'  => 'bg-violet-50 text-violet-700 ring-violet-600/20',
        default       => 'bg-slate-100 text-slate-600 ring-slate-500/20',
    };
}

/**
 * Human readable label for a task's status.
 */
function task_status_label(string $status): string
{
    return match ($status) {
        'assigned'    => 'Assigned',
        'accepted'    => 'Accepted by Employee',
        'in_progress' => 'In Progress',
        'completed'   => 'Completed',
        'exited'      => 'Exited',
        'reassigned'  => 'Reassigned',
        default       => ucfirst($status),
    };
}

/**
 * Badge classes for a task's priority.
 */
function task_priority_badge(string $priority): string
{
    return match ($priority) {
        'high'   => 'bg-red-50 text-red-700 ring-red-600/20',
        'medium' => 'bg-amber-50 text-amber-700 ring-amber-600/20',
        'low'    => 'bg-slate-100 text-slate-600 ring-slate-500/20',
        default  => 'bg-slate-100 text-slate-600 ring-slate-500/20',
    };
}

function task_priority_label(string $priority): string
{
    return match ($priority) {
        'high'   => 'High',
        'medium' => 'Medium',
        'low'    => 'Low',
        default  => ucfirst($priority),
    };
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

/**
 * A 'Y-m' salary month as "March 2026".
 */
function pretty_month(string $month): string
{
    $timestamp = strtotime($month . '-01');

    return $timestamp === false ? $month : date('F Y', $timestamp);
}

/**
 * Badge classes for an employee's salary status (Monthly Salary spec s17).
 */
function salary_status_badge(string $status): string
{
    return match ($status) {
        'pending'            => 'bg-slate-100 text-slate-600 ring-slate-500/20',
        'partially_settled'  => 'bg-amber-50 text-amber-700 ring-amber-600/20',
        'fully_settled'      => 'bg-emerald-50 text-emerald-700 ring-emerald-600/20',
        default              => 'bg-slate-100 text-slate-600 ring-slate-500/20',
    };
}

/**
 * Human readable label for an employee's salary status.
 */
function salary_status_label(string $status): string
{
    return match ($status) {
        'pending'           => 'Pending',
        'partially_settled' => 'Partially Settled',
        'fully_settled'     => 'Fully Settled',
        default             => ucfirst($status),
    };
}

/**
 * Badge classes for a project's computed payment status (Payment Management).
 */
function payment_status_badge(string $status): string
{
    return match ($status) {
        'due'              => 'bg-slate-100 text-slate-600 ring-slate-500/20',
        'advance_received' => 'bg-sky-50 text-sky-700 ring-sky-600/20',
        'partial'          => 'bg-amber-50 text-amber-700 ring-amber-600/20',
        'paid'             => 'bg-emerald-50 text-emerald-700 ring-emerald-600/20',
        'overdue'          => 'bg-red-50 text-red-700 ring-red-600/20',
        default            => 'bg-slate-100 text-slate-600 ring-slate-500/20',
    };
}

/**
 * Human readable label for a project's computed payment status.
 */
function payment_status_label(string $status): string
{
    return match ($status) {
        'due'              => 'Payment Due',
        'advance_received' => 'Advance Received',
        'partial'          => 'Partially Paid',
        'paid'             => 'Fully Paid',
        'overdue'          => 'Overdue',
        default            => ucfirst($status),
    };
}

/**
 * Human readable label for a payment's type.
 */
function payment_type_label(string $type): string
{
    return match ($type) {
        'advance'   => 'Advance Payment',
        'full'      => 'Full Payment',
        'milestone' => 'Milestone Payment',
        'partial'   => 'Partial Payment',
        'final'     => 'Final Payment',
        'other'     => 'Other',
        default     => ucfirst($type),
    };
}

/**
 * Human readable label for a payment's method.
 */
function payment_method_label(string $method): string
{
    return match ($method) {
        'cash'          => 'Cash',
        'bank_transfer' => 'Bank Transfer',
        'upi'           => 'UPI',
        'cheque'        => 'Cheque',
        'card'          => 'Card',
        'other'         => 'Other',
        default         => ucfirst(str_replace('_', ' ', $method)),
    };
}

/**
 * A phone number reduced to the digits-only international form WhatsApp's
 * click-to-chat links expect, or null when it cannot plausibly be one.
 *
 * WhatsApp will not open a chat for a number written the way people type it
 * locally ("+91 98765-43210", "098765 43210"), and it never uses the phone's
 * address book - so a number normalised here opens the chat whether or not
 * the admin has the photographer saved as a contact.
 *
 * $countryCode is only applied to a bare 10-digit local number; anything
 * already carrying its country code is left alone.
 */
function whatsapp_number(?string $phone, string $countryCode = '91'): ?string
{
    if ($phone === null) {
        return null;
    }

    $digits = preg_replace('/\D+/', '', $phone) ?? '';

    if ($digits === '') {
        return null;
    }

    // "00" international prefix, e.g. 0091 98765 43210.
    if (str_starts_with($digits, '00')) {
        $digits = substr($digits, 2);
    }

    // A local number written with its trunk "0", e.g. 0 98765 43210.
    if (strlen($digits) === 11 && str_starts_with($digits, '0')) {
        $digits = substr($digits, 1);
    }

    // A bare local number - prefix the country code so the link resolves.
    if (strlen($digits) === 10) {
        $digits = $countryCode . $digits;
    }

    // Shorter than this is not a dialable mobile number; longer than 15 is
    // beyond what E.164 allows, so in either case offer no link at all
    // rather than one that opens WhatsApp on a dead number.
    return strlen($digits) >= 11 && strlen($digits) <= 15 ? $digits : null;
}

/**
 * A WhatsApp click-to-chat link for $phone, with $message pre-typed into the
 * chat box, or null when the number cannot be normalised - callers hide the
 * WhatsApp action entirely in that case rather than render a broken link.
 */
function whatsapp_url(?string $phone, string $message = '', string $countryCode = '91'): ?string
{
    $number = whatsapp_number($phone, $countryCode);

    if ($number === null) {
        return null;
    }

    $message = trim($message);

    return 'https://wa.me/' . $number . ($message !== '' ? '?text=' . rawurlencode($message) : '');
}
