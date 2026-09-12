<?php

declare(strict_types=1);

use App\Core\Config;
use App\Core\Exceptions\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Router;
use App\Core\Session;
use App\Core\View;
use App\Middleware\Authenticate;
use App\Middleware\AuthorizeRoles;
use App\Middleware\RedirectIfAuthenticated;
use App\Middleware\VerifyCsrfToken;
use App\Models\AuthToken;
use App\Models\LoginAttempt;
use App\Models\PasswordReset;
use App\Services\AuthService;

define('BASE_PATH', dirname(__DIR__));

// Let the PHP built-in server hand back real files (CSS, JS, images) itself.
if (PHP_SAPI === 'cli-server') {
    $file = __DIR__ . parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);

    if ($file !== __DIR__ . '/' && is_file($file)) {
        return false;
    }
}

require BASE_PATH . '/app/bootstrap.php';

$request = Request::capture();

Response::applySecurityHeaders();
Session::start($request->isSecure());

// Resolve the authenticated identity once, from the token (spec s13), so views
// and controllers all see the same user.
AuthService::resolve($request);

$router = new Router();
$router->registerMiddleware([
    'auth'  => Authenticate::class,
    'guest' => RedirectIfAuthenticated::class,
    'csrf'  => VerifyCsrfToken::class,
    'roles' => AuthorizeRoles::class,
]);

/** @var callable(Router):void $routes */
$routes = require BASE_PATH . '/routes/web.php';
$routes($router);

// Occasional housekeeping so expired tokens and stale attempt records do not
// accumulate. Cheap, and keeps the install free of a cron dependency.
if (random_int(1, 200) === 1) {
    AuthToken::purgeExpired();
    PasswordReset::purgeExpired();
    LoginAttempt::purgeOld();
}

try {
    $router->dispatch($request);
} catch (HttpException $e) {
    Response::html(
        View::render('errors.error', [
            'appName' => (string) Config::get('app.name'),
            'title'   => 'Error ' . $e->status(),
            'status'  => $e->status(),
            'message' => $e->getMessage(),
            'user'    => AuthService::user(),
        ], 'auth'),
        $e->status(),
    );
} catch (Throwable $e) {
    error_log((string) $e);

    $debug = Config::get('app.debug') === true;

    Response::html(
        View::render('errors.error', [
            'appName' => (string) Config::get('app.name'),
            'title'   => 'Something went wrong',
            'status'  => 500,
            'message' => $debug
                ? $e->getMessage() . ' (' . $e->getFile() . ':' . $e->getLine() . ')'
                : 'Something went wrong on our side. Please try again.',
            'user'    => AuthService::user(),
        ], 'auth'),
        500,
    );
}
