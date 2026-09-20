<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Photographer;
use App\Models\Payment;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;

/**
 * The constraint layer in front of every report.
 *
 * Without it a report can quietly lie: the models behind the reports ignore a
 * filter value they do not recognise, so "status=completd" or an employee id
 * that no longer exists would silently widen the report back to every record
 * while its header still claimed a filter was applied. A report that says
 * "one photographer" but prints the whole company is worse than no report, so
 * every incoming value is checked here first and a bad one stops the report
 * instead of quietly changing what it covers.
 *
 * It also resolves ids to names, so a generated report reads
 * "Employee: Priya Sharma" rather than "Employee ID: 7".
 */
final class ReportFilters
{
    private const MAX_SEARCH = 100;

    /**
     * @param array<string, string>                  $values  clean, non-empty filters only
     * @param array<string, string>                  $errors  field => message
     * @param list<array{label:string,value:string}> $applied resolved, human-readable
     */
    private function __construct(
        public readonly string $type,
        public readonly array $values,
        public readonly array $errors,
        public readonly array $applied,
    ) {
    }

    /**
     * Filters that must be chosen before a report can be generated at all:
     * an "Employee Work Report" without an employee is not a wider report,
     * it is a different one, so it is refused rather than silently answered
     * with every employee's work.
     *
     * @return list<string>
     */
    public static function requiredKeys(string $type): array
    {
        return match ($type) {
            ReportService::TYPE_EMPLOYEE_WORK     => ['employee_id'],
            ReportService::TYPE_PHOTOGRAPHER_WORK => ['photographer_id'],
            default                               => [],
        };
    }

    /**
     * @param array<string, string> $raw the whitelisted query values
     */
    public static function make(string $type, array $raw): self
    {
        $values  = [];
        $errors  = [];
        $applied = [];
        $labels  = [];

        foreach (ReportService::filterKeys($type) as $key) {
            $value = trim((string) ($raw[$key] ?? ''));

            if ($value === '') {
                continue;
            }

            $result = self::check($type, $key, $value);

            if ($result['error'] !== null) {
                $errors[$key] = $result['error'];

                continue;
            }

            $values[$key] = $result['value'];
            $labels[$key] = $result['label'];
        }

        foreach (self::requiredKeys($type) as $key) {
            if (!isset($values[$key]) && !isset($errors[$key])) {
                $errors[$key] = self::label($key) . ' is required for this report - pick one before generating it.';
            }
        }

        // Cross-field: a range that runs backwards returns an empty report
        // with no hint as to why, so it is refused outright.
        if (isset($values['start_date'], $values['end_date']) && $values['start_date'] > $values['end_date']) {
            $errors['end_date'] = 'The "To" date cannot be earlier than the "From" date.';
        }

        if ($errors === []) {
            foreach ($values as $key => $value) {
                $applied[] = ['label' => self::label($key), 'value' => $labels[$key] ?? $value];
            }
        }

        return new self($type, $values, $errors, $applied);
    }

    /**
     * Validate one filter and resolve its display label.
     *
     * @return array{value: string, label: string, error: string|null}
     */
    private static function check(string $type, string $key, string $value): array
    {
        return match ($key) {
            'q' => mb_strlen($value) > self::MAX_SEARCH
                ? self::bad('Search is limited to ' . self::MAX_SEARCH . ' characters.')
                : self::ok($value),

            'status' => in_array($value, self::statusesFor($type), true)
                ? self::ok($value, self::statusLabel($type, $value))
                : self::bad('That status does not exist for this report.'),

            'role' => in_array($value, [User::ROLE_MANAGER, User::ROLE_EMPLOYEE], true)
                ? self::ok($value, ucfirst($value))
                : self::bad('Role must be either Manager or Employee.'),

            'priority' => in_array($value, Task::priorities(), true)
                ? self::ok($value, task_priority_label($value))
                : self::bad('That priority does not exist.'),

            'type' => in_array($value, Payment::types(), true)
                ? self::ok($value, payment_type_label($value))
                : self::bad('That payment type does not exist.'),

            'method' => in_array($value, Payment::methods(), true)
                ? self::ok($value, payment_method_label($value))
                : self::bad('That payment method does not exist.'),

            'month' => SalaryService::isValidMonth($value)
                ? self::ok($value, pretty_month($value))
                : self::bad('Salary month must be a real month, e.g. 2026-09.'),

            'start_date', 'end_date' => self::isDate($value)
                ? self::ok($value, date('d M Y', (int) strtotime($value)))
                : self::bad('Enter a valid date (YYYY-MM-DD).'),

            'photographer_id' => self::resolvePhotographer($value),
            'project_id'  => self::resolveProject($value),
            'employee_id' => self::resolveEmployee($value),

            default => self::ok($value),
        };
    }

    /**
     * @return array{value: string, label: string, error: null}
     */
    private static function ok(string $value, ?string $label = null): array
    {
        return ['value' => $value, 'label' => $label ?? $value, 'error' => null];
    }

