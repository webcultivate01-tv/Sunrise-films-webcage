<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Config;
use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Core\Validator;
use App\Models\User;
use App\Services\PasswordPolicy;
use App\Services\UserService;
use App\Services\WelcomeMailer;

/**
 * Employee Management (module spec s6 - s12).
 *
 * One controller serves the Admin and the Manager panel. What differs between
 * them - which accounts are listed, which roles may be assigned - is decided
 * by UserService from the signed-in user, never from the request, so a Manager
 * cannot reach a Manager account by editing a URL or a form field.
 */
final class EmployeeController extends Controller
{
    /**
     * GET /{panel}/employees - the list and the search box (module spec s16).
     *
     * @param array<string, string> $params
     */
    public function index(Request $request, array $params): never
    {
        $user   = $this->user();
        $search = $request->string('q');
        $role   = $request->string('role');

        $this->view('employees.index', [
            'title'      => 'Employee Management',
            'baseUrl'    => $this->baseUrl($user),
            'people'     => UserService::people($user, $search, $role),
            'search'     => $search,
            'roles'      => UserService::assignableRoles($user),
            'roleFilter' => $role,
            'canDelete'  => UserService::canDelete($user),
        ], 'panel');
    }

    /**
     * GET /{panel}/employees/create - module spec s7, s9.
     *
     * @param array<string, string> $params
     */
    public function create(Request $request, array $params): never
    {
        $user = $this->user();

        $this->view('employees.form', [
            'title'   => 'Add User',
            'baseUrl' => $this->baseUrl($user),
            'account' => null,
            'roles'   => $this->roleOptions($user),
        ], 'panel');
    }

    /**
     * POST /{panel}/employees - module spec s9, s11.
     *
     * @param array<string, string> $params
     */
    public function store(Request $request, array $params): never
    {
        $user  = $this->user();
        $base  = $this->baseUrl($user);
        $input = $request->only(['name', 'email', 'phone', 'address', 'role', 'status']);

        $temporaryPassword = $request->string('temporary_password');

        $validator = UserService::validateAccount($user, $input);

        // The admin may set the sign-in password directly, rather than always
        // having the system generate one, so a manager or employee can log in
        // right away.
        if ($temporaryPassword !== '') {
            PasswordPolicy::validate($validator, $temporaryPassword, $temporaryPassword, 'temporary_password', 'temporary_password');
        }

        if ($validator->fails()) {
            $this->redirectWithErrors($base . '/create', $validator->errors(), $input + ['temporary_password' => $temporaryPassword]);
        }

        // Module spec s7 lists no password field, so when the admin leaves it
        // blank the system generates one and sends it with the welcome email
        // (module spec s11).
        $password          = $temporaryPassword !== '' ? $temporaryPassword : PasswordPolicy::generate();
        $input['password'] = $password;

        $created = UserService::createAccount($user, $input);
        $emailed = WelcomeMailer::send($created, $user, $password);

        $this->redirectWithFlash(
            $base . '/' . $created->id,
            'success',
            $emailed
                ? sprintf(
                    '%s account for %s created. A welcome email with their temporary password was sent to %s. '
                    . 'Temporary password: %s',
                    $created->roleLabel(),
                    $created->name,
                    $created->email,
                    $password,
                )
                : sprintf(
                    '%s account for %s created, but the welcome email could not be sent. '
                    . 'Share their temporary password securely: %s',
                    $created->roleLabel(),
                    $created->name,
                    $password,
                ),
        );
    }

    /**
     * GET /{panel}/employees/suggest - live suggestions for the search box.
     *
     * @param array<string, string> $params
     */
    public function suggest(Request $request, array $params): never
    {
        $user   = $this->user();
        $base   = $this->baseUrl($user);
        $search = $request->string('q');

        $results = array_map(
            static fn (User $account): array => [
                'id'     => $account->id,
                'name'   => $account->name,
                'sub'    => $account->email,
                'status' => $account->status,
                'url'    => $base . '/' . $account->id,
            ],
            UserService::suggest($user, $search),
        );

        Response::json(['results' => $results]);
    }

    /**
     * GET /{panel}/employees/{id} - module spec s16 (View action).
     *
     * @param array<string, string> $params
     */
    public function show(Request $request, array $params): never
    {
        $user   = $this->user();
        $target = UserService::findManagedOrFail($user, (int) $params['id']);

        $this->view('employees.show', [
            'title'     => $target->name,
            'baseUrl'   => $this->baseUrl($user),
            'account'   => $target,
            'createdBy' => $target->createdBy !== null ? User::findById($target->createdBy) : null,
            'canDelete' => UserService::canDelete($user),
        ], 'panel');
    }

