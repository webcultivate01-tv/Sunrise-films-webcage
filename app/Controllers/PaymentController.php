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

/**
 * Payment Management.
 *
 * Admins and Managers share this controller and see the same projects and
 * transactions - PaymentService recomputes every collected/outstanding
 * figure and payment status from the permanent Payment rows, never from a
 * stored total, so the numbers here can never drift from the history.
 */
final class PaymentController extends Controller
{
    /**
     * GET /{panel}/payments - the Payment Dashboard and Project Payment
     * Overview (module spec s1, s4, s9), search, status and customer filter.
     *
     * @param array<string, string> $params
     */
    public function index(Request $request, array $params): never
    {
        $user       = $this->user();
        $search     = $request->string('q');
        $status     = $request->string('status');
        $customerId = $request->string('customer_id');

        $this->view('payments.index', [
            'title'      => 'Payment Management',
            'baseUrl'    => $this->baseUrl($user),
            'summary'    => PaymentService::dashboardSummary($user),
            'rows'       => PaymentService::projectOverview($user, $search, $status, $customerId !== '' ? (int) $customerId : null),
            'search'     => $search,
            'status'     => $status,
            'customerId' => $customerId,
            'customers'  => Customer::all(),
        ], 'panel');
    }

    /**
     * GET /{panel}/payments/suggest - live suggestions for the search box on
     * the Payment Dashboard.
     *
     * @param array<string, string> $params
     */
    public function suggest(Request $request, array $params): never
    {
        $user   = $this->user();
        $base   = $this->baseUrl($user);
        $search = $request->string('q');

        $results = array_map(
            static fn (array $row): array => [
                'id'   => $row['project']->id,
                'name' => $row['project']->name,
                'sub'  => $row['project']->customerName ?? 'Unknown customer',
                'url'  => $base . '/history?project_id=' . $row['project']->id,
            ],
            PaymentService::suggestProjects($user, $search),
        );

        Response::json(['results' => $results]);
    }

    /**
     * GET /{panel}/payments/history - the Payment History (module spec s2,
     * s5): every transaction across every project, filterable and sortable.
     *
     * @param array<string, string> $params
     */
    public function history(Request $request, array $params): never
    {
        $user    = $this->user();
        $filters = $request->only([
            'q', 'project_id', 'customer_id', 'type', 'method', 'start_date', 'end_date', 'sort',
        ]);

        $filterProject = $filters['project_id'] !== '' ? Project::findById((int) $filters['project_id']) : null;

        $this->view('payments.history', [
            'title'         => 'Payment History',
            'baseUrl'       => $this->baseUrl($user),
            'payments'      => PaymentService::history($user, $filters),
            'filters'       => $filters,
            'customers'     => Customer::all(),
            'filterProject' => $filterProject,
        ], 'panel');
    }

    /**
     * GET /{panel}/payments/history/suggest - live suggestions for the search
     * box on the Payment History.
     *
     * @param array<string, string> $params
     */
    public function historySuggest(Request $request, array $params): never
    {
        $user   = $this->user();
        $base   = $this->baseUrl($user);
        $search = $request->string('q');

        $results = array_map(
            static fn (Payment $payment): array => [
                'id'   => $payment->id,
                'name' => $payment->projectName ?? 'Unknown project',
                'sub'  => money($payment->amount) . ' - ' . ($payment->customerName ?? 'Unknown customer'),
                'url'  => $base . '/' . $payment->id,
            ],
            PaymentService::suggestHistory($user, $search),
        );

        Response::json(['results' => $results]);
    }

    /**
     * GET /{panel}/payments/create - Record Payment, optionally preselecting
     * a project via ?project_id=.
     *
     * @param array<string, string> $params
     */
    public function create(Request $request, array $params): never
    {
        $user = $this->user();

        PaymentService::assertAccess($user);

        $projectId = $request->string('project_id');

        $this->view('payments.create', [
            'title'           => 'Record Payment',
            'baseUrl'         => $this->baseUrl($user),
            'projects'        => Project::all(),
            'customers'       => Customer::all(),
            'projectSummary'  => PaymentService::projectPaymentIndex($user),
            'preselectedId'   => $projectId !== '' ? (int) $projectId : null,
        ], 'panel');
    }

