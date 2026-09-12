<?php

declare(strict_types=1);

/**
 * End-to-end check of the Customer & Employee Management acceptance criteria
 * (module spec s17), run against the real database.
 *
 * It creates a throwaway Manager, Employee and Customer, exercises the role
 * rules, the scope rules, validation, search and the welcome email, then
 * deletes everything it created.
 *
 * Usage:  php tests/modules_check.php
 */

use App\Core\Config;
use App\Core\Database;
use App\Models\Customer;
use App\Models\User;
use App\Services\CustomerService;
use App\Services\PasswordPolicy;
use App\Services\UserService;
use App\Services\WelcomeMailer;

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
$managerEmail  = 'mod-manager-' . $suffix . '@sunrisefilms.test';
$employeeEmail = 'mod-employee-' . $suffix . '@sunrisefilms.test';
$strayEmail    = 'mod-stray-' . $suffix . '@sunrisefilms.test';
$customerEmail = 'mod-customer-' . $suffix . '@sunrisefilms.test';
$adminEmpEmail = 'mod-adminemp-' . $suffix . '@sunrisefilms.test';

/** @var list<int> $userIds */
$userIds = [];
/** @var list<int> $customerIds */
$customerIds = [];

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

    // =======================================================================
    echo PHP_EOL . "Employee Management - access (s6, s12, s17)" . PHP_EOL;

    check('admin can access employee management', UserService::managesPeople($admin));

    // -----------------------------------------------------------------------
    echo PHP_EOL . "Employee Management - role assignment (s8, s9, s17)" . PHP_EOL;

    check('admin is offered both roles',
        UserService::assignableRoles($admin) === [User::ROLE_MANAGER, User::ROLE_EMPLOYEE]);

    $manager = UserService::createAccount($admin, [
        'name'     => 'Module Manager',
        'email'    => $managerEmail,
        'phone'    => '+91 98765 43210',
        'address'  => '12 Residency Road, Bengaluru 560025',
        'role'     => User::ROLE_MANAGER,
        'password' => PasswordPolicy::generate(),
    ]);
    $userIds[] = $manager->id;

    check('admin can create managers', $manager->role === User::ROLE_MANAGER);

    $adminEmployee = UserService::createAccount($admin, [
        'name'     => 'Module Admin-made Employee',
        'email'    => $adminEmpEmail,
        'phone'    => '+91 98765 11111',
        'address'  => '9 MG Road, Bengaluru 560001',
        'role'     => User::ROLE_EMPLOYEE,
        'password' => PasswordPolicy::generate(),
    ]);
    $userIds[] = $adminEmployee->id;

    check('admin can create employees directly', $adminEmployee->role === User::ROLE_EMPLOYEE);

    check('manager is offered the employee role only',
        UserService::assignableRoles($manager) === [User::ROLE_EMPLOYEE]);

    $employee = UserService::createAccount($manager, [
        'name'     => 'Module Employee',
        'email'    => $employeeEmail,
        'phone'    => '+91 98765 43211',
        'address'  => '44 Church Street, Bengaluru 560001',
        'role'     => User::ROLE_EMPLOYEE,
        'password' => PasswordPolicy::generate(),
    ]);
    $userIds[] = $employee->id;

    check('manager can create employees', $employee->role === User::ROLE_EMPLOYEE
        && $employee->createdBy === $manager->id);

    check('manager cannot create managers', refused(static fn () => UserService::createAccount($manager, [
        'name'     => 'Should Not Exist',
        'email'    => 'nope-' . $suffix . '@sunrisefilms.test',
        'phone'    => '+91 98765 43212',
        'address'  => 'Nowhere',
        'role'     => User::ROLE_MANAGER,
        'password' => PasswordPolicy::generate(),
    ])));

    check('employee cannot access employee management', !UserService::managesPeople($employee));

    check('employee cannot create anybody', refused(static fn () => UserService::createAccount($employee, [
        'name'     => 'Should Not Exist',
        'email'    => 'nope2-' . $suffix . '@sunrisefilms.test',
        'phone'    => '+91 98765 43213',
        'address'  => 'Nowhere',
        'role'     => User::ROLE_EMPLOYEE,
        'password' => PasswordPolicy::generate(),
    ])));

    // -----------------------------------------------------------------------
    echo PHP_EOL . "Employee Management - captured fields (s10, s17)" . PHP_EOL;

    $stored = User::findById($employee->id);

    check('employee name is stored', $stored?->name === 'Module Employee');
    check('employee email is stored', $stored?->email === mb_strtolower($employeeEmail));
    check('employee mobile number is stored', $stored?->phone === '+91 98765 43211');
    check('employee address is stored', $stored?->address === '44 Church Street, Bengaluru 560001');
    check('employee role is stored', $stored?->role === User::ROLE_EMPLOYEE);

    // -----------------------------------------------------------------------
    echo PHP_EOL . "Employee Management - validation (s14)" . PHP_EOL;

    $blank = UserService::validateAccount($admin, [
        'name' => '', 'email' => '', 'phone' => '', 'address' => '', 'role' => '',
    ]);

    check('every required field is enforced',
        count(array_intersect(['name', 'email', 'phone', 'address', 'role'], array_keys($blank->errors()))) === 5);

    check('a malformed email is refused', array_key_exists('email', UserService::validateAccount($admin, [
        'name' => 'Bad Email', 'email' => 'not-an-email', 'phone' => '9876543210',
        'address' => 'Somewhere', 'role' => User::ROLE_EMPLOYEE,
    ])->errors()));

    check('a malformed mobile number is refused', array_key_exists('phone', UserService::validateAccount($admin, [
        'name' => 'Bad Phone', 'email' => 'ok-' . $suffix . '@sunrisefilms.test', 'phone' => 'call me',
        'address' => 'Somewhere', 'role' => User::ROLE_EMPLOYEE,
    ])->errors()));

    check('a duplicate user email is refused', array_key_exists('email', UserService::validateAccount($admin, [
        'name' => 'Duplicate', 'email' => $employeeEmail, 'phone' => '9876543210',
        'address' => 'Somewhere', 'role' => User::ROLE_EMPLOYEE,
    ])->errors()));

    check('an edit keeps its own email address', !array_key_exists('email', UserService::validateAccount($admin, [
        'name' => 'Module Employee', 'email' => $employeeEmail, 'phone' => '9876543210',
        'address' => 'Somewhere',
    ], $employee)->errors()));

    // -----------------------------------------------------------------------
    echo PHP_EOL . "Employee Management - scope (s15, updated - Manager reach widened to match Admin)" . PHP_EOL;

    $stray = UserService::createAccount($admin, [
        'name'     => 'Module Stray Employee',
        'email'    => $strayEmail,
        'phone'    => '+91 98765 43214',
        'address'  => '7 Brigade Road, Bengaluru 560001',
        'role'     => User::ROLE_EMPLOYEE,
        'password' => PasswordPolicy::generate(),
    ]);
    $userIds[] = $stray->id;

    $adminIds = array_map(static fn (User $u): int => $u->id, UserService::people($admin));

    check('admin sees managers and employees system-wide',
        in_array($manager->id, $adminIds, true)
        && in_array($employee->id, $adminIds, true)
        && in_array($stray->id, $adminIds, true));

    $managerIds = array_map(static fn (User $u): int => $u->id, UserService::people($manager));

    check('manager sees every employee system-wide, not just their own',
        in_array($employee->id, $managerIds, true) && in_array($stray->id, $managerIds, true));

    check('manager can reach an employee they did not create',
        User::findById($stray->id) !== null
        && UserService::findManagedOrFail($manager, $stray->id)->id === $stray->id);

    check('manager cannot reach a manager account',
        refused(static fn () => UserService::findManagedOrFail($manager, $manager->id)));

    check('employee cannot reach anybody',
        refused(static fn () => UserService::findManagedOrFail($employee, $stray->id)));

    // -----------------------------------------------------------------------
    echo PHP_EOL . "Employee Management - search (s16)" . PHP_EOL;

    $found = UserService::people($admin, 'Module Stray');
    check('search finds an account by name', count($found) === 1 && $found[0]->id === $stray->id);

    $foundByPhone = UserService::people($admin, '98765 43214');
    check('search finds an account by mobile number',
        count($foundByPhone) === 1 && $foundByPhone[0]->id === $stray->id);

    check('search that matches nothing returns nothing',
        UserService::people($admin, 'no-such-person-' . $suffix) === []);

    // -----------------------------------------------------------------------
    echo PHP_EOL . "Employee Management - edit, status and delete (s12)" . PHP_EOL;

    $edited = UserService::updateAccount($manager, $employee, [
        'name'    => 'Module Employee Renamed',
        'email'   => $employeeEmail,
        'phone'   => '+91 90000 00000',
        'address' => '101 New Address, Bengaluru 560002',
    ]);

    check('manager can edit their employee', $edited->name === 'Module Employee Renamed'
        && $edited->phone === '+91 90000 00000'
        && $edited->address === '101 New Address, Bengaluru 560002');

    UserService::setManagedStatus($manager, $employee, User::STATUS_INACTIVE);
    check('manager can deactivate their employee',
        User::findById($employee->id)?->status === User::STATUS_INACTIVE);

    UserService::setManagedStatus($manager, $employee, User::STATUS_ACTIVE);

    UserService::setManagedStatus($manager, $stray, User::STATUS_INACTIVE);
    check('manager can deactivate an employee they did not create',
        User::findById($stray->id)?->status === User::STATUS_INACTIVE);
    UserService::setManagedStatus($manager, $stray, User::STATUS_ACTIVE);

    check('manager is refused permission to delete an account',
        refused(static fn () => UserService::deleteAccount($manager, $stray)));

    check('a manager who still owns employees cannot be deleted',
        refused(static fn () => UserService::deleteAccount($admin, $manager)));

    UserService::deleteAccount($admin, $stray);
    check('admin can delete an account', User::findById($stray->id) === null);
    $userIds = array_values(array_filter($userIds, static fn (int $id): bool => $id !== $stray->id));

    // -----------------------------------------------------------------------
    echo PHP_EOL . "Welcome email (s11, s17)" . PHP_EOL;

    $mailDir = (string) Config::get('mail.log_path');
    $before  = glob($mailDir . '/*.html') ?: [];

    $sent     = WelcomeMailer::send($employee, $manager, 'TempPass123');
    $after    = glob($mailDir . '/*.html') ?: [];
    $newFiles = array_values(array_diff($after, $before));

    check('a welcome email is sent when an account is created', $sent);
    check('the welcome email is addressed to the new account',
        $newFiles !== [] && str_contains((string) file_get_contents($newFiles[0]), $employeeEmail));
    check('the welcome email carries the temporary password',
        $newFiles !== [] && str_contains((string) file_get_contents($newFiles[0]), 'TempPass123'));
    check('the welcome email names the sign-in page',
        $newFiles !== [] && str_contains((string) file_get_contents($newFiles[0]), '/employee'));

    foreach ($newFiles as $file) {
        @unlink($file);
    }

    // =======================================================================
    echo PHP_EOL . "Customer Management - access (s2, s13, s17)" . PHP_EOL;

    check('admin can access customer management', CustomerService::canAccess($admin));
    check('manager can access customer management', CustomerService::canAccess($manager));
    check('employee cannot access customer management', !CustomerService::canAccess($employee));
    check('employee is refused the customer list',
        refused(static fn () => CustomerService::list($employee)));

    // -----------------------------------------------------------------------
    echo PHP_EOL . "Customer Management - add and capture (s3, s4, s17)" . PHP_EOL;

    $customer = CustomerService::create($admin, [
        'name'    => 'Module Customer',
        'email'   => $customerEmail,
        'phone'   => '+91 99999 88888',
        'address' => '5 Lavelle Road, Bengaluru 560001',
    ]);
    $customerIds[] = $customer->id;

    check('admin can add customers', $customer->id > 0);

    $stored = Customer::findById($customer->id);

    check('customer name is stored', $stored?->name === 'Module Customer');
    check('customer email is stored', $stored?->email === mb_strtolower($customerEmail));
    check('customer mobile number is stored', $stored?->phone === '+91 99999 88888');
    check('customer address is stored', $stored?->address === '5 Lavelle Road, Bengaluru 560001');
    check('the registering user is recorded', $stored?->createdBy === $admin->id);

    $managerCustomer = CustomerService::create($manager, [
        'name'    => 'Module Manager Customer',
        'email'   => 'mod-mcustomer-' . $suffix . '@sunrisefilms.test',
        'phone'   => '+91 99999 77777',
        'address' => '8 Infantry Road, Bengaluru 560001',
    ]);
    $customerIds[] = $managerCustomer->id;

    check('manager can add customers', $managerCustomer->createdBy === $manager->id);

    check('employee cannot add customers', refused(static fn () => CustomerService::create($employee, [
        'name'    => 'Should Not Exist',
        'email'   => 'nope3-' . $suffix . '@sunrisefilms.test',
        'phone'   => '+91 99999 66666',
        'address' => 'Nowhere',
    ])));

    // -----------------------------------------------------------------------
    echo PHP_EOL . "Customer Management - validation (s14)" . PHP_EOL;

    $blank = CustomerService::validate(['name' => '', 'email' => '', 'phone' => '', 'address' => '']);

    check('every customer field is required',
        count(array_intersect(['name', 'email', 'phone', 'address'], array_keys($blank->errors()))) === 4);

    check('a duplicate customer email is refused',
        array_key_exists('email', CustomerService::validate([
            'name' => 'Duplicate', 'email' => $customerEmail,
            'phone' => '9999988888', 'address' => 'Somewhere',
        ])->errors()));

    check('editing a customer keeps its own email address',
        !array_key_exists('email', CustomerService::validate([
            'name' => 'Module Customer', 'email' => $customerEmail,
            'phone' => '9999988888', 'address' => 'Somewhere',
        ], $customer)->errors()));

    // -----------------------------------------------------------------------
    echo PHP_EOL . "Customer Management - view, search, edit, remove (s5, s13)" . PHP_EOL;

    check('a manager sees the customers an admin registered',
        in_array($customer->id, array_map(
            static fn (Customer $c): int => $c->id,
            CustomerService::list($manager),
        ), true));

    $hits = CustomerService::list($admin, 'Module Manager Customer');
    check('customers can be searched by name', count($hits) === 1 && $hits[0]->id === $managerCustomer->id);

    $hits = CustomerService::list($admin, 'Lavelle');
    check('customers can be searched by address', count($hits) === 1 && $hits[0]->id === $customer->id);

    $updated = CustomerService::update($manager, $customer, [
        'name'    => 'Module Customer Renamed',
        'email'   => $customerEmail,
        'phone'   => '+91 91111 22222',
        'address' => '6 Lavelle Road, Bengaluru 560001',
    ]);

    check('manager can edit a customer', $updated->name === 'Module Customer Renamed'
        && $updated->phone === '+91 91111 22222');

    CustomerService::setStatus($manager, $customer, Customer::STATUS_INACTIVE);
    check('manager can deactivate a customer',
        Customer::findById($customer->id)?->status === Customer::STATUS_INACTIVE);

    check('the status filter narrows the list',
        array_map(
            static fn (Customer $c): string => $c->status,
            CustomerService::list($admin, 'Module ', Customer::STATUS_INACTIVE),
        ) === [Customer::STATUS_INACTIVE]);

    CustomerService::setStatus($admin, $customer, Customer::STATUS_ACTIVE);
    check('a deactivated customer can be reactivated',
        Customer::findById($customer->id)?->status === Customer::STATUS_ACTIVE);

    check('a manager cannot delete a customer outright',
        refused(static fn () => CustomerService::delete($manager, $customer)));

    CustomerService::delete($admin, $managerCustomer);
    check('admin can delete a customer', Customer::findById($managerCustomer->id) === null);
    $customerIds = array_values(array_filter(
        $customerIds,
        static fn (int $id): bool => $id !== $managerCustomer->id,
    ));
} finally {
    // ---------------------------------------------------------------------
    // Clean up. auth_tokens and password_resets cascade with the user rows;
    // employees are removed before the manager that owns them.
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
