<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Config;
use App\Core\Controller;
use App\Core\Exceptions\HttpException;
use App\Core\Request;
use App\Core\Validator;
use App\Models\User;
use App\Services\PasswordPolicy;
use App\Services\UserService;

/**
 * Managing the accounts one level down: Admin to Managers (spec s10),
 * Manager to Employees (spec s11). The same controller serves both because the
 * rules are identical; only the role pairing changes, and that comes from
 * config rather than from the request.
 */
final class AccountController extends Controller
{
    /**
     * GET /admin/managers, /manager/employees
     *
     * @param array<string, string> $params
     */
    public function index(Request $request, array $params): never
    {
        $user    = $this->user();
        $manages = $this->manageableRole($user);

        $this->view('accounts.index', [
            'title'        => (string) Config::get('roles.' . $manages . '.label') . 's',
            'manages'      => $manages,
            'managesLabel' => (string) Config::get('roles.' . $manages . '.label'),
            'baseUrl'      => $this->baseUrl($user, $manages),
            'accounts'     => UserService::subordinates($user),
        ], 'panel');
    }

    /**
     * GET /{panel}/{managed}s/create
     *
     * @param array<string, string> $params
     */
    public function create(Request $request, array $params): never
    {
        $user    = $this->user();
        $manages = $this->manageableRole($user);

        $this->view('accounts.create', [
            'title'        => 'Create ' . (string) Config::get('roles.' . $manages . '.label'),
            'manages'      => $manages,
            'managesLabel' => (string) Config::get('roles.' . $manages . '.label'),
            'baseUrl'      => $this->baseUrl($user, $manages),
            'loginPath'    => (string) Config::get('roles.' . $manages . '.login'),
            'policy'       => PasswordPolicy::description(),
        ], 'panel');
    }

    /**
     * POST /{panel}/{managed}s - spec s12.
     *
     * @param array<string, string> $params
     */
    public function store(Request $request, array $params): never
    {
        $user    = $this->user();
        $manages = $this->manageableRole($user);
        $base    = $this->baseUrl($user, $manages);

        $input = $request->only(['name', 'email', 'phone', 'status']);
        $input['password']              = (string) $request->input('password', '');
        $input['password_confirmation'] = (string) $request->input('password_confirmation', '');

        $validator = UserService::validateNewAccount($input);

        if ($validator->fails()) {
            $this->redirectWithErrors($base . '/create', $validator->errors(), $input);
        }

        $created = UserService::createSubordinate($user, $input);

        $this->redirectWithFlash(
            $base,
            'success',
            sprintf(
                '%s account for %s created. They can now sign in at %s.',
                $created->roleLabel(),
                $created->name,
                $created->loginPath(),
            ),
        );
    }

    /**
     * GET /{panel}/{managed}s/{id}/password
     *
     * @param array<string, string> $params
     */
    public function editPassword(Request $request, array $params): never
    {
        $user    = $this->user();
        $manages = $this->manageableRole($user);
        $target  = UserService::findSubordinateOrFail($user, (int) $params['id']);

        $this->view('accounts.reset-password', [
            'title'   => 'Reset Password',
            'account' => $target,
            'baseUrl' => $this->baseUrl($user, $manages),
            'policy'  => PasswordPolicy::description(),
        ], 'panel');
    }

    /**
     * POST /{panel}/{managed}s/{id}/password - spec s10, s11.
     *
     * @param array<string, string> $params
     */
    public function updatePassword(Request $request, array $params): never
    {
        $user    = $this->user();
        $manages = $this->manageableRole($user);
        $target  = UserService::findSubordinateOrFail($user, (int) $params['id']);
        $base    = $this->baseUrl($user, $manages);

        $password     = (string) $request->input('password', '');
        $confirmation = (string) $request->input('password_confirmation', '');

        $validator = new Validator();
        PasswordPolicy::validate($validator, $password, $confirmation);

        if ($validator->fails()) {
            $this->redirectWithErrors($base . '/' . $target->id . '/password', $validator->errors());
        }

        UserService::resetSubordinatePassword($user, $target, $password);

        $this->redirectWithFlash(
            $base,
            'success',
            sprintf(
                'The password for %s has been reset. Any active sessions have been signed out.',
                $target->name,
            ),
        );
    }

    /**
     * POST /{panel}/{managed}s/{id}/status - spec s17.
     *
     * @param array<string, string> $params
     */
    public function updateStatus(Request $request, array $params): never
    {
        $user    = $this->user();
        $manages = $this->manageableRole($user);
        $target  = UserService::findSubordinateOrFail($user, (int) $params['id']);
        $base    = $this->baseUrl($user, $manages);

        $status = $request->string('status');

        UserService::setSubordinateStatus($user, $target, $status);

        $message = $status === User::STATUS_ACTIVE
            ? sprintf('%s can sign in again.', $target->name)
            : sprintf('%s is now %s and can no longer sign in.', $target->name, $status);

        $this->redirectWithFlash($base, 'success', $message);
    }

    private function manageableRole(User $user): string
    {
        $manages = UserService::manageableRole($user);

        if ($manages === null) {
            throw HttpException::forbidden('You are not authorized to manage accounts.');
        }

        return $manages;
    }

    /**
     * An Admin managing Managers gets /admin/managers, and so on.
     */
    private function baseUrl(User $user, string $manages): string
    {
        return (string) Config::get('roles.' . $user->role . '.login') . '/' . $manages . 's';
    }
}
