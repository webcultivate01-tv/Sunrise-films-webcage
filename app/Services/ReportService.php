<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Exceptions\HttpException;
use App\Models\Payment;
use App\Models\Photographer;
use App\Models\Project;
use App\Models\SalarySettlement;
use App\Models\Task;
use App\Models\User;
use App\Support\ReportExcel;
use App\Support\ReportPdf;

/**
 * Reports (spec: one page of report cards for Admin and Manager, each card
 * generating its own report from its own filters, downloadable as PDF or
 * Excel).
 *
 * Every report is built the same way: assertAccess(), run the raw query
 * values through ReportFilters - so a value the underlying model would have
 * ignored stops the report instead of silently widening it - pull the rows
 * from the same models every other module already queries against, then
 * format them into the one column/row/summary/totals shape that the on-screen
 * preview, ReportPdf and ReportExcel all render from.
 *
 * Two of the reports are drill-downs rather than lists: pick one photographer
 * and get every piece of work they have given us, or pick one employee and
 * get every piece of work we have given them. Both refuse to run without that
 * choice, so neither can quietly turn back into "everybody".
 */
final class ReportService
{
    public const TYPE_PHOTOGRAPHERS     = 'photographers';
    public const TYPE_PHOTOGRAPHER_WORK = 'photographer-work';
    public const TYPE_EMPLOYEES         = 'employees';
    public const TYPE_EMPLOYEE_WORK     = 'employee-work';
    public const TYPE_PROJECTS          = 'projects';
    public const TYPE_TASKS             = 'tasks';
    public const TYPE_PAYMENTS          = 'payments';
    public const TYPE_SALARY            = 'salary';

    /** @return list<string> */
    public static function types(): array
    {
        return [
            self::TYPE_PHOTOGRAPHERS, self::TYPE_PHOTOGRAPHER_WORK, self::TYPE_PROJECTS,
            self::TYPE_EMPLOYEES, self::TYPE_EMPLOYEE_WORK, self::TYPE_TASKS,
            self::TYPE_PAYMENTS, self::TYPE_SALARY,
        ];
    }

    public static function canAccess(User $actor): bool
    {
        return in_array($actor->role, [User::ROLE_ADMIN, User::ROLE_MANAGER], true);
    }

    public static function assertAccess(User $actor): void
    {
        if (!self::canAccess($actor)) {
            throw HttpException::forbidden('You are not authorized to access Reports.');
        }
    }

    /**
     * A report type that does not exist is a 404, never an empty report - the
     * single gate every entry point (preview, PDF, Excel) passes through.
     */
    public static function assertType(string $type): void
    {
        if (!in_array($type, self::types(), true)) {
            throw HttpException::notFound('That report does not exist.');
        }
    }

    public static function label(string $type): string
    {
        return match ($type) {
            self::TYPE_PHOTOGRAPHERS     => 'Photographer Report',
            self::TYPE_PHOTOGRAPHER_WORK => 'Photographer Work Report',
            self::TYPE_EMPLOYEES         => 'Employee Report',
            self::TYPE_EMPLOYEE_WORK     => 'Employee Work Report',
            self::TYPE_PROJECTS          => 'Work Report',
            self::TYPE_TASKS             => 'Task Report',
            self::TYPE_PAYMENTS          => 'Payment Report',
            self::TYPE_SALARY            => 'Salary Report',
            default                      => throw HttpException::notFound('That report does not exist.'),
        };
    }

    public static function description(string $type): string
    {
        return match ($type) {
            self::TYPE_PHOTOGRAPHERS     => 'Every photographer on record, narrowed by status and registration date.',
            self::TYPE_PHOTOGRAPHER_WORK => 'Pick one photographer to get every project they have given us - what each was worth, what has been collected and what is still due.',
            self::TYPE_EMPLOYEES         => 'Every Manager and Employee account, narrowed by role, status and join date.',
            self::TYPE_EMPLOYEE_WORK     => 'Pick one employee to get every piece of work given to them, with their earnings and settlement position.',
            self::TYPE_PROJECTS          => 'Every project, narrowed by status, photographer and registration date.',
            self::TYPE_TASKS             => 'Every task across the studio, narrowed by status, priority, employee, project and date range.',
            self::TYPE_PAYMENTS          => 'Every transaction, narrowed by project, photographer, type, method and date range.',
            self::TYPE_SALARY            => 'Every salary settlement, narrowed by employee, salary month and settlement date.',
            default                      => throw HttpException::notFound('That report does not exist.'),
        };
    }

