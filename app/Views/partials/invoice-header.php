<?php

use App\Models\Setting;

/**
 * Shared invoice/receipt letterhead: the mark on the left, Sunrise Films'
 * contact block on the right, both directly on the bill itself - reused by
 * the Project Invoice and the Payment Receipt so both print the same
 * identity. Blank contact fields are skipped rather than printed empty.
 *
 * @var Setting $company
 */
$contactLines = array_filter([
    $company->companyPhone,
    $company->companyEmail,
    $company->companyWebsite,
    $company->companyAddress,
]);
?>
<div class="flex flex-wrap items-start justify-between gap-6 border-b border-line px-8 pb-6 pt-8">
    <img src="/assets/img/sunrise-mark.png" alt="<?= e($company->companyName) ?>" class="h-14 w-14 shrink-0 object-contain">
    <div class="text-right">
        <p class="text-xl font-bold tracking-tight text-ink"><?= e($company->companyName) ?></p>
        <?php foreach ($contactLines as $line): ?>
            <p class="mt-0.5 text-xs text-slate-500"><?= e($line) ?></p>
        <?php endforeach; ?>
    </div>
</div>
