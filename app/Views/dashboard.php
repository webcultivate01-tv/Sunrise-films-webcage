<?php

use App\Models\Photographer;
use App\Models\Project;
use App\Models\Task;
use App\Models\User;
use App\Services\PaymentService;

/**
 * The panel each role lands on (auth spec s4.5). An Admin/Manager - who share
 * full reach over Work, Payment and Task Management - gets the business KPIs
 * and charts below; an Employee gets their own task/salary snapshot instead.
 *
 * @var User                    $authUser
 * @var bool                    $managesPeople
 * @var array<string, int>      $peopleCounts       role => total, in scope
 * @var string|null             $employeesUrl
 * @var string|null             $photographersUrl
 * @var array<string, int>      $photographerCounts
 * @var int                     $activeTokens
 * @var bool                    $canBusiness
 * @var array<string, mixed>    $paymentSummary
 * @var array<string, int>      $projectStatusCounts
 * @var array<string, int>      $paymentStatusCounts
 * @var array<string, int>      $taskStatusCounts
 * @var array<string, float>    $revenueTrend       'Y-m' => total collected
 * @var string|null             $projectsUrl
 * @var string|null             $paymentsUrl
 * @var string|null             $tasksUrl
 * @var array<string, int>                                                                          $myTaskStatusCounts Employee only: their own tasks by status
 * @var array{earned: float, paid: float, outstanding: float, status: string}                        $mySalaryTotals     Employee only
 * @var list<array{month: string, earned: float}>                                                     $mySalaryTrend      Employee only, oldest first, zero-filled
 * @var string|null             $myWorkUrl
 * @var string|null             $mySalaryUrl
 */
$base = (string) config('roles.' . $authUser->role . '.login');
?>
<div class="mb-8">
    <h1 class="text-2xl font-semibold tracking-tight text-ink"><?= e($authUser->roleLabel()) ?> Panel</h1>
    <p class="mt-1 text-sm text-slate-500">
        Signed in as <?= e($authUser->email) ?> &middot; last sign-in <?= e(pretty_date($authUser->lastLoginAt, 'this is your first')) ?>
    </p>
</div>

