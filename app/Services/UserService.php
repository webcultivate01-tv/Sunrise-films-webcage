<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Exceptions\HttpException;
use App\Core\Validator;
use App\Models\AuthToken;
use App\Models\User;

/**
 * Who may create and manage whom (module spec s8, s12, s15, updated).
 *
 *   Admin    -> Manager and Employee, anywhere in the system
 *   Manager  -> Employee only, but every Employee in the system (the same
 *               reach as Admin) - not just the ones they created
 *   Employee -> nobody, and no access to the module at all
 *
 * Every read and write in Employee Management goes through the scope checks
 * here, so a controller or a view cannot widen them by accident. In particular
 * a Manager must never be able to create or assign the Manager role
 * (module spec s8), and that rule lives in exactly one place: assignableRoles().
 * A Manager also may never permanently delete an account - see canDelete().
 */
final class UserService
{
    /**
     * The roles $actor may assign to an account, which is also the set of
     * roles they may see and manage - module spec s12 grants the same set for
     * viewing and for assigning.
     *
     * @return list<string>
     */
    public static function assignableRoles(User $actor): array
    {
        return match ($actor->role) {
            User::ROLE_ADMIN   => [User::ROLE_MANAGER, User::ROLE_EMPLOYEE],
            User::ROLE_MANAGER => [User::ROLE_EMPLOYEE],
            default            => [],
        };
    }

    /**
     * Whether $actor may open Employee Management and Photographer Management at
     * all. Employees may not (module spec s2, s6).
     */
    public static function managesPeople(User $actor): bool
    {
        return self::assignableRoles($actor) !== [];
    }

    /**
     * A Manager's scope was widened to match Admin: both now see every
     * account in the roles they may manage (module spec update - Manager gets
     * full read/add/edit reach, the same as Admin). What still tells them
     * apart is assignableRoles() (a Manager may only ever touch Employees) and
     * canDelete() (only an Admin may remove a record outright). Returns the
     * owner id a query should filter on, or null for "no ownership filter".
     */
    private static function scopeOwner(User $actor): ?int
    {
        return null;
    }

    /**
     * Only an Admin may permanently delete an account - a Manager can
     * deactivate one instead (module spec update).
     */
    public static function canDelete(User $actor): bool
    {
        return $actor->role === User::ROLE_ADMIN;
    }

    /**
     * The accounts $actor may list, optionally narrowed by the search box
     * (module spec s16) and by a role filter (e.g. the Employees /
     * Managers tabs) - a role outside $actor's own assignable roles is
     * ignored rather than widening what they can see.
     *
     * @return list<User>
     */
    public static function people(User $actor, string $search = '', string $roleFilter = ''): array
    {
        $roles = self::assignableRoles($actor);

        if ($roleFilter !== '' && in_array($roleFilter, $roles, true)) {
            $roles = [$roleFilter];
        }

        return User::inRoles($roles, self::scopeOwner($actor), $search);
    }

    /**
     * Up to 8 accounts matching the search box, for the live suggestion
     * dropdown (module spec s16).
     *
     * @return list<User>
     */
    public static function suggest(User $actor, string $search): array
    {
        if (trim($search) === '') {
            return [];
        }

        return array_slice(self::people($actor, $search), 0, 8);
    }

    /**
     * How many accounts of each manageable role are in scope, e.g.
     * ['manager' => 3, 'employee' => 11].
     *
     * @return array<string, int>
     */
    public static function peopleCounts(User $actor): array
    {
        $counts = [];

        foreach (self::assignableRoles($actor) as $role) {
            $counts[$role] = array_sum(User::statusCounts($role, self::scopeOwner($actor)));
        }

        return $counts;
    }

    /**
     * Load one account and prove it is inside $actor's scope. Anything else -
     * a peer or a superior - is a 403 rather than a 404, and never reaches
     * the caller (module spec s15).
     */
    public static function findManagedOrFail(User $actor, int $id): User
    {
        $roles = self::assignableRoles($actor);

        if ($roles === []) {
            throw HttpException::forbidden('You are not authorized to manage other accounts.');
        }

        $target = User::findById($id);

        if ($target === null || !in_array($target->role, $roles, true)) {
            throw HttpException::forbidden('You are not authorized to manage that account.');
        }

        $owner = self::scopeOwner($actor);

        if ($owner !== null && $target->createdBy !== $owner) {
            throw HttpException::forbidden('You are not authorized to manage that account.');
        }

        return $target;
    }

