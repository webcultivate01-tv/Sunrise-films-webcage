<?php
/**
 * Layout for the unauthenticated pages: the three login screens, the Forgot
 * Password flow, the landing page and error pages.
 *
 * @var string $title
 * @var string $appName
 * @var string $content
 * @var bool|null $centered Whether to drop the brand panel and centre the
 *                          content alone on a plain white page (spec: the
 *                          three role login screens).
 */
$flash    = $flash ?? [];
$centered = $centered ?? false;
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
<body class="h-full bg-white text-ink antialiased">
    <?php if ($centered): ?>
        <div class="flex min-h-full items-center justify-center px-5 py-12 sm:px-10">
            <div class="w-full max-w-md">
                <?php foreach ($flash as $type => $message): ?>
                    <?php require BASE_PATH . '/app/Views/partials/flash.php'; ?>
                <?php endforeach; ?>

                <?= $content ?>
            </div>
        </div>
    <?php else: ?>
    <div class="min-h-full lg:grid lg:grid-cols-2">
        <!-- Brand panel -->
        <aside class="relative hidden lg:flex flex-col justify-between overflow-hidden bg-gradient-to-br from-brand-600 via-brand-500 to-violet-500 p-12 text-white">
            <div class="pointer-events-none absolute -right-24 -top-24 h-96 w-96 rounded-full bg-white/10"></div>
            <div class="pointer-events-none absolute -bottom-32 -left-16 h-96 w-96 rounded-full bg-black/10"></div>

            <div class="relative">
                <div class="flex items-center gap-3">
                    <span class="grid h-11 w-11 place-items-center rounded-xl bg-white/20 text-lg font-bold ring-1 ring-white/40">SF</span>
                    <span class="text-lg font-semibold tracking-tight"><?= e($appName) ?></span>
                </div>
            </div>

            <div class="relative max-w-md">
                <h1 class="text-4xl font-semibold leading-tight tracking-tight">Every role, its own door.</h1>
                <p class="mt-4 text-white/80">
                    Admin, Manager and Employee each sign in through a separate entry point,
                    and each one reaches only the panel it is entitled to.
                </p>
            </div>

            <p class="relative text-sm text-white/70">&copy; <?= date('Y') ?> <?= e($appName) ?>. Authorised access only.</p>
        </aside>

        <!-- Content -->
        <main class="flex min-h-full items-center justify-center px-5 py-12 sm:px-10">
            <div class="w-full max-w-md">
                <div class="mb-8 flex items-center gap-3 lg:hidden">
                    <span class="grid h-10 w-10 place-items-center rounded-xl bg-brand-gradient text-sm font-bold text-white">SF</span>
                    <span class="text-base font-semibold tracking-tight"><?= e($appName) ?></span>
                </div>

                <?php foreach ($flash as $type => $message): ?>
                    <?php require BASE_PATH . '/app/Views/partials/flash.php'; ?>
                <?php endforeach; ?>

                <?= $content ?>
            </div>
        </main>
    </div>
    <?php endif; ?>
    <script src="/assets/js/app.js" defer></script>
</body>
</html>
