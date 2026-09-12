<?php

declare(strict_types=1);

/**
 * End-to-end check of the Payment Management acceptance criteria, run
 * against the real database.
 *
 * It creates a throwaway Manager, Customer and Project, exercises the role
 * rules, validation and recomputed totals on payments, then deletes
 * everything it created.
 *
 * Usage:  php tests/payment_management_check.php
 */

use App\Core\Database;
use App\Models\Customer;
use App\Models\Payment;
use App\Models\Project;
use App\Models\User;
use App\Services\CustomerService;
use App\Services\PasswordPolicy;
use App\Services\PaymentService;
use App\Services\ProjectService;
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
$managerEmail  = 'pm-manager-' . $suffix . '@sunrisefilms.test';
$employeeEmail = 'pm-employee-' . $suffix . '@sunrisefilms.test';
$customerEmail = 'pm-customer-' . $suffix . '@sunrisefilms.test';

/** @var list<int> $userIds */
$userIds = [];
/** @var list<int> $customerIds */
$customerIds = [];
/** @var list<int> $projectIds */
$projectIds = [];
/** @var list<int> $paymentIds */
$paymentIds = [];

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
        'name'     => 'Payment Mgmt Manager',
        'email'    => $managerEmail,
        'phone'    => '+91 98765 43230',
        'address'  => '1 Residency Road, Bengaluru 560025',
        'role'     => User::ROLE_MANAGER,
        'password' => PasswordPolicy::generate(),
    ]);
    $userIds[] = $manager->id;

    $employee = UserService::createAccount($manager, [
        'name'     => 'Payment Mgmt Employee',
        'email'    => $employeeEmail,
        'phone'    => '+91 98765 43231',
        'address'  => '2 Church Street, Bengaluru 560001',
        'role'     => User::ROLE_EMPLOYEE,
        'password' => PasswordPolicy::generate(),
    ]);
    $userIds[] = $employee->id;

    $customer = CustomerService::create($admin, [
        'name'    => 'Payment Mgmt Customer',
        'email'   => $customerEmail,
        'phone'   => '+91 99999 66666',
        'address' => '5 Brigade Road, Bengaluru 560001',
    ]);
    $customerIds[] = $customer->id;

    $project = ProjectService::create($admin, [
        'customer_id'   => (string) $customer->id,
        'name'          => 'Payment Mgmt Project',
        'description'   => 'A project to exercise Payment Management against.',
        'folder_name'   => 'PMCustomer_Project_2026',
        'deadline'      => '2026-12-31',
        'total_payment' => '100000',
    ]);
    $projectIds[] = $project->id;

    // =======================================================================
    echo PHP_EOL . 'Payment Management - access' . PHP_EOL;

    check('admin can access payment management', PaymentService::canAccess($admin));
    check('manager can access payment management', PaymentService::canAccess($manager));
    check('employee cannot access payment management', !PaymentService::canAccess($employee));
    check('employee is refused the payment dashboard', refused(static fn () => PaymentService::projectOverview($employee)));

    // -----------------------------------------------------------------------
    echo PHP_EOL . 'Payment Management - project starts at Payment Due' . PHP_EOL;

    check(
        'a project with no payments is Payment Due',
        PaymentService::statusFor($project, 0.0) === PaymentService::STATUS_DUE,
    );

    // -----------------------------------------------------------------------
    echo PHP_EOL . 'Payment Management - validation' . PHP_EOL;

    $blank = PaymentService::validate([
        'project_id' => '', 'amount' => '', 'payment_type' => '', 'payment_method' => '', 'payment_date' => '',
    ]);

    check(
        'every required field is enforced',
        count(array_intersect(
            ['project_id', 'amount', 'payment_type', 'payment_method', 'payment_date'],
            array_keys($blank->errors()),
        )) === 5,
    );

    check('a non-existent project is refused', array_key_exists('project_id', PaymentService::validate([
        'project_id' => '999999999', 'amount' => '100', 'payment_type' => 'advance', 'payment_method' => 'cash',
        'payment_date' => '2026-09-12',
    ])->errors()));

    check('a zero amount is refused', array_key_exists('amount', PaymentService::validate([
        'project_id' => (string) $project->id, 'amount' => '0', 'payment_type' => 'advance', 'payment_method' => 'cash',
        'payment_date' => '2026-09-12',
    ])->errors()));

    check('an unrecognised payment type is refused', array_key_exists('payment_type', PaymentService::validate([
        'project_id' => (string) $project->id, 'amount' => '100', 'payment_type' => 'bogus', 'payment_method' => 'cash',
        'payment_date' => '2026-09-12',
    ])->errors()));

    check('an amount above the project total is refused', array_key_exists('amount', PaymentService::validate([
        'project_id' => (string) $project->id, 'amount' => '999999', 'payment_type' => 'advance', 'payment_method' => 'cash',
        'payment_date' => '2026-09-12',
    ])->errors()));

    // -----------------------------------------------------------------------
    echo PHP_EOL . 'Payment Management - record and recompute' . PHP_EOL;

    check('employee cannot record a payment', refused(static fn () => PaymentService::record($employee, [
        'project_id' => (string) $project->id, 'amount' => '30000', 'payment_type' => 'advance',
        'payment_method' => 'cash', 'payment_date' => '2026-09-01',
    ])));

    $advance = PaymentService::record($admin, [
        'project_id'     => (string) $project->id,
        'amount'         => '30000',
        'payment_type'   => 'advance',
        'payment_method' => 'cash',
        'payment_date'   => '2026-09-01',
        'reference_no'   => 'ADV-001',
        'notes'          => 'Booking advance',
    ]);
    $paymentIds[] = $advance->id;

    check('admin can record a payment', $advance->id > 0);
    check('the receiving user is recorded', $advance->receivedBy === $admin->id);
    check('the amount is stored', $advance->amount === 30000.0);

    check(
        'a project with only an advance is Advance Received',
        PaymentService::statusFor($project, 30000.0) === PaymentService::STATUS_ADVANCE_RECEIVED,
    );

    $milestone = PaymentService::record($manager, [
        'project_id'     => (string) $project->id,
        'amount'         => '40000',
        'payment_type'   => 'milestone',
        'payment_method' => 'bank_transfer',
        'payment_date'   => '2026-09-15',
    ]);
    $paymentIds[] = $milestone->id;

    check('manager can record a payment', $milestone->receivedBy === $manager->id);

    check(
        'a project past its advance is Partially Paid',
        PaymentService::statusFor($project, 70000.0) === PaymentService::STATUS_PARTIAL,
    );

    check('total collected is recomputed from history', Payment::totalForProject($project->id) === 70000.0);

    $summary = PaymentService::projectSummary($admin, $project);
    check('project summary collected matches history', $summary['collected'] === 70000.0);
    check('project summary outstanding is total minus collected', $summary['outstanding'] === 30000.0);
    check('project summary timeline has both payments, oldest first', count($summary['timeline']) === 2
        && $summary['timeline'][0]->id === $advance->id);

    $final = PaymentService::record($admin, [
        'project_id'     => (string) $project->id,
        'amount'         => '30000',
        'payment_type'   => 'final',
        'payment_method' => 'upi',
        'payment_date'   => '2026-09-20',
    ]);
    $paymentIds[] = $final->id;

    check(
        'a fully collected project is Fully Paid',
        PaymentService::statusFor($project, 100000.0) === PaymentService::STATUS_PAID,
    );

    check('a further payment is refused once fully paid', array_key_exists('amount', PaymentService::validate([
        'project_id' => (string) $project->id, 'amount' => '1', 'payment_type' => 'other', 'payment_method' => 'cash',
        'payment_date' => '2026-09-21',
    ])->errors()));

    // -----------------------------------------------------------------------
    echo PHP_EOL . 'Payment Management - history, search and filters' . PHP_EOL;

    $history = PaymentService::history($admin, ['project_id' => (string) $project->id]);
    check('history returns every payment for the project', count($history) === 3);

    $byReference = PaymentService::history($admin, ['q' => 'ADV-001']);
    check('history can be searched by reference number', count($byReference) === 1 && $byReference[0]->id === $advance->id);

    $byType = PaymentService::history($admin, ['project_id' => (string) $project->id, 'type' => 'milestone']);
    check('history can be filtered by payment type', count($byType) === 1 && $byType[0]->id === $milestone->id);

    $newestFirst = PaymentService::history($admin, ['project_id' => (string) $project->id]);
    check('history defaults to newest first', $newestFirst[0]->id === $final->id);

    $found = PaymentService::findOrFail($admin, $advance->id);
    check('a payment can be loaded by id', $found->id === $advance->id);
    check('employee is refused a payment detail', refused(static fn () => PaymentService::findOrFail($employee, $advance->id)));

    // -----------------------------------------------------------------------
    echo PHP_EOL . 'Payment Management - the financial trail is preserved' . PHP_EOL;

    check(
        'a project with payment history cannot be deleted',
        refused(static fn () => ProjectService::delete($admin, $project)),
    );
} finally {
    foreach ($paymentIds as $id) {
        Database::statement('DELETE FROM payments WHERE id = ?', [$id]);
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