    /**
     * Validate the Employee Management form (module spec s10, s14). Pass
     * $existing when editing, so the account keeps its own email address.
     *
     * A password is validated only when the caller actually supplies one:
     * creation generates it - module spec s7 asks for no password field - and
     * a password reset validates it on its own.
     *
     * @param array<string, string> $input
     */
    public static function validateAccount(User $actor, array $input, ?User $existing = null): Validator
    {
        $validator = new Validator();

        $name    = trim($input['name'] ?? '');
        $email   = trim($input['email'] ?? '');
        $phone   = trim($input['phone'] ?? '');
        $address = trim($input['address'] ?? '');

        $validator->require('name', $name, 'Please enter a full name.');
        $validator->maxLength('name', $name, 120, 'Name must be 120 characters or fewer.');

        $validator->require('email', $email, 'Please enter an email address.');
        $validator->email('email', $email);
        $validator->maxLength('email', $email, 190, 'Email must be 190 characters or fewer.');

        $validator->require('phone', $phone, 'Please enter a mobile number.');
        $validator->phone('phone', $phone);

        $validator->require('address', $address, 'Please enter an address.');
        $validator->maxLength('address', $address, 500, 'Address must be 500 characters or fewer.');

        // The role is chosen at creation only; an edit keeps the role it has.
        if ($existing === null) {
            $role = trim($input['role'] ?? '');

            $validator->require('role', $role, 'Please select a role.');

            if ($role !== '') {
                $validator->in(
                    'role',
                    $role,
                    self::assignableRoles($actor),
                    'You are not authorized to assign that role.',
                );
            }
        }

        if (array_key_exists('status', $input)) {
            $validator->in(
                'status',
                trim($input['status']),
                [User::STATUS_ACTIVE, User::STATUS_INACTIVE, User::STATUS_SUSPENDED],
                'That account status is not recognised.',
            );
        }

        // Only worth a lookup once the address itself is well formed, and only
        // a conflict when it belongs to somebody else (module spec s14).
        if ($email !== ''
            && !array_key_exists('email', $validator->errors())
            && mb_strtolower($email) !== ($existing->email ?? null)
            && User::emailExists($email)
        ) {
            $validator->add('email', 'An account with this email address already exists.');
        }

        if (array_key_exists('password', $input)) {
            PasswordPolicy::validate(
                $validator,
                $input['password'],
                $input['password_confirmation'] ?? '',
            );
        }

        return $validator;
    }

    /**
     * Create an account in one of the roles $actor is allowed to assign
     * (module spec s9). The role is re-checked here rather than trusted from
     * the form, so a tampered <select> cannot promote anybody.
     *
     * @param array<string, string> $input
     */
    public static function createAccount(User $actor, array $input): User
    {
        $role = trim($input['role'] ?? '');

        if (!in_array($role, self::assignableRoles($actor), true)) {
            throw HttpException::forbidden('You are not authorized to create an account with that role.');
        }

        $status = $input['status'] ?? User::STATUS_ACTIVE;

        $id = User::create(
            name:         trim($input['name']),
            email:        trim($input['email']),
            phone:        trim($input['phone'] ?? ''),
            passwordHash: PasswordPolicy::hash($input['password']),
            role:         $role,
            createdBy:    $actor->id,
            status:       in_array($status, [User::STATUS_ACTIVE, User::STATUS_INACTIVE], true)
                ? $status
                : User::STATUS_ACTIVE,
            address:      trim($input['address'] ?? ''),
        );

        $created = User::findById($id);

        if ($created === null) {
            throw new \RuntimeException('The account could not be created.');
        }

        return $created;
    }

    /**
     * Edit an account's details (module spec s12). The role is deliberately
     * not editable here: changing somebody's role changes everything they can
     * reach, and the spec defines role assignment at creation time only.
     *
     * @param array<string, string> $input
     */
    public static function updateAccount(User $actor, User $target, array $input): User
    {
        self::findManagedOrFail($actor, $target->id);

        User::updateRecord(
            $target->id,
            trim($input['name']),
            trim($input['email']),
            trim($input['phone'] ?? ''),
            trim($input['address'] ?? ''),
        );

        $updated = User::findById($target->id);

        if ($updated === null) {
            throw new \RuntimeException('The account could not be found after updating.');
        }

        return $updated;
    }