    /**
     * The query-string keys a report's filter card is allowed to submit - the
     * whitelist a controller pulls Request::only() through before anything
     * reaches ReportFilters, which then validates each one.
     *
     * @return list<string>
     */
    public static function filterKeys(string $type): array
    {
        return match ($type) {
            self::TYPE_PHOTOGRAPHERS     => ['q', 'status', 'start_date', 'end_date'],
            self::TYPE_PHOTOGRAPHER_WORK => ['photographer_id', 'status', 'start_date', 'end_date'],
            self::TYPE_EMPLOYEES         => ['q', 'role', 'status', 'start_date', 'end_date'],
            self::TYPE_EMPLOYEE_WORK     => ['employee_id', 'status', 'priority', 'project_id', 'start_date', 'end_date'],
            self::TYPE_PROJECTS          => ['q', 'status', 'photographer_id', 'start_date', 'end_date'],
            self::TYPE_TASKS             => ['q', 'status', 'priority', 'employee_id', 'project_id', 'start_date', 'end_date'],
            self::TYPE_PAYMENTS          => ['q', 'project_id', 'photographer_id', 'type', 'method', 'start_date', 'end_date'],
            self::TYPE_SALARY            => ['q', 'employee_id', 'month', 'start_date', 'end_date'],
            default                      => throw HttpException::notFound('That report does not exist.'),
        };
    }

    /**
     * Validate one report's raw query values. Callers must check isValid()
     * before build(): build() refuses invalid filters outright rather than
     * generating a report that does not match its own header.
     *
     * @param array<string, string> $raw
     */
    public static function filters(string $type, array $raw): ReportFilters
    {
        self::assertType($type);

        return ReportFilters::make($type, $raw);
    }

    /**
     * @return array{
     *     type: string,
     *     title: string,
     *     subtitle: string,
     *     columns: list<array{label:string, width:float, align:string, total?:bool}>,
     *     rows: list<list<string>>,
     *     summary: list<array{label:string, value:string}>,
     *     totals: list<string>|null
     * }
     */
    public static function build(User $actor, string $type, ReportFilters $filters): array
    {
        self::assertAccess($actor);
        self::assertType($type);

        if ($filters->type !== $type) {
            throw new \InvalidArgumentException('Those filters were validated for a different report.');
        }

        if (!$filters->isValid()) {
            throw new \InvalidArgumentException((string) $filters->firstError());
        }

        $report = match ($type) {
            self::TYPE_PHOTOGRAPHERS     => self::buildPhotographers($filters),
            self::TYPE_PHOTOGRAPHER_WORK => self::buildPhotographerWork($actor, $filters),
            self::TYPE_EMPLOYEES         => self::buildEmployees($filters),
            self::TYPE_EMPLOYEE_WORK     => self::buildEmployeeWork($actor, $filters),
            self::TYPE_PROJECTS          => self::buildProjects($filters),
            self::TYPE_TASKS             => self::buildTasks($filters),
            self::TYPE_PAYMENTS          => self::buildPayments($filters),
            self::TYPE_SALARY            => self::buildSalary($filters),
        };

        $report['type']     = $type;
        $report['subtitle'] = $report['subtitle'] ?? '';
        $report['summary']  = $report['summary'] ?? [];
        $report['totals']   = self::totalsRow($report['columns'], $report['rows']);

        return $report;
    }