<?php if ($canBusiness): ?>

    <?php
    $activeProjects  = ($projectStatusCounts[Project::STATUS_PENDING] ?? 0) + ($projectStatusCounts[Project::STATUS_IN_PROGRESS] ?? 0);
    $activePhotographers = $photographerCounts[Photographer::STATUS_ACTIVE] ?? 0;
    $overdueAmount   = (float) ($paymentSummary['overduePayments'] ?? 0.0);

    $kpis = [
        [
            'label'  => 'Revenue Collected',
            'value'  => money((float) $paymentSummary['totalCollected']),
            'note'   => 'of ' . money((float) $paymentSummary['totalProjectValue']) . ' total project value',
            'accent' => 'emerald',
            'icon'   => '<path d="M3 17l6-6 4 4 8-8"/><path d="M14 7h7v7"/>',
        ],
        [
            'label'  => 'Outstanding Balance',
            'value'  => money((float) $paymentSummary['totalOutstanding']),
            'note'   => $overdueAmount > 0 ? money($overdueAmount) . ' overdue' : 'No overdue balances',
            'accent' => $overdueAmount > 0 ? 'red' : 'amber',
            'icon'   => '<circle cx="12" cy="12" r="9"/><path d="M12 8v5M12 16h.01"/>',
        ],
        [
            'label'  => 'Active Projects',
            'value'  => (string) $activeProjects,
            'note'   => ($projectStatusCounts[Project::STATUS_COMPLETED] ?? 0) . ' completed so far',
            'accent' => 'brand',
            'icon'   => '<rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/>',
        ],
        [
            'label'  => 'Active Photographers',
            'value'  => (string) $activePhotographers,
            'note'   => ($photographerCounts[Photographer::STATUS_INACTIVE] ?? 0) . ' inactive',
            'accent' => 'sky',
            'icon'   => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M19 8v6M22 11h-6"/>',
        ],
    ];

    $accentClasses = [
        'emerald' => 'bg-emerald-50 text-emerald-600',
        'red'     => 'bg-red-50 text-red-600',
        'amber'   => 'bg-amber-50 text-amber-600',
        'brand'   => 'bg-brand-50 text-brand-700',
        'sky'     => 'bg-sky-50 text-sky-600',
    ];
    ?>

    <p class="mb-3 text-[11px] font-semibold uppercase tracking-wide text-slate-400">Overview</p>
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <?php foreach ($kpis as $kpi): ?>
            <div class="rounded-xl border border-line bg-white p-5">
                <div class="flex items-center justify-between">
                    <p class="text-xs font-medium uppercase tracking-wide text-slate-500"><?= e($kpi['label']) ?></p>
                    <span class="grid h-9 w-9 shrink-0 place-items-center rounded-lg <?= e($accentClasses[$kpi['accent']]) ?>">
                        <svg class="h-4.5 w-4.5" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                             stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <?= $kpi['icon'] ?>
                        </svg>
                    </span>
                </div>
                <p class="mt-3 text-2xl font-semibold tracking-tight text-ink"><?= e($kpi['value']) ?></p>
                <p class="mt-1 text-xs text-slate-500"><?= e($kpi['note']) ?></p>
            </div>
        <?php endforeach; ?>
    </div>

    <p class="mb-3 mt-8 text-[11px] font-semibold uppercase tracking-wide text-slate-400">Business Insights</p>
    <div class="grid gap-4 lg:grid-cols-3">
        <!-- ============ Revenue trend ============ -->
        <section class="rounded-xl border border-line bg-white p-6 lg:col-span-2">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <h2 class="text-base font-semibold text-ink">Revenue Trend</h2>
                    <p class="mt-1 text-xs text-slate-500">Payments collected over the last 6 months</p>
                </div>
                <span class="shrink-0 rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-700">
                    <?= e(money(array_sum($revenueTrend))) ?>
                </span>
            </div>

            <div class="mt-6 grid grid-cols-6 gap-3 sm:gap-4">
                <?php $maxRevenue = max(array_merge(array_values($revenueTrend), [1.0])); ?>
                <?php foreach ($revenueTrend as $ym => $total): ?>
                    <?php $pct = $maxRevenue > 0 ? max(2, (int) round($total / $maxRevenue * 100)) : 2; ?>
                    <div class="flex flex-col items-center gap-2">
                        <div class="relative h-40 w-full overflow-hidden rounded-lg bg-slate-50" title="<?= e(money($total)) ?>">
                            <div class="absolute inset-x-0 bottom-0 rounded-t-lg bg-gradient-to-t from-brand-600 to-brand-400"
                                 style="height: <?= (int) $pct ?>%"></div>
                        </div>
                        <span class="text-[11px] font-medium text-slate-500"><?= e(date('M', strtotime($ym . '-01'))) ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>

        <!-- ============ Project status ============ -->
        <section class="rounded-xl border border-line bg-white p-6">
            <h2 class="text-base font-semibold text-ink">Project Status</h2>
            <p class="mt-1 text-xs text-slate-500">Where every registered project stands</p>

            <?php
            $projectMeta = [
                Project::STATUS_PENDING     => ['label' => 'Pending',     'color' => '#94a3b8', 'dot' => 'bg-slate-400'],
                Project::STATUS_IN_PROGRESS => ['label' => 'In Progress', 'color' => '#5145e5', 'dot' => 'bg-brand-600'],
                Project::STATUS_COMPLETED   => ['label' => 'Completed',   'color' => '#10b981', 'dot' => 'bg-emerald-500'],
                Project::STATUS_CANCELLED   => ['label' => 'Cancelled',   'color' => '#ef4444', 'dot' => 'bg-red-500'],
            ];

            $totalProjects = array_sum($projectStatusCounts);
            $cursor        = 0.0;
            $stops         = [];

            foreach ($projectMeta as $statusKey => $meta) {
                $count = $projectStatusCounts[$statusKey] ?? 0;

                if ($count <= 0 || $totalProjects <= 0) {
                    continue;
                }

                $start   = $cursor;
                $cursor += $count / $totalProjects * 360;
                $stops[] = sprintf('%s %sdeg %sdeg', $meta['color'], round($start, 2), round($cursor, 2));
            }

            $donutStyle = $stops !== [] ? 'background: conic-gradient(' . implode(', ', $stops) . ');' : 'background: #e8eaf3;';
            ?>

            <div class="relative mx-auto mt-6 h-36 w-36 rounded-full" style="<?= e($donutStyle) ?>">
                <div class="absolute inset-3 flex flex-col items-center justify-center rounded-full bg-white">
                    <span class="text-2xl font-semibold text-ink"><?= (int) $totalProjects ?></span>
                    <span class="text-[11px] text-slate-500">Projects</span>
                </div>
            </div>

            <ul class="mt-6 space-y-2.5">
                <?php foreach ($projectMeta as $statusKey => $meta): ?>
                    <li class="flex items-center justify-between text-sm">
                        <span class="flex items-center gap-2 text-slate-600">
                            <span class="h-2.5 w-2.5 rounded-full <?= e($meta['dot']) ?>"></span>
                            <?= e($meta['label']) ?>
                        </span>
                        <span class="font-semibold text-ink"><?= (int) ($projectStatusCounts[$statusKey] ?? 0) ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
        </section>

        <!-- ============ Payment status ============ -->
        <section class="rounded-xl border border-line bg-white p-6">
            <h2 class="text-base font-semibold text-ink">Payment Status</h2>
            <p class="mt-1 text-xs text-slate-500">Every project, by collection status</p>

            <?php
            $paymentMeta = [
                PaymentService::STATUS_PAID             => ['label' => 'Fully Paid',       'color' => '#10b981', 'dot' => 'bg-emerald-500'],
                PaymentService::STATUS_PARTIAL          => ['label' => 'Partially Paid',   'color' => '#f59e0b', 'dot' => 'bg-amber-500'],
                PaymentService::STATUS_ADVANCE_RECEIVED => ['label' => 'Advance Received', 'color' => '#0ea5e9', 'dot' => 'bg-sky-500'],
                PaymentService::STATUS_OVERDUE          => ['label' => 'Overdue',          'color' => '#ef4444', 'dot' => 'bg-red-500'],
                PaymentService::STATUS_DUE              => ['label' => 'Payment Due',      'color' => '#94a3b8', 'dot' => 'bg-slate-400'],
            ];

            $totalPaymentProjects = array_sum($paymentStatusCounts);
            $paymentCursor        = 0.0;
            $paymentStops         = [];

            foreach ($paymentMeta as $statusKey => $meta) {
                $count = $paymentStatusCounts[$statusKey] ?? 0;

                if ($count <= 0 || $totalPaymentProjects <= 0) {
                    continue;
                }

                $start           = $paymentCursor;
                $paymentCursor  += $count / $totalPaymentProjects * 360;
                $paymentStops[]  = sprintf('%s %sdeg %sdeg', $meta['color'], round($start, 2), round($paymentCursor, 2));
            }

            $paymentDonutStyle = $paymentStops !== [] ? 'background: conic-gradient(' . implode(', ', $paymentStops) . ');' : 'background: #e8eaf3;';
            ?>

            <div class="relative mx-auto mt-6 h-36 w-36 rounded-full" style="<?= e($paymentDonutStyle) ?>">
                <div class="absolute inset-3 flex flex-col items-center justify-center rounded-full bg-white">
                    <span class="text-2xl font-semibold text-ink"><?= (int) $totalPaymentProjects ?></span>
                    <span class="text-[11px] text-slate-500">Projects</span>
                </div>
            </div>

            <ul class="mt-6 space-y-2.5">
                <?php foreach ($paymentMeta as $statusKey => $meta): ?>
                    <li class="flex items-center justify-between text-sm">
                        <span class="flex items-center gap-2 text-slate-600">
                            <span class="h-2.5 w-2.5 rounded-full <?= e($meta['dot']) ?>"></span>
                            <?= e($meta['label']) ?>
                        </span>
                        <span class="font-semibold text-ink"><?= (int) ($paymentStatusCounts[$statusKey] ?? 0) ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
        </section>

        <!-- ============ Task status ============ -->
        <section class="rounded-xl border border-line bg-white p-6">
            <h2 class="text-base font-semibold text-ink">Task Progress</h2>
            <p class="mt-1 text-xs text-slate-500">Every assigned task, by current status</p>

            <?php
            $taskMeta = [
                Task::STATUS_ASSIGNED    => ['label' => 'Assigned',    'bar' => 'bg-slate-400'],
                Task::STATUS_ACCEPTED    => ['label' => 'Accepted',    'bar' => 'bg-sky-500'],
                Task::STATUS_IN_PROGRESS => ['label' => 'In Progress', 'bar' => 'bg-brand-600'],
                Task::STATUS_COMPLETED   => ['label' => 'Completed',   'bar' => 'bg-emerald-500'],
                Task::STATUS_EXITED      => ['label' => 'Exited',      'bar' => 'bg-red-500'],
            ];

            $maxTaskCount = max(array_merge(array_values($taskStatusCounts), [1]));
            ?>

            <div class="mt-6 space-y-4">
                <?php foreach ($taskMeta as $statusKey => $meta): ?>
                    <?php
                    $count = $taskStatusCounts[$statusKey] ?? 0;
                    $pct   = $maxTaskCount > 0 ? (int) round($count / $maxTaskCount * 100) : 0;
                    ?>
                    <div>
                        <div class="mb-1.5 flex items-center justify-between text-sm">
                            <span class="text-slate-600"><?= e($meta['label']) ?></span>
                            <span class="font-semibold text-ink"><?= (int) $count ?></span>
                        </div>
                        <div class="h-2.5 w-full rounded-full bg-slate-100">
                            <div class="h-2.5 rounded-full <?= e($meta['bar']) ?>" style="width: <?= (int) $pct ?>%"></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>

        <!-- ============ Collection rate ============ -->
        <section class="rounded-xl border border-line bg-white p-6">
            <h2 class="text-base font-semibold text-ink">Collection Rate</h2>
            <p class="mt-1 text-xs text-slate-500">Collected against total project value</p>

            <?php
            $totalValue     = (float) $paymentSummary['totalProjectValue'];
            $collected      = (float) $paymentSummary['totalCollected'];
            $collectionPct  = $totalValue > 0.0 ? min(100, (int) round($collected / $totalValue * 100)) : 0;
            $collectionDeg  = $collectionPct / 100 * 360;
            $collectionStyle = sprintf(
                'background: conic-gradient(#10b981 0deg %sdeg, #e8eaf3 %sdeg 360deg);',
                round($collectionDeg, 2),
                round($collectionDeg, 2),
            );
            ?>

            <div class="relative mx-auto mt-6 h-36 w-36 rounded-full" style="<?= e($collectionStyle) ?>">
                <div class="absolute inset-3 flex flex-col items-center justify-center rounded-full bg-white">
                    <span class="text-2xl font-semibold text-ink"><?= (int) $collectionPct ?>%</span>
                    <span class="text-[11px] text-slate-500">Collected</span>
                </div>
            </div>

            <ul class="mt-6 space-y-2.5 text-sm">
                <li class="flex items-center justify-between">
                    <span class="flex items-center gap-2 text-slate-600">
                        <span class="h-2.5 w-2.5 rounded-full bg-emerald-500"></span>
                        Collected
                    </span>
                    <span class="font-semibold text-ink"><?= e(money($collected)) ?></span>
                </li>
                <li class="flex items-center justify-between">
                    <span class="flex items-center gap-2 text-slate-600">
                        <span class="h-2.5 w-2.5 rounded-full bg-slate-300"></span>
                        Total value
                    </span>
                    <span class="font-semibold text-ink"><?= e(money($totalValue)) ?></span>
                </li>
                <li class="flex items-center justify-between">
                    <span class="flex items-center gap-2 text-slate-600">
                        <span class="h-2.5 w-2.5 rounded-full bg-red-400"></span>
                        Overdue
                    </span>
                    <span class="font-semibold text-ink"><?= e(money((float) $paymentSummary['overduePayments'])) ?></span>
                </li>
            </ul>
        </section>
    </div>

