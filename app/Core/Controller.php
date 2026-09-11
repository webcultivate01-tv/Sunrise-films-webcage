<?php

declare(strict_types=1);

namespace App\Core;

use App\Models\User;
use App\Services\AuthService;

abstract class Controller
{
    /**
     * Render a view inside a layout and send it.
     *
     * @param array<string, mixed> $data
     */
    protected function view(string $template, array $data = [], string $layout = 'app'): never
    {
        $data += [
            'appName'    => (string) Config::get('app.name'),
            'authUser'   => AuthService::user(),
            'flash'      => Session::pullFlash(),
            'errors'     => Session::pullErrors(),
            'old'        => Session::pullOld(),
            'title'      => (string) Config::get('app.name'),
        ];

        Response::html(View::render($template, $data, $layout));
    }

    protected function redirect(string $path): never
    {
        Response::redirect($path);
    }

    /**
     * Send the user back to $path with validation errors and their input.
     *
     * @param array<string, string> $errors
     * @param array<string, string> $input
     */
    protected function redirectWithErrors(string $path, array $errors, array $input = []): never
    {
        Session::flashErrors($errors, $input);

        Response::redirect($path);
    }

    protected function redirectWithFlash(string $path, string $type, string $message): never
    {
        Session::flash($type, $message);

        Response::redirect($path);
    }

    /**
     * The authenticated user, guaranteed present behind the `auth` middleware.
     */
    protected function user(): User
    {
        $user = AuthService::user();

        if ($user === null) {
            throw new \RuntimeException('No authenticated user on a protected route.');
        }

        return $user;
    }
}