    /**
     * @return array{value: string, label: string, error: string}
     */
    private static function bad(string $message): array
    {
        return ['value' => '', 'label' => '', 'error' => $message];
    }

    /**
     * @return array{value: string, label: string, error: string|null}
     */
    private static function resolvePhotographer(string $value): array
    {
        if (!self::isId($value)) {
            return self::bad('Pick a photographer from the list.');
        }

        $photographer = Photographer::findById((int) $value);

        return $photographer === null
            ? self::bad('That photographer no longer exists.')
            : self::ok((string) $photographer->id, $photographer->name);
    }

    /**
     * @return array{value: string, label: string, error: string|null}
     */
    private static function resolveProject(string $value): array
    {
        if (!self::isId($value)) {
            return self::bad('Pick a project from the list.');
        }

        $project = Project::findById((int) $value);

        // A project has no name of its own - it is identified by the customer
        // the shoot is for, under the photographer who gave the work.
        return $project === null
            ? self::bad('That project no longer exists.')
            : self::ok((string) $project->id, project_title($project));
    }

    /**
     * An employee filter must land on a real Employee account.
     *
     * Not an Admin, not a Manager, and not a deleted id: work is only ever
     * assigned to Employees and salary is only ever credited to them, so any
     * other id would produce a confidently empty report - or, further down,
     * a 404 from the service that finally looks the employee up. Rejecting it
     * here turns that into a message against the field instead.
     *
     * @return array{value: string, label: string, error: string|null}
     */
    private static function resolveEmployee(string $value): array
    {
        if (!self::isId($value)) {
            return self::bad('Pick an employee from the list.');
        }

        $user = User::findById((int) $value);

        if ($user === null) {
            return self::bad('That employee no longer exists.');
        }

        if ($user->role !== User::ROLE_EMPLOYEE) {
            return self::bad('Only an Employee can be reported on here - work is not assigned to ' . $user->roleLabel() . ' accounts.');
        }

        return self::ok((string) $user->id, $user->name);
    }

    /**
     * The statuses each report's Status filter may hold - a report never
     * accepts a status belonging to a different module.
     *
     * @return list<string>
     */
    public static function statusesFor(string $type): array
    {
        return match ($type) {
            ReportService::TYPE_PHOTOGRAPHERS => [Photographer::STATUS_ACTIVE, Photographer::STATUS_INACTIVE],
            ReportService::TYPE_EMPLOYEES    => [User::STATUS_ACTIVE, User::STATUS_INACTIVE, User::STATUS_SUSPENDED],
            // The photographer's work report lists that photographer's
            // projects, so its Status filter is a project status, not a
            // photographer one.
            ReportService::TYPE_PROJECTS, ReportService::TYPE_PHOTOGRAPHER_WORK => Project::statuses(),
            ReportService::TYPE_TASKS, ReportService::TYPE_EMPLOYEE_WORK        => Task::statuses(),
            default                          => [],
        };
    }

    private static function statusLabel(string $type, string $value): string
    {
        return match ($type) {
            ReportService::TYPE_PROJECTS, ReportService::TYPE_PHOTOGRAPHER_WORK => project_status_label($value),
            ReportService::TYPE_TASKS, ReportService::TYPE_EMPLOYEE_WORK        => task_status_label($value),
            default                                                             => ucfirst($value),
        };
    }

    private static function isId(string $value): bool
    {
        return preg_match('/^[1-9][0-9]*$/', $value) === 1;
    }

    private static function isDate(string $value): bool
    {
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) !== 1) {
            return false;
        }

        [$year, $month, $day] = array_map('intval', explode('-', $value));

        return checkdate($month, $day, $year);
    }

    public static function label(string $key): string
    {
        return match ($key) {
            'q'           => 'Search',
            'status'      => 'Status',
            'role'        => 'Role',
            'priority'    => 'Priority',
            'type'        => 'Payment type',
            'method'      => 'Payment method',
            'month'       => 'Salary month',
            'start_date'  => 'From',
            'end_date'    => 'To',
            'photographer_id' => 'Photographer',
            'employee_id' => 'Employee',
            'project_id'  => 'Project',
            default       => ucfirst(str_replace('_', ' ', $key)),
        };
    }

    public function isValid(): bool
    {
        return $this->errors === [];
    }

    public function firstError(): ?string
    {
        foreach ($this->errors as $message) {
            return $message;
        }

        return null;
    }

    public function has(string $key): bool
    {
        return ($this->values[$key] ?? '') !== '';
    }

    public function get(string $key, string $default = ''): string
    {
        return $this->values[$key] ?? $default;
    }

    public function int(string $key): ?int
    {
        return $this->has($key) ? (int) $this->values[$key] : null;
    }

    /**
     * The applied filters as a query string, so the preview page's download
     * buttons regenerate exactly the report shown on screen.
     */
    public function queryString(): string
    {
        return http_build_query($this->values);
    }
}