    /**
     * POST /{panel}/payments - Record Payment (module spec s10): always
     * creates a new transaction, never edits an existing one.
     *
     * @param array<string, string> $params
     */
    public function store(Request $request, array $params): never
    {
        $user  = $this->user();
        $base  = $this->baseUrl($user);
        $input = $request->only([
            'project_id', 'amount', 'payment_type', 'payment_method', 'payment_date', 'reference_no', 'notes',
        ]);

        PaymentService::assertAccess($user);

        $validator = PaymentService::validate($input);

        if ($validator->fails()) {
            $back = $input['project_id'] !== '' ? $base . '/create?project_id=' . $input['project_id'] : $base . '/create';
            $this->redirectWithErrors($back, $validator->errors(), $input);
        }

        $payment = PaymentService::record($user, $input);

        $this->redirectWithFlash(
            $base . '/' . $payment->id,
            'success',
            sprintf('Payment of %s has been recorded.', money($payment->amount)),
        );
    }

    /**
     * GET /{panel}/payments/{id} - Payment Details (module spec s7): the
     * complete transaction, including the running collected/outstanding
     * figures immediately before and after it.
     *
     * @param array<string, string> $params
     */
    public function show(Request $request, array $params): never
    {
        $user    = $this->user();
        $payment = PaymentService::findOrFail($user, (int) $params['id']);
        $project = Project::findById($payment->projectId);

        $timeline        = Payment::timelineForProject($payment->projectId);
        $collectedBefore = 0.0;
        $previousPayment = null;

        foreach ($timeline as $entry) {
            if ($entry->id === $payment->id) {
                break;
            }

            $collectedBefore += $entry->amount;
            $previousPayment  = $entry;
        }

        $this->view('payments.show', [
            'title'            => 'Payment ' . ($payment->referenceNo ?? '#' . $payment->id),
            'baseUrl'          => $this->baseUrl($user),
            'payment'          => $payment,
            'project'          => $project,
            'projectUrl'       => $project !== null ? $this->projectsBaseUrl($user) . '/' . $project->id : null,
            'previousPayment'  => $previousPayment,
            'collectedAfter'   => $collectedBefore + $payment->amount,
            'remainingAfter'   => $project !== null ? max(0.0, $project->totalPayment - ($collectedBefore + $payment->amount)) : 0.0,
        ], 'panel');
    }

    /**
     * GET /{panel}/payments/bills/{id} - View Bill: the same receipt whether
     * it was generated automatically for a project's advance (Work
     * Management) or is being pulled up later from Payment Management.
     *
     * @param array<string, string> $params
     */
    public function billView(Request $request, array $params): never
    {
        $user    = $this->user();
        $payment = PaymentService::findOrFail($user, (int) $params['id']);
        $project = Project::findById($payment->projectId);
        $customer = $payment->customerId !== null ? Customer::findById($payment->customerId) : null;

        $this->view('payments.bill', [
            'title'       => 'Bill ' . PaymentService::billReference($payment),
            'baseUrl'     => $this->baseUrl($user),
            'payment'     => $payment,
            'project'     => $project,
            'customer'    => $customer,
            'reference'   => PaymentService::billReference($payment),
            'collected'   => Payment::totalForProject($payment->projectId),
            'backUrl'     => $this->baseUrl($user) . '/' . $payment->id,
            'downloadUrl' => $this->baseUrl($user) . '/bills/' . $payment->id . '/download',
        ], 'invoice');
    }

    /**
     * GET /{panel}/payments/bills/{id}/download - Download Bill.
     *
     * @param array<string, string> $params
     */
    public function billDownload(Request $request, array $params): never
    {
        $user    = $this->user();
        $payment = PaymentService::findOrFail($user, (int) $params['id']);

        Response::binary(
            PaymentService::generateBillPdf($payment),
            'application/pdf',
            PaymentService::billReference($payment) . '.pdf',
        );
    }

    private function baseUrl(User $user): string
    {
        return (string) Config::get('roles.' . $user->role . '.login') . '/payments';
    }

    private function projectsBaseUrl(User $user): string
    {
        return (string) Config::get('roles.' . $user->role . '.login') . '/projects';
    }
}
