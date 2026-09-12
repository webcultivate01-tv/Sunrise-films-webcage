<?php

use App\Models\SalarySettlement;

/**
 * My Salary - the Employee's own summary, monthly breakdown, one month's
 * drill-down entries, and history (salary spec s2, s3, s13).
 *
 * @var string                                                                                       $baseUrl
 * @var array{earned: float, paid: float, outstanding: float, status: string}                        $totals
 * @var list<array{month: string, earned: float, paid: float, outstanding: float, status: string}>   $breakdown
 * @var string                                                                                        $month
 * @var array{credits: list<array<string, mixed>>, settlements: list<SalarySettlement>, earned: float, paid: float, outstanding: float, status: string}|null $monthData
 * @var list<array<string, mixed>>                                                                    $history
 */
?>
<div class="mb-6">
    <h1 class="text-2xl font-semibold tracking-tight text-ink">My Salary</h1>
    <p class="mt-1 text-sm text-slate-500">Your earnings, settlements and outstanding balance.</p>
</div>

<div class="mb-6 grid gap-4 sm:grid-cols-3">
    <div class="rounded-xl border border-line bg-white p-5">
        <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Total earned</p>
        <p class="mt-1.5 text-xl font-semibold text-ink"><?= e(money($totals['earned'])) ?></p>
    </div>
    <div class="rounded-xl border border-line bg-white p-5">
        <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Total paid / settled</p>
        <p class="mt-1.5 text-xl font-semibold text-ink"><?= e(money($totals['paid'])) ?></p>
    </div>
    <div class="rounded-xl border border-line bg-white p-5">
        <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Current outstanding</p>
        <p class="mt-1.5 text-xl font-semibold text-ink"><?= e(money($totals['outstanding'])) ?></p>
        <span class="mt-2 inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium ring-1 ring-inset <?= salary_status_badge($totals['status']) ?>">
            <?= e(salary_status_label($totals['status'])) ?>
        </span>
    </div>
</div>

<section class="mb-6 rounded-xl border border-line bg-white p-5">
    <div class="flex items-start justify-between gap-3">
        <div>
            <h2 class="text-base font-semibold text-ink">Monthly Salary</h2>
            <p class="mt-1 text-xs text-slate-500">Your earnings by month</p>
        </div>
        <span class="shrink-0 rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-700">
            <?= e(money(array_sum(array_column($breakdown, 'earned')))) ?>
        </span>
    </div>

    <?php if ($breakdown === []): ?>
        <p class="mt-6 text-sm text-slate-500">No salary activity recorded yet.</p>
    <?php else: ?>
        <?php $chartMonths = array_reverse($breakdown); ?>
        <div class="mt-6 overflow-x-auto">
            <div class="flex gap-3 pb-1 sm:gap-4" style="min-width: <?= (int) (count($chartMonths) * 56) ?>px">
                <?php $maxSalary = max(array_merge(array_column($chartMonths, 'earned'), [1.0])); ?>
                <?php foreach ($chartMonths as $row): ?>
                    <?php $pct = $maxSalary > 0 ? max(2, (int) round($row['earned'] / $maxSalary * 100)) : 2; ?>
                    <a href="<?= e($baseUrl) ?>?month=<?= e($row['month']) ?>"
                       class="flex w-12 shrink-0 flex-col items-center gap-2">
                        <div class="relative h-40 w-full overflow-hidden rounded-lg bg-slate-50"
                             title="<?= e(pretty_month($row['month'])) ?>: <?= e(money($row['earned'])) ?>">
                            <div class="absolute inset-x-0 bottom-0 rounded-t-lg <?= $month === $row['month'] ? 'bg-gradient-to-t from-brand-700 to-brand-500' : 'bg-gradient-to-t from-brand-600 to-brand-400' ?>"
                                 style="height: <?= (int) $pct ?>%"></div>
                        </div>
                        <span class="text-[11px] font-medium text-slate-500"><?= e(date('M \'y', strtotime($row['month'] . '-01'))) ?></span>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>
</section>

