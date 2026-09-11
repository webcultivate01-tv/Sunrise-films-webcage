<?php

declare(strict_types=1);

use App\Controllers\AccountController;
use App\Controllers\Auth\AuthController;
use App\Controllers\DashboardController;
use App\Controllers\HomeController;
use App\Controllers\ModuleController;
use App\Controllers\ProfileController;
use App\Core\Config;
use App\Core\Router;
use App\Support\PanelModules;

/**
 * Every role gets the same set of routes under its own entry point (spec s2):
 * /admin, /manager and /employee are built from the same definitions, so the
 * three panels cannot drift apart.
 */
return static function (Router $router): void {
    $router->get('/', [HomeController::class, 'index']);

    foreach (Config::roleNames() as $role) {
        $roleConfig = Config::role($role);

        $router->group(
            [
                'prefix'   => (string) $roleConfig['login'],
                'defaults' => ['role' => $role],
            ],
            static function (Router $router) use ($role, $roleConfig): void {
                // --- Guest ------------------------------------------------
                $router->get('', [AuthController::class, 'showLogin'], ['guest']);
                $router->post('/login', [AuthController::class, 'login'], ['csrf', 'guest']);

                // Self-service recovery is always on for Manager and Employee,
                // and for Admin only when policy enables it (spec s7).
                if ($role !== 'admin' || Config::get('auth.admin_forgot_password') === true) {
                    $router->get('/forgot-password', [AuthController::class, 'showForgotPassword'], ['guest']);
                    $router->post('/forgot-password', [AuthController::class, 'sendResetLink'], ['csrf', 'guest']);
                    $router->get('/reset-password', [AuthController::class, 'showResetPassword'], ['guest']);
                    $router->post('/reset-password', [AuthController::class, 'resetPassword'], ['csrf', 'guest']);
                }

                // --- Authenticated ----------------------------------------
                $router->post('/logout', [AuthController::class, 'logout'], ['csrf', 'auth']);
                $router->get('/dashboard', [DashboardController::class, 'index'], ['auth']);
                $router->get('/profile', [ProfileController::class, 'show'], ['auth']);
                $router->post('/profile', [ProfileController::class, 'updateDetails'], ['csrf', 'auth']);
                $router->post('/profile/photo', [ProfileController::class, 'updatePhoto'], ['csrf', 'auth']);
                $router->post('/profile/password', [ProfileController::class, 'updatePassword'], ['csrf', 'auth']);

                // --- Managing the role one level down ---------------------
                $manages = $roleConfig['manages'] ?? null;

                if (!is_string($manages)) {
                    return;
                }

                $segment = '/' . $manages . 's';

                $router->get($segment, [AccountController::class, 'index'], ['auth']);
                $router->get($segment . '/create', [AccountController::class, 'create'], ['auth']);
                $router->post($segment, [AccountController::class, 'store'], ['csrf', 'auth']);
                $router->get($segment . '/{id}/password', [AccountController::class, 'editPassword'], ['auth']);
                $router->post($segment . '/{id}/password', [AccountController::class, 'updatePassword'], ['csrf', 'auth']);
                $router->post($segment . '/{id}/status', [AccountController::class, 'updateStatus'], ['csrf', 'auth']);
            },
        );

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
