<?php

declare(strict_types=1);

use App\Core\Config;

/**
 * PSR-4 style autoloading for the App\ namespace, without Composer.
 */
spl_autoload_register(static function (string $class): void {
    $prefix = 'App\\';

    if (!str_starts_with($class, $prefix)) {
        return;
    }

    $relative = substr($class, strlen($prefix));
    $path     = BASE_PATH . '/app/' . str_replace('\\', '/', $relative) . '.php';

    if (is_file($path)) {
        require $path;
    }
});

require BASE_PATH . '/app/Helpers/functions.php';

Config::load(BASE_PATH . '/config/config.php');

date_default_timezone_set('Asia/Kolkata');
mb_internal_encoding('UTF-8');

$debug = Config::get('app.debug') === true;

error_reporting(E_ALL);
ini_set('display_errors', $debug ? '1' : '0');
ini_set('log_errors', '1');
ini_set('error_log', BASE_PATH . '/storage/logs/php-error.log');
