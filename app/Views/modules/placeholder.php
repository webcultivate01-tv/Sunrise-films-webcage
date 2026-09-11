<?php

use App\Support\PanelModules;

/**
 * @var array{label:string, description:string, icon:string} $module
 */
?>
<div class="mb-8">
    <h1 class="text-2xl font-semibold tracking-tight text-ink"><?= e($module['label']) ?></h1>
    <p class="mt-1 text-sm text-slate-500"><?= e($module['description']) ?></p>
</div>

<section class="flex flex-col items-center gap-4 rounded-xl border border-dashed border-slate-300 bg-white p-16 text-center">
    <span class="grid h-14 w-14 place-items-center rounded-full bg-brand-50 text-brand-600">
        <?= PanelModules::icon($module['icon']) ?>
    </span>
    <p class="text-sm font-medium text-ink"><?= e($module['label']) ?></p>
    <p class="max-w-sm text-sm text-slate-500">
        This module has not been built yet. Its screens, tables and forms will render in this area.
    </p>
    <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-medium text-slate-500">Module placeholder</span>
</section>
