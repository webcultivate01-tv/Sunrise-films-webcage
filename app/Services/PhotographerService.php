<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Exceptions\HttpException;
use App\Core\Validator;
use App\Models\Photographer;
use App\Models\Project;
use App\Models\User;

/**
 * Photographer Management (module spec s2 - s5, s13).
 *
 * Admins and Managers both have full reach over the photographer list: module spec
 * s13 grants View, Add, Edit and Search to each of them without qualification,
 * so - unlike Employee Management - there is no per-manager ownership filter.
 * The one place the two roles differ is removal: a Manager may deactivate a
 * photographer, only an Admin may delete the record outright.
 *
 * Employees have no access to this module at all (module spec s2).
 */
final class PhotographerService
{
    /**
     * Module spec s2: Admin and Manager only.
     */
    public static function canAccess(User $actor): bool
    {
        return in_array($actor->role, [User::ROLE_ADMIN, User::ROLE_MANAGER], true);
    }

    /**
     * Module spec s13: "Delete/Deactivate Photographer - Admin: yes, Manager:
     * according to permission". A deactivation is reversible and keeps the
     * history attached to the photographer, so both roles may do it; an outright
     * delete destroys that history and is reserved for an Admin.
     */
    public static function canDelete(User $actor): bool
    {
        return $actor->role === User::ROLE_ADMIN;
    }

    public static function assertAccess(User $actor): void
    {
        if (!self::canAccess($actor)) {
            throw HttpException::forbidden('You are not authorized to access Photographer Management.');
        }
    }

    /**
     * The photographer list, narrowed by the search box and the status filter
     * (module spec s5, s16).
     *
     * @return list<Photographer>
     */
    public static function list(User $actor, string $search = '', string $status = ''): array
    {
        self::assertAccess($actor);

        if (!in_array($status, [Photographer::STATUS_ACTIVE, Photographer::STATUS_INACTIVE], true)) {
            $status = '';
        }

        return Photographer::all($search, $status);
    }

    /**
     * Up to 8 photographers matching the search box, for the live suggestion
     * dropdown - across every status, so a typed name still surfaces an
     * inactive photographer.
     *
     * @return list<Photographer>
     */
    public static function suggest(User $actor, string $search): array
    {
        self::assertAccess($actor);

        if (trim($search) === '') {
            return [];
        }

        return array_slice(Photographer::all($search), 0, 8);
    }

    /**
     * Load one photographer, or 404 if there is no such record.
     */
    public static function findOrFail(User $actor, int $id): Photographer
    {
        self::assertAccess($actor);

        return Photographer::findById($id) ?? throw HttpException::notFound('That photographer could not be found.');
    }

    /**
     * Validate the Photographer Registration form (module spec s4, s14). Every
     * field is required; pass $existing when editing so the record keeps its
     * own email address.
     *
     * @param array<string, string> $input
     */
    public static function validate(array $input, ?Photographer $existing = null): Validator
    {
        $validator = new Validator();

        $name    = trim($input['name'] ?? '');
        $email   = trim($input['email'] ?? '');
        $phone   = trim($input['phone'] ?? '');
        $address = trim($input['address'] ?? '');

        $validator->require('name', $name, 'Please enter the photographer name.');
        $validator->maxLength('name', $name, 120, 'Name must be 120 characters or fewer.');

        $validator->require('email', $email, 'Please enter an email address.');
        $validator->email('email', $email);
        $validator->maxLength('email', $email, 190, 'Email must be 190 characters or fewer.');

        $validator->require('phone', $phone, 'Please enter a mobile number.');
        $validator->phone('phone', $phone);

        $validator->require('address', $address, 'Please enter an address.');
        $validator->maxLength('address', $address, 500, 'Address must be 500 characters or fewer.');

        if (array_key_exists('status', $input)) {
            $validator->in(
                'status',
                trim($input['status']),
                [Photographer::STATUS_ACTIVE, Photographer::STATUS_INACTIVE],
                'That photographer status is not recognised.',
            );
        }

        // Module spec s14: one photographer record per email address.
        if ($email !== ''
            && !array_key_exists('email', $validator->errors())
            && Photographer::emailExists($email, $existing?->id)
        ) {
            $validator->add('email', 'A photographer with this email address already exists.');
        }

        return $validator;
    }

    /**
     * @param array<string, string> $input
     */
    public static function create(User $actor, array $input): Photographer
    {
        self::assertAccess($actor);

        $status = $input['status'] ?? Photographer::STATUS_ACTIVE;

        $id = Photographer::create(
            name:      trim($input['name']),
            email:     trim($input['email']),
            phone:     trim($input['phone']),
            address:   trim($input['address']),
            createdBy: $actor->id,
            status:    in_array($status, [Photographer::STATUS_ACTIVE, Photographer::STATUS_INACTIVE], true)
                ? $status
                : Photographer::STATUS_ACTIVE,
        );

        return Photographer::findById($id)
            ?? throw new \RuntimeException('The photographer could not be created.');
    }

    /**
     * @param array<string, string> $input
     */
    public static function update(User $actor, Photographer $photographer, array $input): Photographer
    {
        self::assertAccess($actor);

        Photographer::update(
            $photographer->id,
            trim($input['name']),
            trim($input['email']),
            trim($input['phone']),
            trim($input['address']),
        );

        return Photographer::findById($photographer->id)
            ?? throw new \RuntimeException('The photographer could not be found after updating.');
    }

    /**
     * Deactivate or reactivate a photographer. Available to both roles.
     */
    public static function setStatus(User $actor, Photographer $photographer, string $status): void
    {
        self::assertAccess($actor);

        if (!in_array($status, [Photographer::STATUS_ACTIVE, Photographer::STATUS_INACTIVE], true)) {
            throw HttpException::forbidden('That photographer status is not recognised.');
        }

        Photographer::updateStatus($photographer->id, $status);
    }

    /**
     * Remove the record for good. Admin only - see canDelete().
     */
    public static function delete(User $actor, Photographer $photographer): void
    {
        self::assertAccess($actor);

        if (!self::canDelete($actor)) {
            throw HttpException::forbidden(
                'You are not authorized to delete photographers. Deactivate the photographer instead.',
            );
        }

        // Work Management projects point at this photographer by ID; deleting the
        // record out from under them would either fail on the foreign key or
        // orphan their history, so removal is refused while any exist.
        if (Project::existsForPhotographer($photographer->id)) {
            throw HttpException::forbidden(
                'This photographer has projects on record and cannot be deleted. Deactivate the photographer instead.',
            );
        }

        Photographer::delete($photographer->id);
    }
}
