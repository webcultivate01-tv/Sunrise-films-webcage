<?php

use App\Models\TaskDescription;

/**
 * The running description thread on a task, shared by the Admin/Manager task
 * page and the Employee's My Work page.
 *
 * Every round the photographer sent is listed oldest first, so an employee
 * reads the brief the way it actually arrived: the original, then each
 * correction on top of it. Only Admin/Manager gets the "send another" box -
 * $threadAction is null on the employee side, which renders it read only.
 *
 * @var list<TaskDescription> $descriptions
 * @var string|null           $threadAction  POST url, or null for read only.
 * @var bool                  $threadClosed  True when the task can take no more.
 * @var array<string, string> $errors
 * @var array<string, string> $old
 */
$threadAction = $threadAction ?? null;
$threadClosed = $threadClosed ?? false;
$errors       = $errors ?? [];
$old          = $old ?? [];
$total        = count($descriptions);
?>
<section class="rounded-2xl border border-line bg-white p-6 shadow-sm sm:p-7">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div class="flex items-center gap-3">
            <span class="grid h-9 w-9 shrink-0 place-items-center rounded-lg bg-brand-50 text-brand-600">
                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M21 11.5a8.4 8.4 0 0 1-9 8.4 8.9 8.9 0 0 1-3.8-.9L3 21l1.9-5A8.4 8.4 0 0 1 12 3.1a8.4 8.4 0 0 1 9 8.4Z"/>
                </svg>
            </span>
            <div>
                <h2 class="text-base font-semibold text-ink">Description</h2>
                <p class="mt-0.5 text-xs text-slate-500">
                    <?= $total > 1
                        ? 'The original brief plus ' . ($total - 1) . ' later ' . ($total === 2 ? 'update' : 'updates') . ', oldest first.'
                        : 'What this task covers.' ?>
                </p>
            </div>
        </div>
        <?php if ($total > 1): ?>
            <span class="inline-flex items-center rounded-full bg-brand-50 px-2.5 py-0.5 text-xs font-medium text-brand-700 ring-1 ring-inset ring-brand-600/20">
                <?= (int) $total ?> rounds
            </span>
        <?php endif; ?>
    </div>

    <?php if ($descriptions === []): ?>
        <p class="mt-5 text-sm text-slate-500">No description has been recorded for this task.</p>
    <?php else: ?>
        <ol class="mt-5 space-y-0">
            <?php foreach ($descriptions as $index => $entry): ?>
                <?php $isLast = $index === $total - 1; ?>
                <li class="relative flex gap-4 <?= $isLast ? '' : 'pb-5' ?>">
                    <?php if (!$isLast): ?>
                        <span class="absolute left-[11px] top-7 bottom-0 w-px bg-line" aria-hidden="true"></span>
                    <?php endif; ?>
                    <span class="mt-1 grid h-6 w-6 shrink-0 place-items-center rounded-full text-[10px] font-semibold
                                 <?= $index === 0 ? 'bg-slate-100 text-slate-500' : 'bg-brand-50 text-brand-700 ring-1 ring-inset ring-brand-600/20' ?>">
                        <?= (int) ($index + 1) ?>
                    </span>
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-baseline gap-x-2 gap-y-0.5">
                            <span class="text-[11px] font-semibold uppercase tracking-wide <?= $index === 0 ? 'text-slate-400' : 'text-brand-700' ?>">
                                <?= $index === 0 ? 'Original brief' : 'Update ' . (int) $index ?>
                            </span>
                            <span class="text-xs text-slate-400">
                                <?= e($entry->createdByName ?? 'Unknown') ?>
                                <?php if ($entry->createdByRole !== null): ?>
                                    (<?= e(ucfirst($entry->createdByRole)) ?>)
                                <?php endif; ?>
                                &middot; <?= e(pretty_date($entry->createdAt)) ?>
                            </span>
                        </div>
                        <p class="mt-1 whitespace-pre-line break-words text-sm text-slate-600"><?= e($entry->body) ?></p>
                    </div>
                </li>
            <?php endforeach; ?>
        </ol>
    <?php endif; ?>

    <?php if ($threadAction !== null): ?>
        <div class="mt-6 border-t border-line pt-5">
            <form method="post" action="<?= e($threadAction) ?>" class="space-y-3" novalidate>
                <?= csrf_field() ?>
                <div>
                    <label for="field-body" class="mb-1.5 block text-sm font-medium text-slate-700">Send another description</label>
                    <textarea id="field-body" name="body" rows="3"
                              class="<?= input_classes($errors, 'body') ?>"
                              placeholder="Paste what the photographer sent this time - the employee keeps everything above as well."><?= old($old, 'body') ?></textarea>
                    <p class="mt-1.5 text-xs text-slate-500">Added to the thread, never over the top of it. The assigned employee sees it the moment you send.</p>
                    <?= field_error($errors, 'body') ?>
                </div>
                <button type="submit"
                        class="inline-flex items-center gap-2 rounded-lg bg-brand-gradient px-4 py-2.5 text-sm font-semibold text-white shadow-sm shadow-brand-600/20 transition hover:shadow-md hover:brightness-110 focus:outline-none focus:ring-2 focus:ring-brand-300 focus:ring-offset-2">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="m22 2-7 20-4-9-9-4Z"/><path d="M22 2 11 13"/>
                    </svg>
                    Send to employee
                </button>
            </form>
        </div>
    <?php elseif ($threadClosed): ?>
        <p class="mt-6 border-t border-line pt-5 text-xs text-slate-500">
            This task is closed - any new instructions belong on the task that replaced it.
        </p>
    <?php endif; ?>
</section>
