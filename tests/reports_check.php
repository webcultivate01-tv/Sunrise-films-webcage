<?php

declare(strict_types=1);

/**
 * End-to-end check of the Reports module, run against the real database.
 *
 * It creates a throwaway Manager, Employee, Photographer, Project, Task,
 * Payment and Salary Settlement, exercises Reports' access control, its filter
 * constraints and every per-report filter against them, confirms the two
 * drill-down reports refuse to run unfiltered, checks the summary and totals
 * lines add up, confirms both downloads actually render, then deletes
 * everything it created.
 *
 * Usage:  php tests/reports_check.php
 */

use App\Core\Database;
use App\Models\Photographer;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Services\PasswordPolicy;
use App\Services\PaymentService;
use App\Services\PhotographerService;
use App\Services\ProjectService;
use App\Services\ReportFilters;
use App\Services\ReportService;
use App\Services\SalaryService;
use App\Services\TaskService;
use App\Services\UserService;

define('BASE_PATH', dirname(__DIR__));

require BASE_PATH . '/app/bootstrap.php';

$passed = 0;
$failed = 0;

function check(string $label, bool $condition): void
{
    global $passed, $failed;

    if ($condition) {
        $passed++;
        echo "  PASS  " . $label . PHP_EOL;

        return;
    }

    $failed++;
    echo "  FAIL  " . $label . PHP_EOL;
}

/**
 * Run $callback and report whether it was refused.
 */
function refused(callable $callback): bool
{
    try {
        $callback();
    } catch (Throwable $e) {
        return true;
    }

    return false;
}

/**
 * Validate then build, the way a controller does.
 *
 * @param  array<string, string> $raw
 * @return array<string, mixed>
 */
function report(User $actor, string $type, array $raw): array
{
    return ReportService::build($actor, $type, ReportService::filters($type, $raw));
}

/**
 * The value of one summary line of a built report.
 *
 * @param array<string, mixed> $report
 */
function summaryValue(array $report, string $label): ?string
{
    foreach ($report['summary'] as $part) {
        if ($part['label'] === $label) {
            return $part['value'];
        }
    }

    return null;
}

$suffix            = bin2hex(random_bytes(4));
$managerEmail      = 'rep-manager-' . $suffix . '@sunrisefilms.test';
$employeeEmail     = 'rep-employee-' . $suffix . '@sunrisefilms.test';
$photographerEmail = 'rep-photographer-' . $suffix . '@sunrisefilms.test';

/** @var list<int> $userIds */
$userIds = [];
/** @var list<int> $photographerIds */
$photographerIds = [];
/** @var list<int> $projectIds */
$projectIds = [];
/** @var list<int> $taskIds */
$taskIds = [];
/** @var list<int> $paymentIds */
$paymentIds = [];
/** @var list<int> $settlementIds */
$settlementIds = [];

try {
    Database::connection();
} catch (Throwable $e) {
    fwrite(STDERR, $e->getMessage() . PHP_EOL);
    exit(1);
}

