<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Config;
use App\Core\Exceptions\HttpException;
use App\Core\Validator;
use App\Models\AuthToken;
use App\Models\User;

/**
 * The account-creation hierarchy (spec s3, s12): Admin creates Managers,
 * Manager creates Employees, Employee creates nobody. Every write here is
 * checked against that hierarchy, so a controller cannot widen it by accident.
 */
final class UserService
{
    /**
     * The role $actor is permitted to create and manage, or null.
     */
    public static function manageableRole(User $actor): ?string
    {
        $role = Config::get('roles.' . $actor->role . '.manages');

        return is_string($role) ? $role : null;
    }

    /**
     * Accounts within $actor's management scope.
     *
     * An Admin oversees every Manager in the system; a Manager oversees the
     * Employees they created (spec s11: "Employees assigned to them").
     *
     * @return list<User>
     */
    public static function subordinates(User $actor): array
    {
        $role = self::manageableRole($actor);

        if ($role === null) {
            return [];
        }

        return $actor->role === User::ROLE_ADMIN
            ? User::allOfRole($role)
            : User::managedBy($actor->id, $role);
    }

    /**
     * @return array<string, int>
     */
    public static function subordinateStatusCounts(User $actor): array
    {
        $role = self::manageableRole($actor);

        if ($role === null) {
            return [User::STATUS_ACTIVE => 0, User::STATUS_INACTIVE => 0, User::STATUS_SUSPENDED => 0];
        }

        return User::statusCounts(
            $role,
            $actor->role === User::ROLE_ADMIN ? null : $actor->id,
        );
    }

    /**
     * Load one account and prove it is inside $actor's scope. Anything else —
     * a peer, a superior, or another Manager's Employee — is a 403 rather than
     * a 404, and never reaches the caller (spec s11).
     */
    public static function findSubordinateOrFail(User $actor, int $id): User
    {
        $role = self::manageableRole($actor);

        if ($role === null) {
            throw HttpException::forbidden('You are not authorized to manage other accounts.');
        }

        $target = User::findById($id);

        if ($target === null || $target->role !== $role) {
            throw HttpException::forbidden('You are not authorized to manage that account.');
        }

        if ($actor->role !== User::ROLE_ADMIN && $target->createdBy !== $actor->id) {
            throw HttpException::forbidden('You are not authorized to manage that account.');
        }

        return $target;
    }

    /**
     * Validate the details for a new subordinate account.
     *
     * @param array<string, string> $input
     */
    public static function validateNewAccount(array $input): Validator
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

        if ($phone !== '' && preg_match('/^[0-9+\-\s()]{6,30}$/', $phone) !== 1) {
            $validator->add('phone', 'Please enter a valid phone number.');
        }

        // Only worth a lookup once the address itself is well formed.
        if ($email !== '' && !array_key_exists('email', $validator->errors()) && User::emailExists($email)) {
            $validator->add('email', 'An account with this email address already exists.');
        }

        PasswordPolicy::validate(
            $validator,
            $input['password'] ?? '',
            $input['password_confirmation'] ?? '',
        );

        return $validator;
    }

    /**
     * Create an account one level below $actor.
     *
     * @param array<string, string> $input
     */
    public static function createSubordinate(User $actor, array $input): User
    {
        $role = self::manageableRole($actor);

        if ($role === null) {
            throw HttpException::forbidden('You are not authorized to create accounts.');
        }

        $id = User::create(
            name:         trim($input['name']),
            email:        trim($input['email']),
            phone:        trim($input['phone'] ?? ''),
            passwordHash: PasswordPolicy::hash($input['password']),
            role:         $role,
            createdBy:    $actor->id,
            status:       in_array($input['status'] ?? '', [User::STATUS_ACTIVE, User::STATUS_INACTIVE], true)
                ? $input['status']
                : User::STATUS_ACTIVE,
        );

        $created = User::findById($id);

        if ($created === null) {
            throw new \RuntimeException('The account could not be created.');
        }

        return $created;
    }

    /**
     * Admin resets a Manager's password; Manager resets an Employee's
     * (spec s10, s11). Existing tokens for that account are revoked, so a
     * session opened with the old password cannot survive the reset.
     */
    public static function resetSubordinatePassword(User $actor, User $target, string $password): void
    {
        // Re-assert the relationship even though the caller loaded the target
        // through findSubordinateOrFail().
        self::findSubordinateOrFail($actor, $target->id);

        User::updatePassword($target->id, PasswordPolicy::hash($password));
        AuthToken::revokeAllForUser($target->id);
    }

    /**
     * Account status management (spec s17).
     */
    public static function setSubordinateStatus(User $actor, User $target, string $status): void
    {
        self::findSubordinateOrFail($actor, $target->id);

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

        if ($phone !== '' && preg_match('/^[0-9+\-\s()]{6,30}$/', $phone) !== 1) {
            $validator->add('phone', 'Please enter a valid phone number.');
        }

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
     * A user updating their own name, email and phone. Status, role and
     * password are untouched here.
     *
     * @param array<string, string> $input
     */
    public static function updateOwnProfile(User $user, array $input): User
    {
        User::updateDetails($user->id, trim($input['name']), trim($input['email']), trim($input['phone'] ?? ''));

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