    /**
     * Permanently delete an account (module spec s12). A Manager who still has
     * Employees is refused: deleting them would cut those Employees loose from
     * the person responsible for them, so they are deactivated instead.
     */
    public static function deleteAccount(User $actor, User $target): void
    {
        self::findManagedOrFail($actor, $target->id);

        if (!self::canDelete($actor)) {
            throw HttpException::forbidden('You are not authorized to delete accounts. Deactivate the account instead.');
        }

        if ($actor->id === $target->id) {
            throw HttpException::forbidden('You cannot delete your own account.');
        }

        if (User::countCreatedBy($target->id) > 0) {
            throw HttpException::forbidden(sprintf(
                '%s still has accounts under them. Reassign or remove those first, or deactivate this account instead.',
                $target->name,
            ));
        }

        User::delete($target->id);
    }

    /**
     * A superior setting somebody's password. Existing tokens for that account
     * are revoked, so a session opened with the old password cannot survive
     * the reset.
     */
    public static function resetManagedPassword(User $actor, User $target, string $password): void
    {
        // Re-assert the relationship even though the caller loaded the target
        // through findManagedOrFail().
        self::findManagedOrFail($actor, $target->id);

        User::updatePassword($target->id, PasswordPolicy::hash($password));
        AuthToken::revokeAllForUser($target->id);
    }

    /**
     * Account status management - the "deactivate" half of module spec s12.
     */
    public static function setManagedStatus(User $actor, User $target, string $status): void
    {
        self::findManagedOrFail($actor, $target->id);

        $allowed = [User::STATUS_ACTIVE, User::STATUS_INACTIVE, User::STATUS_SUSPENDED];

        if (!in_array($status, $allowed, true)) {
            throw HttpException::forbidden('That account status is not recognised.');
        }

        User::updateStatus($target->id, $status);

        // Losing active status must take effect immediately, not at token expiry.
        if ($status !== User::STATUS_ACTIVE) {
            AuthToken::revokeAllForUser($target->id);
        }
    }

    /**
     * A user changing their own password. Every other token for the account is
     * revoked and a fresh one is issued by the caller.
     */
    public static function changeOwnPassword(User $user, string $password): void
    {
        User::updatePassword($user->id, PasswordPolicy::hash($password));
        AuthToken::revokeAllForUser($user->id);
    }

    /**
     * Validate a user's edit to their own name, email and phone.
     *
     * @param array<string, string> $input
     */
    public static function validateOwnProfile(User $user, array $input): Validator
    {
        $validator = new Validator();

        $name  = trim($input['name'] ?? '');
        $email = trim($input['email'] ?? '');
        $phone = trim($input['phone'] ?? '');

        $validator->require('name', $name, 'Please enter a full name.');
        $validator->maxLength('name', $name, 120, 'Name must be 120 characters or fewer.');

        $validator->require('email', $email, 'Please enter an email address.');
        $validator->email('email', $email);
        $validator->maxLength('email', $email, 190, 'Email must be 190 characters or fewer.');

        $validator->phone('phone', $phone);

        // Only worth a lookup once the address itself is well formed, and only
        // a conflict if it belongs to someone other than this user.
        if ($email !== ''
            && !array_key_exists('email', $validator->errors())
            && mb_strtolower($email) !== $user->email
            && User::emailExists($email)
        ) {
            $validator->add('email', 'An account with this email address already exists.');
        }

        return $validator;
    }

    /**
     * A user updating their own name, email and phone. Their postal address is
     * maintained by Employee Management, so it is carried through untouched.
     *
     * @param array<string, string> $input
     */
    public static function updateOwnProfile(User $user, array $input): User
    {
        User::updateRecord(
            $user->id,
            trim($input['name']),
            trim($input['email']),
            trim($input['phone'] ?? ''),
            $user->address ?? '',
        );

        $updated = User::findById($user->id);

        if ($updated === null) {
            throw new \RuntimeException('The account could not be found after updating.');
        }

        return $updated;
    }

    /**
     * A user replacing their own profile photo. The previous file, if any, is
     * deleted so uploads do not accumulate.
     */
    public static function updateOwnPhoto(User $user, string $filename): User
    {
        User::updatePhoto($user->id, $filename);

        if ($user->photo !== null) {
            PhotoUploadService::delete($user->photo);
        }

        $updated = User::findById($user->id);

        if ($updated === null) {
            throw new \RuntimeException('The account could not be found after updating.');
        }

        return $updated;
    }
}