    /**
     * GET /{panel}/employees/{id}/edit - module spec s12 (Edit Employee).
     *
     * @param array<string, string> $params
     */
    public function edit(Request $request, array $params): never
    {
        $user   = $this->user();
        $target = UserService::findManagedOrFail($user, (int) $params['id']);

        $this->view('employees.form', [
            'title'   => 'Edit ' . $target->name,
            'baseUrl' => $this->baseUrl($user),
            'account' => $target,
            'roles'   => $this->roleOptions($user),
        ], 'panel');
    }

    /**
     * POST /{panel}/employees/{id} - module spec s12.
     *
     * @param array<string, string> $params
     */
    public function update(Request $request, array $params): never
    {
        $user   = $this->user();
        $target = UserService::findManagedOrFail($user, (int) $params['id']);
        $base   = $this->baseUrl($user);
        $input  = $request->only(['name', 'email', 'phone', 'address']);

        $validator = UserService::validateAccount($user, $input, $target);

        if ($validator->fails()) {
            $this->redirectWithErrors($base . '/' . $target->id . '/edit', $validator->errors(), $input);
        }

        $updated = UserService::updateAccount($user, $target, $input);

        $this->redirectWithFlash(
            $base . '/' . $updated->id,
            'success',
            sprintf('%s has been updated.', $updated->name),
        );
    }

    /**
     * GET /{panel}/employees/{id}/password
     *
     * @param array<string, string> $params
     */
    public function editPassword(Request $request, array $params): never
    {
        $user   = $this->user();
        $target = UserService::findManagedOrFail($user, (int) $params['id']);

        $this->view('employees.reset-password', [
            'title'   => 'Reset Password',
            'baseUrl' => $this->baseUrl($user),
            'account' => $target,
            'policy'  => PasswordPolicy::description(),
        ], 'panel');
    }

    /**
     * POST /{panel}/employees/{id}/password
     *
     * @param array<string, string> $params
     */
    public function updatePassword(Request $request, array $params): never
    {
        $user   = $this->user();
        $target = UserService::findManagedOrFail($user, (int) $params['id']);
        $base   = $this->baseUrl($user);

        $validator = new Validator();
        PasswordPolicy::validate(
            $validator,
            (string) $request->input('password', ''),
            (string) $request->input('password_confirmation', ''),
        );

        if ($validator->fails()) {
            $this->redirectWithErrors($base . '/' . $target->id . '/password', $validator->errors());
        }

        UserService::resetManagedPassword($user, $target, (string) $request->input('password', ''));

        $this->redirectWithFlash(
            $base . '/' . $target->id,
            'success',
            sprintf('The password for %s has been reset. Any active sessions have been signed out.', $target->name),
        );
    }

    /**
     * POST /{panel}/employees/{id}/status - module spec s12 (Deactivate).
     *
     * @param array<string, string> $params
     */
    public function updateStatus(Request $request, array $params): never
    {
        $user   = $this->user();
        $target = UserService::findManagedOrFail($user, (int) $params['id']);
        $status = $request->string('status');

        UserService::setManagedStatus($user, $target, $status);

        $this->redirectWithFlash(
            $this->baseUrl($user),
            'success',
            $status === User::STATUS_ACTIVE
                ? sprintf('%s can sign in again.', $target->name)
                : sprintf('%s is now %s and can no longer sign in.', $target->name, $status),
        );
    }

    /**
     * POST /{panel}/employees/{id}/delete - module spec s12 (Delete).
     *
     * @param array<string, string> $params
     */
    public function destroy(Request $request, array $params): never
    {
        $user   = $this->user();
        $target = UserService::findManagedOrFail($user, (int) $params['id']);

        UserService::deleteAccount($user, $target);

        $this->redirectWithFlash(
            $this->baseUrl($user),
            'success',
            sprintf('%s has been deleted.', $target->name),
        );
    }

    /**
     * The role <select> for the create form: both roles for an Admin, Employee
     * alone for a Manager (module spec s8).
     *
     * @return array<string, string> role => label
     */
    private function roleOptions(User $user): array
    {
        $options = [];

        foreach (UserService::assignableRoles($user) as $role) {
            $options[$role] = (string) Config::get('roles.' . $role . '.label', ucfirst($role));
        }

        return $options;
    }

    private function baseUrl(User $user): string
    {
        return (string) Config::get('roles.' . $user->role . '.login') . '/employees';
    }
}
