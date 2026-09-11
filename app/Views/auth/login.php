<?php
/**
 * Role login page (spec s4). The same template serves /admin, /manager and
 * /employee; only the role labelling and the form action differ.
 *
 * @var string               $role
 * @var array<string, mixed> $roleConfig
 * @var array<string, string> $errors
 * @var array<string, string> $old
 * @var string|null          $forgotUrl
 */
?>
<div class="rounded-2xl border border-line bg-white p-7 shadow-sm sm:p-9">
    <div class="mb-7">
        <span class="inline-flex items-center rounded-full bg-brand-50 px-3 py-1 text-xs font-semibold uppercase tracking-wide text-brand-700 ring-1 ring-inset ring-brand-600/20">
            <?= e((string) $roleConfig['label']) ?>
        </span>
        <h2 class="mt-4 text-2xl font-semibold tracking-tight text-ink">Sign in to the <?= e((string) $roleConfig['label']) ?> Panel</h2>
        <p class="mt-1.5 text-sm text-slate-500">Use the email address and password issued to your account.</p>
    </div>

    <form method="post" action="<?= e((string) $roleConfig['login']) ?>/login" class="space-y-5" novalidate>
        <?= csrf_field() ?>

        <div>
            <label for="field-email" class="mb-1.5 block text-sm font-medium text-slate-700">Email address</label>
            <input type="email"
                   id="field-email"
                   name="email"
                   value="<?= old($old, 'email') ?>"
                   autocomplete="username"
                   autofocus
                   class="<?= input_classes($errors, 'email') ?>"
                   placeholder="you@sunrisefilms.com">
            <?= field_error($errors, 'email') ?>
        </div>

        <?php $pf = ['name' => 'password', 'label' => 'Password', 'autocomplete' => 'current-password']; ?>
        <?php require BASE_PATH . '/app/Views/partials/password-input.php'; ?>

        <button type="submit"
                class="w-full rounded-lg bg-brand-gradient px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:brightness-110 focus:outline-none focus:ring-2 focus:ring-brand-400 focus:ring-offset-2">
            Log in
        </button>

        <?php if ($forgotUrl !== null): ?>
            <p class="text-center text-sm">
                <a href="<?= e($forgotUrl) ?>" class="font-medium text-brand-700 underline-offset-2 hover:underline">Forgot password?</a>
            </p>
        <?php else: ?>
            <p class="text-center text-xs text-slate-400">
                Password recovery for this panel is handled by system policy.
            </p>
        <?php endif; ?>
    </form>
</div>

<p class="mt-6 text-center text-xs text-slate-500">
    Accounts are issued by your administrator &mdash; there is no public sign-up.
</p>
