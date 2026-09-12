<?php

use App\Models\User;
use App\Support\PanelModules;

/**
 * Sidebar shell for every signed-in panel. The navigation is built from the
 * authenticated user's role, so a panel never offers a link the role is not
 * allowed to follow.
 *
 * @var string      $title
 * @var string      $appName
 * @var string      $content
 * @var User|null   $authUser
 */
$flash = $flash ?? [];
$user  = $authUser;
$base  = $user !== null ? (string) config('roles.' . $user->role . '.login') : '/';

$navItems = [];

if ($user !== null) {
    $navItems[] = [
        'url'   => $base . '/dashboard',
        'label' => 'Dashboard',
        'icon'  => '<rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/>'
            . '<rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/>',
    ];

    // Modules that are built and routed for this role (module spec s2, s6).
    foreach (PanelModules::built($user->role) as $built) {
        $module = PanelModules::module($built['id']);

        $navItems[] = [
            'url'   => $base . $built['path'],
            'label' => $module['label'],
            'icon'  => $module['icon'],
        ];
    }

    foreach (PanelModules::placeholders($user->role) as $moduleId) {
        $module = PanelModules::module($moduleId);

        $navItems[] = [
            'url'         => $base . '/' . $moduleId,
            'label'       => $module['label'],
            'icon'        => $module['icon'],
            'placeholder' => true,
        ];
    }

    $navItems[] = [
        'url'   => $base . '/profile',
        'label' => 'My Account',
        'icon'  => '<circle cx="12" cy="8" r="4"/><path d="M6 21v-1a4 4 0 0 1 4-4h4a4 4 0 0 1 4 4v1"/>',
    ];
}

$currentPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
?>
<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title><?= e($title) ?> &middot; <?= e($appName) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <?php require BASE_PATH . '/app/Views/partials/theme.php'; ?>
    <link rel="stylesheet" href="/assets/css/app.css">
</head>
<body class="h-full bg-canvas text-ink antialiased">

<div class="min-h-full lg:flex">
    <input type="checkbox" id="nav-toggle" class="peer hidden">
    <label for="nav-toggle"
           class="fixed inset-0 z-30 hidden bg-black/30 peer-checked:block lg:hidden" aria-hidden="true"></label>

    <!-- ============ Sidebar ============ -->
    <aside class="fixed inset-y-0 left-0 z-40 flex w-72 -translate-x-full flex-col border-r border-line bg-white text-ink transition-transform duration-200 peer-checked:translate-x-0 lg:translate-x-0 lg:shrink-0">
        <div class="flex items-center gap-3 border-b border-line px-5 py-5">
            <a href="<?= e($base) ?>/dashboard" class="flex min-w-0 items-center gap-3">
                <img src="/assets/img/sunrise-mark.png" alt="<?= e($appName) ?>" class="h-9 w-9 shrink-0 object-contain">
                <div class="min-w-0 leading-tight">
                    <p class="truncate text-sm font-semibold"><?= e($appName) ?></p>
                    <p class="truncate text-xs text-slate-500"><?= $user !== null ? e($user->roleLabel()) : '' ?> Panel</p>
                </div>
            </a>
        </div>

        <nav class="flex-1 px-3 py-4" aria-label="Panel navigation">
            <?php if ($navItems !== []): ?>
                <p class="px-3 pb-2 text-[11px] font-semibold uppercase tracking-wide text-slate-400">Modules</p>
                <ul class="space-y-1">
                    <?php foreach ($navItems as $item): ?>
                        <?php $active = $currentPath === $item['url'] || str_starts_with($currentPath, $item['url'] . '/'); ?>
                        <li>
                            <a href="<?= e($item['url']) ?>"
                               class="flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition <?= $active
                                   ? 'bg-brand-50 text-brand-700'
                                   : 'text-slate-600 hover:bg-slate-50 hover:text-ink' ?>">
                                <?= PanelModules::icon($item['icon']) ?>
                                <span class="flex-1 truncate"><?= e($item['label']) ?></span>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </nav>

        <?php if ($user !== null): ?>
            <div class="border-t border-line p-4">
                <div class="mb-3 flex items-center gap-3">
                    <?php if ($user->photoUrl() !== null): ?>
                        <img src="<?= e($user->photoUrl()) ?>" alt="" class="h-9 w-9 shrink-0 rounded-full object-cover ring-1 ring-line">
                    <?php else: ?>
                        <span class="grid h-9 w-9 shrink-0 place-items-center rounded-full bg-brand-gradient text-xs font-semibold text-white"><?= e($user->initials()) ?></span>
                    <?php endif; ?>
                    <div class="min-w-0 leading-tight">
                        <p class="truncate text-sm font-medium"><?= e($user->name) ?></p>
                        <p class="truncate text-xs text-slate-500"><?= e($user->roleLabel()) ?></p>
                    </div>
                </div>
                <form method="post" action="<?= e($base) ?>/logout" onsubmit="return confirm('Are you sure you want to log out?');">
                    <?= csrf_field() ?>
                    <button type="submit"
                            class="flex w-full items-center justify-center gap-2 rounded-lg border border-line px-3 py-2 text-sm font-medium text-slate-600 transition hover:bg-slate-50 hover:text-ink">
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                             stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/>
                            <path d="m16 17 5-5-5-5M21 12H9"/>
                        </svg>
                        Log out
                    </button>
                </form>
            </div>
        <?php endif; ?>
    </aside>

    <div class="flex min-h-full flex-1 flex-col lg:ml-72">
        <!-- ============ Top bar ============ -->
        <header class="sticky top-0 z-20 flex items-center gap-4 border-b border-line bg-white px-5 py-3.5 sm:px-8">
            <label for="nav-toggle"
                   class="grid h-9 w-9 shrink-0 cursor-pointer place-items-center rounded-lg border border-slate-200 text-slate-600 lg:hidden">
                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                     stroke-width="1.75" stroke-linecap="round" aria-hidden="true">
                    <path d="M3 6h18M3 12h18M3 18h18"/>
                </svg>
            </label>

            <h1 class="min-w-0 flex-1 truncate text-base font-semibold"><?= e($title) ?></h1>

            <?php if ($user !== null): ?>
                <div class="hidden text-right sm:block">
                    <p class="text-sm font-medium leading-tight"><?= e($user->name) ?></p>
                    <p class="text-xs text-slate-500"><?= e($user->email) ?></p>
                    <p class="text-[11px] text-slate-400">
                        Last sign-in: <?= e(pretty_date($user->lastLoginAt, 'this is your first sign-in')) ?>
                    </p>
                </div>
                <?php if ($user->photoUrl() !== null): ?>
                    <img src="<?= e($user->photoUrl()) ?>" alt="" class="h-9 w-9 shrink-0 rounded-full object-cover ring-1 ring-line">
                <?php else: ?>
                    <span class="grid h-9 w-9 shrink-0 place-items-center rounded-full bg-brand-gradient text-xs font-semibold text-white"><?= e($user->initials()) ?></span>
                <?php endif; ?>
            <?php endif; ?>
        </header>

        <main class="flex-1 px-5 py-8 sm:px-8">
            <div class="mx-auto max-w-6xl">
                <?php foreach ($flash as $type => $message): ?>
                    <?php require BASE_PATH . '/app/Views/partials/flash.php'; ?>
                <?php endforeach; ?>

                <?= $content ?>
            </div>
        </main>
    </div>
</div>
<script src="/assets/js/app.js" defer></script>
</body>
</html>
