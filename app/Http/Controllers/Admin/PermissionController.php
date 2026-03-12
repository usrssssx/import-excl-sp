<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\InteractsWithBitrixContext;
use App\Http\Controllers\Controller;
use App\Models\SmartProcessPermission;
use App\Services\Bitrix24\Bitrix24Service;
use App\Services\Permissions\SmartProcessPermissionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PermissionController extends Controller
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

        $this->ensureManager($user->canManagePermissions());

        $smartProcesses = $this->bitrix24->listSmartProcesses($portal);

        $permissionMap = SmartProcessPermission::query()
            ->where('portal_id', $portal->id)
            ->with(['users', 'departments'])
            ->get()
            ->keyBy('entity_type_id');

        return view('admin.permissions.index', [
            'portal' => $portal,
            'user' => $user,
            'smartProcesses' => $smartProcesses,
            'permissionMap' => $permissionMap,
        ]);
    }

    public function update(Request $request, int $entityTypeId): RedirectResponse
    {
        $portal = $this->currentPortal($request);
        $user = $this->currentPortalUser($request);

        $this->ensureManager($user->canManagePermissions());

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'is_enabled' => ['nullable', 'boolean'],
            'allow_all_users' => ['nullable', 'boolean'],
            'user_ids' => ['nullable', 'string', 'max:1000'],
            'department_ids' => ['nullable', 'string', 'max:1000'],
        ]);

        $this->permissions->savePermission($portal, $entityTypeId, [
            'title' => $validated['title'],
            'is_enabled' => (bool) ($validated['is_enabled'] ?? false),
            'allow_all_users' => (bool) ($validated['allow_all_users'] ?? false),
            'user_ids' => $this->parseIntegerCsv($validated['user_ids'] ?? '', 'user_ids'),
            'department_ids' => $this->parseIntegerCsv($validated['department_ids'] ?? '', 'department_ids'),
        ]);

        return back()->with('status', 'Настройки прав сохранены.');
    }

    /**
     * @return array<int,int>
     */
    private function parseIntegerCsv(string $value, string $field): array
    {
        $items = array_filter(array_map(static fn (string $part): string => trim($part), explode(',', $value)));
        $numbers = [];

        foreach ($items as $item) {
            if (! ctype_digit($item)) {
                throw ValidationException::withMessages([
                    $field => 'Используйте формат: числа через запятую.',
                ]);
            }

            $numbers[] = (int) $item;
        }

        return array_values(array_unique($numbers));
    }

    private function ensureManager(bool $condition): void
    {
        abort_unless($condition, 403, 'Недостаточно прав.');
    }
}
