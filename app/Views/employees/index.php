<?php

use App\Core\Config;
use App\Models\User;

/**
 * Employee Management - the list, search box and row actions
 * (module spec s12, s16).
 *
 * @var list<User>   $people
 * @var string       $baseUrl
 * @var string       $search
 * @var list<string> $roles      Roles this user may see and assign.
 * @var string       $roleFilter The active role tab, or '' for all.
 * @var bool         $canDelete
 * @var User         $authUser
 */
$isAdmin   = $authUser->role === User::ROLE_ADMIN;
$noun      = $isAdmin ? 'user' : 'employee';
$hasSearch = $search !== '';

// The Employees / Managers tabs only make sense when this user manages more
// than one role - a Manager only ever sees Employees, so they get no tabs.
$roleTabs = [];

if (count($roles) > 1) {
    $roleTabs[''] = 'All';

    foreach ($roles as $role) {
        $roleTabs[$role] = (string) Config::get('roles.' . $role . '.label', ucfirst($role)) . 's';
    }
}

$tabUrl = static function (string $role) use ($baseUrl, $search): string {
    $query = array_filter(['role' => $role, 'q' => $search], static fn ($value) => $value !== '');

    return $baseUrl . ($query !== [] ? '?' . http_build_query($query) : '');
};
?>
<div class="mb-6 flex flex-wrap items-end justify-between gap-4">
    <div>
        <h1 class="text-2xl font-semibold tracking-tight text-ink">Employee Management</h1>
        <p class="mt-1 text-sm text-slate-500">
            <?= count($people) ?> <?= e($noun) ?><?= count($people) === 1 ? '' : 's' ?>
            <?= $hasSearch ? 'matching your search' : ($isAdmin ? 'across the system' : 'in your team') ?>.
        </p>
    </div>
    <a href="<?= e($baseUrl) ?>/create"
       class="rounded-lg bg-brand-gradient px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:brightness-110">
        Add <?= e(ucfirst($noun)) ?>
    </a>
</div>

<?php if ($roleTabs !== []): ?>
    <div class="mb-5 flex flex-wrap gap-2" role="tablist" aria-label="Filter by role">
        <?php foreach ($roleTabs as $role => $label): ?>
            <?php $tabActive = $roleFilter === $role; ?>
            <a href="<?= e($tabUrl($role)) ?>"
               role="tab"
               aria-selected="<?= $tabActive ? 'true' : 'false' ?>"
               class="rounded-full px-4 py-2 text-sm font-medium transition <?= $tabActive
                   ? 'bg-brand-gradient text-white shadow-sm'
                   : 'border border-slate-300 bg-white text-slate-600 hover:bg-slate-50' ?>">
                <?= e($label) ?>
            </a>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<form method="get" action="<?= e($baseUrl) ?>" class="mb-5 flex flex-wrap items-center gap-3">
    <?php if ($roleFilter !== ''): ?>
        <input type="hidden" name="role" value="<?= e($roleFilter) ?>">
    <?php endif; ?>
    <div class="relative min-w-0 flex-1 sm:max-w-sm" data-suggest data-suggest-url="<?= e($baseUrl) ?>/suggest">
        <input type="search" name="q" value="<?= e($search) ?>"
               placeholder="Search by name, email or mobile"
               autocomplete="off"
               class="block w-full rounded-lg border border-slate-300 bg-white py-2.5 pl-10 pr-3.5 text-sm text-ink placeholder:text-slate-400 transition focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-200"
               data-suggest-input>
        <svg class="pointer-events-none absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400"
             viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true">
            <circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/>
        </svg>
        <ul data-suggest-list
            class="absolute left-0 right-0 top-full z-20 mt-1 hidden max-h-72 overflow-y-auto rounded-lg border border-line bg-white py-1 text-sm shadow-lg"></ul>
    </div>
    <button type="submit"
            class="rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 transition hover:bg-slate-50">
        Search
    </button>
    <?php if ($hasSearch): ?>
        <a href="<?= e($tabUrl($roleFilter)) ?>" class="text-sm font-medium text-slate-500 hover:text-slate-800">Clear</a>
    <?php endif; ?>
</form>

