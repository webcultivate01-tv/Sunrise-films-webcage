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
 * undone. An optional date range narrows the deletion to records created
 * within that window.
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
        $from         = trim((string) $request->input('date_from', ''));
        $to           = trim((string) $request->input('date_to', ''));
        $useRange     = $from !== '' || $to !== '';

        $errors = [];

        if (!DataPurgeService::exists($module)) {
            $errors['module'] = 'Please choose what to delete.';
        }

        if ($useRange) {
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) {
                $errors['date_from'] = 'Enter a valid from date.';
            }
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) {
                $errors['date_to'] = 'Enter a valid to date.';
            }
            if (!isset($errors['date_from'], $errors['date_to']) && $from > $to) {
                $errors['date_to'] = 'To date must be on or after the from date.';
            }
        }

        if ($confirmation !== 'DELETE') {
            $errors['confirmation'] = 'Type DELETE in capital letters to confirm.';
        }

        if ($password === '' || !PasswordPolicy::verify($password, $user->passwordHash)) {
            $errors['password'] = 'Your password is incorrect.';
        }

        if ($errors !== []) {
            $this->redirectWithErrors($url, $errors, array_filter([
                'module'    => $module,
                'date_from' => $from,
                'date_to'   => $to,
            ]));
        }

        $label = DataPurgeService::modules()[$module]['label'];

        if ($useRange) {
            $deleted = DataPurgeService::purgeByDateRange($module, $from, $to);
            $message = sprintf(
                '%s data from %s to %s deleted permanently (%d %s removed).',
                $label, $from, $to, $deleted, $deleted === 1 ? 'record' : 'records',
            );
        } else {
            $deleted = DataPurgeService::purge($module);
            $message = sprintf(
                '%s data deleted permanently (%d %s removed).',
                $label, $deleted, $deleted === 1 ? 'record' : 'records',
            );
        }

        $this->redirectWithFlash($url, 'success', $message);
    }
}
