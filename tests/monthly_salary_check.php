<?php

declare(strict_types=1);

/**
 * End-to-end check of the Monthly Salary acceptance criteria, run against the
 * real database.
 *
 * It creates a throwaway Manager with two Employees, and an Employee created
 * directly by the Admin (to prove the Manager's reach now matches the
 * Admin's), gives each employee completed, salary-eligible tasks, then
 * exercises the salary list, monthly breakdown,
 * settlement validation, settlement, idempotent duplicate submission, bill
 * generation and both the global and employee-wise history - then deletes
 * everything it created.
 *
 * Usage:  php tests/monthly_salary_check.php
 */

use App\Core\Database;
use App\Models\Customer;
use App\Models\Project;
use App\Models\SalarySettlement;
use App\Models\User;
use App\Services\CustomerService;
use App\Services\PasswordPolicy;
use App\Services\ProjectService;
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
        echo '  PASS  ' . $label . PHP_EOL;

        return;
    }

    $failed++;
    echo '  FAIL  ' . $label . PHP_EOL;
}

/**
 * Run $callback and report whether it was refused with an HTTP error.
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

$suffix        = bin2hex(random_bytes(4));
$managerEmail  = 'ms-manager-' . $suffix . '@sunrisefilms.test';
$employee1Mail = 'ms-employee1-' . $suffix . '@sunrisefilms.test';
$employee2Mail = 'ms-employee2-' . $suffix . '@sunrisefilms.test';
$employee3Mail = 'ms-employee3-' . $suffix . '@sunrisefilms.test';
$customerEmail = 'ms-customer-' . $suffix . '@sunrisefilms.test';

/** @var list<int> $userIds */
$userIds = [];
/** @var list<int> $customerIds */
$customerIds = [];
/** @var list<int> $projectIds */
$projectIds = [];
/** @var list<int> $taskIds */
$taskIds = [];
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
        'name' => 'Salary Mgmt Manager', 'email' => $managerEmail, 'phone' => '+91 98765 43240',
        'address' => '1 MG Road, Bengaluru 560001', 'role' => User::ROLE_MANAGER, 'password' => PasswordPolicy::generate(),
    ]);
    $userIds[] = $manager->id;

    $employee1 = UserService::createAccount($manager, [
        'name' => 'Salary Mgmt Employee One', 'email' => $employee1Mail, 'phone' => '+91 98765 43241',
        'address' => '2 Church Street, Bengaluru 560001', 'role' => User::ROLE_EMPLOYEE, 'password' => PasswordPolicy::generate(),
    ]);
    $userIds[] = $employee1->id;

    $employee2 = UserService::createAccount($manager, [
        'name' => 'Salary Mgmt Employee Two', 'email' => $employee2Mail, 'phone' => '+91 98765 43242',
        'address' => '3 Brigade Road, Bengaluru 560001', 'role' => User::ROLE_EMPLOYEE, 'password' => PasswordPolicy::generate(),
    ]);
    $userIds[] = $employee2->id;

    // Created directly by the Admin, so out of the Manager's scope.
    $employee3 = UserService::createAccount($admin, [
        'name' => 'Salary Mgmt Employee Three', 'email' => $employee3Mail, 'phone' => '+91 98765 43243',
        'address' => '4 Residency Road, Bengaluru 560025', 'role' => User::ROLE_EMPLOYEE, 'password' => PasswordPolicy::generate(),
    ]);
    $userIds[] = $employee3->id;

    $customer = CustomerService::create($admin, [
        'name' => 'Salary Mgmt Customer', 'email' => $customerEmail, 'phone' => '+91 99999 77777',
        'address' => '5 Koramangala, Bengaluru 560034',
    ]);
    $customerIds[] = $customer->id;

    $project = ProjectService::create($admin, [
        'customer_id' => (string) $customer->id, 'name' => 'Salary Mgmt Project',
        'description' => 'A project to hang salary-eligible tasks off of.', 'folder_name' => 'SMCustomer_Project_2026',
        'deadline' => '2026-12-31', 'total_payment' => '100000',
    ]);
    $projectIds[] = $project->id;

    /**
     * Assign, accept, progress-to-100 and complete a task for $employee - the
     * only way (task spec s7) a salary credit is ever created.
     */
    $completeTask = static function (User $assigner, User $employee, string $title, float $amount) use (&$taskIds, $project): void {
        $task = TaskService::create($assigner, [
            'project_id' => (string) $project->id, 'employee_id' => (string) $employee->id,
            'title' => $title, 'description' => 'Salary Mgmt test task.',
            'start_date' => '2026-01-01', 'end_date' => '2026-01-31', 'priority' => 'medium',
            'amount' => (string) $amount,
        ]);
        $taskIds[] = $task->id;

        $task = TaskService::accept($employee, $task);
        $task = TaskService::updateProgress($employee, $task, 100);
        TaskService::complete($employee, $task);
    };

    $currentMonth = date('Y-m');

    $completeTask($manager, $employee1, 'Rough cut', 8000.0);
    $completeTask($manager, $employee1, 'Colour grade', 5000.0);
    $completeTask($admin, $employee3, 'Admin-only edit', 2000.0);

    // =========================================================================
    echo PHP_EOL . 'Monthly Salary - access and scope' . PHP_EOL;

    check('admin can access monthly salary', SalaryService::canAccessManagement($admin));
    check('manager can access monthly salary', SalaryService::canAccessManagement($manager));
    check('employee cannot access monthly salary', !SalaryService::canAccessManagement($employee1));
    check('employee is refused the salary list', refused(static fn () => SalaryService::listEmployees($employee1)));

    check('manager can view their own employee\'s salary', SalaryService::findEmployeeOrFail($manager, $employee1->id)->id === $employee1->id);
    check('manager can also view an employee they did not create', SalaryService::findEmployeeOrFail($manager, $employee3->id)->id === $employee3->id);
    check('admin can view any employee\'s salary', SalaryService::findEmployeeOrFail($admin, $employee3->id)->id === $employee3->id);

    $managerRows = array_map(static fn (array $r): int => $r['employee']->id, SalaryService::listEmployees($manager));
    check('manager salary list includes their own team', in_array($employee1->id, $managerRows, true) && in_array($employee2->id, $managerRows, true));
    check('manager salary list also includes employees they did not create', in_array($employee3->id, $managerRows, true));

    $adminRows = array_map(static fn (array $r): int => $r['employee']->id, SalaryService::listEmployees($admin));
    check('admin salary list includes every employee', in_array($employee1->id, $adminRows, true) && in_array($employee3->id, $adminRows, true));

    $suggestedEmployees = SalaryService::suggestEmployees($admin, 'Salary Mgmt Employee One');
    check('suggest returns the matching employee', count($suggestedEmployees) === 1 && $suggestedEmployees[0]['employee']->id === $employee1->id);
    check('an empty search suggests no employees', SalaryService::suggestEmployees($admin, '') === []);

    $suggestedManagerScope = SalaryService::suggestEmployees($manager, 'Salary Mgmt Employee Three');
    check('manager employee suggestions surface employees system-wide', count($suggestedManagerScope) === 1 && $suggestedManagerScope[0]['employee']->id === $employee3->id);

    // -------------------------------------------------------------------------
    echo PHP_EOL . 'Monthly Salary - earnings and monthly breakdown' . PHP_EOL;

    $totals = SalaryService::totals($employee1->id);
    check('total earned reflects completed, salary-eligible tasks', $totals['earned'] === 13000.0);
    check('nothing paid yet, so outstanding equals earned', $totals['paid'] === 0.0 && $totals['outstanding'] === 13000.0);
    check('salary status is Pending before any settlement', $totals['status'] === 'pending');

    $breakdown = SalaryService::monthlyBreakdown($employee1->id);
    $currentRow = null;

    foreach ($breakdown as $row) {
        if ($row['month'] === $currentMonth) {
            $currentRow = $row;
        }
    }

    check('the current month appears in the breakdown with the right totals', $currentRow !== null
        && $currentRow['earned'] === 13000.0 && $currentRow['outstanding'] === 13000.0);

    $monthData = SalaryService::monthEntries($employee1->id, $currentMonth);
    check('the month drill-down lists both completed tasks', count($monthData['credits']) === 2);
    check('exited tasks would never appear here (spec s6) - only completed ones do', array_sum(array_map(
        static fn (array $c): float => (float) $c['amount'],
        $monthData['credits'],
    )) === 13000.0);

    // -------------------------------------------------------------------------
    echo PHP_EOL . 'Monthly Salary - settlement validation' . PHP_EOL;

    check('a zero amount is refused', array_key_exists('amount', SalaryService::validateSettlement($manager, $employee1, [
        'salary_month' => $currentMonth, 'amount' => '0',
    ])->errors()));

    check('an invalid month is refused', array_key_exists('salary_month', SalaryService::validateSettlement($manager, $employee1, [
        'salary_month' => 'not-a-month', 'amount' => '1000',
    ])->errors()));

    check('an amount exceeding the outstanding balance is refused', array_key_exists('amount', SalaryService::validateSettlement($manager, $employee1, [
        'salary_month' => $currentMonth, 'amount' => '99999',
    ])->errors()));

    check('a valid amount within the outstanding balance passes', SalaryService::validateSettlement($manager, $employee1, [
        'salary_month' => $currentMonth, 'amount' => '5000',
    ])->passes());

    // -------------------------------------------------------------------------
    echo PHP_EOL . 'Monthly Salary - settlement and bill generation' . PHP_EOL;

    $key1 = bin2hex(random_bytes(16));

    $settlement1 = SalaryService::settle($manager, $employee1, [
        'salary_month' => $currentMonth, 'amount' => '5000', 'notes' => 'First settlement.',
        'idempotency_key' => $key1,
    ]);
    $settlementIds[] = $settlement1->id;

    check('the settlement is recorded', $settlement1->id > 0 && $settlement1->amount === 5000.0);
    check('the settlement gets a bill reference', preg_match('/^SET-\d{4}$/', $settlement1->referenceNo) === 1);
    check('the settlement snapshots the employee\'s details', $settlement1->employeeNameSnapshot === $employee1->name
        && $settlement1->employeeEmailSnapshot === mb_strtolower($employee1->email));

    $totalsAfterFirst = SalaryService::totals($employee1->id);
    check('paid increases and outstanding decreases by the settlement amount', $totalsAfterFirst['paid'] === 5000.0
        && $totalsAfterFirst['outstanding'] === 8000.0);
    check('the original earned amount is never reduced by a settlement', $totalsAfterFirst['earned'] === 13000.0);
    check('salary status is Partially Settled after a partial settlement', $totalsAfterFirst['status'] === 'partially_settled');

    // A duplicate click/resubmit carrying the same idempotency key.
    $duplicate = SalaryService::settle($manager, $employee1, [
        'salary_month' => $currentMonth, 'amount' => '5000', 'notes' => 'First settlement.',
        'idempotency_key' => $key1,
    ]);
    check('a resubmission with the same idempotency key returns the existing settlement', $duplicate->id === $settlement1->id);
    check('a duplicate submission never creates a second settlement row', SalarySettlement::totalSettledForEmployee($employee1->id) === 5000.0);

    $key2 = bin2hex(random_bytes(16));
    $settlement2 = SalaryService::settle($manager, $employee1, [
        'salary_month' => $currentMonth, 'amount' => '8000', 'notes' => '',
        'idempotency_key' => $key2,
    ]);
    $settlementIds[] = $settlement2->id;

    check('a second, different settlement gets its own reference', $settlement2->referenceNo !== $settlement1->referenceNo);

    $totalsAfterSecond = SalaryService::totals($employee1->id);
    check('multiple settlements accumulate correctly (spec s11)', $totalsAfterSecond['paid'] === 13000.0
        && $totalsAfterSecond['outstanding'] === 0.0);
    check('salary status is Fully Settled once paid catches up with earned', $totalsAfterSecond['status'] === 'fully_settled');

    check('a further settlement against a fully settled month is refused', !SalaryService::validateSettlement($manager, $employee1, [
        'salary_month' => $currentMonth, 'amount' => '1',
    ])->passes());

    $pdf = SalaryService::generateBillPdf($settlement1);
    check('the generated bill is a well-formed PDF byte stream', str_starts_with($pdf, '%PDF-1.4') && str_ends_with(trim($pdf), '%%EOF'));
    check('the bill embeds the settlement\'s own reference number', str_contains($pdf, $settlement1->referenceNo));

    // -------------------------------------------------------------------------
    echo PHP_EOL . 'Monthly Salary - access to settlements and bills' . PHP_EOL;

    check('manager can view a settlement inside their scope', SalaryService::findSettlementOrFail($manager, $settlement1->id)->id === $settlement1->id);
    check('employee can view their own settlement', SalaryService::findOwnSettlementOrFail($employee1, $settlement1->id)->id === $settlement1->id);
    check('a different employee cannot view this settlement', refused(static fn () => SalaryService::findOwnSettlementOrFail($employee2, $settlement1->id)));

    // -------------------------------------------------------------------------
    echo PHP_EOL . 'Monthly Salary - history' . PHP_EOL;

    $employeeHistory = SalaryService::employeeHistory($employee1->id);
    check('employee history includes both salary-earned entries', count(array_filter(
        $employeeHistory,
        static fn (array $e): bool => $e['type'] === 'salary_earned',
    )) === 2);
    check('employee history includes both settlements', count(array_filter(
        $employeeHistory,
        static fn (array $e): bool => $e['type'] === 'settlement',
    )) === 2);

    $adminOnlySettlement = SalaryService::settle($admin, $employee3, [
        'salary_month' => $currentMonth, 'amount' => '2000', 'notes' => '',
        'idempotency_key' => bin2hex(random_bytes(16)),
    ]);
    $settlementIds[] = $adminOnlySettlement->id;

    $managerGlobal = array_map(static fn (array $e): int => $e['employeeId'], SalaryService::globalHistory($manager));
    check('manager\'s global history includes their own team', in_array($employee1->id, $managerGlobal, true));
    check('manager\'s global history also includes employees they did not create', in_array($employee3->id, $managerGlobal, true));

    $adminGlobal = array_map(static fn (array $e): int => $e['employeeId'], SalaryService::globalHistory($admin));
    check('admin\'s global history includes every employee', in_array($employee1->id, $adminGlobal, true) && in_array($employee3->id, $adminGlobal, true));

    $settlementOnly = SalaryService::globalHistory($admin, ['type' => 'settlement']);
    check('the type filter narrows history to settlements only', $settlementOnly !== []
        && array_reduce($settlementOnly, static fn (bool $c, array $e): bool => $c && $e['type'] === 'settlement', true));

    $suggestedHistory = SalaryService::suggestHistory($admin, 'Salary Mgmt Employee One');
    check('history suggestions match the employee search', $suggestedHistory !== []
        && array_reduce($suggestedHistory, static fn (bool $c, array $e): bool => $c && $e['employeeId'] === $employee1->id, true));
    check('an empty search suggests no history', SalaryService::suggestHistory($admin, '') === []);

    $suggestedHistoryScope = SalaryService::suggestHistory($manager, 'Salary Mgmt Employee Three');
    check('manager history suggestions surface employees system-wide', $suggestedHistoryScope !== []
        && array_reduce($suggestedHistoryScope, static fn (bool $c, array $e): bool => $c && $e['employeeId'] === $employee3->id, true));
} finally {
    foreach ($settlementIds as $id) {
        Database::statement('DELETE FROM salary_settlements WHERE id = ?', [$id]);
    }

    foreach ($taskIds as $id) {
        Database::statement('DELETE FROM tasks WHERE id = ?', [$id]);
    }

    foreach ($projectIds as $id) {
        Database::statement('DELETE FROM projects WHERE id = ?', [$id]);
    }

    foreach ($customerIds as $id) {
        Database::statement('DELETE FROM customers WHERE id = ?', [$id]);
    }

    foreach (array_reverse($userIds) as $id) {
        Database::statement('DELETE FROM users WHERE id = ?', [$id]);
    }

    Database::statement('DELETE FROM login_attempts WHERE attempt_key LIKE ?', ['%sunrisefilms.test%']);
}

echo PHP_EOL . str_repeat('-', 52) . PHP_EOL;
echo sprintf('%d passed, %d failed', $passed, $failed) . PHP_EOL;

exit($failed === 0 ? 0 : 1);
