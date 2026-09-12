<?php

declare(strict_types=1);

use App\Controllers\Auth\AuthController;
use App\Controllers\CustomerController;
use App\Controllers\DashboardController;
use App\Controllers\EmployeeController;
use App\Controllers\HomeController;
use App\Controllers\ModuleController;
use App\Controllers\MySalaryController;
use App\Controllers\MyWorkController;
use App\Controllers\PaymentController;
use App\Controllers\ProfileController;
use App\Controllers\ProjectController;
use App\Controllers\SalaryController;
use App\Controllers\TaskController;
use App\Core\Config;
use App\Core\Router;
use App\Models\User;
use App\Support\PanelModules;

/**
 * Every role gets the same set of routes under its own entry point (auth spec
 * s2): /admin, /manager and /employee are built from the same definitions, so
 * the three panels cannot drift apart.
 *
 * Customer Management and Employee Management are the exception: they are
 * registered only on the panels allowed to have them (module spec s2, s6), so
 * on the Employee panel those URLs do not exist at all.
 */
return static function (Router $router): void {
    $router->get('/', [HomeController::class, 'index']);

    // Module spec s2, s6: Admin and Manager only.
    $managementRoles = [User::ROLE_ADMIN, User::ROLE_MANAGER];
    $management      = 'roles:' . implode(',', $managementRoles);

    foreach (Config::roleNames() as $role) {
        $roleConfig = Config::role($role);

        $router->group(
            [
                'prefix'   => (string) $roleConfig['login'],
                'defaults' => ['role' => $role],
            ],
            static function (Router $router) use ($role): void {
                // --- Guest ------------------------------------------------
                $router->get('', [AuthController::class, 'showLogin'], ['guest']);
                $router->post('/login', [AuthController::class, 'login'], ['csrf', 'guest']);

                // The routes exist for every role alike; for Admin, whether
                // they actually do anything is a live policy check inside
                // AuthController / PasswordResetService (auth spec s7), kept
                // in Admin Management's settings rather than .env so it can
                // change without a redeploy.
                $router->get('/forgot-password', [AuthController::class, 'showForgotPassword'], ['guest']);
                $router->post('/forgot-password', [AuthController::class, 'sendResetLink'], ['csrf', 'guest']);
                $router->get('/reset-password', [AuthController::class, 'showResetPassword'], ['guest']);
                $router->post('/reset-password', [AuthController::class, 'resetPassword'], ['csrf', 'guest']);

                // --- Authenticated ----------------------------------------
                $router->post('/logout', [AuthController::class, 'logout'], ['csrf', 'auth']);
                $router->get('/dashboard', [DashboardController::class, 'index'], ['auth']);
                $router->get('/profile', [ProfileController::class, 'show'], ['auth']);
                $router->post('/profile', [ProfileController::class, 'updateDetails'], ['csrf', 'auth']);
                $router->post('/profile/photo', [ProfileController::class, 'updatePhoto'], ['csrf', 'auth']);
                $router->post('/profile/password', [ProfileController::class, 'updatePassword'], ['csrf', 'auth']);
            },
        );

        if (in_array($role, $managementRoles, true)) {
            $router->group(
                [
                    'prefix'     => (string) $roleConfig['login'],
                    'defaults'   => ['role' => $role],
                    'middleware' => ['auth', $management],
                ],
                static function (Router $router): void {
                    // --- Customer Management (module spec s2 - s5) ---------
                    $router->get('/customers', [CustomerController::class, 'index']);
                    $router->get('/customers/create', [CustomerController::class, 'create']);
                    $router->get('/customers/suggest', [CustomerController::class, 'suggest']);
                    $router->post('/customers', [CustomerController::class, 'store'], ['csrf']);
                    $router->get('/customers/{id}', [CustomerController::class, 'show']);
                    $router->get('/customers/{id}/edit', [CustomerController::class, 'edit']);
                    $router->post('/customers/{id}', [CustomerController::class, 'update'], ['csrf']);
                    $router->post('/customers/{id}/status', [CustomerController::class, 'updateStatus'], ['csrf']);
                    $router->post('/customers/{id}/delete', [CustomerController::class, 'destroy'], ['csrf']);

                    // --- Employee Management (module spec s6 - s12) --------
                    $router->get('/employees', [EmployeeController::class, 'index']);
                    $router->get('/employees/create', [EmployeeController::class, 'create']);
                    $router->get('/employees/suggest', [EmployeeController::class, 'suggest']);
                    $router->post('/employees', [EmployeeController::class, 'store'], ['csrf']);
                    $router->get('/employees/{id}', [EmployeeController::class, 'show']);
                    $router->get('/employees/{id}/edit', [EmployeeController::class, 'edit']);
                    $router->post('/employees/{id}', [EmployeeController::class, 'update'], ['csrf']);
                    $router->get('/employees/{id}/password', [EmployeeController::class, 'editPassword']);
                    $router->post('/employees/{id}/password', [EmployeeController::class, 'updatePassword'], ['csrf']);
                    $router->post('/employees/{id}/status', [EmployeeController::class, 'updateStatus'], ['csrf']);
                    $router->post('/employees/{id}/delete', [EmployeeController::class, 'destroy'], ['csrf']);

                    // --- Work Management -----------------------------------
                    $router->get('/projects', [ProjectController::class, 'index']);
                    $router->get('/projects/create', [ProjectController::class, 'create']);
                    $router->get('/projects/suggest', [ProjectController::class, 'suggest']);
                    $router->post('/projects', [ProjectController::class, 'store'], ['csrf']);
                    $router->get('/projects/{id}', [ProjectController::class, 'show']);
                    $router->get('/projects/{id}/bill', [ProjectController::class, 'billView']);
                    $router->get('/projects/{id}/bill/download', [ProjectController::class, 'billDownload']);
                    $router->get('/projects/{id}/edit', [ProjectController::class, 'edit']);
                    $router->post('/projects/{id}', [ProjectController::class, 'update'], ['csrf']);
                    $router->post('/projects/{id}/status', [ProjectController::class, 'updateStatus'], ['csrf']);
                    $router->post('/projects/{id}/delete', [ProjectController::class, 'destroy'], ['csrf']);

                    // --- Payment Management ---------------------------------
                    $router->get('/payments', [PaymentController::class, 'index']);
                    $router->get('/payments/suggest', [PaymentController::class, 'suggest']);
                    $router->get('/payments/history', [PaymentController::class, 'history']);
                    $router->get('/payments/history/suggest', [PaymentController::class, 'historySuggest']);
                    $router->get('/payments/create', [PaymentController::class, 'create']);
                    $router->post('/payments', [PaymentController::class, 'store'], ['csrf']);
                    $router->get('/payments/bills/{id}', [PaymentController::class, 'billView']);
                    $router->get('/payments/bills/{id}/download', [PaymentController::class, 'billDownload']);
                    $router->get('/payments/{id}', [PaymentController::class, 'show']);

                    // --- Task Management (task spec) -----------------------
                    $router->get('/tasks', [TaskController::class, 'index']);
                    $router->get('/tasks/create', [TaskController::class, 'create']);
                    $router->get('/tasks/suggest', [TaskController::class, 'suggest']);
                    $router->post('/tasks', [TaskController::class, 'store'], ['csrf']);
                    $router->get('/tasks/{id}', [TaskController::class, 'show']);
                    $router->post('/tasks/{id}/cancel', [TaskController::class, 'cancel'], ['csrf']);
                    $router->get('/tasks/{id}/reassign', [TaskController::class, 'reassignForm']);
                    $router->post('/tasks/{id}/reassign', [TaskController::class, 'reassign'], ['csrf']);

                    // --- Monthly Salary (salary spec) -----------------------
                    $router->get('/monthly-salary', [SalaryController::class, 'index']);
                    $router->get('/monthly-salary/suggest', [SalaryController::class, 'suggest']);
                    $router->get('/monthly-salary/history', [SalaryController::class, 'history']);
                    $router->get('/monthly-salary/history/suggest', [SalaryController::class, 'historySuggest']);
                    $router->get('/monthly-salary/bills/{id}', [SalaryController::class, 'billView']);
                    $router->get('/monthly-salary/bills/{id}/download', [SalaryController::class, 'billDownload']);
                    $router->get('/monthly-salary/{id}', [SalaryController::class, 'show']);
                    $router->get('/monthly-salary/{id}/settle', [SalaryController::class, 'settleForm']);
                    $router->post('/monthly-salary/{id}/settle', [SalaryController::class, 'settle'], ['csrf']);
                },
            );
        }

        // My Work - the Employee side of Task Management (task spec s10, s11, s13).
        if ($role === User::ROLE_EMPLOYEE) {
            $router->group(
                [
                    'prefix'     => (string) $roleConfig['login'],
                    'defaults'   => ['role' => $role],
                    'middleware' => ['auth'],
                ],
                static function (Router $router): void {
                    $router->get('/my-work', [MyWorkController::class, 'index']);
                    $router->get('/my-work/suggest', [MyWorkController::class, 'suggest']);
                    $router->get('/my-work/{id}', [MyWorkController::class, 'show']);
                    $router->post('/my-work/{id}/accept', [MyWorkController::class, 'accept'], ['csrf']);
                    $router->post('/my-work/{id}/progress', [MyWorkController::class, 'progress'], ['csrf']);
                    $router->post('/my-work/{id}/complete', [MyWorkController::class, 'complete'], ['csrf']);
                    $router->post('/my-work/{id}/exit', [MyWorkController::class, 'exit'], ['csrf']);

                    // My Salary - the Employee side of Monthly Salary (salary spec).
                    $router->get('/my-salary', [MySalaryController::class, 'index']);
                    $router->get('/my-salary/bills/{id}', [MySalaryController::class, 'billView']);
                    $router->get('/my-salary/bills/{id}/download', [MySalaryController::class, 'billDownload']);
                },
            );
        }

        // Sidebar modules the panel links to but has not built yet.
        $router->group(
            [
                'prefix'   => (string) $roleConfig['login'],
                'defaults' => ['role' => $role],
            ],
            static function (Router $router) use ($role): void {
                foreach (PanelModules::placeholders($role) as $moduleId) {
                    $router->get('/' . $moduleId, [ModuleController::class, 'placeholder'], ['auth']);
                }
            },
        );
    }
};
