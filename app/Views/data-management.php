<?php

/**
 * Admin-only permanent data deletion.
 *
 * @var string                $base
 * @var array<string, array{label:string, description:string, includes:string, tables:list<string>}> $modules
 * @var array<string, int>    $counts
 * @var array<string, string> $errors
 * @var array<string, string> $old
 */
$selected = (string) ($old['module'] ?? '');
?>
<div class="mb-8">
    <h1 class="text-2xl font-semibold tracking-tight text-ink">Data Management</h1>
    <p class="mt-0.5 text-sm text-slate-500">Permanently delete the data of one module, or of the entire system.</p>
</div>

<div class="mb-6 flex gap-3 rounded-2xl border border-red-200 bg-red-50 p-4 text-sm text-red-800">
    <svg class="mt-0.5 h-5 w-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
        <path d="M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0z"/><path d="M12 9v4M12 17h.01"/>
    </svg>
    <p><strong>Danger zone.</strong> Deleted data is gone for good and cannot be recovered. There is no undo and no backup is made. Admin accounts and system settings are never deleted.</p>
</div>

<form method="post" action="<?= e($base) ?>/data-management" id="purge-form" class="grid gap-6 lg:grid-cols-3" novalidate>
    <?= csrf_field() ?>

    <section class="rounded-2xl border border-line bg-white p-6 shadow-sm lg:col-span-2">
        <h2 class="text-base font-semibold text-ink">1. Choose what to delete</h2>
        <?= field_error($errors, 'module') ?>

        <div class="mt-4 space-y-3">
            <?php foreach ($modules as $id => $module): ?>
                <?php $isAll = $id === 'all'; ?>
                <label class="flex cursor-pointer items-start gap-3 rounded-xl border p-4 transition has-[:checked]:border-red-400 has-[:checked]:bg-red-50/60 <?= $isAll ? 'border-red-200' : 'border-line' ?>">
                    <input type="radio" name="module" value="<?= e($id) ?>" data-label="<?= e($module['label']) ?>"
                           class="mt-1 h-4 w-4 accent-red-600" <?= $selected === $id ? 'checked' : '' ?> required>
                    <span class="min-w-0 flex-1">
                        <span class="flex flex-wrap items-center justify-between gap-2">
                            <span class="text-sm font-semibold <?= $isAll ? 'text-red-700' : 'text-ink' ?>"><?= e($module['label']) ?></span>
                            <span class="rounded-full bg-slate-100 px-2.5 py-0.5 text-xs font-medium text-slate-600">
                                <?= (int) $counts[$id] ?> <?= $counts[$id] === 1 ? 'record' : 'records' ?>
                            </span>
                        </span>
                        <span class="mt-0.5 block text-sm text-slate-500"><?= e($module['description']) ?></span>
                        <span class="mt-1 block text-xs text-slate-500"><strong>Also deletes:</strong> <?= e($module['includes']) ?></span>
                    </span>
                </label>
            <?php endforeach; ?>
        </div>
    </section>

    <section class="h-fit rounded-2xl border border-line bg-white p-6 shadow-sm lg:col-span-1">
        <h2 class="text-base font-semibold text-ink">2. Confirm</h2>
        <p class="mt-1.5 text-sm text-slate-500">Deleting <strong id="purge-target" class="text-ink">nothing selected</strong>.</p>

        <div class="mt-5 space-y-5">
            <div>
                <label for="confirmation" class="mb-1.5 block text-sm font-medium text-ink">Type <span class="font-mono font-semibold">DELETE</span> to confirm</label>
                <input type="text" id="confirmation" name="confirmation" autocomplete="off"
                       class="<?= input_classes($errors, 'confirmation') ?>" required>
                <?= field_error($errors, 'confirmation') ?>
            </div>

            <div>
                <label for="password" class="mb-1.5 block text-sm font-medium text-ink">Your password</label>
                <input type="password" id="password" name="password" autocomplete="current-password"
                       class="<?= input_classes($errors, 'password') ?>" required>
                <?= field_error($errors, 'password') ?>
            </div>

            <button type="submit"
                    class="inline-flex w-full items-center justify-center gap-2 rounded-lg bg-red-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-300 focus:ring-offset-2">
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M3 6h18M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2m3 0v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6"/>
                </svg>
                Delete permanently
            </button>
        </div>
    </section>
</form>

<script>
    (function () {
        var form = document.getElementById('purge-form');
        var target = document.getElementById('purge-target');

        function selected() {
            return form.querySelector('input[name="module"]:checked');
        }

        function sync() {
            var radio = selected();
            target.textContent = radio ? radio.dataset.label + ' data' : 'nothing selected';
        }

        form.addEventListener('change', sync);
        sync();

        form.addEventListener('submit', function (event) {
            var radio = selected();

            if (!radio || !confirm('Permanently delete ' + radio.dataset.label + ' data? This cannot be undone.')) {
                event.preventDefault();
            }
        });
    })();
</script>
