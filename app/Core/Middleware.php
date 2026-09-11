<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Middleware runs before the controller. It either lets the request through or
 * ends it (redirect / HttpException).
 *
 * @param array<string, string> $params Route parameters plus route defaults.
 */
interface Middleware
{
    public function handle(Request $request, array $params, ?string $argument = null): void;
}
