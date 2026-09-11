<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Config;
use App\Core\Controller;
use App\Core\Request;
use App\Core\Validator;
use App\Services\AuthService;
use App\Services\PasswordPolicy;
use App\Services\PhotoUploadService;
use App\Services\UserService;

/**
 * A signed-in user's own account: the one place any role, Employee included,
 * can change their own password without involving a superior.
 */
final class ProfileController extends Controller
{
    /**
     * @param array<string, string> $params
     */
    public function show(Request $request, array $params): never
    {
        $user = $this->user();

        $this->view('profile', [
            'title'  => 'My Account',
            'policy' => PasswordPolicy::description(),
            'base'   => (string) Config::get('roles.' . $user->role . '.login'),
        ], 'panel');
    }

    /**
     * POST /{panel}/profile
     *
     * @param array<string, string> $params
     */
    public function updateDetails(Request $request, array $params): never
    {
        $user  = $this->user();
        $url   = (string) Config::get('roles.' . $user->role . '.login') . '/profile';
        $input = $request->only(['name', 'email', 'phone']);

        $validator = UserService::validateOwnProfile($user, $input);

        if ($validator->fails()) {
            $this->redirectWithErrors($url, $validator->errors(), $input);
        }

        UserService::updateOwnProfile($user, $input);

        $this->redirectWithFlash($url, 'success', 'Your details have been updated.');
    }

    /**
     * POST /{panel}/profile/photo
     *
     * @param array<string, string> $params
     */
    public function updatePhoto(Request $request, array $params): never
    {
        $user = $this->user();
        $url  = (string) Config::get('roles.' . $user->role . '.login') . '/profile';

        $file = $request->file('photo');

        if ($file === null) {
            $this->redirectWithErrors($url, ['photo' => 'Please choose an image to upload.']);
        }

        $error = PhotoUploadService::validate($file);

        if ($error !== null) {
            $this->redirectWithErrors($url, ['photo' => $error]);
        }

        UserService::updateOwnPhoto($user, PhotoUploadService::store($file));

        $this->redirectWithFlash($url, 'success', 'Your profile photo has been updated.');
    }

    /**
     * POST /{panel}/profile/password
     *
     * @param array<string, string> $params
     */
    public function updatePassword(Request $request, array $params): never
    {
        $user = $this->user();
        $url  = (string) Config::get('roles.' . $user->role . '.login') . '/profile';

        $current      = (string) $request->input('current_password', '');
        $password     = (string) $request->input('password', '');
        $confirmation = (string) $request->input('password_confirmation', '');

        $validator = new Validator();
        $validator->require('current_password', $current, 'Please enter your current password.');

        if ($current !== '' && !PasswordPolicy::verify($current, $user->passwordHash)) {
            $validator->add('current_password', 'Your current password is incorrect.');
        }

        PasswordPolicy::validate($validator, $password, $confirmation);

        if ($validator->passes() && $current !== '' && hash_equals($current, $password)) {
            $validator->add('password', 'Please choose a password you have not used here before.');
        }

        if ($validator->fails()) {
            $this->redirectWithErrors($url, $validator->errors());
        }

        // Changing a password revokes every token for the account, so the
        // browser doing the change needs a new one to stay signed in.
        UserService::changeOwnPassword($user, $password);
        AuthService::login($user, $request);

        $this->redirectWithFlash($url, 'success', 'Your password has been updated.');
    }
}
