<?php
/**
 * @var int    $status
 * @var string $message
 */
?>
<div class="rounded-2xl border border-line bg-white p-7 text-center shadow-sm sm:p-9">
    <p class="text-5xl font-semibold tracking-tight text-slate-300"><?= e((string) $status) ?></p>
    <h2 class="mt-4 text-xl font-semibold tracking-tight text-ink">
        <?= $status === 403 ? 'Access denied' : ($status === 404 ? 'Page not found' : 'Something went wrong') ?>
    </h2>
    <p class="mt-2 text-sm text-slate-500"><?= e($message) ?></p>

    <a href="<?= e($user !== null ? $user->dashboardPath() : '/') ?>"
       class="mt-7 inline-block rounded-lg bg-brand-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-brand-700">
        <?= $user !== null ? 'Back to my panel' : 'Back to sign in' ?>
    </a>
</div>
