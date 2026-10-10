<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Config;
use App\Core\Controller;
use App\Core\Request;
use App\Services\DataPurgeService;
use App\Services\PasswordPolicy;

/**
 * Admin-only: permanently delete one module's data, or the whole system's.
 * Deletion needs the admin's password and a typed confirmation, and cannot be
 * undone.
 */
final class DataManagementController extends Controller
{
    /**
     * @param array<string, string> $params
     */
    public function index(Request $request, array $params): never
    {
        $user = $this->user();

        $this->view('data-management', [
            'title'   => 'Data Management',
            'base'    => (string) Config::get('roles.' . $user->role . '.login'),
            'modules' => DataPurgeService::modules(),
            'counts'  => DataPurgeService::counts(),
        ], 'panel');
    }

    /**
     * POST /admin/data-management
     *
     * @param array<string, string> $params
     */
    public function destroy(Request $request, array $params): never
    {
        $user = $this->user();
        $url  = (string) Config::get('roles.' . $user->role . '.login') . '/data-management';

        $module       = (string) $request->input('module', '');
        $confirmation = trim((string) $request->input('confirmation', ''));
        $password     = (string) $request->input('password', '');

        $errors = [];

        if (!DataPurgeService::exists($module)) {
            $errors['module'] = 'Please choose what to delete.';
        }

        if ($confirmation !== 'DELETE') {
            $errors['confirmation'] = 'Type DELETE in capital letters to confirm.';
        }

        if ($password === '' || !PasswordPolicy::verify($password, $user->passwordHash)) {
            $errors['password'] = 'Your password is incorrect.';
        }

        if ($errors !== []) {
            $this->redirectWithErrors($url, $errors, ['module' => $module]);
        }

        $label   = DataPurgeService::modules()[$module]['label'];
        $deleted = DataPurgeService::purge($module);

        $this->redirectWithFlash(
            $url,
            'success',
            sprintf('%s data deleted permanently (%d %s removed).', $label, $deleted, $deleted === 1 ? 'record' : 'records'),
        );
    }
}
