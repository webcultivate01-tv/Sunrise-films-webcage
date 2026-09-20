<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Config;
use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Models\Photographer;
use App\Models\Project;
use App\Models\User;
use App\Services\ReportFilters;
use App\Services\ReportService;

/**
 * Reports: one page of report cards (Photographers, their work, Employees,
 * their work, Projects, Tasks, Payments, Salary), each generated from its own
 * filters, previewed on screen and downloadable as PDF or Excel. Admin and
 * Manager share this controller and see the same studio-wide data every other
 * module already grants them.
 *
 * Every entry point runs the same two gates first - ReportService::assertType
 * for the report and ReportFilters for its filters - so a report is either
 * generated exactly as its header describes it, or not generated at all.
 */
final class ReportController extends Controller
{
    /**
     * GET /{panel}/reports - the report cards, each with its own filter form.
     *
     * @param array<string, string> $params
     */
    public function index(Request $request, array $params): never
    {
        $user = $this->user();

        ReportService::assertAccess($user);

        $this->view('reports.index', [
            'title'         => 'Reports',
            'baseUrl'       => $this->baseUrl($user),
            'photographers' => Photographer::all(),
            'projects'      => Project::all(),
            'employees'     => User::allOfRole(User::ROLE_EMPLOYEE),
        ], 'panel');
    }

    /**
     * GET /{panel}/reports/{type} - the report itself, on screen.
     *
     * The preview is where a rejected filter is explained: a download can only
     * answer with a file, so a report that cannot honestly be generated is
     * shown here with the reason instead, its filters still on the page to be
     * corrected.
     *
     * @param array<string, string> $params
     */
    public function show(Request $request, array $params): never
    {
        $user = $this->user();

        ReportService::assertAccess($user);

        $type    = (string) $params['type'];
        $filters = $this->filtersFor($request, $type);

        $this->view('reports.show', [
            'title'         => ReportService::label($type),
            'baseUrl'       => $this->baseUrl($user),
            'type'          => $type,
            'filters'       => $filters,
            'raw'           => $request->only(ReportService::filterKeys($type)),
            'report'        => $filters->isValid() ? ReportService::build($user, $type, $filters) : null,
            'photographers' => Photographer::all(),
            'projects'      => Project::all(),
            'employees'     => User::allOfRole(User::ROLE_EMPLOYEE),
        ], 'panel');
    }

    /**
     * GET /{panel}/reports/{type}/pdf - download one report as a PDF.
     *
     * @param array<string, string> $params
     */
    public function pdf(Request $request, array $params): never
    {
        $user    = $this->user();
        $type    = (string) $params['type'];
        $filters = $this->filtersFor($request, $type);

        $this->assertGeneratable($user, $type, $filters, $request);

        Response::binary(
            ReportService::renderPdf(ReportService::build($user, $type, $filters), $user),
            'application/pdf',
            ReportService::filename($type, $filters) . '.pdf',
        );
    }

    /**
     * GET /{panel}/reports/{type}/excel - download one report as Excel.
     *
     * @param array<string, string> $params
     */
    public function excel(Request $request, array $params): never
    {
        $user    = $this->user();
        $type    = (string) $params['type'];
        $filters = $this->filtersFor($request, $type);

        $this->assertGeneratable($user, $type, $filters, $request);

        Response::binary(
            ReportService::renderExcel(ReportService::build($user, $type, $filters), $user),
            'application/vnd.ms-excel',
            ReportService::filename($type, $filters) . '.xls',
        );
    }

    /**
     * Whitelist the query values for this report, then validate them.
     */
    private function filtersFor(Request $request, string $type): ReportFilters
    {
        ReportService::assertType($type);

        return ReportService::filters($type, $request->only(ReportService::filterKeys($type)));
    }

    /**
     * A download cannot show a validation message, so a rejected filter sends
     * the user to that report's preview - where the reason is on the page and
     * the filters that produced it are still filled in - rather than handing
     * back a file that does not match what was asked for.
     */
    private function assertGeneratable(User $user, string $type, ReportFilters $filters, Request $request): void
    {
        if ($filters->isValid()) {
            return;
        }

        // The rejected values are carried over untouched, not the cleaned
        // ones, so the preview re-reaches the same verdict and can point at
        // the field that caused it.
        $query = http_build_query(array_filter(
            $request->only(ReportService::filterKeys($type)),
            static fn (string $value): bool => $value !== '',
        ));

        $this->redirectWithFlash(
            $this->baseUrl($user) . '/' . $type . ($query === '' ? '' : '?' . $query),
            'error',
            (string) $filters->firstError(),
        );
    }

    private function baseUrl(User $user): string
    {
        return (string) Config::get('roles.' . $user->role . '.login') . '/reports';
    }
}
