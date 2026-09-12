<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Config;
use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Models\Customer;
use App\Models\Payment;
use App\Models\Project;
use App\Models\User;
use App\Services\PaymentService;
use App\Services\ProjectService;

/**
 * Work Management.
 *
 * Admins and Managers share this controller and see the same project list;
 * only the delete action is Admin-only, and ProjectService is what decides
 * that, not the view.
 */
final class ProjectController extends Controller
{
    /**
     * GET /{panel}/projects - list, search, status and customer filter.
     *
     * @param array<string, string> $params
     */
    public function index(Request $request, array $params): never
    {
        $user       = $this->user();
        $search     = $request->string('q');
        $status     = $request->string('status');
        $customerId = $request->string('customer_id');
        $sort       = $request->string('sort');

        $this->view('projects.index', [
            'title'      => 'Work Management',
            'baseUrl'    => $this->baseUrl($user),
            'projects'   => ProjectService::list($user, $search, $status, $customerId !== '' ? (int) $customerId : null, $sort),
            'search'     => $search,
            'status'     => $status,
            'customerId' => $customerId,
            'sort'       => $sort,
            'customers'  => Customer::all(),
            'counts'     => Project::statusCounts(),
            'canDelete'  => ProjectService::canDelete($user),
        ], 'panel');
    }

    /**
     * GET /{panel}/projects/create - the project registration form.
     *
     * @param array<string, string> $params
     */
    public function create(Request $request, array $params): never
    {
        $user = $this->user();

        ProjectService::assertAccess($user);

        $this->view('projects.form', [
            'title'     => 'Add Project',
            'baseUrl'   => $this->baseUrl($user),
            'project'   => null,
            'customers' => Customer::all(),
        ], 'panel');
    }

    /**
     * POST /{panel}/projects
     *
     * @param array<string, string> $params
     */
    public function store(Request $request, array $params): never
    {
        $user  = $this->user();
        $base  = $this->baseUrl($user);
        $input = $request->only([
            'customer_id', 'name', 'description', 'folder_name', 'deadline', 'total_payment',
            'advance_amount', 'advance_payment_method',
        ]);

        ProjectService::assertAccess($user);

        $validator = ProjectService::validate($input);

        if ($validator->fails()) {
            $this->redirectWithErrors($base . '/create', $validator->errors(), $input);
        }

        $project = ProjectService::create($user, $input);

        $advanceAmount = trim($input['advance_amount'] ?? '');
        $payment       = null;

        if ($advanceAmount !== '' && (float) $advanceAmount > 0) {
            $payment = PaymentService::record($user, [
                'project_id'     => (string) $project->id,
                'amount'         => $advanceAmount,
                'payment_type'   => Payment::TYPE_ADVANCE,
                'payment_method' => trim($input['advance_payment_method']),
                'payment_date'   => date('Y-m-d'),
                'reference_no'   => '',
                'notes'          => 'Advance collected at project creation.',
            ]);
        }

        $message = sprintf('%s has been added to Work Management.', $project->name);

        if ($payment !== null) {
            $message .= sprintf(' Advance payment of %s recorded and the bill has been generated.', money($payment->amount));
        }

        $this->redirectWithFlash(
            $payment !== null
                ? $this->paymentsBaseUrl($user) . '/bills/' . $payment->id
                : $base . '/' . $project->id,
            'success',
            $message,
        );
    }

    /**
     * GET /{panel}/projects/suggest - live suggestions for the search box.
     *
     * @param array<string, string> $params
     */
    public function suggest(Request $request, array $params): never
    {
        $user   = $this->user();
        $base   = $this->baseUrl($user);
        $search = $request->string('q');

        $results = array_map(
            static fn (Project $project): array => [
                'id'   => $project->id,
                'name' => $project->name,
                'sub'  => $project->customerName ?? 'Unknown customer',
                'url'  => $base . '/' . $project->id,
            ],
            ProjectService::suggest($user, $search),
        );

        Response::json(['results' => $results]);
    }

