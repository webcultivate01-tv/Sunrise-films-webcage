<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Payment;
use App\Models\Photographer;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Services\ReportFilters;
use App\Services\ReportService;

/**
 * The filter fields of a report card, built once and rendered in both places
 * a report is asked for: the Reports index, where every card carries its own
 * blank form, and a report's own preview page, where the same form comes back
 * filled in with what produced the report on screen.
 *
 * Which fields a report has is not decided here - it is read straight off
 * ReportService::filterKeys(), the same whitelist the controller filters the
 * query string through. A field can therefore never appear on a form that the
 * server would then ignore, nor go missing from one it accepts.
 */
final class ReportFields
{
    private const INPUT = 'block w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm text-ink placeholder:text-slate-400 transition focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-200';
    private const INPUT_ERROR = 'block w-full rounded-lg border border-red-300 bg-red-50/40 px-3 py-2 text-sm text-ink transition focus:border-red-400 focus:outline-none focus:ring-2 focus:ring-red-200';
    private const LABEL = 'mb-1 block text-xs font-medium text-slate-500';

    /**
     * @param list<Photographer>    $photographers
     * @param list<Project>         $projects
     * @param list<User>            $employees
     * @param array<string, string> $values selected values, to fill the form back in
     * @param array<string, string> $errors field => message, from ReportFilters
     */
    public static function render(
        string $type,
        string $panelBase,
        array $photographers,
        array $projects,
        array $employees,
        array $values = [],
        array $errors = [],
    ): string {
        $html = '';

        foreach (ReportService::filterKeys($type) as $key) {
            $html .= self::field($type, $key, $panelBase, $photographers, $projects, $employees, $values, $errors);
        }

        return $html;
    }

    /**
     * @param list<Photographer>    $photographers
     * @param list<Project>         $projects
     * @param list<User>            $employees
     * @param array<string, string> $values
     * @param array<string, string> $errors
     */
    private static function field(
        string $type,
        string $key,
        string $panelBase,
        array $photographers,
        array $projects,
        array $employees,
        array $values,
        array $errors,
    ): string {
        $value    = $values[$key] ?? '';
        $error    = $errors[$key] ?? null;
        $required = in_array($key, ReportFilters::requiredKeys($type), true);

        return match ($key) {
            'q' => self::search($type, $panelBase, $value, $error),

            'status' => self::select($key, 'Status', self::statusOptions($type), $value, $error, $required),

            'role' => self::select($key, 'Role', [
                '' => 'All roles',
                User::ROLE_MANAGER => 'Manager',
                User::ROLE_EMPLOYEE => 'Employee',
            ], $value, $error, $required),

            'priority' => self::select($key, 'Priority', self::options(
                'All priorities',
                Task::priorities(),
                static fn (string $v): string => task_priority_label($v),
            ), $value, $error, $required),

            'type' => self::select($key, 'Payment type', self::options(
                'All types',
                Payment::types(),
                static fn (string $v): string => payment_type_label($v),
            ), $value, $error, $required),

            'method' => self::select($key, 'Payment method', self::options(
                'All methods',
                Payment::methods(),
                static fn (string $v): string => payment_method_label($v),
            ), $value, $error, $required),

            'photographer_id' => self::select(
                $key,
                'Photographer',
                self::photographerOptions($photographers, $required),
                $value,
                $error,
                $required,
            ),

            'employee_id' => self::select(
                $key,
                'Employee',
                self::employeeOptions($employees, $required),
                $value,
                $error,
                $required,
            ),

            'project_id' => self::select($key, 'Project', self::projectOptions($projects), $value, $error, $required),

            'month' => self::input($key, 'Salary month', 'month', $value, $error, $required),

            'start_date' => self::input(
                $key,
                $type === ReportService::TYPE_SALARY ? 'Settled from' : 'From',
                'date',
                $value,
                $error,
                $required,
            ),

            'end_date' => self::input(
                $key,
                $type === ReportService::TYPE_SALARY ? 'Settled to' : 'To',
                'date',
                $value,
                $error,
                $required,
            ),

            default => '',
        };
    }