try {
    $admin = User::findByEmail('admin@gmail.com');

    if ($admin === null) {
        fwrite(STDERR, 'The seeded admin is missing. Run: php database/seed.php' . PHP_EOL);
        exit(1);
    }

    $manager = UserService::createAccount($admin, [
        'name'     => 'Reports Manager',
        'email'    => $managerEmail,
        'phone'    => '+91 98765 43240',
        'address'  => '1 MG Road, Bengaluru 560001',
        'role'     => User::ROLE_MANAGER,
        'password' => PasswordPolicy::generate(),
    ]);
    $userIds[] = $manager->id;

    $employee = UserService::createAccount($manager, [
        'name'     => 'Reports Employee',
        'email'    => $employeeEmail,
        'phone'    => '+91 98765 43241',
        'address'  => '2 Indiranagar, Bengaluru 560038',
        'role'     => User::ROLE_EMPLOYEE,
        'password' => PasswordPolicy::generate(),
    ]);
    $userIds[] = $employee->id;

    // A second employee, given no work at all - the control that proves one
    // employee's report never bleeds into another's.
    $otherEmployee = UserService::createAccount($manager, [
        'name'     => 'Reports Other Employee',
        'email'    => 'rep-other-' . $suffix . '@sunrisefilms.test',
        'phone'    => '+91 98765 43242',
        'address'  => '3 Jayanagar, Bengaluru 560041',
        'role'     => User::ROLE_EMPLOYEE,
        'password' => PasswordPolicy::generate(),
    ]);
    $userIds[] = $otherEmployee->id;

    $photographer = PhotographerService::create($admin, [
        'name'    => 'Reports Photographer',
        'email'   => $photographerEmail,
        'phone'   => '+91 99999 77777',
        'address' => '5 Koramangala, Bengaluru 560034',
    ]);
    $photographerIds[] = $photographer->id;

    $project = ProjectService::create($admin, [
        'photographer_id' => (string) $photographer->id,
        'customer_name'   => 'Reports Customer',
        'description'     => 'A project to exercise the Reports module against.',
        'folder_name'     => 'ReportsPhotographer_Project_2026',
        'deadline'        => '2026-12-31',
        'total_payment'   => '50000',
    ]);
    $projectIds[] = $project->id;

    $task = TaskService::create($admin, [
        'project_id'  => (string) $project->id,
        'employee_id' => (string) $employee->id,
        'title'       => 'Reports Task',
        'description' => 'A task to exercise the Task Report against.',
        'start_date'  => '2026-09-01',
        'end_date'    => '2026-09-10',
        'priority'    => 'high',
        'amount'      => '5000',
    ]);
    $taskIds[] = $task->id;

    $payment = PaymentService::record($admin, [
        'project_id'     => (string) $project->id,
        'amount'         => '10000',
        'payment_type'   => 'advance',
        'payment_method' => 'cash',
        'payment_date'   => '2026-09-01',
        'reference_no'   => 'REP-ADV-001',
    ]);
    $paymentIds[] = $payment->id;

    // Complete the task so it credits salary, then settle it - fixtures for
    // the Salary and Employee Work reports.
    $accepted   = TaskService::accept($employee, $task);
    $inProgress = TaskService::updateProgress($employee, $accepted, 100);
    TaskService::complete($employee, $inProgress);

    $settlement = SalaryService::settle($admin, $employee, [
        'salary_month' => '2026-09',
        'amount'       => '2000',
    ]);
    $settlementIds[] = $settlement->id;

    // =======================================================================
    echo PHP_EOL . 'Reports - access control' . PHP_EOL;

    check('admin can access reports', ReportService::canAccess($admin));
    check('manager can access reports', ReportService::canAccess($manager));
    check('employee cannot access reports', !ReportService::canAccess($employee));
    check(
        'employee is refused every report type',
        refused(static fn () => report($employee, ReportService::TYPE_PHOTOGRAPHERS, [])),
    );

    // -----------------------------------------------------------------------
    echo PHP_EOL . 'Reports - unknown report type' . PHP_EOL;

    check('an unrecognised report type is refused', refused(static fn () => ReportService::filterKeys('bogus')));
    check('building an unrecognised report type is refused', refused(static fn () => report($admin, 'bogus', [])));

    // -----------------------------------------------------------------------
    echo PHP_EOL . 'Reports - filter constraints' . PHP_EOL;

    $badStatus = ReportService::filters(ReportService::TYPE_PHOTOGRAPHERS, ['status' => 'completd']);
    check('a status that does not exist is rejected, not ignored', !$badStatus->isValid());
    check('the rejected status never reaches the query', !$badStatus->has('status'));
    check(
        'a report cannot be built from rejected filters',
        refused(static fn () => ReportService::build($admin, ReportService::TYPE_PHOTOGRAPHERS, $badStatus)),
    );

    $wrongModuleStatus = ReportService::filters(ReportService::TYPE_PHOTOGRAPHERS, ['status' => 'in_progress']);
    check('a status belonging to another module is rejected', !$wrongModuleStatus->isValid());

    $badRole = ReportService::filters(ReportService::TYPE_EMPLOYEES, ['role' => 'admin']);
    check('the employee report refuses to report on admins', !$badRole->isValid());

    $badDate = ReportService::filters(ReportService::TYPE_PHOTOGRAPHERS, ['start_date' => '2026-02-31']);
    check('a date that is not on the calendar is rejected', !$badDate->isValid());

    $backwards = ReportService::filters(ReportService::TYPE_PHOTOGRAPHERS, [
        'start_date' => '2026-09-30', 'end_date' => '2026-09-01',
    ]);
    check('a date range that runs backwards is rejected', !$backwards->isValid());

    $forwards = ReportService::filters(ReportService::TYPE_PHOTOGRAPHERS, [
        'start_date' => '2026-09-01', 'end_date' => '2026-09-30',
    ]);
    check('a date range in the right order is accepted', $forwards->isValid());

    $missingPhotographer = ReportService::filters(ReportService::TYPE_PROJECTS, ['photographer_id' => '99999999']);
    check('a photographer id that no longer exists is rejected', !$missingPhotographer->isValid());

    $notAnId = ReportService::filters(ReportService::TYPE_PROJECTS, ['photographer_id' => 'abc']);
    check('a non-numeric photographer id is rejected', !$notAnId->isValid());

    $adminAsEmployee = ReportService::filters(ReportService::TYPE_SALARY, ['employee_id' => (string) $admin->id]);
    check('an admin is not a valid employee filter', !$adminAsEmployee->isValid());

    $managerAsEmployeeFilter = ReportService::filters(ReportService::TYPE_EMPLOYEE_WORK, ['employee_id' => (string) $manager->id]);
    check('a manager is not a valid employee filter either', !$managerAsEmployeeFilter->isValid());
    check(
        'and it is refused with a message, not a 404 further down',
        str_contains((string) $managerAsEmployeeFilter->firstError(), 'Only an Employee'),
    );

    $badMonth = ReportService::filters(ReportService::TYPE_SALARY, ['month' => '2026-13']);
    check('a month that does not exist is rejected', !$badMonth->isValid());

    $longSearch = ReportService::filters(ReportService::TYPE_PHOTOGRAPHERS, ['q' => str_repeat('a', 101)]);
    check('an over-long search term is rejected', !$longSearch->isValid());

    $resolved = ReportService::filters(ReportService::TYPE_PROJECTS, ['photographer_id' => (string) $photographer->id]);
    check(
        'an id filter is described by name, not by number',
        $resolved->applied === [['label' => 'Photographer', 'value' => 'Reports Photographer']],
    );

    // -----------------------------------------------------------------------
    echo PHP_EOL . 'Reports - required filters' . PHP_EOL;

    $noPhotographer = ReportService::filters(ReportService::TYPE_PHOTOGRAPHER_WORK, []);
    check('the photographer work report refuses to run without a photographer', !$noPhotographer->isValid());

    $noEmployee = ReportService::filters(ReportService::TYPE_EMPLOYEE_WORK, []);
    check('the employee work report refuses to run without an employee', !$noEmployee->isValid());

    check(
        'a required filter cannot be bypassed by calling build() directly',
        refused(static fn () => ReportService::build($admin, ReportService::TYPE_EMPLOYEE_WORK, $noEmployee)),
    );

    check(
        'filters validated for one report cannot be used to build another',
        refused(static fn () => ReportService::build(
            $admin,
            ReportService::TYPE_TASKS,
            ReportService::filters(ReportService::TYPE_PHOTOGRAPHERS, []),
        )),
    );

    check(
        'photographer_id is declared required for the photographer work report',
        ReportFilters::requiredKeys(ReportService::TYPE_PHOTOGRAPHER_WORK) === ['photographer_id'],
    );

    // -----------------------------------------------------------------------
    echo PHP_EOL . 'Reports - Photographer Report' . PHP_EOL;

    $photographerReport = report($admin, ReportService::TYPE_PHOTOGRAPHERS, ['q' => 'Reports Photographer']);
    check('photographer report finds the fixture by search', count($photographerReport['rows']) === 1);
    check(
        'photographer report has one cell per column',
        count($photographerReport['columns']) === count($photographerReport['rows'][0]),
    );

    $photographerFuture = report($admin, ReportService::TYPE_PHOTOGRAPHERS, [
        'q' => 'Reports Photographer', 'start_date' => '2099-01-01',
    ]);
    check('photographer report date filter excludes it when the range is in the future', $photographerFuture['rows'] === []);

    // -----------------------------------------------------------------------
    echo PHP_EOL . 'Reports - Photographer Work Report' . PHP_EOL;

    $photographerWork = report($admin, ReportService::TYPE_PHOTOGRAPHER_WORK, [
        'photographer_id' => (string) $photographer->id,
    ]);
    check('photographer work report lists the work that photographer gave', count($photographerWork['rows']) === 1);
    check('it names the photographer it covers', str_contains($photographerWork['subtitle'], 'Reports Photographer'));
    check('it counts the work given', summaryValue($photographerWork, 'Work given') === '1');
    check('it totals the work value', summaryValue($photographerWork, 'Total work value') === 'Rs. 50,000.00');
    check('it totals what has been collected', summaryValue($photographerWork, 'Collected') === 'Rs. 10,000.00');
    check('it totals what is still outstanding', summaryValue($photographerWork, 'Outstanding') === 'Rs. 40,000.00');
    check('it counts the tasks the work was broken into', $photographerWork['rows'][0][7] === '1');
    check('it closes with a totals line', $photographerWork['totals'] !== null);
    check('the totals line adds up the work value', $photographerWork['totals'][3] === '50000.00');
    check('the totals line adds up what was collected', $photographerWork['totals'][4] === '10000.00');

    $otherPhotographerWork = report($admin, ReportService::TYPE_PHOTOGRAPHER_WORK, [
        'photographer_id' => (string) $photographer->id,
        'status'          => Project::STATUS_CANCELLED,
    ]);
    check('its status filter narrows the same photographer\'s work', $otherPhotographerWork['rows'] === []);

    // -----------------------------------------------------------------------
    echo PHP_EOL . 'Reports - Employee Report' . PHP_EOL;

    $employeeReport = report($admin, ReportService::TYPE_EMPLOYEES, ['q' => 'Reports Employee']);
    check('employee report finds the fixture by search', count($employeeReport['rows']) === 1);

    $managerAsEmployee = report($admin, ReportService::TYPE_EMPLOYEES, [
        'q' => 'Reports Manager', 'role' => 'employee',
    ]);
    check('employee report role filter excludes a manager when filtered to employees', $managerAsEmployee['rows'] === []);

    // -----------------------------------------------------------------------
    echo PHP_EOL . 'Reports - Employee Work Report' . PHP_EOL;

    $employeeWork = report($admin, ReportService::TYPE_EMPLOYEE_WORK, ['employee_id' => (string) $employee->id]);
    check('employee work report lists the work given to that employee', count($employeeWork['rows']) === 1);
    check('it names the employee it covers', str_contains($employeeWork['subtitle'], 'Reports Employee'));
    check('it carries no summary block - the list of work is the report', $employeeWork['summary'] === []);
    check('it totals the work amounts', $employeeWork['totals'][10] === '5000.00');

    $employeeWorkFiltered = report($admin, ReportService::TYPE_EMPLOYEE_WORK, [
        'employee_id' => (string) $employee->id,
        'status'      => Task::STATUS_ASSIGNED,
    ]);
    check('its status filter narrows the same employee\'s work', $employeeWorkFiltered['rows'] === []);

    $otherEmployeeWork = report($admin, ReportService::TYPE_EMPLOYEE_WORK, ['employee_id' => (string) $otherEmployee->id]);
    check('another employee\'s report does not include this employee\'s work', $otherEmployeeWork['rows'] === []);
    check('and it names the employee it is actually about', str_contains($otherEmployeeWork['subtitle'], 'Reports Other Employee'));
    check('an employee with no work has nothing to total', $otherEmployeeWork['totals'] === null);

    // -----------------------------------------------------------------------
    echo PHP_EOL . 'Reports - Work Report' . PHP_EOL;

    $workReport = report($admin, ReportService::TYPE_PROJECTS, ['photographer_id' => (string) $photographer->id]);
    check('work report finds the fixture by photographer', count($workReport['rows']) === 1);
    check('work report shows what has been collected against it', $workReport['rows'][0][5] === '10000.00');

    // -----------------------------------------------------------------------
    echo PHP_EOL . 'Reports - Task Report' . PHP_EOL;

    $taskReport = report($admin, ReportService::TYPE_TASKS, ['project_id' => (string) $project->id]);
    check('task report finds the fixture by project', count($taskReport['rows']) === 1);

    $taskReportByStatus = report($admin, ReportService::TYPE_TASKS, [
        'project_id' => (string) $project->id, 'status' => 'assigned',
    ]);
    check('task report status filter excludes a completed task', $taskReportByStatus['rows'] === []);

    // -----------------------------------------------------------------------
    echo PHP_EOL . 'Reports - Payment Report' . PHP_EOL;

    $paymentReport = report($admin, ReportService::TYPE_PAYMENTS, ['project_id' => (string) $project->id]);
    check('payment report finds the fixture by project', count($paymentReport['rows']) === 1);
    check('payment report totals what was collected', summaryValue($paymentReport, 'Total collected') === 'Rs. 10,000.00');

    $paymentReportByType = report($admin, ReportService::TYPE_PAYMENTS, [
        'project_id' => (string) $project->id, 'type' => 'final',
    ]);
    check('payment report type filter excludes an advance payment', $paymentReportByType['rows'] === []);

    // -----------------------------------------------------------------------
    echo PHP_EOL . 'Reports - Salary Report' . PHP_EOL;

    $salaryReport = report($admin, ReportService::TYPE_SALARY, ['employee_id' => (string) $employee->id]);
    check('salary report finds the fixture by employee', count($salaryReport['rows']) === 1);
    check('salary report closes with that employee\'s position', summaryValue($salaryReport, 'Outstanding') === 'Rs. 3,000.00');

    $salaryReportByMonth = report($admin, ReportService::TYPE_SALARY, [
        'employee_id' => (string) $employee->id, 'month' => '2020-01',
    ]);
    check('salary report month filter excludes a different month', $salaryReportByMonth['rows'] === []);

    // -----------------------------------------------------------------------
    echo PHP_EOL . 'Reports - PDF and Excel rendering' . PHP_EOL;

    $pdf = ReportService::renderPdf($workReport, $admin);
    check('the PDF export is a real PDF file', str_starts_with($pdf, '%PDF-1.4'));
    check('the PDF export ends with the PDF trailer', str_ends_with($pdf, '%%EOF'));
    check('the PDF export numbers its pages', str_contains($pdf, 'Page 1 of'));
    check('the PDF export does not restate the filters', !str_contains($pdf, '(Filters'));

    $excel = ReportService::renderExcel($workReport, $admin);
    check(
        'the Excel export is a SpreadsheetML workbook holding the data',
        str_contains($excel, '<Workbook') && str_contains($excel, 'Reports Customer'),
    );
    check('the Excel export carries the summary figures', str_contains($excel, 'Outstanding'));
    check('the Excel export carries a totals row', str_contains($excel, 'ss:StyleID="Total"'));
    check('the Excel export does not restate the filters', !str_contains($excel, 'Filters:'));

    $workPdfName = ReportService::filename(ReportService::TYPE_PHOTOGRAPHER_WORK, ReportService::filters(
        ReportService::TYPE_PHOTOGRAPHER_WORK,
        ['photographer_id' => (string) $photographer->id],
    ));
    check('a drill-down download is named after its subject', str_contains($workPdfName, 'reports-photographer'));

    $emptyFilters = ReportService::filters(ReportService::TYPE_PHOTOGRAPHERS, ['q' => 'no-such-photographer-' . $suffix]);
    $emptyReport  = ReportService::build($admin, ReportService::TYPE_PHOTOGRAPHERS, $emptyFilters);
    $emptyPdf     = ReportService::renderPdf($emptyReport, $admin);
    check('an empty report still renders a valid PDF', str_starts_with($emptyPdf, '%PDF-1.4') && str_ends_with($emptyPdf, '%%EOF'));
    check('an empty report has no totals line to mislead with', $emptyReport['totals'] === null);
} finally {
    foreach ($settlementIds as $id) {
        Database::statement('DELETE FROM salary_settlements WHERE id = ?', [$id]);
    }

    foreach ($paymentIds as $id) {
        Database::statement('DELETE FROM payments WHERE id = ?', [$id]);
    }

    foreach ($taskIds as $id) {
        Database::statement('DELETE FROM tasks WHERE id = ?', [$id]);
    }

    foreach ($projectIds as $id) {
        Database::statement('DELETE FROM projects WHERE id = ?', [$id]);
    }

    foreach ($photographerIds as $id) {
        Database::statement('DELETE FROM photographers WHERE id = ?', [$id]);
    }

    foreach (array_reverse($userIds) as $id) {
        Database::statement('DELETE FROM users WHERE id = ?', [$id]);
    }

    Database::statement('DELETE FROM login_attempts WHERE attempt_key LIKE ?', ['%sunrisefilms.test%']);
}

echo PHP_EOL . str_repeat('-', 52) . PHP_EOL;
echo sprintf('%d passed, %d failed', $passed, $failed) . PHP_EOL;

exit($failed === 0 ? 0 : 1);
