<?php

use App\Models\User;

/**
 * A superior setting a managed account's password (auth spec s10, s11).
 *
 * @var User                  $account
 * @var string                $baseUrl
 * @var string                $policy
 * @var array<string, string> $errors
 */
?>
<div class="mb-8">
    <a href="<?= e($baseUrl) ?>/<?= (int) $account->id ?>"
       class="inline-flex items-center gap-1.5 text-sm font-medium text-slate-500 transition hover:text-brand-700">
        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="m15 18-6-6 6-6"/>
        </svg>
        Back
    </a>
    <div class="mt-3 flex items-center gap-4">
        <span class="grid h-11 w-11 shrink-0 place-items-center rounded-full bg-brand-gradient text-white shadow-sm">
            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <rect x="4.5" y="10.5" width="15" height="9.5" rx="2"/><path d="M8 10.5V7a4 4 0 0 1 8 0v3.5"/>
            </svg>
        </span>
        <div>
            <h1 class="text-2xl font-semibold tracking-tight text-ink">Reset password</h1>
            <p class="mt-0.5 text-sm text-slate-500">
                For <span class="font-medium text-slate-700"><?= e($account->name) ?></span> (<?= e($account->email) ?>).
            </p>
        </div>
    </div>
</div>

<div class="grid gap-6 lg:grid-cols-3">
    <div class="max-w-md rounded-2xl border border-line bg-white p-6 shadow-sm sm:p-7 lg:col-span-2">
        <div class="mb-6 flex items-start gap-3 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3.5 text-sm text-amber-800">
            <svg class="mt-0.5 h-5 w-5 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M12 9v4.5M12 17h.01"/><path d="M10.3 3.9 2.6 17.1a1.8 1.8 0 0 0 1.6 2.7h15.6a1.8 1.8 0 0 0 1.6-2.7L13.7 3.9a1.8 1.8 0 0 0-3.4 0Z"/>
            </svg>
            Setting a new password signs this account out everywhere. Share the new password with them securely.
        </div>

        <form method="post" action="<?= e($baseUrl) ?>/<?= (int) $account->id ?>/password" class="space-y-5" novalidate>
            <?= csrf_field() ?>

            <?php $pf = ['name' => 'password', 'label' => 'New password', 'autocomplete' => 'new-password', 'hint' => $policy]; ?>
            <?php require BASE_PATH . '/app/Views/partials/password-input.php'; ?>

            <?php $pf = ['name' => 'password_confirmation', 'label' => 'Confirm new password', 'autocomplete' => 'new-password']; ?>
            <?php require BASE_PATH . '/app/Views/partials/password-input.php'; ?>

            <div class="flex items-center gap-3 pt-1">
                <button type="submit"
                        class="inline-flex items-center gap-2 rounded-lg bg-brand-gradient px-5 py-2.5 text-sm font-semibold text-white shadow-sm shadow-brand-600/20 transition hover:shadow-md hover:brightness-110 focus:outline-none focus:ring-2 focus:ring-brand-300 focus:ring-offset-2">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <rect x="4.5" y="10.5" width="15" height="9.5" rx="2"/><path d="M8 10.5V7a4 4 0 0 1 8 0v3.5"/>
                    </svg>
                    Reset password
                </button>
                <a href="<?= e($baseUrl) ?>/<?= (int) $account->id ?>"
                   class="text-sm font-medium text-slate-500 hover:text-slate-800">Cancel</a>
            </div>
        </form>
    </div>
</div>