    /**
     * GET /{panel}/projects/{id}
     *
     * @param array<string, string> $params
     */
    public function show(Request $request, array $params): never
    {
        $user    = $this->user();
        $project = ProjectService::findOrFail($user, (int) $params['id']);

        $this->view('projects.show', [
            'title'          => $project->name,
            'baseUrl'        => $this->baseUrl($user),
            'project'        => $project,
            'canDelete'      => ProjectService::canDelete($user),
            'paymentSummary' => PaymentService::projectSummary($user, $project),
            'paymentsBaseUrl' => (string) Config::get('roles.' . $user->role . '.login') . '/payments',
        ], 'panel');
    }

    /**
     * GET /{panel}/projects/{id}/bill - the project Invoice/Bill.
     *
     * @param array<string, string> $params
     */
    public function billView(Request $request, array $params): never
    {
        $user    = $this->user();
        $project = ProjectService::findOrFail($user, (int) $params['id']);
        $base    = $this->baseUrl($user);

        $this->view('projects.bill', [
            'title'       => 'Invoice ' . ProjectService::billReference($project),
            'baseUrl'     => $base,
            'project'     => $project,
            'customer'    => Customer::findById($project->customerId),
            'reference'   => ProjectService::billReference($project),
            'summary'     => PaymentService::projectSummary($user, $project),
            'backUrl'     => $base . '/' . $project->id,
            'downloadUrl' => $base . '/' . $project->id . '/bill/download',
        ], 'invoice');
    }

    /**
     * GET /{panel}/projects/{id}/bill/download - Download Invoice.
     *
     * @param array<string, string> $params
     */
    public function billDownload(Request $request, array $params): never
    {
        $user    = $this->user();
        $project = ProjectService::findOrFail($user, (int) $params['id']);

        Response::binary(
            ProjectService::generateBillPdf($project),
            'application/pdf',
            ProjectService::billReference($project) . '.pdf',
        );
    }

    /**
     * GET /{panel}/projects/{id}/edit
     *
     * @param array<string, string> $params
     */
    public function edit(Request $request, array $params): never
    {
        $user    = $this->user();
        $project = ProjectService::findOrFail($user, (int) $params['id']);

        $this->view('projects.form', [
            'title'     => 'Edit ' . $project->name,
            'baseUrl'   => $this->baseUrl($user),
            'project'   => $project,
            'customers' => Customer::all(),
        ], 'panel');
    }

    /**
     * POST /{panel}/projects/{id}
     *
     * @param array<string, string> $params
     */
    public function update(Request $request, array $params): never
    {
        $user    = $this->user();
        $project = ProjectService::findOrFail($user, (int) $params['id']);
        $base    = $this->baseUrl($user);
        $input   = $request->only([
            'customer_id', 'name', 'description', 'folder_name', 'deadline', 'total_payment',
        ]);

        $validator = ProjectService::validate($input, $project);

        if ($validator->fails()) {
            $this->redirectWithErrors($base . '/' . $project->id . '/edit', $validator->errors(), $input);
        }

        $updated = ProjectService::update($user, $project, $input);

        $this->redirectWithFlash(
            $base . '/' . $updated->id,
            'success',
            sprintf('%s has been updated.', $updated->name),
        );
    }

    /**
     * POST /{panel}/projects/{id}/status
     *
     * @param array<string, string> $params
     */
    public function updateStatus(Request $request, array $params): never
    {
        $user    = $this->user();
        $project = ProjectService::findOrFail($user, (int) $params['id']);
        $status  = $request->string('status');

        ProjectService::setStatus($user, $project, $status);

        $this->redirectWithFlash(
            $this->baseUrl($user) . '/' . $project->id,
            'success',
            sprintf('%s is now %s.', $project->name, project_status_label($status)),
        );
    }

    /**
     * POST /{panel}/projects/{id}/delete - Admin only.
     *
     * @param array<string, string> $params
     */
    public function destroy(Request $request, array $params): never
    {
        $user    = $this->user();
        $project = ProjectService::findOrFail($user, (int) $params['id']);

        ProjectService::delete($user, $project);

        $this->redirectWithFlash(
            $this->baseUrl($user),
            'success',
            sprintf('%s has been deleted.', $project->name),
        );
    }

    private function baseUrl(User $user): string
    {
        return (string) Config::get('roles.' . $user->role . '.login') . '/projects';
    }

    private function paymentsBaseUrl(User $user): string
    {
        return (string) Config::get('roles.' . $user->role . '.login') . '/payments';
    }
}
