<?php

use App\Models\Photographer;
use App\Models\Project;
use App\Models\User;
use App\Support\ReportFields;
use App\Services\ReportService;

/**
 * Reports: one card per report, each with its own filters and three ways out -
 * view it on screen, or download it straight as PDF or Excel.
 *
 * Every form is a plain GET, so a report can be bookmarked or shared with its
 * filters already applied; the buttons only differ in which route they point
 * at via formaction. The fields themselves come from ReportFields, which reads
 * them off the same whitelist the server validates against, so a card can
 * never offer a filter the report would ignore.
 *
 * @var string             $baseUrl
 * @var list<Photographer> $photographers
 * @var list<Project>      $projects
 * @var list<User>         $employees
 */
$panelBase = preg_replace('#/reports$#', '', $baseUrl);

/**
 * One filter card: the report's title, what it covers, its fields and the
 * three actions - the same shell for every report, with only the fields and
 * the target report changing.
 */
$renderCard = static function (string $type) use ($baseUrl, $panelBase, $photographers, $projects, $employees): void {
    $url    = $baseUrl . '/' . $type;
    $fields = ReportFields::render($type, $panelBase, $photographers, $projects, $employees);
    ?>
    <section class="flex flex-col rounded-xl border border-line bg-white p-5">
        <h2 class="text-base font-semibold text-ink"><?= e(ReportService::label($type)) ?></h2>
        <p class="mt-1 text-sm text-slate-500"><?= e(ReportService::description($type)) ?></p>

        <form method="get" action="<?= e($url) ?>" class="mt-4 flex flex-1 flex-col">
            <div class="grid flex-1 grid-cols-2 items-start gap-3">
                <?= $fields ?>
            </div>

            <div class="mt-4 flex flex-wrap gap-2 border-t border-line pt-4">
                <button type="submit"
                        class="inline-flex items-center gap-1.5 rounded-lg bg-brand-gradient px-3.5 py-2 text-xs font-semibold text-white shadow-sm transition hover:brightness-110">
                    <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7-10-7-10-7Z"/><circle cx="12" cy="12" r="3"/>
                    </svg>
                    Generate report
                </button>
                <button type="submit" formaction="<?= e($url) ?>/pdf"
                        class="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 bg-white px-3.5 py-2 text-xs font-semibold text-slate-700 transition hover:bg-slate-50">
                    <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6M9 15h6M9 11h6"/>
                    </svg>
                    PDF
                </button>
                <button type="submit" formaction="<?= e($url) ?>/excel"
                        class="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 bg-white px-3.5 py-2 text-xs font-semibold text-slate-700 transition hover:bg-slate-50">
                    <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <rect x="3" y="4" width="18" height="16" rx="2"/><path d="m9 9 6 6M15 9l-6 6"/>
                    </svg>
                    Excel
                </button>
            </div>
        </form>
    </section>
    <?php
};
?>
<div class="mb-6">
    <h1 class="text-2xl font-semibold tracking-tight text-ink">Reports</h1>
    <p class="mt-1 text-sm text-slate-500">
        Set the filters on any card, then generate the report on screen or download it straight as PDF or Excel.
        Fields marked <span class="font-medium text-red-500">*</span> must be chosen before that report can run.
    </p>
</div>

<div class="grid grid-cols-1 gap-5 md:grid-cols-2 xl:grid-cols-3">
    <?php foreach (ReportService::types() as $type): ?>
        <?php $renderCard($type); ?>
    <?php endforeach; ?>
</div>
