<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Config;
use App\Core\Controller;
use App\Core\Request;
use App\Models\AuthToken;
use App\Services\UserService;

/**
 * The panel each role lands on after signing in (spec s4.5).
 */
final class DashboardController extends Controller
{
    /**
     * @param array<string, string> $params
     */
    public function index(Request $request, array $params): never
    {
        $user    = $this->user();
        $manages = UserService::manageableRole($user);

        $this->view('dashboard', [
            'title'         => $user->roleLabel() . ' Panel',
            'role'          => $user->role,
            'manages'       => $manages,
            'managesLabel'  => $manages !== null ? (string) Config::get('roles.' . $manages . '.label') : null,
            'managesUrl'    => $manages !== null ? $this->manageUrl($user->role, $manages) : null,
            'statusCounts'  => UserService::subordinateStatusCounts($user),
            'activeTokens'  => AuthToken::activeCountForUser($user->id),
        ], 'panel');
    }

    private function manageUrl(string $role, string $manages): string
    {
        return (string) Config::get('roles.' . $role . '.login') . '/' . $manages . 's';
    }
}
