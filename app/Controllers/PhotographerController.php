<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Config;
use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Models\Photographer;
use App\Models\User;
use App\Services\PhotographerService;

/**
 * Photographer Management (module spec s2 - s5, s13, s16).
 *
 * Admins and Managers share this controller and see the same photographer list;
 * only the delete action is Admin-only, and PhotographerService is what decides
 * that, not the view.
 */
final class PhotographerController extends Controller
{
    /**
     * GET /{panel}/photographers - list, search and status filter (module spec s5).
     *
     * @param array<string, string> $params
     */
    public function index(Request $request, array $params): never
    {
        $user   = $this->user();
        $search = $request->string('q');
        $status = $request->string('status');

        $this->view('photographers.index', [
            'title'         => 'Photographer Management',
            'baseUrl'       => $this->baseUrl($user),
            'photographers' => PhotographerService::list($user, $search, $status),
            'search'        => $search,
            'status'        => $status,
            'counts'        => Photographer::statusCounts(),
            'canDelete'     => PhotographerService::canDelete($user),
        ], 'panel');
    }

    /**
     * GET /{panel}/photographers/create - the Photographer Registration form
     * (module spec s3).
     *
     * @param array<string, string> $params
     */
    public function create(Request $request, array $params): never
    {
        $user = $this->user();

        PhotographerService::assertAccess($user);

        $this->view('photographers.form', [
            'title'        => 'Register Photographer',
            'baseUrl'      => $this->baseUrl($user),
            'photographer' => null,
        ], 'panel');
    }

    /**
     * POST /{panel}/photographers - module spec s3.
     *
     * @param array<string, string> $params
     */
    public function store(Request $request, array $params): never
    {
        $user  = $this->user();
        $base  = $this->baseUrl($user);
        $input = $request->only(['name', 'email', 'phone', 'address', 'status']);

        PhotographerService::assertAccess($user);

        $validator = PhotographerService::validate($input);

        if ($validator->fails()) {
            $this->redirectWithErrors($base . '/create', $validator->errors(), $input);
        }

        $photographer = PhotographerService::create($user, $input);

        $this->redirectWithFlash(
            $base . '/' . $photographer->id,
            'success',
            sprintf('%s has been added to your photographers.', $photographer->name),
        );
    }

    /**
     * GET /{panel}/photographers/suggest - live suggestions for the search box.
     *
     * @param array<string, string> $params
     */
    public function suggest(Request $request, array $params): never
    {
        $user   = $this->user();
        $base   = $this->baseUrl($user);
        $search = $request->string('q');

        $results = array_map(
            static fn (Photographer $photographer): array => [
                'id'     => $photographer->id,
                'name'   => $photographer->name,
                'sub'    => $photographer->email,
                'status' => $photographer->status,
                'url'    => $base . '/' . $photographer->id,
            ],
            PhotographerService::suggest($user, $search),
        );

        Response::json(['results' => $results]);
    }

    /**
     * GET /{panel}/photographers/{id} - module spec s5 (View Photographer).
     *
     * @param array<string, string> $params
     */
    public function show(Request $request, array $params): never
    {
        $user     = $this->user();
        $photographer = PhotographerService::findOrFail($user, (int) $params['id']);

        $this->view('photographers.show', [
            'title'        => $photographer->name,
            'baseUrl'      => $this->baseUrl($user),
            'photographer' => $photographer,
            'canDelete'    => PhotographerService::canDelete($user),
        ], 'panel');
    }

    /**
     * GET /{panel}/photographers/{id}/edit - module spec s5 (Edit Photographer).
     *
     * @param array<string, string> $params
     */
    public function edit(Request $request, array $params): never
    {
        $user     = $this->user();
        $photographer = PhotographerService::findOrFail($user, (int) $params['id']);

        $this->view('photographers.form', [
            'title'        => 'Edit ' . $photographer->name,
            'baseUrl'      => $this->baseUrl($user),
            'photographer' => $photographer,
        ], 'panel');
    }

    /**
     * POST /{panel}/photographers/{id} - module spec s5.
     *
     * @param array<string, string> $params
     */
    public function update(Request $request, array $params): never
    {
        $user     = $this->user();
        $photographer = PhotographerService::findOrFail($user, (int) $params['id']);
        $base     = $this->baseUrl($user);
        $input    = $request->only(['name', 'email', 'phone', 'address']);

        $validator = PhotographerService::validate($input, $photographer);

        if ($validator->fails()) {
            $this->redirectWithErrors($base . '/' . $photographer->id . '/edit', $validator->errors(), $input);
        }

        $updated = PhotographerService::update($user, $photographer, $input);

        $this->redirectWithFlash(
            $base . '/' . $updated->id,
            'success',
            sprintf('%s has been updated.', $updated->name),
        );
    }

    /**
     * POST /{panel}/photographers/{id}/status - the "deactivate" half of
     * module spec s13.
     *
     * @param array<string, string> $params
     */
    public function updateStatus(Request $request, array $params): never
    {
        $user     = $this->user();
        $photographer = PhotographerService::findOrFail($user, (int) $params['id']);
        $status   = $request->string('status');

        PhotographerService::setStatus($user, $photographer, $status);

        $this->redirectWithFlash(
            $this->baseUrl($user),
            'success',
            $status === Photographer::STATUS_ACTIVE
                ? sprintf('%s is active again.', $photographer->name)
                : sprintf('%s has been deactivated.', $photographer->name),
        );
    }

    /**
     * POST /{panel}/photographers/{id}/delete - Admin only (module spec s13).
     *
     * @param array<string, string> $params
     */
    public function destroy(Request $request, array $params): never
    {
        $user     = $this->user();
        $photographer = PhotographerService::findOrFail($user, (int) $params['id']);

        PhotographerService::delete($user, $photographer);

        $this->redirectWithFlash(
            $this->baseUrl($user),
            'success',
            sprintf('%s has been deleted.', $photographer->name),
        );
    }

    private function baseUrl(User $user): string
    {
        return (string) Config::get('roles.' . $user->role . '.login') . '/photographers';
    }
}
