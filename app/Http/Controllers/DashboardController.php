<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\InteractsWithBitrixContext;
use App\Models\ImportJob;
use App\Services\Bitrix24\Bitrix24Service;
use App\Services\Permissions\SmartProcessPermissionService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    use InteractsWithBitrixContext;

    public function __construct(
        private readonly Bitrix24Service $bitrix24,
        private readonly SmartProcessPermissionService $permissions,
    ) {
    }

    public function index(Request $request): View
    {
        $portal = $this->currentPortal($request);
        $user = $this->currentPortalUser($request);

        $smartProcesses = [];
        $loadError = null;

        try {
            $allProcesses = $this->bitrix24->listSmartProcesses($portal);
            $smartProcesses = $this->permissions->filterAllowedProcesses($portal, $user, $allProcesses);
        } catch (\Throwable $exception) {
            $loadError = $exception->getMessage();
        }

        $recentImports = ImportJob::query()
            ->where('portal_id', $portal->id)
            ->when(! $user->canManagePermissions(), static function ($query) use ($user) {
                $query->where('bitrix_user_id', $user->bitrix_user_id);
            })
            ->latest()
            ->limit(15)
            ->get();

        return view('dashboard.index', [
            'portal' => $portal,
            'user' => $user,
            'smartProcesses' => $smartProcesses,
            'recentImports' => $recentImports,
            'loadError' => $loadError,
        ]);
    }
}
