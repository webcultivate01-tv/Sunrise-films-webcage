<?php

use App\Models\User;

/**
 * The accounts one level below the signed-in user: Managers for an Admin,
 * Employees for a Manager (spec s10, s11).
 *
 * @var list<User> $accounts
 * @var string     $managesLabel
 * @var string     $baseUrl
 */
?>
<div class="mb-8 flex flex-wrap items-end justify-between gap-4">
    <div>
        <h1 class="text-2xl font-semibold tracking-tight text-ink"><?= e($managesLabel) ?>s</h1>
        <p class="mt-1 text-sm text-slate-500">
            <?= count($accounts) ?> account<?= count($accounts) === 1 ? '' : 's' ?> in your management scope.
        </p>
    </div>
    <a href="<?= e($baseUrl) ?>/create"
       class="rounded-lg bg-brand-gradient px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:brightness-110">
        Create <?= e($managesLabel) ?>
    </a>
</div>

<?php if ($accounts === []): ?>
    <div class="rounded-xl border border-dashed border-slate-300 bg-white p-12 text-center">
        <p class="text-sm font-medium text-ink">No <?= e(mb_strtolower($managesLabel)) ?> accounts yet</p>
        <p class="mt-1 text-sm text-slate-500">Accounts you create will appear here.</p>
    </div>
<?php else: ?>
    <div class="overflow-x-auto rounded-xl border border-line bg-white">
        <table class="min-w-full divide-y divide-line text-sm">
            <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                <tr>
                    <th scope="col" class="px-5 py-3 font-medium">Name</th>
                    <th scope="col" class="px-5 py-3 font-medium">Email</th>
                    <th scope="col" class="px-5 py-3 font-medium">Status</th>
                    <th scope="col" class="px-5 py-3 font-medium">Last sign-in</th>
                    <th scope="col" class="px-5 py-3 text-right font-medium">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                <?php foreach ($accounts as $account): ?>
                    <tr class="hover:bg-slate-50/70">
                        <td class="px-5 py-3.5">
                            <p class="font-medium text-ink"><?= e($account->name) ?></p>
                            <?php if ($account->phone !== null): ?>
                                <p class="text-xs text-slate-500"><?= e($account->phone) ?></p>
                            <?php endif; ?>
                        </td>
                        <td class="px-5 py-3.5 text-slate-600"><?= e($account->email) ?></td>
                        <td class="px-5 py-3.5">
                            <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium ring-1 ring-inset <?= status_badge($account->status) ?>">
                                <?= e(ucfirst($account->status)) ?>
                            </span>
                        </td>
                        <td class="px-5 py-3.5 text-slate-500"><?= e(pretty_date($account->lastLoginAt)) ?></td>
                        <td class="px-5 py-3.5">
                            <div class="flex items-center justify-end gap-2">
                                <a href="<?= e($baseUrl) ?>/<?= (int) $account->id ?>/password"
                                   class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-semibold text-slate-700 transition hover:bg-slate-50">
                                    Reset password
                                </a>

                                <form method="post" action="<?= e($baseUrl) ?>/<?= (int) $account->id ?>/status">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="status"
                                           value="<?= $account->isActive() ? User::STATUS_SUSPENDED : User::STATUS_ACTIVE ?>">
                                    <button type="submit"
                                            class="rounded-lg border px-3 py-1.5 text-xs font-semibold transition <?= $account->isActive()
                                                ? 'border-red-200 text-red-700 hover:bg-red-50'
                                                : 'border-emerald-200 text-emerald-700 hover:bg-emerald-50' ?>">
                                        <?= $account->isActive() ? 'Suspend' : 'Activate' ?>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
