<?php
/**
 * One flash message. Expects $type ('success' | 'error' | anything else) and
 * $message, supplied by the foreach in the layout.
 *
 * @var string $type
 * @var string $message
 */
$styles = match ($type) {
    'success' => 'border-emerald-200 bg-emerald-50 text-emerald-800',
    'error'   => 'border-red-200 bg-red-50 text-red-800',
    default   => 'border-brand-200 bg-brand-50 text-brand-900',
};
?>
<div class="mb-5 flex items-start gap-3 rounded-xl border px-4 py-3 text-sm <?= $styles ?>" role="status">
    <span aria-hidden="true" class="mt-0.5 font-semibold"><?= $type === 'error' ? '!' : '✓' ?></span>
    <p><?= e($message) ?></p>
</div>
