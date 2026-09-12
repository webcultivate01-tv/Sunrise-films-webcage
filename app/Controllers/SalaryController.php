<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Config;
use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Models\User;
use App\Services\SalaryService;

/**
 * Monthly Salary - the Admin/Manager side (salary spec s2-s9, s12, s16).
 *
 * Admin and Manager share this controller; SalaryService decides which
 * employees and settlements are in scope for whichever of the two is signed
 * in, so a Manager never sees or settles salary outside their own team.
 */
final class SalaryController extends Controller
{
    /**
     * GET /{panel}/monthly-salary - the Employee Salary List (spec s3).
     *
     * @param array<string, string> $params
     */
    public function index(Request $request, array $params): never
    {
        $user   = $this->user();
        $search = $request->string('q');
        $month  = $request->string('month');

        $this->view('salary.index', [
            'title'   => 'Monthly Salary',
            'baseUrl' => $this->baseUrl($user),
            'rows'    => SalaryService::listEmployees($user, $search, $month),
            'search'  => $search,
            'month'   => $month,
            'summary' => SalaryService::currentMonthSummary($user, $month),
        ], 'panel');
    }

    /**
     * GET /{panel}/monthly-salary/suggest - live suggestions for the search
     * box on the Employee Salary List.
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
                'id'   => $row['employee']->id,
                'name' => $row['employee']->name,
                'sub'  => $row['employee']->email,
                'url'  => $base . '/' . $row['employee']->id,
            ],
            SalaryService::suggestEmployees($user, $search),
        );

        Response::json(['results' => $results]);
    }

    /**
     * GET /{panel}/monthly-salary/history/suggest - live suggestions for the
     * search box on Global History.
     *
     * @param array<string, string> $params
     */
    public function historySuggest(Request $request, array $params): never
    {
        $user   = $this->user();
        $base   = $this->baseUrl($user);
        $search = $request->string('q');

        $results = array_map(
            static fn (array $entry): array => [
                'id'   => $entry['employeeId'],
                'name' => $entry['employeeName'],
                'sub'  => $entry['detail'],
                'url'  => $base . '/' . $entry['employeeId'],
            ],
            SalaryService::suggestHistory($user, $search),
        );

        Response::json(['results' => $results]);
    }

    /**
     * GET /{panel}/monthly-salary/{id} - View Employee Salary (spec s4, s5),
     * with an optional ?month= to drill into one month's entries.
     *
     * @param array<string, string> $params
     */
    public function show(Request $request, array $params): never
    {
        $user      = $this->user();
        $employee  = SalaryService::findEmployeeOrFail($user, (int) $params['id']);
        $breakdown = SalaryService::monthlyBreakdown($employee->id);
        $month     = $request->string('month');

        if ($month === '' && $breakdown !== []) {
            $month = $breakdown[0]['month'];
        }

        $this->view('salary.show', [
            'title'     => $employee->name . ' - Monthly Salary',
            'baseUrl'   => $this->baseUrl($user),
            'employee'  => $employee,
            'totals'    => SalaryService::totals($employee->id),
            'breakdown' => $breakdown,
            'month'     => $month,
            'monthData' => $month !== '' ? SalaryService::monthEntries($employee->id, $month) : null,
            'history'   => SalaryService::employeeHistory($employee->id),
        ], 'panel');
    }

    /**
     * GET /{panel}/monthly-salary/{id}/settle - the Settlement form (spec s7).
     *
     * @param array<string, string> $params
     */
    public function settleForm(Request $request, array $params): never
    {
        $user     = $this->user();
        $employee = SalaryService::findEmployeeOrFail($user, (int) $params['id']);

        $this->view('salary.settle', [
            'title'          => 'Settle Salary - ' . $employee->name,
            'baseUrl'        => $this->baseUrl($user),
            'employee'       => $employee,
            'breakdown'      => SalaryService::monthlyBreakdown($employee->id),
            'preselectMonth' => $request->string('month'),
            'idempotencyKey' => bin2hex(random_bytes(16)),
        ], 'panel');
    }

    /**
     * POST /{panel}/monthly-salary/{id}/settle - Confirm Settlement (spec
     * s7-s10). A resubmission carrying the same idempotency key is redirected
     * to the bill it already created rather than settling twice (spec s18).
     *
     * @param array<string, string> $params
     */
    public function settle(Request $request, array $params): never
    {
        $user     = $this->user();
        $employee = SalaryService::findEmployeeOrFail($user, (int) $params['id']);
        $base     = $this->baseUrl($user);
        $input    = $request->only(['salary_month', 'amount', 'notes', 'idempotency_key']);

        $existing = SalaryService::findSettlementByIdempotencyKey($input['idempotency_key']);

        if ($existing !== null) {
            $this->redirectWithFlash(
                $base . '/bills/' . $existing->id,
                'success',
                'This settlement has already been recorded.',
            );
        }

        $validator = SalaryService::validateSettlement($user, $employee, $input);

        if ($validator->fails()) {
            $this->redirectWithErrors($base . '/' . $employee->id . '/settle', $validator->errors(), $input);
        }

        $settlement = SalaryService::settle($user, $employee, $input);

        $this->redirectWithFlash(
            $base . '/bills/' . $settlement->id,
            'success',
            sprintf('Settlement of %s recorded for %s.', money($settlement->amount), $employee->name),
        );
    }

    /**
     * GET /{panel}/monthly-salary/history - Global History (spec s12, s16).
     *
     * @param array<string, string> $params
     */
    public function history(Request $request, array $params): never
    {
        $user    = $this->user();
        $filters = $request->only(['employee_id', 'type', 'month', 'start_date', 'end_date', 'q']);

        $this->view('salary.history', [
            'title'     => 'Salary History',
            'baseUrl'   => $this->baseUrl($user),
            'entries'   => SalaryService::globalHistory($user, $filters),
            'filters'   => $filters,
            'employees' => SalaryService::assignableEmployees($user),
        ], 'panel');
    }

    /**
     * GET /{panel}/monthly-salary/bills/{id} - View Bill (spec s9).
     *
     * @param array<string, string> $params
     */
    public function billView(Request $request, array $params): never
    {
        $user       = $this->user();
        $settlement = SalaryService::findSettlementOrFail($user, (int) $params['id']);

        $this->view('salary.bill', [
            'title'       => 'Bill ' . $settlement->referenceNo,
            'settlement'  => $settlement,
            'backUrl'     => $this->baseUrl($user) . '/' . $settlement->employeeId,
            'downloadUrl' => $this->baseUrl($user) . '/bills/' . $settlement->id . '/download',
        ], 'invoice');
    }

    /**
     * GET /{panel}/monthly-salary/bills/{id}/download - Download Bill (spec s9).
     *
     * @param array<string, string> $params
     */
    public function billDownload(Request $request, array $params): never
    {
        $user       = $this->user();
        $settlement = SalaryService::findSettlementOrFail($user, (int) $params['id']);

        Response::binary(
            SalaryService::generateBillPdf($settlement),
            'application/pdf',
            $settlement->referenceNo . '.pdf',
        );
    }

    private function baseUrl(User $user): string
    {
        return (string) Config::get('roles.' . $user->role . '.login') . '/monthly-salary';
    }
}
