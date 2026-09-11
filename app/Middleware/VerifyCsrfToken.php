<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Csrf;
use App\Core\Middleware;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;

/**
 * Every state-changing form in the app carries a CSRF token. Because
 * authentication rides in a cookie, this is what stops another site from
 * submitting to these endpoints on a signed-in user's behalf.
 */
final class VerifyCsrfToken implements Middleware
{
    /**
     * @param array<string, string> $params
     */
    public function handle(Request $request, array $params, ?string $argument = null): void
    {
        if (in_array($request->method(), ['GET', 'HEAD', 'OPTIONS'], true)) {
            return;
        }

        $token = $request->input('_token');

        if (Csrf::check(is_string($token) ? $token : null)) {
            return;
        }

        Session::flash('error', 'Your form session expired. Please try again.');

        Response::redirect($this->backTo($request));
    }

    /**
     * Send the user back where they came from, but only within this site.
     */
    private function backTo(Request $request): string
    {
        $referer = $request->header('Referer');

        if (is_string($referer)) {
            $path = parse_url($referer, PHP_URL_PATH);

            if (is_string($path) && str_starts_with($path, '/')) {
                return $path;
            }
        }

        return '/';
    }
}
