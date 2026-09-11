<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Middleware;
use App\Core\Request;
use App\Core\Response;
use App\Services\AuthService;

/**
 * Keeps a signed-in user off their own login and password-recovery pages.
 *
 * Someone signed in under a different role is let through: they are allowed to
 * sign in to the panel they are looking at, which replaces their token.
 */
final class RedirectIfAuthenticated implements Middleware
{
    /**
     * @param array<string, string> $params
     */
    public function handle(Request $request, array $params, ?string $argument = null): void
    {
        $role = $argument ?? ($params['role'] ?? '');
        $user = AuthService::resolve($request);

        if ($user !== null && $user->role === $role) {
            Response::redirect($user->dashboardPath());
        }
    }
}
