<?php

declare(strict_types=1);

/**
 * End-to-end check of the authentication acceptance criteria (spec s21),
 * run against the real database.
 *
 * It creates a throwaway Manager and Employee, exercises login, role
 * restriction, tokens, logout, account status and both password-reset paths,
 * then deletes everything it created.
 *
 * Usage:  php tests/auth_check.php
 */

use App\Core\Database;
use App\Core\Request;
use App\Models\AuthToken;
use App\Models\User;
use App\Services\AuthService;
use App\Services\PasswordPolicy;
use App\Services\PasswordResetService;
use App\Services\TokenService;
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

$suffix         = bin2hex(random_bytes(4));
$managerEmail   = 'check-manager-' . $suffix . '@sunrisefilms.test';
$employeeEmail  = 'check-employee-' . $suffix . '@sunrisefilms.test';
$managerId      = null;
$employeeId     = null;

$request = Request::capture();

try {
    Database::connection();
} catch (Throwable $e) {
    fwrite(STDERR, $e->getMessage() . PHP_EOL);
    exit(1);
}

try {
    // -----------------------------------------------------------------------
    echo PHP_EOL . "Admin" . PHP_EOL;

    $admin = User::findByEmail('admin@gmail.com');

    if ($admin === null) {
        fwrite(STDERR, 'The seeded admin is missing. Run: php database/seed.php' . PHP_EOL);
        exit(1);
    }

    check('admin password is hashed, not stored in plain text', $admin->passwordHash !== 'admin123'
        && PasswordPolicy::verify('admin123', $admin->passwordHash));

    check('admin signs in at the admin entry point',
        AuthService::attempt($request, 'admin@gmail.com', 'admin123', User::ROLE_ADMIN)->succeeded);

    check('wrong password is rejected',
        !AuthService::attempt($request, 'admin@gmail.com', 'wrong-password', User::ROLE_ADMIN)->succeeded);

    check('admin cannot sign in at the manager entry point',
        !AuthService::attempt($request, 'admin@gmail.com', 'admin123', User::ROLE_MANAGER)->succeeded);

    check('unknown email is rejected with the same message',
        AuthService::attempt($request, 'nobody-' . $suffix . '@example.com', 'admin123', User::ROLE_ADMIN)->message
            === 'Invalid email or password.');

    // -----------------------------------------------------------------------
    echo PHP_EOL . "Account creation hierarchy" . PHP_EOL;

    check('admin can assign manager and employee roles',
        UserService::assignableRoles($admin) === [User::ROLE_MANAGER, User::ROLE_EMPLOYEE]);

    $manager   = UserService::createAccount($admin, [
        'name'     => 'Check Manager',
        'email'    => $managerEmail,
        'phone'    => '9876543210',
        'address'  => '1 Check Street',
        'role'     => User::ROLE_MANAGER,
        'password' => 'manager123',
        'status'   => User::STATUS_ACTIVE,
    ]);
    $managerId = $manager->id;

    check('admin created a manager', $manager->role === User::ROLE_MANAGER && $manager->createdBy === $admin->id);
    check('manager can assign the employee role only',
        UserService::assignableRoles($manager) === [User::ROLE_EMPLOYEE]);

    $employee   = UserService::createAccount($manager, [
        'name'     => 'Check Employee',
        'email'    => $employeeEmail,
        'phone'    => '9876501234',
        'address'  => '2 Check Street',
        'role'     => User::ROLE_EMPLOYEE,
        'password' => 'employee123',
        'status'   => User::STATUS_ACTIVE,
    ]);
    $employeeId = $employee->id;

    check('manager created an employee', $employee->role === User::ROLE_EMPLOYEE && $employee->createdBy === $manager->id);
    check('employee can assign no roles', UserService::assignableRoles($employee) === []);

    check('duplicate email is refused', UserService::validateAccount($admin, [
        'name'    => 'Duplicate',
        'email'   => $managerEmail,
        'phone'   => '9876543210',
        'address' => '3 Check Street',
        'role'    => User::ROLE_MANAGER,
    ])->fails());

    check('a manager cannot assign the manager role', UserService::validateAccount($manager, [
        'name'    => 'Sneaky Manager',
        'email'   => 'sneaky-' . $suffix . '@sunrisefilms.test',
        'phone'   => '9876543210',
        'address' => '4 Check Street',
        'role'    => User::ROLE_MANAGER,
    ])->errors()['role'] === 'You are not authorized to assign that role.');

    // -----------------------------------------------------------------------
    echo PHP_EOL . "Role restrictions" . PHP_EOL;

    check('manager signs in at /manager',
        AuthService::attempt($request, $managerEmail, 'manager123', User::ROLE_MANAGER)->succeeded);

    check('manager cannot sign in at /admin',
        !AuthService::attempt($request, $managerEmail, 'manager123', User::ROLE_ADMIN)->succeeded);

    check('employee cannot sign in at /manager',
        !AuthService::attempt($request, $employeeEmail, 'employee123', User::ROLE_MANAGER)->succeeded);

    $employeeCanReachManager = true;

    try {
        UserService::findManagedOrFail($employee, $managerId);
    } catch (Throwable $e) {
        $employeeCanReachManager = false;
    }

    check('employee cannot manage anyone', !$employeeCanReachManager);

    $managerCanReachManager = true;

    try {
        UserService::findManagedOrFail($manager, $managerId);
    } catch (Throwable $e) {
        $managerCanReachManager = false;
    }

    check('manager cannot manage another manager', !$managerCanReachManager);

    // -----------------------------------------------------------------------
    echo PHP_EOL . "Tokens" . PHP_EOL;

    $token = TokenService::issue($manager, $request);

    check('issued token resolves to its user', TokenService::resolve($token)?->id === $managerId);
    check('token is not stored in plain text',
        Database::selectOne('SELECT id FROM auth_tokens WHERE validator_hash = ?', [explode('.', $token)[1]]) === null);
    check('tampered token is refused', TokenService::resolve(explode('.', $token)[0] . '.' . str_repeat('a', 64)) === null);
    check('malformed token is refused', TokenService::resolve('not-a-token') === null);

    TokenService::revoke($token);
    check('revoked token no longer resolves (logout)', TokenService::resolve($token) === null);

    // -----------------------------------------------------------------------
    echo PHP_EOL . "Account status" . PHP_EOL;

    $liveToken = TokenService::issue($employee, $request);
    UserService::setManagedStatus($manager, $employee, User::STATUS_SUSPENDED);

    check('suspended account cannot sign in',
        !AuthService::attempt($request, $employeeEmail, 'employee123', User::ROLE_EMPLOYEE)->succeeded);
    check('suspending revokes existing tokens', TokenService::resolve($liveToken) === null);

    UserService::setManagedStatus($manager, $employee, User::STATUS_ACTIVE);
    check('reactivated account can sign in again',
        AuthService::attempt($request, $employeeEmail, 'employee123', User::ROLE_EMPLOYEE)->succeeded);

    // -----------------------------------------------------------------------
    echo PHP_EOL . "Password reset by a superior" . PHP_EOL;

    $survivingToken = TokenService::issue($employee, $request);
    UserService::resetManagedPassword($manager, $employee, 'reset456789');

    check('manager reset the employee password',
        AuthService::attempt($request, $employeeEmail, 'reset456789', User::ROLE_EMPLOYEE)->succeeded);
    check('old password no longer works',
        !AuthService::attempt($request, $employeeEmail, 'employee123', User::ROLE_EMPLOYEE)->succeeded);
    check('reset revoked the employee tokens', TokenService::resolve($survivingToken) === null);

    UserService::resetManagedPassword($admin, $manager, 'reset987654');
    check('admin reset the manager password',
        AuthService::attempt($request, $managerEmail, 'reset987654', User::ROLE_MANAGER)->succeeded);

    // -----------------------------------------------------------------------
    echo PHP_EOL . "Forgot Password flow" . PHP_EOL;

    $outcome = PasswordResetService::request($request, $employeeEmail, User::ROLE_EMPLOYEE);
    check('a reset request for a real account is accepted', $outcome['accepted']);

    $unknown = PasswordResetService::request($request, 'ghost-' . $suffix . '@example.com', User::ROLE_EMPLOYEE);
    check('an unknown email gets the same message', $unknown['message'] === $outcome['message']);

    $resetToken = null;

    if (is_string($outcome['link'])) {
        parse_str((string) parse_url($outcome['link'], PHP_URL_QUERY), $query);
        $resetToken = is_string($query['token'] ?? null) ? $query['token'] : null;
    } else {
        // Not in local+log mode, so rebuild what the emailed link carries.
        echo "  SKIP  reset link not surfaced (APP_ENV/MAIL_DRIVER are not local/log)" . PHP_EOL;
    }

    if ($resetToken !== null) {
        check('reset link authorises its own account',
            PasswordResetService::authorise($resetToken, User::ROLE_EMPLOYEE)?->id === $employeeId);
        check('reset link is bound to its role',
            PasswordResetService::authorise($resetToken, User::ROLE_MANAGER) === null);
        check('password and confirmation must match', (function (): bool {
            $v = new App\Core\Validator();
            PasswordPolicy::validate($v, 'newpass123', 'different123');

            return ($v->errors()['password_confirmation'] ?? '') === 'Passwords do not match.';
        })());
        check('a short password is refused', (function (): bool {
            $v = new App\Core\Validator();
            PasswordPolicy::validate($v, 'ab1', 'ab1');

            return $v->fails();
        })());

        check('reset completes', PasswordResetService::complete($resetToken, User::ROLE_EMPLOYEE, 'brandnew123'));
        check('new password works',
            AuthService::attempt($request, $employeeEmail, 'brandnew123', User::ROLE_EMPLOYEE)->succeeded);
        check('the link cannot be reused',
            !PasswordResetService::complete($resetToken, User::ROLE_EMPLOYEE, 'thirdtime123'));
    }
} finally {
    // ---------------------------------------------------------------------
    // Clean up. auth_tokens and password_resets cascade with the user rows.
    foreach ([$employeeId, $managerId] as $id) {
        if ($id !== null) {
            Database::statement('DELETE FROM users WHERE id = ?', [$id]);
        }
    }

    Database::statement("DELETE FROM login_attempts WHERE attempt_key LIKE ?", ['%' . $suffix . '%']);
    Database::statement("DELETE FROM login_attempts WHERE attempt_key LIKE ?", ['%sunrisefilms.test%']);
}

echo PHP_EOL . str_repeat('-', 52) . PHP_EOL;
echo sprintf('%d passed, %d failed', $passed, $failed) . PHP_EOL;

exit($failed === 0 ? 0 : 1);
