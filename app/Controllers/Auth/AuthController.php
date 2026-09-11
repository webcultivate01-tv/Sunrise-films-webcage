<?php

declare(strict_types=1);

namespace App\Controllers\Auth;

use App\Core\Config;
use App\Core\Controller;
use App\Core\Request;
use App\Core\Session;
use App\Core\Validator;
use App\Services\AuthService;
use App\Services\PasswordPolicy;
use App\Services\PasswordResetService;

/**
 * Login, logout and the Forgot Password flow for every role.
 *
 * One controller serves all three entry points: the role comes from the route
 * (spec s2), which is what keeps /admin, /manager and /employee behaving
 * identically apart from the account they will accept.
 */
final class AuthController extends Controller
{
    /**
     * GET /admin, /manager, /employee
     */
    public function showLogin(Request $request, array $params): never
    {
        $role = $params['role'];

        $this->view('auth.login', [
            'role'        => $role,
            'roleConfig'  => Config::role($role),
            'title'       => Config::role($role)['label'] . ' Login',
            'forgotUrl'   => $this->forgotPasswordUrl($role),
            'centered'    => true,
        ], 'auth');
    }

    /**
     * POST /{role}/login
     */
    public function login(Request $request, array $params): never
    {
        $role  = $params['role'];
        $login = (string) Config::role($role)['login'];

        $email    = $request->string('email');
        $password = (string) $request->input('password', '');

        // Spec s4.3 — both fields required, email well formed.
        $validator = new Validator();
        $validator->require('email', $email, 'Please enter your email address.');
        $validator->email('email', $email);
        $validator->require('password', $password, 'Please enter your password.');

        if ($validator->fails()) {
            $this->redirectWithErrors($login, $validator->errors(), ['email' => $email]);
        }

        $result = AuthService::attempt($request, $email, $password, $role);

        if (!$result->succeeded) {
            $this->redirectWithErrors(
                $login,
                [$result->field => $result->message],
                ['email' => $email],
            );
        }

        AuthService::login($result->user, $request);

        $this->redirectWithFlash(
            $result->user->dashboardPath(),
            'success',
            'Welcome back, ' . $result->user->name . '.',
        );
    }

    /**
     * POST /{role}/logout — spec s15.
     */
    public function logout(Request $request, array $params): never
    {
        $role = $params['role'];

        AuthService::logout($request);

        $this->redirectWithFlash(
            (string) Config::role($role)['login'],
            'success',
            'You have been signed out.',
        );
    }

    /**
     * GET /{role}/forgot-password — spec s8 step 1.
     */
    public function showForgotPassword(Request $request, array $params): never
    {
        $role = $params['role'];

        $this->view('auth.forgot-password', [
            'role'       => $role,
            'roleConfig' => Config::role($role),
            'title'      => 'Forgot Password',
            'resetLink'  => Session::pull('_reset_link'),
            'centered'   => true,
        ], 'auth');
    }

    /**
     * POST /{role}/forgot-password — spec s8 steps 2 and 3.
     */
    public function sendResetLink(Request $request, array $params): never
    {
        $role = $params['role'];
        $url  = (string) Config::role($role)['login'] . '/forgot-password';

        $email = $request->string('email');

        $validator = new Validator();
        $validator->require('email', $email, 'Please enter your email address.');
        $validator->email('email', $email);

        if ($validator->fails()) {
            $this->redirectWithErrors($url, $validator->errors(), ['email' => $email]);
        }

        $outcome = PasswordResetService::request($request, $email, $role);

        if (!$outcome['accepted']) {
            $this->redirectWithErrors($url, ['email' => $outcome['message']], ['email' => $email]);
        }

        // Development convenience only: with MAIL_DRIVER=log there is no inbox
        // to open, so the link is shown on the page instead.
        if ($outcome['link'] !== null) {
            Session::put('_reset_link', $outcome['link']);
        }

        $this->redirectWithFlash($url, 'success', $outcome['message']);
    }

    /**
     * GET /{role}/reset-password?token=... — spec s8 step 4.
     */
    public function showResetPassword(Request $request, array $params): never
    {
        $role  = $params['role'];
        $token = $request->string('token');
        $user  = PasswordResetService::authorise($token, $role);

        if ($user === null) {
            $this->redirectWithFlash(
                (string) Config::role($role)['login'] . '/forgot-password',
                'error',
                PasswordResetService::expiredMessage(),
            );
        }

        $this->view('auth.reset-password', [
            'role'       => $role,
            'roleConfig' => Config::role($role),
            'title'      => 'Create a New Password',
            'token'      => $token,
            'email'      => $user->email,
            'policy'     => PasswordPolicy::description(),
            'centered'   => true,
        ], 'auth');
    }

    /**
     * POST /{role}/reset-password — spec s8 steps 5 and 6.
     */
    public function resetPassword(Request $request, array $params): never
    {
        $role    = $params['role'];
        $token   = $request->string('token');
        $current = (string) Config::role($role)['login'] . '/reset-password?token=' . rawurlencode($token);

        $password     = (string) $request->input('password', '');
        $confirmation = (string) $request->input('password_confirmation', '');

        $validator = new Validator();
        PasswordPolicy::validate($validator, $password, $confirmation);

        if ($validator->fails()) {
            $this->redirectWithErrors($current, $validator->errors());
        }

        if (!PasswordResetService::complete($token, $role, $password)) {
            $this->redirectWithFlash(
                (string) Config::role($role)['login'] . '/forgot-password',
                'error',
                PasswordResetService::expiredMessage(),
            );
        }

        $this->redirectWithFlash(
            (string) Config::role($role)['login'],
            'success',
            'Your password has been reset. You can now sign in with your new password.',
        );
    }

    private function forgotPasswordUrl(string $role): ?string
    {
        if ($role === 'admin' && Config::get('auth.admin_forgot_password') !== true) {
            return null;
        }

        return (string) Config::role($role)['login'] . '/forgot-password';
    }
}
