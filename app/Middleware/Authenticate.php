<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Config;
use App\Core\Middleware;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Services\AuthService;

/**
 * Guards a panel (spec s14). The argument is the role the route belongs to:
 * `auth:manager` only lets a Manager through.
 */
final class Authenticate implements Middleware
{
    /**
     * @param array<string, string> $params
     */
    public function handle(Request $request, array $params, ?string $argument = null): void
    {
        $role = $argument ?? ($params['role'] ?? '');
        $user = AuthService::resolve($request);

        // No token, or an expired/invalid/revoked one: back to the login page
        // for the panel that was asked for.
        if ($user === null) {
            Session::flash('error', 'Please sign in to continue.');

            Response::redirect((string) Config::get('roles.' . $role . '.login', '/'));
        }

        // A valid token for the wrong panel. The token carries the role, so it
        // cannot be used sideways (spec s6, s14) — the user is sent to the
        // panel they are actually entitled to.
        if ($user->role !== $role) {
            Session::flash('error', 'You are not authorized to access this page.');

            Response::redirect($user->dashboardPath());
        }
    }
}