<div class="grid gap-4 lg:grid-cols-5">
    <section class="rounded-xl border border-line bg-white p-5 lg:col-span-2">
        <h2 class="text-base font-semibold text-ink">Monthly breakdown</h2>
        <?php if ($breakdown === []): ?>
            <p class="mt-3 text-sm text-slate-500">No salary activity recorded yet.</p>
        <?php else: ?>
            <div class="mt-3 divide-y divide-slate-100">
                <?php foreach ($breakdown as $row): ?>
                    <a href="<?= e($baseUrl) ?>?month=<?= e($row['month']) ?>"
                       class="flex items-center justify-between gap-3 py-3 transition hover:bg-slate-50/70 <?= $month === $row['month'] ? '-mx-2 rounded-lg bg-brand-50/60 px-2' : '' ?>">
                        <div>
                            <p class="text-sm font-medium text-ink"><?= e(pretty_month($row['month'])) ?></p>
                            <p class="text-xs text-slate-500">
                                Earned <?= e(money($row['earned'])) ?> &middot; Paid <?= e(money($row['paid'])) ?>
                            </p>
                        </div>
                        <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium ring-1 ring-inset <?= salary_status_badge($row['status']) ?>">
                            <?= e(salary_status_label($row['status'])) ?>
                        </span>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

    <section class="rounded-xl border border-line bg-white p-5 lg:col-span-3">
        <?php if ($monthData === null): ?>
            <h2 class="text-base font-semibold text-ink">Month detail</h2>
            <p class="mt-3 text-sm text-slate-500">Select a month from the breakdown to see its entries.</p>
        <?php else: ?>
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h2 class="text-base font-semibold text-ink"><?= e(pretty_month($month)) ?></h2>
                <p class="text-sm text-slate-500">
                    Earned <?= e(money($monthData['earned'])) ?> &middot; Paid <?= e(money($monthData['paid'])) ?>
                    &middot; Outstanding <span class="font-medium text-ink"><?= e(money($monthData['outstanding'])) ?></span>
                </p>
            </div>

            <h3 class="mt-5 text-xs font-semibold uppercase tracking-wide text-slate-500">Completed tasks</h3>
            <?php if ($monthData['credits'] === []): ?>
                <p class="mt-2 text-sm text-slate-500">No completed tasks credited this month.</p>
            <?php else: ?>
                <ul class="mt-2 divide-y divide-slate-100">
                    <?php foreach ($monthData['credits'] as $credit): ?>
                        <li class="flex items-center justify-between gap-3 py-2 text-sm">
                            <div>
                                <p class="font-medium text-ink"><?= e((string) $credit['task_title']) ?></p>
                                <p class="text-xs text-slate-500">
                                    <?= e((string) ($credit['project_name'] ?? 'Unknown project')) ?>
                                    &middot; <?= e(pretty_date((string) $credit['credited_at'])) ?>
                                </p>
                            </div>
                            <p class="font-medium text-ink"><?= e(money((float) $credit['amount'])) ?></p>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>

            <h3 class="mt-5 text-xs font-semibold uppercase tracking-wide text-slate-500">Settlements</h3>
            <?php if ($monthData['settlements'] === []): ?>
                <p class="mt-2 text-sm text-slate-500">No settlements recorded for this month.</p>
            <?php else: ?>
                <ul class="mt-2 divide-y divide-slate-100">
                    <?php foreach ($monthData['settlements'] as $settlement): ?>
                        <li class="flex items-center justify-between gap-3 py-2 text-sm">
                            <div>
                                <p class="font-medium text-ink"><?= e($settlement->referenceNo) ?></p>
                                <p class="text-xs text-slate-500"><?= e(pretty_date($settlement->settledAt)) ?></p>
                            </div>
                            <div class="flex items-center gap-3">
                                <p class="font-medium text-ink"><?= e(money($settlement->amount)) ?></p>
                                <a href="<?= e($baseUrl) ?>/bills/<?= (int) $settlement->id ?>"
                                   class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-semibold text-slate-700 transition hover:bg-slate-50">
                                    View Bill
                                </a>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        <?php endif; ?>
    </section>
</div>

<section class="mt-6 rounded-xl border border-line bg-white p-5">
    <h2 class="text-base font-semibold text-ink">History</h2>
    <?php if ($history === []): ?>
        <p class="mt-3 text-sm text-slate-500">No salary transactions recorded yet.</p>
    <?php else: ?>
        <div class="mt-3 overflow-x-auto">
            <table class="min-w-full divide-y divide-line text-sm">
                <thead class="text-left text-xs uppercase tracking-wide text-slate-500">
                    <tr>
                        <th scope="col" class="py-2 pr-4 font-medium">Date</th>
                        <th scope="col" class="py-2 pr-4 font-medium">Type</th>
                        <th scope="col" class="py-2 pr-4 font-medium">Amount</th>
                        <th scope="col" class="py-2 pr-4 font-medium">Reference</th>
                        <th scope="col" class="py-2 pr-4 font-medium">Detail</th>
                        <th scope="col" class="py-2 text-right font-medium">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php foreach ($history as $entry): ?>
                        <tr>
                            <td class="py-2.5 pr-4 text-slate-600"><?= e(pretty_date($entry['date'])) ?></td>
                            <td class="py-2.5 pr-4">
                                <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium ring-1 ring-inset <?= $entry['type'] === 'settlement' ? 'bg-emerald-50 text-emerald-700 ring-emerald-600/20' : 'bg-sky-50 text-sky-700 ring-sky-600/20' ?>">
                                    <?= $entry['type'] === 'settlement' ? 'Settlement' : 'Salary Earned' ?>
                                </span>
                            </td>
                            <td class="py-2.5 pr-4 font-medium text-ink"><?= e(money($entry['amount'])) ?></td>
                            <td class="py-2.5 pr-4 text-slate-600"><?= e($entry['reference'] ?? '-') ?></td>
                            <td class="py-2.5 pr-4 text-slate-500"><?= e($entry['detail']) ?></td>
                            <td class="py-2.5 text-right">
                                <?php if ($entry['settlementId'] !== null): ?>
                                    <a href="<?= e($baseUrl) ?>/bills/<?= (int) $entry['settlementId'] ?>"
                                       class="text-xs font-semibold text-brand-700 hover:underline">View Bill</a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
