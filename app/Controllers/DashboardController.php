<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Config;
use App\Core\Controller;
use App\Core\Request;
use App\Models\AuthToken;
use App\Models\Photographer;
use App\Models\Payment;
use App\Models\Project;
use App\Models\User;
use App\Services\PhotographerService;
use App\Services\PaymentService;
use App\Services\ProjectService;
use App\Services\SalaryService;
use App\Services\TaskService;
use App\Services\UserService;

/**
 * The panel each role lands on after signing in (auth spec s4.5). What it
 * summarises depends on which modules the role can reach: an Employee sees
 * neither the photographer nor the people figures (module spec s2, s6), and
 * only an Admin/Manager - who share full reach over Work, Payment and Task
 * Management - sees the business-wide KPIs and charts.
 */
final class DashboardController extends Controller
{
    /**
     * @param array<string, string> $params
     */
    public function index(Request $request, array $params): never
    {
        $user        = $this->user();
        $base        = (string) Config::get('roles.' . $user->role . '.login');
        $manages     = UserService::managesPeople($user);
        $canBusiness = ProjectService::canAccess($user);

        $data = [
            'title'              => $user->roleLabel() . ' Panel',
            'managesPeople'      => $manages,
            'peopleCounts'       => $manages ? UserService::peopleCounts($user) : [],
            'employeesUrl'       => $manages ? $base . '/employees' : null,
            'photographersUrl'   => PhotographerService::canAccess($user) ? $base . '/photographers' : null,
            'photographerCounts' => PhotographerService::canAccess($user) ? Photographer::statusCounts() : [],
            'activeTokens'       => AuthToken::activeCountForUser($user->id),
            'canBusiness'        => $canBusiness,
        ];

        if ($canBusiness) {
            $data['paymentSummary']      = PaymentService::dashboardSummary($user);
            $data['projectStatusCounts'] = Project::statusCounts();
            $data['paymentStatusCounts'] = PaymentService::statusCounts($user);
            $data['taskStatusCounts']    = TaskService::statusCounts($user);
            $data['revenueTrend']        = Payment::monthlyTotals(6);
            $data['projectsUrl']         = $base . '/projects';
            $data['paymentsUrl']         = $base . '/payments';
            $data['tasksUrl']            = $base . '/tasks';
        } elseif ($user->role === User::ROLE_EMPLOYEE) {
            $data['myTaskStatusCounts'] = TaskService::myStatusCounts($user);
            $data['mySalaryTotals']     = SalaryService::totals($user->id);
            $data['mySalaryTrend']      = SalaryService::monthlyTrend($user->id, 6);
            $data['myWorkUrl']          = $base . '/my-work';
            $data['mySalaryUrl']        = $base . '/my-salary';
        }

        $this->view('dashboard', $data, 'panel');
    }
}
