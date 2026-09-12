<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Config;
use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Models\Customer;
use App\Models\User;
use App\Services\CustomerService;

/**
 * Customer Management (module spec s2 - s5, s13, s16).
 *
 * Admins and Managers share this controller and see the same customer list;
 * only the delete action is Admin-only, and CustomerService is what decides
 * that, not the view.
 */
final class CustomerController extends Controller
{
    /**
     * GET /{panel}/customers - list, search and status filter (module spec s5).
     *
     * @param array<string, string> $params
     */
    public function index(Request $request, array $params): never
    {
        $user   = $this->user();
        $search = $request->string('q');
        $status = $request->string('status');

        $this->view('customers.index', [
            'title'     => 'Customer Management',
            'baseUrl'   => $this->baseUrl($user),
            'customers' => CustomerService::list($user, $search, $status),
            'search'    => $search,
            'status'    => $status,
            'counts'    => Customer::statusCounts(),
            'canDelete' => CustomerService::canDelete($user),
        ], 'panel');
    }

    /**
     * GET /{panel}/customers/create - the Customer Registration form
     * (module spec s3).
     *
     * @param array<string, string> $params
     */
    public function create(Request $request, array $params): never
    {
        $user = $this->user();

        CustomerService::assertAccess($user);

        $this->view('customers.form', [
            'title'    => 'Register Customer',
            'baseUrl'  => $this->baseUrl($user),
            'customer' => null,
        ], 'panel');
    }

    /**
     * POST /{panel}/customers - module spec s3.
     *
     * @param array<string, string> $params
     */
    public function store(Request $request, array $params): never
    {
        $user  = $this->user();
        $base  = $this->baseUrl($user);
        $input = $request->only(['name', 'email', 'phone', 'address', 'status']);

        CustomerService::assertAccess($user);

        $validator = CustomerService::validate($input);

        if ($validator->fails()) {
            $this->redirectWithErrors($base . '/create', $validator->errors(), $input);
        }

        $customer = CustomerService::create($user, $input);

        $this->redirectWithFlash(
            $base . '/' . $customer->id,
            'success',
            sprintf('%s has been added to your customers.', $customer->name),
        );
    }

    /**
     * GET /{panel}/customers/suggest - live suggestions for the search box.
     *
     * @param array<string, string> $params
     */
    public function suggest(Request $request, array $params): never
    {
        $user   = $this->user();
        $base   = $this->baseUrl($user);
        $search = $request->string('q');

        $results = array_map(
            static fn (Customer $customer): array => [
                'id'     => $customer->id,
                'name'   => $customer->name,
                'sub'    => $customer->email,
                'status' => $customer->status,
                'url'    => $base . '/' . $customer->id,
            ],
            CustomerService::suggest($user, $search),
        );

        Response::json(['results' => $results]);
    }

    /**
     * GET /{panel}/customers/{id} - module spec s5 (View Customer).
     *
     * @param array<string, string> $params
     */
    public function show(Request $request, array $params): never
    {
        $user     = $this->user();
        $customer = CustomerService::findOrFail($user, (int) $params['id']);

        $this->view('customers.show', [
            'title'     => $customer->name,
            'baseUrl'   => $this->baseUrl($user),
            'customer'  => $customer,
            'canDelete' => CustomerService::canDelete($user),
        ], 'panel');
    }

    /**
     * GET /{panel}/customers/{id}/edit - module spec s5 (Edit Customer).
     *
     * @param array<string, string> $params
     */
    public function edit(Request $request, array $params): never
    {
        $user     = $this->user();
        $customer = CustomerService::findOrFail($user, (int) $params['id']);

        $this->view('customers.form', [
            'title'    => 'Edit ' . $customer->name,
            'baseUrl'  => $this->baseUrl($user),
            'customer' => $customer,
        ], 'panel');
    }

    /**
     * POST /{panel}/customers/{id} - module spec s5.
     *
     * @param array<string, string> $params
     */
    public function update(Request $request, array $params): never
    {
        $user     = $this->user();
        $customer = CustomerService::findOrFail($user, (int) $params['id']);
        $base     = $this->baseUrl($user);
        $input    = $request->only(['name', 'email', 'phone', 'address']);

        $validator = CustomerService::validate($input, $customer);

        if ($validator->fails()) {
            $this->redirectWithErrors($base . '/' . $customer->id . '/edit', $validator->errors(), $input);
        }

        $updated = CustomerService::update($user, $customer, $input);

        $this->redirectWithFlash(
            $base . '/' . $updated->id,
            'success',
            sprintf('%s has been updated.', $updated->name),
        );
    }

    /**
     * POST /{panel}/customers/{id}/status - the "deactivate" half of
     * module spec s13.
     *
     * @param array<string, string> $params
     */
    public function updateStatus(Request $request, array $params): never
    {
        $user     = $this->user();
        $customer = CustomerService::findOrFail($user, (int) $params['id']);
        $status   = $request->string('status');

        CustomerService::setStatus($user, $customer, $status);

        $this->redirectWithFlash(
            $this->baseUrl($user),
            'success',
            $status === Customer::STATUS_ACTIVE
                ? sprintf('%s is active again.', $customer->name)
                : sprintf('%s has been deactivated.', $customer->name),
        );
    }

    /**
     * POST /{panel}/customers/{id}/delete - Admin only (module spec s13).
     *
     * @param array<string, string> $params
     */
    public function destroy(Request $request, array $params): never
    {
        $user     = $this->user();
        $customer = CustomerService::findOrFail($user, (int) $params['id']);

        CustomerService::delete($user, $customer);

        $this->redirectWithFlash(
            $this->baseUrl($user),
            'success',
            sprintf('%s has been deleted.', $customer->name),
        );
    }

    private function baseUrl(User $user): string
    {
        return (string) Config::get('roles.' . $user->role . '.login') . '/customers';
    }
}