    /**
     * @return array<string, mixed>
     */
    private static function buildPhotographers(ReportFilters $filters): array
    {
        $rows = Photographer::all(
            $filters->get('q'),
            $filters->get('status'),
            $filters->get('start_date'),
            $filters->get('end_date'),
        );

        $active = count(array_filter($rows, static fn (Photographer $p): bool => $p->status === Photographer::STATUS_ACTIVE));

        return [
            'title'   => self::label(self::TYPE_PHOTOGRAPHERS),
            'summary' => [
                ['label' => 'Photographers', 'value' => (string) count($rows)],
                ['label' => 'Active', 'value' => (string) $active],
                ['label' => 'Inactive', 'value' => (string) (count($rows) - $active)],
            ],
            'columns' => [
                ['label' => 'ID', 'width' => 30, 'align' => 'left'],
                ['label' => 'Name', 'width' => 110, 'align' => 'left'],
                ['label' => 'Email', 'width' => 140, 'align' => 'left'],
                ['label' => 'Phone', 'width' => 90, 'align' => 'left'],
                ['label' => 'Address', 'width' => 160, 'align' => 'left'],
                ['label' => 'Status', 'width' => 60, 'align' => 'left'],
                ['label' => 'Registered By', 'width' => 90, 'align' => 'left'],
                ['label' => 'Registered On', 'width' => 80, 'align' => 'left'],
            ],
            'rows' => array_map(static fn (Photographer $p): array => [
                (string) $p->id,
                $p->name,
                $p->email,
                $p->phone,
                $p->address,
                ucfirst($p->status),
                $p->createdByName ?? '-',
                self::formatDate($p->createdAt),
            ], $rows),
        ];
    }

