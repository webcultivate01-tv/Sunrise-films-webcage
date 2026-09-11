<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Support\PanelModules;

/**
 * Modules the sidebar links to that have not been built yet (spec roadmap in
 * the retired static prototype). Renders a "not built yet" placeholder inside
 * the real, authenticated panel shell.
 */
final class ModuleController extends Controller
{
    /**
     * @param array<string, string> $params
     */
    public function placeholder(Request $request, array $params): never
    {
        $module = PanelModules::module(basename($request->path()));

        $this->view('modules.placeholder', [
            'title'  => $module['label'],
            'module' => $module,
        ], 'panel');
    }
}
