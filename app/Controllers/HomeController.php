<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Config;
use App\Core\Controller;
use App\Core\Request;
use App\Services\AuthService;

final class HomeController extends Controller
{
    /**
     * GET / — there is no shared landing page; every role signs in directly
     * at its own URL (/admin, /manager, /employee), so this just forwards to
     * the Admin login, or to the dashboard of whoever is already signed in.
     *
     * @param array<string, string> $params
     */
    public function index(Request $request, array $params): never
    {
        $user = AuthService::resolve($request);

        if ($user !== null) {
            $this->redirect($user->dashboardPath());
        }

        $this->redirect((string) Config::get('roles.admin.login'));
    }
}