    /**
     * Each report's Search box reuses that module's own live "/suggest"
     * endpoint - the same one behind its own search box - but fills the picked
     * value into the field instead of navigating away, since here it only
     * feeds a filter form rather than linking to a record page.
     */
    private static function search(string $type, string $panelBase, string $value, ?string $error): string
    {
        [$path, $placeholder] = match ($type) {
            ReportService::TYPE_PHOTOGRAPHERS => ['/photographers/suggest', 'Name, email or phone'],
            ReportService::TYPE_EMPLOYEES     => ['/employees/suggest', 'Name, email or phone'],
            ReportService::TYPE_PROJECTS      => ['/projects/suggest', 'Customer, folder or photographer'],
            ReportService::TYPE_TASKS         => ['/tasks/suggest', 'Task, customer or employee'],
            ReportService::TYPE_PAYMENTS      => ['/payments/history/suggest', 'Customer, photographer or reference'],
            ReportService::TYPE_SALARY        => ['/monthly-salary/history/suggest', 'Employee name, email or reference'],
            default                           => ['', 'Search'],
        };

        return '
            <div class="relative col-span-2 min-w-0" data-suggest data-suggest-mode="fill" data-suggest-url="' . e($panelBase . $path) . '">
                <label class="' . self::LABEL . '" for="' . e('filter-q') . '">Search</label>
                <input type="text" id="filter-q" name="q" value="' . e($value) . '" placeholder="' . e($placeholder) . '"
                       autocomplete="off" class="' . ($error === null ? self::INPUT : self::INPUT_ERROR) . '" data-suggest-input>
                <ul data-suggest-list class="absolute left-0 right-0 top-full z-20 mt-1 hidden max-h-72 overflow-y-auto rounded-lg border border-line bg-white py-1 text-sm shadow-lg"></ul>
                ' . self::error($error) . '
            </div>';
    }

    /**
     * @param array<string, string> $options value => label
     */
    private static function select(string $name, string $label, array $options, string $value, ?string $error, bool $required): string
    {
        $markup = '';

        foreach ($options as $optionValue => $optionLabel) {
            $markup .= '<option value="' . e((string) $optionValue) . '"'
                . ((string) $optionValue === $value ? ' selected' : '') . '>' . e($optionLabel) . '</option>';
        }

        return '
            <div class="min-w-0">
                <label class="' . self::LABEL . '">' . e($label) . self::requiredMark($required) . '</label>
                <select name="' . e($name) . '" class="' . ($error === null ? self::INPUT : self::INPUT_ERROR) . '"'
                    . ($required ? ' required' : '') . '>' . $markup . '</select>
                ' . self::error($error) . '
            </div>';
    }

    private static function input(string $name, string $label, string $inputType, string $value, ?string $error, bool $required): string
    {
        return '
            <div class="min-w-0">
                <label class="' . self::LABEL . '">' . e($label) . self::requiredMark($required) . '</label>
                <input type="' . e($inputType) . '" name="' . e($name) . '" value="' . e($value) . '"
                       class="' . ($error === null ? self::INPUT : self::INPUT_ERROR) . '"' . ($required ? ' required' : '') . '>
                ' . self::error($error) . '
            </div>';
    }

    /**
     * A required filter has no "All ..." option at all - the only way to leave
     * it unset is to not choose, which the form blocks and ReportFilters
     * refuses, so the report can never silently widen to everyone.
     *
     * @param  list<Photographer> $photographers
     * @return array<string, string>
     */
    private static function photographerOptions(array $photographers, bool $required): array
    {
        $options = ['' => $required ? 'Choose a photographer...' : 'All photographers'];

        foreach ($photographers as $photographer) {
            $options[(string) $photographer->id] = $photographer->name;
        }

        return $options;
    }

    /**
     * @param  list<User> $employees
     * @return array<string, string>
     */
    private static function employeeOptions(array $employees, bool $required): array
    {
        // Employees only - work is never assigned to a Manager or an Admin, and
        // ReportFilters refuses one, so neither may be offered here either.
        $options = ['' => $required ? 'Choose an employee...' : 'All employees'];

        foreach ($employees as $employee) {
            $options[(string) $employee->id] = $employee->name;
        }

        return $options;
    }

    /**
     * @param  list<Project> $projects
     * @return array<string, string>
     */
    private static function projectOptions(array $projects): array
    {
        $options = ['' => 'All projects'];

        foreach ($projects as $project) {
            $options[(string) $project->id] = project_title($project)
                . ($project->photographerName === null ? '' : ' (' . $project->photographerName . ')');
        }

        return $options;
    }

    /**
     * @return array<string, string>
     */
    private static function statusOptions(string $type): array
    {
        $labeller = match ($type) {
            ReportService::TYPE_PROJECTS, ReportService::TYPE_PHOTOGRAPHER_WORK => static fn (string $v): string => project_status_label($v),
            ReportService::TYPE_TASKS, ReportService::TYPE_EMPLOYEE_WORK        => static fn (string $v): string => task_status_label($v),
            default                                                             => static fn (string $v): string => ucfirst($v),
        };

        return self::options('All statuses', ReportFilters::statusesFor($type), $labeller);
    }

    /**
     * @param  list<string>            $values
     * @param  callable(string):string $labeller
     * @return array<string, string>
     */
    private static function options(string $blank, array $values, callable $labeller): array
    {
        $options = ['' => $blank];

        foreach ($values as $value) {
            $options[$value] = $labeller($value);
        }

        return $options;
    }

    private static function requiredMark(bool $required): string
    {
        return $required ? ' <span class="text-red-500" title="Required">*</span>' : '';
    }

    private static function error(?string $message): string
    {
        return $message === null ? '' : '<p class="mt-1 text-xs font-medium text-red-600">' . e($message) . '</p>';
    }
}
