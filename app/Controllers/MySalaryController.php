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
 * My Salary - the Employee side of Monthly Salary (salary spec s2, s3, s13).
 *
 * Every method here only ever reads or acts on the signed-in Employee's own
 * id, so a tampered URL can never reach somebody else's salary or bill.
 */
final class MySalaryController extends Controller
{
    /**
     * GET /employee/my-salary - own summary, monthly breakdown and history,
     * with an optional ?month= to drill into one month's entries.
     *
     * @param array<string, string> $params
     */
    public function index(Request $request, array $params): never
    {
        $user      = $this->user();
        $breakdown = SalaryService::monthlyBreakdown($user->id);
        $month     = $request->string('month');

        if ($month === '' && $breakdown !== []) {
            $month = $breakdown[0]['month'];
        }

        $this->view('my-salary.index', [
            'title'     => 'My Salary',
            'baseUrl'   => $this->baseUrl($user),
            'totals'    => SalaryService::totals($user->id),
            'breakdown' => $breakdown,
            'month'     => $month,
            'monthData' => $month !== '' ? SalaryService::monthEntries($user->id, $month) : null,
            'history'   => SalaryService::employeeHistory($user->id),
        ], 'panel');
    }

    /**
     * GET /employee/my-salary/bills/{id} - View one of my bills.
     *
     * @param array<string, string> $params
     */
    public function billView(Request $request, array $params): never
    {
        $user       = $this->user();
        $settlement = SalaryService::findOwnSettlementOrFail($user, (int) $params['id']);

        $this->view('salary.bill', [
            'title'       => 'Bill ' . $settlement->referenceNo,
            'settlement'  => $settlement,
            'backUrl'     => $this->baseUrl($user),
            'downloadUrl' => $this->baseUrl($user) . '/bills/' . $settlement->id . '/download',
        ], 'invoice');
    }

    /**
     * GET /employee/my-salary/bills/{id}/download - Download one of my bills.
     *
     * @param array<string, string> $params
     */
    public function billDownload(Request $request, array $params): never
    {
        $user       = $this->user();
        $settlement = SalaryService::findOwnSettlementOrFail($user, (int) $params['id']);

        Response::binary(
            SalaryService::generateBillPdf($settlement),
            'application/pdf',
            $settlement->referenceNo . '.pdf',
        );
    }

    private function baseUrl(User $user): string
    {
        return (string) Config::get('roles.' . $user->role . '.login') . '/my-salary';
    }
}
