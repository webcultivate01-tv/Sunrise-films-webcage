<?php

use App\Models\Photographer;
use App\Models\Project;
use App\Models\User;
use App\Services\ReportFilters;
use App\Services\ReportService;
use App\Support\ReportFields;

/**
 * One report, on screen: the same filters that produced it (still editable),
 * the figures it adds up to, and the table itself - exactly the rows the PDF
 * and Excel downloads would contain, because all three are rendered from the
 * one array ReportService::build() returns.
 *
 * When the filters were refused, $report is null: the page then shows why,
 * against the field that caused it, instead of a table that would not match
 * what was asked for.
 *
 * @var string                  $baseUrl
 * @var string                  $type
 * @var ReportFilters           $filters
 * @var array<string, string>   $raw
 * @var array<string, mixed>|null $report
 * @var list<Photographer>      $photographers
 * @var list<Project>           $projects
 * @var list<User>              $employees
 */
$panelBase = preg_replace('#/reports$#', '', $baseUrl);
$url       = $baseUrl . '/' . $type;
$query     = $filters->queryString();
$download  = $url . ($query === '' ? '' : '?' . $query);
?>
<div class="mb-6 flex flex-wrap items-start justify-between gap-4">
    <div>
        <nav class="mb-1 text-xs text-slate-500">
            <a href="<?= e($baseUrl) ?>" class="underline-offset-2 hover:underline">Reports</a>
            <span class="mx-1">/</span>
            <span><?= e(ReportService::label($type)) ?></span>
        </nav>
        <h1 class="text-2xl font-semibold tracking-tight text-ink"><?= e(ReportService::label($type)) ?></h1>
        <?php if ($report !== null && ($report['subtitle'] ?? '') !== ''): ?>
            <p class="mt-1 text-sm font-medium text-slate-700"><?= e($report['subtitle']) ?></p>
        <?php endif; ?>
        <p class="mt-1 text-sm text-slate-500">
            <?php if ($report === null): ?>
                This report has not been generated - correct the filters below and try again.
            <?php else: ?>
                <?= count($report['rows']) ?> record<?= count($report['rows']) === 1 ? '' : 's' ?>
                <?= $filters->applied === [] ? 'in total' : 'matching the filters below' ?>.
            <?php endif; ?>
        </p>
    </div>

    <?php if ($report !== null): ?>
        <div class="flex flex-wrap gap-2">
            <a href="<?= e($download === $url ? $url . '/pdf' : $url . '/pdf?' . $query) ?>"
               class="inline-flex items-center gap-1.5 rounded-lg bg-brand-gradient px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:brightness-110">
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6M9 15h6M9 11h6"/>
                </svg>
                Download PDF
            </a>
            <a href="<?= e($download === $url ? $url . '/excel' : $url . '/excel?' . $query) ?>"
               class="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 transition hover:bg-slate-50">
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <rect x="3" y="4" width="18" height="16" rx="2"/><path d="m9 9 6 6M15 9l-6 6"/>
                </svg>
                Download Excel
            </a>
        </div>
    <?php endif; ?>
</div>

<section class="mb-5 rounded-xl border border-line bg-white p-5">
    <h2 class="text-sm font-semibold text-ink">Filters</h2>
    <p class="mt-1 text-xs text-slate-500"><?= e(ReportService::description($type)) ?></p>

    <form method="get" action="<?= e($url) ?>" class="mt-4">
        <div class="grid grid-cols-1 items-start gap-3 sm:grid-cols-2 xl:grid-cols-4">
            <?= ReportFields::render($type, $panelBase, $photographers, $projects, $employees, $raw, $filters->errors) ?>
        </div>

        <div class="mt-4 flex flex-wrap gap-2 border-t border-line pt-4">
            <button type="submit"
                    class="rounded-lg bg-brand-gradient px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:brightness-110">
                Generate report
            </button>
            <a href="<?= e($url) ?>"
               class="rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 transition hover:bg-slate-50">
                Clear filters
            </a>
        </div>
    </form>
</section>

<?php if ($report === null): ?>
    <section class="rounded-xl border border-red-200 bg-red-50/50 p-6">
        <h2 class="text-base font-semibold text-red-900">This report was not generated</h2>
        <p class="mt-1 text-sm text-red-800">
            A report is only produced when every filter it was given is valid - otherwise it would quietly cover
            something other than what its heading claims.
        </p>
        <ul class="mt-3 space-y-1 text-sm text-red-800">
            <?php foreach ($filters->errors as $field => $message): ?>
                <li><span class="font-semibold"><?= e(ReportFilters::label($field)) ?>:</span> <?= e($message) ?></li>
            <?php endforeach; ?>
        </ul>
    </section>
<?php else: ?>
    <?php if ($report['summary'] !== []): ?>
        <section class="mb-5 grid grid-cols-2 gap-3 sm:grid-cols-3 xl:grid-cols-6">
            <?php foreach ($report['summary'] as $part): ?>
                <div class="rounded-xl border border-line bg-white px-4 py-3">
                    <p class="text-xs font-medium uppercase tracking-wide text-slate-500"><?= e($part['label']) ?></p>
                    <p class="mt-1 text-lg font-semibold text-ink"><?= e($part['value']) ?></p>
                </div>
            <?php endforeach; ?>
        </section>
    <?php endif; ?>

    <?php if ($filters->applied !== []): ?>
        <div class="mb-4 flex flex-wrap items-center gap-2">
            <span class="text-xs font-medium uppercase tracking-wide text-slate-500">Applied</span>
            <?php foreach ($filters->applied as $part): ?>
                <span class="inline-flex items-center gap-1 rounded-full bg-slate-100 px-2.5 py-1 text-xs text-slate-700">
                    <span class="font-medium"><?= e($part['label']) ?>:</span> <?= e($part['value']) ?>
                </span>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <section class="overflow-x-auto rounded-xl border border-line bg-white">
        <table class="min-w-full divide-y divide-line text-sm">
            <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                <tr>
                    <?php foreach ($report['columns'] as $column): ?>
                        <th scope="col" class="whitespace-nowrap px-4 py-3 font-medium <?= ($column['align'] ?? 'left') === 'right' ? 'text-right' : '' ?>">
                            <?= e($column['label']) ?>
                        </th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                <?php if ($report['rows'] === []): ?>
                    <tr>
                        <td colspan="<?= count($report['columns']) ?>" class="px-4 py-10 text-center text-slate-500">
                            No records match the selected filters.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($report['rows'] as $row): ?>
                        <tr class="hover:bg-slate-50/70">
                            <?php foreach ($report['columns'] as $index => $column): ?>
                                <td class="px-4 py-3 text-slate-700 <?= ($column['align'] ?? 'left') === 'right' ? 'text-right tabular-nums' : '' ?>">
                                    <?= e((string) ($row[$index] ?? '')) ?>
                                </td>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
            <?php if (($report['totals'] ?? null) !== null): ?>
                <tfoot class="border-t-2 border-slate-300 bg-slate-50 font-semibold text-ink">
                    <tr>
                        <?php foreach ($report['columns'] as $index => $column): ?>
                            <td class="px-4 py-3 <?= ($column['align'] ?? 'left') === 'right' ? 'text-right tabular-nums' : '' ?>">
                                <?= e((string) ($report['totals'][$index] ?? '')) ?>
                            </td>
                        <?php endforeach; ?>
                    </tr>
                </tfoot>
            <?php endif; ?>
        </table>
    </section>
<?php endif; ?>