<?php if ($people === []): ?>
    <div class="rounded-xl border border-dashed border-slate-300 bg-white p-12 text-center">
        <p class="text-sm font-medium text-ink">
            <?= $hasSearch ? 'No matches for "' . e($search) . '"' : 'No ' . e($noun) . ' accounts yet' ?>
        </p>
        <p class="mt-1 text-sm text-slate-500">
            <?= $hasSearch
                ? 'Try a different name, email address or mobile number.'
                : 'Accounts you create will appear here.' ?>
        </p>
    </div>
<?php else: ?>
    <div class="overflow-x-auto rounded-xl border border-line bg-white">
        <table class="min-w-full divide-y divide-line text-sm">
            <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                <tr>
                    <th scope="col" class="px-5 py-3 font-medium">Name</th>
                    <th scope="col" class="px-5 py-3 font-medium">Contact</th>
                    <?php if (count($roles) > 1): ?>
                        <th scope="col" class="px-5 py-3 font-medium">Role</th>
                    <?php endif; ?>
                    <th scope="col" class="px-5 py-3 font-medium">Status</th>
                    <th scope="col" class="px-5 py-3 font-medium">Last sign-in</th>
                    <th scope="col" class="px-5 py-3 text-right font-medium">Actions</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                <?php foreach ($people as $account): ?>
                    <tr class="cursor-pointer hover:bg-slate-50/70" data-href="<?= e($baseUrl) ?>/<?= (int) $account->id ?>">
                        <td class="px-5 py-3.5">
                            <a href="<?= e($baseUrl) ?>/<?= (int) $account->id ?>"
                               class="font-medium text-ink underline-offset-2 hover:underline"><?= e($account->name) ?></a>
                        </td>
                        <td class="px-5 py-3.5 text-slate-600">
                            <p><?= e($account->email) ?></p>
                            <?php if ($account->phone !== null): ?>
                                <p class="text-xs text-slate-500"><?= e($account->phone) ?></p>
                            <?php endif; ?>
                        </td>
                        <?php if (count($roles) > 1): ?>
                            <td class="px-5 py-3.5">
                                <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium ring-1 ring-inset <?= role_badge($account->role) ?>">
                                    <?= e($account->roleLabel()) ?>
                                </span>
                            </td>
                        <?php endif; ?>
                        <td class="px-5 py-3.5">
                            <span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium ring-1 ring-inset <?= status_badge($account->status) ?>">
                                <?= e(ucfirst($account->status)) ?>
                            </span>
                        </td>
                        <td class="px-5 py-3.5 text-slate-500"><?= e(pretty_date($account->lastLoginAt)) ?></td>
                        <td class="px-5 py-3.5">
                            <div class="flex items-center justify-end gap-2">
                                <a href="<?= e($baseUrl) ?>/<?= (int) $account->id ?>/edit"
                                   class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-semibold text-slate-700 transition hover:bg-slate-50">
                                    Edit
                                </a>

                                <form method="post" action="<?= e($baseUrl) ?>/<?= (int) $account->id ?>/status">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="status"
                                           value="<?= $account->isActive() ? User::STATUS_INACTIVE : User::STATUS_ACTIVE ?>">
                                    <button type="submit"
                                            title="<?= $account->isActive() ? 'Deactivate' : 'Activate' ?>"
                                            aria-label="<?= $account->isActive() ? 'Deactivate' : 'Activate' ?> <?= e($account->name) ?>"
                                            class="inline-flex h-8 w-8 items-center justify-center rounded-full border transition <?= $account->isActive()
                                                ? 'border-emerald-200 bg-emerald-50 text-emerald-600 hover:bg-emerald-100'
                                                : 'border-red-200 bg-red-50 text-red-600 hover:bg-red-100' ?>">
                                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true">
                                            <circle cx="12" cy="12" r="9"/>
                                            <line x1="5.5" y1="18.5" x2="18.5" y2="5.5"/>
                                        </svg>
                                    </button>
                                </form>

                                <?php if ($canDelete): ?>
                                    <form method="post" action="<?= e($baseUrl) ?>/<?= (int) $account->id ?>/delete"
                                          onsubmit="return confirm('Permanently delete <?= e(addslashes($account->name)) ?>? This cannot be undone.');">
                                        <?= csrf_field() ?>
                                        <button type="submit"
                                                class="rounded-lg border border-red-200 px-3 py-1.5 text-xs font-semibold text-red-700 transition hover:bg-red-50">
                                            Delete
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
