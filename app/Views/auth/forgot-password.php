<?php
/**
 * Forgot Password, steps 1-3 (spec s8).
 *
 * @var string                $role
 * @var array<string, mixed>  $roleConfig
 * @var array<string, string> $errors
 * @var array<string, string> $old
 * @var string|null           $resetLink
 */
?>
<div class="rounded-2xl border border-line bg-white p-7 shadow-sm sm:p-9">
    <div class="mb-7">
        <h2 class="text-2xl font-semibold tracking-tight text-ink">Reset your password</h2>
        <p class="mt-1.5 text-sm text-slate-500">
            Enter the email address registered to your <?= e((string) $roleConfig['label']) ?> account and we will send
            you a link to create a new password.
        </p>
    </div>

    <form method="post" action="<?= e((string) $roleConfig['login']) ?>/forgot-password" class="space-y-5" novalidate>
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

        <button type="submit"
                class="w-full rounded-lg bg-brand-gradient px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:brightness-110 focus:outline-none focus:ring-2 focus:ring-brand-400 focus:ring-offset-2">
            Send reset link
        </button>
    </form>

    <?php if (is_string($resetLink) && $resetLink !== ''): ?>
        <!-- Development only: shown because MAIL_DRIVER=log means there is no inbox to check. -->
        <div class="mt-6 rounded-xl border border-dashed border-brand-200 bg-brand-50 p-4 text-sm">
            <p class="font-semibold text-brand-900">Development mode</p>
            <p class="mt-1 text-brand-800">Mail is being written to <code>storage/mail/</code> rather than sent. Your reset link:</p>
            <a href="<?= e($resetLink) ?>" class="mt-2 block break-all font-mono text-xs text-brand-900 underline"><?= e($resetLink) ?></a>
        </div>
    <?php endif; ?>

    <p class="mt-6 text-center text-sm">
        <a href="<?= e((string) $roleConfig['login']) ?>" class="font-medium text-slate-600 underline-offset-2 hover:underline">
            &larr; Back to <?= e((string) $roleConfig['label']) ?> login
        </a>
    </p>
</div>