<?php elseif ($authUser->role === User::ROLE_EMPLOYEE): ?>

    <?php
    $myActive    = ($myTaskStatusCounts[Task::STATUS_ASSIGNED] ?? 0) + ($myTaskStatusCounts[Task::STATUS_ACCEPTED] ?? 0) + ($myTaskStatusCounts[Task::STATUS_IN_PROGRESS] ?? 0);
    $myPending   = $myTaskStatusCounts[Task::STATUS_ASSIGNED] ?? 0;
    $myCompleted = $myTaskStatusCounts[Task::STATUS_COMPLETED] ?? 0;

    $employeeKpis = [
        [
            'label'  => 'Total Earned',
            'value'  => money($mySalaryTotals['earned']),
            'note'   => 'Lifetime task earnings',
            'accent' => 'emerald',
            'icon'   => '<path d="M3 17l6-6 4 4 8-8"/><path d="M14 7h7v7"/>',
        ],
        [
            'label'  => 'Outstanding',
            'value'  => money($mySalaryTotals['outstanding']),
            'note'   => salary_status_label($mySalaryTotals['status']),
            'accent' => $mySalaryTotals['outstanding'] > 0 ? 'amber' : 'sky',
            'icon'   => '<circle cx="12" cy="12" r="9"/><path d="M12 8v5M12 16h.01"/>',
        ],
        [
            'label'  => 'Active Tasks',
            'value'  => (string) $myActive,
            'note'   => $myPending . ' pending acceptance',
            'accent' => 'brand',
            'icon'   => '<rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/>',
        ],
        [
            'label'  => 'Completed Tasks',
            'value'  => (string) $myCompleted,
            'note'   => 'All-time',
            'accent' => 'sky',
            'icon'   => '<path d="m9 12 2 2 4-4"/><circle cx="12" cy="12" r="9"/>',
        ],
    ];

    $accentClasses = [
        'emerald' => 'bg-emerald-50 text-emerald-600',
        'red'     => 'bg-red-50 text-red-600',
        'amber'   => 'bg-amber-50 text-amber-600',
        'brand'   => 'bg-brand-50 text-brand-700',
        'sky'     => 'bg-sky-50 text-sky-600',
    ];
    ?>

    <p class="mb-3 text-[11px] font-semibold uppercase tracking-wide text-slate-400">Overview</p>
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <?php foreach ($employeeKpis as $kpi): ?>
            <div class="rounded-xl border border-line bg-white p-5">
                <div class="flex items-center justify-between">
                    <p class="text-xs font-medium uppercase tracking-wide text-slate-500"><?= e($kpi['label']) ?></p>
                    <span class="grid h-9 w-9 shrink-0 place-items-center rounded-lg <?= e($accentClasses[$kpi['accent']]) ?>">
                        <svg class="h-4.5 w-4.5" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                             stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <?= $kpi['icon'] ?>
                        </svg>
                    </span>
                </div>
                <p class="mt-3 text-2xl font-semibold tracking-tight text-ink"><?= e($kpi['value']) ?></p>
                <p class="mt-1 text-xs text-slate-500"><?= e($kpi['note']) ?></p>
            </div>
        <?php endforeach; ?>
    </div>

    <p class="mb-3 mt-8 text-[11px] font-semibold uppercase tracking-wide text-slate-400">My Insights</p>
    <div class="grid gap-4 lg:grid-cols-3">
        <!-- ============ My task status ============ -->
        <section class="rounded-xl border border-line bg-white p-6">
            <h2 class="text-base font-semibold text-ink">My Task Status</h2>
            <p class="mt-1 text-xs text-slate-500">Every task assigned to you</p>

            <?php
            $myTaskMeta = [
                Task::STATUS_ASSIGNED    => ['label' => 'Pending',     'color' => '#94a3b8', 'dot' => 'bg-slate-400'],
                Task::STATUS_ACCEPTED    => ['label' => 'Accepted',    'color' => '#0ea5e9', 'dot' => 'bg-sky-500'],
                Task::STATUS_IN_PROGRESS => ['label' => 'In Progress', 'color' => '#5145e5', 'dot' => 'bg-brand-600'],
                Task::STATUS_COMPLETED   => ['label' => 'Completed',   'color' => '#10b981', 'dot' => 'bg-emerald-500'],
                Task::STATUS_EXITED      => ['label' => 'Exited',      'color' => '#ef4444', 'dot' => 'bg-red-500'],
            ];

            $myTotalTasks = array_sum($myTaskStatusCounts);
            $myCursor     = 0.0;
            $myStops      = [];

            foreach ($myTaskMeta as $statusKey => $meta) {
                $count = $myTaskStatusCounts[$statusKey] ?? 0;

                if ($count <= 0 || $myTotalTasks <= 0) {
                    continue;
                }

                $start      = $myCursor;
                $myCursor  += $count / $myTotalTasks * 360;
                $myStops[]  = sprintf('%s %sdeg %sdeg', $meta['color'], round($start, 2), round($myCursor, 2));
            }

            $myDonutStyle = $myStops !== [] ? 'background: conic-gradient(' . implode(', ', $myStops) . ');' : 'background: #e8eaf3;';
            ?>

            <div class="relative mx-auto mt-6 h-36 w-36 rounded-full" style="<?= e($myDonutStyle) ?>">
                <div class="absolute inset-3 flex flex-col items-center justify-center rounded-full bg-white">
                    <span class="text-2xl font-semibold text-ink"><?= (int) $myTotalTasks ?></span>
                    <span class="text-[11px] text-slate-500">Tasks</span>
                </div>
            </div>

            <ul class="mt-6 space-y-2.5">
                <?php foreach ($myTaskMeta as $statusKey => $meta): ?>
                    <li class="flex items-center justify-between text-sm">
                        <span class="flex items-center gap-2 text-slate-600">
                            <span class="h-2.5 w-2.5 rounded-full <?= e($meta['dot']) ?>"></span>
                            <?= e($meta['label']) ?>
                        </span>
                        <span class="font-semibold text-ink"><?= (int) ($myTaskStatusCounts[$statusKey] ?? 0) ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>

            <a href="<?= e($myWorkUrl) ?>" class="mt-5 inline-block text-sm font-semibold text-brand-700 hover:underline">View my work &rarr;</a>
        </section>

        <!-- ============ Monthly salary ============ -->
        <section class="rounded-xl border border-line bg-white p-6 lg:col-span-2">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <h2 class="text-base font-semibold text-ink">Monthly Salary</h2>
                    <p class="mt-1 text-xs text-slate-500">Your earnings over the last <?= count($mySalaryTrend) ?> months</p>
                </div>
                <span class="shrink-0 rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-700">
                    <?= e(money(array_sum(array_column($mySalaryTrend, 'earned')))) ?>
                </span>
            </div>

            <div class="mt-6 grid grid-cols-6 gap-3 sm:gap-4">
                <?php $maxSalary = max(array_merge(array_column($mySalaryTrend, 'earned'), [1.0])); ?>
                <?php foreach ($mySalaryTrend as $row): ?>
                    <?php $pct = $maxSalary > 0 ? max(2, (int) round($row['earned'] / $maxSalary * 100)) : 2; ?>
                    <div class="flex flex-col items-center gap-2">
                        <div class="relative h-40 w-full overflow-hidden rounded-lg bg-slate-50" title="<?= e(money($row['earned'])) ?>">
                            <div class="absolute inset-x-0 bottom-0 rounded-t-lg bg-gradient-to-t from-brand-600 to-brand-400"
                                 style="height: <?= (int) $pct ?>%"></div>
                        </div>
                        <span class="text-[11px] font-medium text-slate-500"><?= e(date('M', strtotime($row['month'] . '-01'))) ?></span>
                    </div>
                <?php endforeach; ?>
            </div>

            <a href="<?= e($mySalaryUrl) ?>" class="mt-5 inline-block text-sm font-semibold text-brand-700 hover:underline">View my salary &rarr;</a>
        </section>
    </div>