    /**
     * The per-photographer work report.
     *
     * Pick one photographer and the report is every project they have given
     * us: which of their own customers the shoot was for, what it is worth,
     * how much of that has actually been collected, what is still outstanding,
     * how many tasks it was broken into and where it got to.
     *
     * The photographer is a required filter (ReportFilters::requiredKeys) and
     * is proven to exist before a single row is read, so this report can never
     * quietly become "every photographer", and can never come back
     * confidently empty for an id that no longer exists.
     *
     * @return array<string, mixed>
     */
    private static function buildPhotographerWork(User $actor, ReportFilters $filters): array
    {
        $photographer = PhotographerService::findOrFail($actor, (int) $filters->int('photographer_id'));

        $projects = Project::all(
            '',
            $filters->get('status'),
            $photographer->id,
            '',
            $filters->get('start_date'),
            $filters->get('end_date'),
        );

        $projectIds = array_map(static fn (Project $p): int => $p->id, $projects);
        $collected  = Payment::totalsForProjects($projectIds);
        $taskCounts = Task::countsForProjects($projectIds);

        $value     = array_sum(array_map(static fn (Project $p): float => $p->totalPayment, $projects));
        $received  = array_sum($collected);
        $completed = count(array_filter($projects, static fn (Project $p): bool => $p->status === Project::STATUS_COMPLETED));
        $overdue   = count(array_filter($projects, static fn (Project $p): bool => $p->isOverdue()));

        return [
            'title'    => self::label(self::TYPE_PHOTOGRAPHER_WORK),
            'subtitle' => $photographer->name . '  -  ' . $photographer->phone . '  -  ' . $photographer->email,
            'summary'  => [
                ['label' => 'Work given', 'value' => (string) count($projects)],
                ['label' => 'Completed', 'value' => (string) $completed],
                ['label' => 'Overdue', 'value' => (string) $overdue],
                ['label' => 'Total work value', 'value' => self::money($value)],
                ['label' => 'Collected', 'value' => self::money($received)],
                ['label' => 'Outstanding', 'value' => self::money(max(0.0, $value - $received))],
            ],
            'columns' => [
                ['label' => 'ID', 'width' => 30, 'align' => 'left'],
                ['label' => 'Customer', 'width' => 105, 'align' => 'left'],
                ['label' => 'Folder', 'width' => 92, 'align' => 'left'],
                ['label' => 'Work Value', 'width' => 72, 'align' => 'right', 'total' => true],
                ['label' => 'Collected', 'width' => 72, 'align' => 'right', 'total' => true],
                ['label' => 'Outstanding', 'width' => 70, 'align' => 'right', 'total' => true],
                ['label' => 'Payment', 'width' => 78, 'align' => 'left'],
                ['label' => 'Tasks', 'width' => 40, 'align' => 'right'],
                ['label' => 'Deadline', 'width' => 70, 'align' => 'left'],
                ['label' => 'Status', 'width' => 70, 'align' => 'left'],
                ['label' => 'Given On', 'width' => 65, 'align' => 'left'],
            ],
            'rows' => array_map(static function (Project $p) use ($collected, $taskCounts): array {
                $paid = $collected[$p->id] ?? 0.0;

                return [
                    (string) $p->id,
                    $p->customerName,
                    $p->folderName,
                    self::formatAmount($p->totalPayment),
                    self::formatAmount($paid),
                    self::formatAmount(max(0.0, $p->totalPayment - $paid)),
                    payment_status_label(PaymentService::statusFor($p, $paid)),
                    (string) ($taskCounts[$p->id] ?? 0),
                    self::formatDate($p->deadline),
                    project_status_label($p->status),
                    self::formatDate($p->createdAt),
                ];
            }, $projects),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function buildEmployees(ReportFilters $filters): array
    {
        $rows = User::forReport($filters->values);

        $managers = count(array_filter($rows, static fn (User $u): bool => $u->role === User::ROLE_MANAGER));
        $active   = count(array_filter($rows, static fn (User $u): bool => $u->status === User::STATUS_ACTIVE));

        return [
            'title'   => self::label(self::TYPE_EMPLOYEES),
            'summary' => [
                ['label' => 'Accounts', 'value' => (string) count($rows)],
                ['label' => 'Managers', 'value' => (string) $managers],
                ['label' => 'Employees', 'value' => (string) (count($rows) - $managers)],
                ['label' => 'Active', 'value' => (string) $active],
            ],
            'columns' => [
                ['label' => 'ID', 'width' => 30, 'align' => 'left'],
                ['label' => 'Name', 'width' => 140, 'align' => 'left'],
                ['label' => 'Email', 'width' => 180, 'align' => 'left'],
                ['label' => 'Phone', 'width' => 100, 'align' => 'left'],
                ['label' => 'Role', 'width' => 80, 'align' => 'left'],
                ['label' => 'Status', 'width' => 80, 'align' => 'left'],
                ['label' => 'Joined On', 'width' => 110, 'align' => 'left'],
            ],
            'rows' => array_map(static fn (User $u): array => [
                (string) $u->id,
                $u->name,
                $u->email,
                $u->phone ?? '-',
                $u->roleLabel(),
                ucfirst($u->status),
                self::formatDate($u->createdAt),
            ], $rows),
        ];
    }

    /**
     * The per-employee work report: pick one employee and the report is every
     * piece of work given to them - whose shoot it belonged to, where it got
     * to and what it was worth.
     *
     * It carries no summary block of its own: the report is the list of work,
     * and the employee's earnings and settlement position are the Salary
     * Report's answer to give, not a second one printed above this table.
     *
     * As with the photographer report, the employee is a required filter and
     * is proven to be a real Manager/Employee account before any row is read.
     *
     * @return array<string, mixed>
     */
    private static function buildEmployeeWork(User $actor, ReportFilters $filters): array
    {
        $employee = SalaryService::findEmployeeOrFail($actor, (int) $filters->int('employee_id'));

        $rows = Task::all([
            'employee_id' => (string) $employee->id,
            'status'      => $filters->get('status'),
            'priority'    => $filters->get('priority'),
            'project_id'  => $filters->get('project_id'),
            'start_date'  => $filters->get('start_date'),
            'end_date'    => $filters->get('end_date'),
        ], null);

        return [
            'title'    => self::label(self::TYPE_EMPLOYEE_WORK),
            'subtitle' => $employee->name . '  -  ' . $employee->roleLabel() . '  -  ' . $employee->email,
            'summary'  => [],
            'columns'  => [
                ['label' => 'Task', 'width' => 35, 'align' => 'left'],
                ['label' => 'Work Given', 'width' => 104, 'align' => 'left'],
                ['label' => 'Customer', 'width' => 95, 'align' => 'left'],
                ['label' => 'Photographer', 'width' => 95, 'align' => 'left'],
                ['label' => 'Priority', 'width' => 50, 'align' => 'left'],
                ['label' => 'Status', 'width' => 70, 'align' => 'left'],
                ['label' => 'Progress', 'width' => 50, 'align' => 'right'],
                ['label' => 'Assigned On', 'width' => 65, 'align' => 'left'],
                ['label' => 'Deadline', 'width' => 65, 'align' => 'left'],
                ['label' => 'Completed On', 'width' => 70, 'align' => 'left'],
                ['label' => 'Amount', 'width' => 65, 'align' => 'right', 'total' => true],
            ],
            'rows' => array_map(static fn (Task $t): array => [
                (string) $t->id,
                $t->title,
                $t->customerName ?? '-',
                $t->photographerName ?? '-',
                task_priority_label($t->priority),
                task_status_label($t->status),
                $t->progress . '%',
                self::formatDate($t->startDate),
                self::formatDate($t->endDate),
                self::formatDate($t->completedAt),
                self::formatAmount($t->amount),
            ], $rows),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function buildProjects(ReportFilters $filters): array
    {
        $projects = Project::all(
            $filters->get('q'),
            $filters->get('status'),
            $filters->int('photographer_id'),
            '',
            $filters->get('start_date'),
            $filters->get('end_date'),
        );

        $projectIds = array_map(static fn (Project $p): int => $p->id, $projects);
        $collected  = Payment::totalsForProjects($projectIds);

        $value     = array_sum(array_map(static fn (Project $p): float => $p->totalPayment, $projects));
        $received  = array_sum($collected);
        $completed = count(array_filter($projects, static fn (Project $p): bool => $p->status === Project::STATUS_COMPLETED));
        $overdue   = count(array_filter($projects, static fn (Project $p): bool => $p->isOverdue()));

        return [
            'title'    => self::label(self::TYPE_PROJECTS),
            'subtitle' => $filters->has('photographer_id')
                ? 'Work given by ' . self::photographerName($filters->int('photographer_id'))
                : 'Every photographer',
            'summary'  => [
                ['label' => 'Projects', 'value' => (string) count($projects)],
                ['label' => 'Completed', 'value' => (string) $completed],
                ['label' => 'Overdue', 'value' => (string) $overdue],
                ['label' => 'Total value', 'value' => self::money($value)],
                ['label' => 'Collected', 'value' => self::money($received)],
                ['label' => 'Outstanding', 'value' => self::money(max(0.0, $value - $received))],
            ],
            'columns' => [
                ['label' => 'ID', 'width' => 30, 'align' => 'left'],
                ['label' => 'Photographer', 'width' => 110, 'align' => 'left'],
                ['label' => 'Customer', 'width' => 110, 'align' => 'left'],
                ['label' => 'Folder', 'width' => 89, 'align' => 'left'],
                ['label' => 'Work Value', 'width' => 75, 'align' => 'right', 'total' => true],
                ['label' => 'Collected', 'width' => 75, 'align' => 'right', 'total' => true],
                ['label' => 'Deadline', 'width' => 70, 'align' => 'left'],
                ['label' => 'Status', 'width' => 70, 'align' => 'left'],
                ['label' => 'Created By', 'width' => 65, 'align' => 'left'],
                ['label' => 'Created On', 'width' => 70, 'align' => 'left'],
            ],
            'rows' => array_map(static fn (Project $p): array => [
                (string) $p->id,
                $p->photographerName ?? '-',
                $p->customerName,
                $p->folderName,
                self::formatAmount($p->totalPayment),
                self::formatAmount($collected[$p->id] ?? 0.0),
                self::formatDate($p->deadline),
                project_status_label($p->status),
                $p->createdByName ?? '-',
                self::formatDate($p->createdAt),
            ], $projects),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function buildTasks(ReportFilters $filters): array
    {
        $rows = Task::all($filters->values, null);

        return [
            'title'    => self::label(self::TYPE_TASKS),
            'subtitle' => $filters->has('employee_id')
                ? 'Work given to ' . self::employeeName($filters->int('employee_id'))
                : 'Every employee',
            'summary'  => self::taskSummary($rows),
            'columns'  => [
                ['label' => 'ID', 'width' => 25, 'align' => 'left'],
                ['label' => 'Title', 'width' => 100, 'align' => 'left'],
                ['label' => 'Customer', 'width' => 92, 'align' => 'left'],
                ['label' => 'Photographer', 'width' => 92, 'align' => 'left'],
                ['label' => 'Employee', 'width' => 85, 'align' => 'left'],
                ['label' => 'Priority', 'width' => 55, 'align' => 'left'],
                ['label' => 'Status', 'width' => 70, 'align' => 'left'],
                ['label' => 'Progress', 'width' => 50, 'align' => 'right'],
                ['label' => 'Start Date', 'width' => 65, 'align' => 'left'],
                ['label' => 'End Date', 'width' => 65, 'align' => 'left'],
                ['label' => 'Amount', 'width' => 65, 'align' => 'right', 'total' => true],
            ],
            'rows' => array_map(static fn (Task $t): array => [
                (string) $t->id,
                $t->title,
                $t->customerName ?? '-',
                $t->photographerName ?? '-',
                $t->employeeName ?? '-',
                task_priority_label($t->priority),
                task_status_label($t->status),
                $t->progress . '%',
                self::formatDate($t->startDate),
                self::formatDate($t->endDate),
                self::formatAmount($t->amount),
            ], $rows),
        ];
    }

    /**
     * The status breakdown both task-shaped reports close with.
     *
     * @param  list<Task> $rows
     * @return list<array{label:string, value:string}>
     */
    private static function taskSummary(array $rows): array
    {
        $countOf = static fn (string $status): int => count(
            array_filter($rows, static fn (Task $t): bool => $t->status === $status),
        );

        $inHand = $countOf(Task::STATUS_ASSIGNED) + $countOf(Task::STATUS_ACCEPTED) + $countOf(Task::STATUS_IN_PROGRESS);

        return [
            ['label' => 'Work given', 'value' => (string) count($rows)],
            ['label' => 'Completed', 'value' => (string) $countOf(Task::STATUS_COMPLETED)],
            ['label' => 'In hand', 'value' => (string) $inHand],
            ['label' => 'Exited', 'value' => (string) $countOf(Task::STATUS_EXITED)],
            ['label' => 'Reassigned', 'value' => (string) $countOf(Task::STATUS_REASSIGNED)],
            ['label' => 'Work value', 'value' => self::money(array_sum(array_map(static fn (Task $t): float => $t->amount, $rows)))],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function buildPayments(ReportFilters $filters): array
    {
        $rows = Payment::all($filters->values);

        $total = array_sum(array_map(static fn (Payment $p): float => $p->amount, $rows));

        return [
            'title'    => self::label(self::TYPE_PAYMENTS),
            'subtitle' => $filters->has('photographer_id')
                ? 'Received against work from ' . self::photographerName($filters->int('photographer_id'))
                : 'Every photographer',
            'summary'  => [
                ['label' => 'Transactions', 'value' => (string) count($rows)],
                ['label' => 'Total collected', 'value' => self::money($total)],
                ['label' => 'Average', 'value' => self::money($rows === [] ? 0.0 : $total / count($rows))],
            ],
            'columns' => [
                ['label' => 'ID', 'width' => 25, 'align' => 'left'],
                ['label' => 'Date', 'width' => 70, 'align' => 'left'],
                ['label' => 'Customer', 'width' => 110, 'align' => 'left'],
                ['label' => 'Photographer', 'width' => 105, 'align' => 'left'],
                ['label' => 'Type', 'width' => 80, 'align' => 'left'],
                ['label' => 'Method', 'width' => 85, 'align' => 'left'],
                ['label' => 'Reference', 'width' => 85, 'align' => 'left'],
                ['label' => 'Amount', 'width' => 80, 'align' => 'right', 'total' => true],
                ['label' => 'Received By', 'width' => 90, 'align' => 'left'],
            ],
            'rows' => array_map(static fn (Payment $p): array => [
                (string) $p->id,
                self::formatDate($p->paymentDate),
                $p->customerName ?? '-',
                $p->photographerName ?? '-',
                payment_type_label($p->paymentType),
                payment_method_label($p->paymentMethod),
                $p->referenceNo ?? '-',
                self::formatAmount($p->amount),
                $p->receivedByName ?? '-',
            ], $rows),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function buildSalary(ReportFilters $filters): array
    {
        $rows = SalarySettlement::allForScope(null, $filters->values);

        $paid = array_sum(array_map(static fn (SalarySettlement $s): float => $s->amount, $rows));

        $summary = [
            ['label' => 'Settlements', 'value' => (string) count($rows)],
            ['label' => 'Total settled', 'value' => self::money($paid)],
        ];

        // A single-employee salary report can close with that employee's live
        // position, which a list of past settlements alone does not show.
        if ($filters->has('employee_id')) {
            $totals    = SalaryService::totals((int) $filters->int('employee_id'));
            $summary[] = ['label' => 'Lifetime earned', 'value' => self::money($totals['earned'])];
            $summary[] = ['label' => 'Lifetime settled', 'value' => self::money($totals['paid'])];
            $summary[] = ['label' => 'Outstanding', 'value' => self::money($totals['outstanding'])];
        }

        return [
            'title'    => self::label(self::TYPE_SALARY),
            'subtitle' => $filters->has('employee_id') ? self::employeeName($filters->int('employee_id')) : 'Every employee',
            'summary'  => $summary,
            'columns'  => [
                ['label' => 'Reference', 'width' => 75, 'align' => 'left'],
                ['label' => 'Employee', 'width' => 120, 'align' => 'left'],
                ['label' => 'Salary Month', 'width' => 65, 'align' => 'left'],
                ['label' => 'Amount', 'width' => 75, 'align' => 'right', 'total' => true],
                ['label' => 'Month Earned', 'width' => 80, 'align' => 'right'],
                ['label' => 'Prev. Settled', 'width' => 80, 'align' => 'right'],
                ['label' => 'Outstanding', 'width' => 85, 'align' => 'right'],
                ['label' => 'Settled By', 'width' => 90, 'align' => 'left'],
                ['label' => 'Settled At', 'width' => 85, 'align' => 'left'],
            ],
            'rows' => array_map(static fn (SalarySettlement $s): array => [
                $s->referenceNo,
                $s->employeeNameSnapshot,
                pretty_month($s->salaryMonth),
                self::formatAmount($s->amount),
                self::formatAmount($s->monthEarned),
                self::formatAmount($s->previousSettled),
                self::formatAmount($s->outstandingAfter),
                $s->settledByName ?? '-',
                self::formatDate($s->settledAt),
            ], $rows),
        ];
    }

    /**
     * A totals line for every column flagged 'total', so a money column in a
     * report adds up on the page instead of in the reader's head.
     *
     * @param  list<array{label:string, width:float, align:string, total?:bool}> $columns
     * @param  list<list<string>>                                                $rows
     * @return list<string>|null null when nothing is summable
     */
    private static function totalsRow(array $columns, array $rows): ?array
    {
        $summable = array_keys(array_filter($columns, static fn (array $c): bool => ($c['total'] ?? false) === true));

        if ($summable === [] || $rows === []) {
            return null;
        }

        $totals    = array_fill(0, count($columns), '');
        $totals[0] = 'TOTAL';

        foreach ($summable as $index) {
            $totals[$index] = self::formatAmount(
                array_sum(array_map(static fn (array $row): float => (float) ($row[$index] ?? 0), $rows)),
            );
        }

        return $totals;
    }

    private static function photographerName(?int $id): string
    {
        $photographer = $id === null ? null : Photographer::findById($id);

        return $photographer?->name ?? 'Unknown photographer';
    }

    private static function employeeName(?int $id): string
    {
        $employee = $id === null ? null : User::findById($id);

        return $employee?->name ?? 'Unknown employee';
    }

    /**
     * Render one built report as a downloadable PDF.
     *
     * @param array<string, mixed> $report
     */
    public static function renderPdf(array $report, User $actor): string
    {
        $pdf = new ReportPdf(
            $report['title'],
            $report['columns'],
            self::metaLines($report, $actor),
            self::summaryLines($report['summary'] ?? []),
        );

        if ($report['rows'] === []) {
            $pdf->addNote('No records match the selected filters.');
        }

        foreach ($report['rows'] as $row) {
            $pdf->addRow($row);
        }

        if (($report['totals'] ?? null) !== null) {
            $pdf->addTotalsRow($report['totals']);
        }

        return $pdf->output();
    }

    /**
     * Render one built report as a downloadable Excel (SpreadsheetML) file.
     *
     * @param array<string, mixed> $report
     */
    public static function renderExcel(array $report, User $actor): string
    {
        $excel = new ReportExcel($report['title'], $report['columns']);

        foreach (self::metaLines($report, $actor) as $line) {
            $excel->addCaption($line);
        }

        foreach ($report['summary'] ?? [] as $part) {
            $excel->addSummary($part['label'], $part['value']);
        }

        foreach ($report['rows'] as $row) {
            $excel->addRow($row);
        }

        if (($report['totals'] ?? null) !== null) {
            $excel->setTotals($report['totals']);
        }

        return $excel->output();
    }

    /**
     * What every export says about itself under the title: who it covers,
     * and who generated it when - so a downloaded file is still
     * self-describing once it has left the panel.
     *
     * The filters are deliberately not restated here. They belong to the
     * panel, where they are on screen next to the report and can still be
     * changed; on the printed page they only crowded the header.
     *
     * @param  array<string, mixed> $report
     * @return list<string>
     */
    private static function metaLines(array $report, User $actor): array
    {
        $lines = [];

        if (($report['subtitle'] ?? '') !== '') {
            $lines[] = $report['subtitle'];
        }

        $lines[] = sprintf(
            'Generated on %s by %s (%s)  |  %d record(s)',
            date('j M Y, g:i a'),
            $actor->name,
            $actor->roleLabel(),
            count($report['rows']),
        );

        return $lines;
    }

    /**
     * Wrap the summary pairs into printable lines - three per line keeps them
     * inside the page margin at 8pt on landscape A4.
     *
     * @param  list<array{label:string, value:string}> $summary
     * @return list<string>
     */
    private static function summaryLines(array $summary): array
    {
        return array_map(
            static fn (array $chunk): string => implode('     ', array_map(
                static fn (array $part): string => $part['label'] . ': ' . $part['value'],
                $chunk,
            )),
            array_chunk($summary, 3),
        );
    }

    /**
     * A safe, descriptive filename for the download - the report, the subject
     * it was narrowed to and the moment it was generated, so two exports
     * never collide and a folder of them stays readable.
     */
    public static function filename(string $type, ?ReportFilters $filters = null): string
    {
        $name = strtolower(str_replace(' ', '-', self::label($type)));

        if ($filters !== null && $filters->has('photographer_id')) {
            $name .= '-' . self::slug(self::photographerName($filters->int('photographer_id')));
        } elseif ($filters !== null && $filters->has('employee_id')) {
            $name .= '-' . self::slug(self::employeeName($filters->int('employee_id')));
        }

        return $name . '-' . date('Ymd-His');
    }

    private static function slug(string $value): string
    {
        return trim(strtolower(preg_replace('/[^A-Za-z0-9]+/', '-', $value) ?? $value), '-');
    }

    private static function formatDate(?string $value): string
    {
        if ($value === null || $value === '') {
            return '-';
        }

        $timestamp = strtotime($value);

        return $timestamp === false ? $value : date('d M Y', $timestamp);
    }

    /**
     * Amounts inside a report table stay plain, so Excel reads them as numbers
     * and the totals line can add them back up.
     */
    private static function formatAmount(float $amount): string
    {
        return number_format($amount, 2, '.', '');
    }

    /**
     * Amounts in a summary line are prose, not data - and they are written
     * "Rs." rather than with the rupee sign, because the PDF's built-in
     * Helvetica has no glyph for it and would drop the symbol silently.
     */
    private static function money(float $amount): string
    {
        return 'Rs. ' . number_format($amount, 2);
    }
}
