<?php

/**
 * A bare, print-friendly shell for a single printable document (project
 * Invoice/Bill) - no sidebar, no panel chrome, just the document itself
 * centered on the page. The "Back" link and "Download PDF" button sit outside
 * the printable card and are hidden via @media print.
 *
 * @var string $title
 * @var string $appName
 * @var string $content
 */
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
    <style>
        @media print {
            /* A zero page margin leaves the browser no room to draw its own
               date/title header and URL/page-number footer, so they don't
               appear - the printable card supplies its own spacing instead. */
            @page { margin: 0; }
            .print\:hidden { display: none !important; }
            body { background: #fff !important; }
            .invoice-shell { padding: 12mm !important; }
            .invoice-card { box-shadow: none !important; margin: 0 auto !important; }
        }
    </style>
</head>
<body class="min-h-full bg-canvas text-ink antialiased">
    <div class="invoice-shell mx-auto max-w-3xl px-4 py-8 sm:px-6">
        <?= $content ?>
    </div>
</body>
</html>