<?php endif; ?>

<p class="mb-3 mt-8 text-[11px] font-semibold uppercase tracking-wide text-slate-400">Quick Actions</p>
<div class="grid gap-4 lg:grid-cols-2">
    <?php if ($photographersUrl !== null): ?>
        <section class="rounded-xl border border-line bg-white p-6">
            <h2 class="text-base font-semibold text-ink">Photographer Management</h2>
            <p class="mt-1.5 text-sm text-slate-500">
                Register new photographers, keep their contact details current and search the photographer list.
            </p>
            <div class="mt-5 flex flex-wrap gap-3">
                <a href="<?= e($photographersUrl) ?>"
                   class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-brand-700">
                    View photographers
                </a>
                <a href="<?= e($photographersUrl) ?>/create"
                   class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-50">
                    Add photographer
                </a>
            </div>
        </section>
    <?php endif; ?>

    <?php if ($employeesUrl !== null): ?>
        <section class="rounded-xl border border-line bg-white p-6">
            <h2 class="text-base font-semibold text-ink">Employee Management</h2>
            <p class="mt-1.5 text-sm text-slate-500">
                <?= $authUser->role === User::ROLE_ADMIN
                    ? 'Create manager and employee accounts, edit their details and control who can sign in.'
                    : 'Create employee accounts, edit their details and control who can sign in.' ?>
            </p>
            <div class="mt-5 flex flex-wrap gap-3">
                <a href="<?= e($employeesUrl) ?>"
                   class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-brand-700">
                    View <?= $authUser->role === User::ROLE_ADMIN ? 'users' : 'employees' ?>
                </a>
                <a href="<?= e($employeesUrl) ?>/create"
                   class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-50">
                    Add <?= $authUser->role === User::ROLE_ADMIN ? 'user' : 'employee' ?>
                </a>
            </div>
        </section>
    <?php endif; ?>

    <?php if ($canBusiness): ?>
        <section class="rounded-xl border border-line bg-white p-6">
            <h2 class="text-base font-semibold text-ink">Work &amp; Payments</h2>
            <p class="mt-1.5 text-sm text-slate-500">
                Track project progress, assign tasks and record photographer payments as they come in.
            </p>
            <div class="mt-5 flex flex-wrap gap-3">
                <a href="<?= e($projectsUrl) ?>"
                   class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-brand-700">
                    View projects
                </a>
                <a href="<?= e($paymentsUrl) ?>"
                   class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-50">
                    Record payment
                </a>
                <a href="<?= e($tasksUrl) ?>"
                   class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-50">
                    Manage tasks
                </a>
            </div>
        </section>
    <?php endif; ?>

    <?php if ($authUser->role === User::ROLE_EMPLOYEE): ?>
        <section class="rounded-xl border border-line bg-white p-6">
            <h2 class="text-base font-semibold text-ink">My Work &amp; Salary</h2>
            <p class="mt-1.5 text-sm text-slate-500">
                Accept and update your assigned tasks, and keep an eye on what you have earned and been paid.
            </p>
            <div class="mt-5 flex flex-wrap gap-3">
                <a href="<?= e($myWorkUrl) ?>"
                   class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-brand-700">
                    View my work
                </a>
                <a href="<?= e($mySalaryUrl) ?>"
                   class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-50">
                    View my salary
                </a>
            </div>
        </section>
    <?php endif; ?>

    <section class="rounded-xl border border-line bg-white p-6">
        <h2 class="text-base font-semibold text-ink">Account security</h2>
        <p class="mt-1.5 text-sm text-slate-500">
            Change your password whenever you need to. Doing so signs out every other device immediately.
        </p>
        <a href="<?= e($base) ?>/profile"
           class="mt-5 inline-block rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 transition hover:bg-slate-50">
            Change my password
        </a>
    </section>
</div>
