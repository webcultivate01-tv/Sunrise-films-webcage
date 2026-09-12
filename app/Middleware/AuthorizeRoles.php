<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Exceptions\HttpException;
use App\Core\Middleware;
use App\Core\Request;
use App\Services\AuthService;

/**
 * Restricts a route to a list of roles: `roles:admin,manager`.
 *
 * Customer Management and Employee Management are for Admins and Managers
 * only (module spec s2, s6, s15), and this is the outermost of the three
 * layers that enforce it - the routes are not even registered on the Employee
 * panel, and the services re-check scope on every read and write. It runs
 * after `auth`, which is what guarantees there is a user to check.
 */
final class AuthorizeRoles implements Middleware
{
    /**
     * @param array<string, string> $params
     */
    public function handle(Request $request, array $params, ?string $argument = null): void
    {
        $allowed = array_filter(array_map('trim', explode(',', (string) $argument)));

        if ($allowed === []) {
            throw new \RuntimeException('The roles middleware needs at least one role, e.g. roles:admin.');
        }

        $user = AuthService::user();

        if ($user === null || !in_array($user->role, $allowed, true)) {
            throw HttpException::forbidden('You are not authorized to access this page.');
        }
    }
}
