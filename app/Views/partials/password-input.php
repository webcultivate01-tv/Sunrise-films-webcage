<?php
/**
 * Password field with the show/hide eye toggle required by spec s4.2.
 * The field is hidden by default; the toggle is wired up in app.js.
 *
 * Expects $pf: ['name', 'label', 'autocomplete', 'hint' (optional)]
 * and $errors from the view.
 *
 * @var array<string, string> $pf
 * @var array<string, string> $errors
 */
$name  = $pf['name'];
$id    = 'field-' . $name;
$hint  = $pf['hint'] ?? '';
?>
<div>
    <label for="<?= e($id) ?>" class="mb-1.5 block text-sm font-medium text-slate-700"><?= e($pf['label']) ?></label>
    <div class="relative">
        <input type="password"
               id="<?= e($id) ?>"
               name="<?= e($name) ?>"
               autocomplete="<?= e($pf['autocomplete'] ?? 'current-password') ?>"
               class="<?= input_classes($errors, $name) ?> pr-12"
               placeholder="••••••••">
        <button type="button"
                class="js-password-toggle absolute inset-y-0 right-0 grid w-12 place-items-center text-slate-400 transition hover:text-slate-700"
                data-target="<?= e($id) ?>"
                aria-label="Show password"
                aria-pressed="false">
            <!-- eye -->
            <svg class="js-eye-open h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true">
                <path d="M2.5 12S6 5.5 12 5.5 21.5 12 21.5 12 18 18.5 12 18.5 2.5 12 2.5 12Z" stroke-linecap="round" stroke-linejoin="round"/>
                <circle cx="12" cy="12" r="3.2"/>
            </svg>
            <!-- eye with a slash -->
            <svg class="js-eye-closed hidden h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" aria-hidden="true">
                <path d="M3 3l18 18" stroke-linecap="round"/>
                <path d="M10.6 6.1A9.7 9.7 0 0 1 12 6c6 0 9.5 6 9.5 6a17 17 0 0 1-3.3 3.9M6.5 7.8A16.6 16.6 0 0 0 2.5 12S6 18 12 18a9.6 9.6 0 0 0 3.6-.7" stroke-linecap="round" stroke-linejoin="round"/>
                <path d="M9.9 9.9a3 3 0 0 0 4.2 4.2" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
        </button>
    </div>
    <?php if ($hint !== ''): ?>
        <p class="mt-1.5 text-xs text-slate-500"><?= e($hint) ?></p>
    <?php endif; ?>
    <?= field_error($errors, $name) ?>
</div>
