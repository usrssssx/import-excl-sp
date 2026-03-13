<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Concerns\InteractsWithBitrixContext;
use App\Http\Controllers\Controller;
use App\Models\PortalAppAdmin;
use App\Models\PortalUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AppAdminController extends Controller
{
    use InteractsWithBitrixContext;

    public function index(Request $request): View
    {
        $portal = $this->currentPortal($request);
        $user = $this->currentPortalUser($request);

        $this->ensureManager($user->canManagePermissions());

        $appAdmins = PortalAppAdmin::query()
            ->where('portal_id', $portal->id)
            ->orderBy('bitrix_user_id')
            ->get();

        $knownUsers = PortalUser::query()
            ->where('portal_id', $portal->id)
            ->get()
            ->keyBy('bitrix_user_id');

        $systemManagers = PortalUser::query()
            ->where('portal_id', $portal->id)
            ->where(static function ($query): void {
                $query->where('is_admin', true)
                    ->orWhere('is_integrator', true);
            })
            ->orderBy('bitrix_user_id')
            ->get();

        return view('admin.app-admins.index', [
            'portal' => $portal,
            'user' => $user,
            'appAdmins' => $appAdmins,
            'knownUsers' => $knownUsers,
            'systemManagers' => $systemManagers,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $portal = $this->currentPortal($request);
        $user = $this->currentPortalUser($request);

        $this->ensureManager($user->canManagePermissions());

        $validated = $request->validate([
            'bitrix_user_id' => ['required', 'integer', 'min:1'],
        ]);

        $targetUserId = (int) $validated['bitrix_user_id'];

        $appAdmin = PortalAppAdmin::query()->firstOrCreate(
            [
                'portal_id' => $portal->id,
                'bitrix_user_id' => $targetUserId,
            ],
            [
                'granted_by_bitrix_user_id' => $user->bitrix_user_id,
            ],
        );

        if (! $appAdmin->wasRecentlyCreated && $appAdmin->granted_by_bitrix_user_id === null) {
            $appAdmin->forceFill([
                'granted_by_bitrix_user_id' => $user->bitrix_user_id,
            ])->save();
        }

        return back()->with('status', $appAdmin->wasRecentlyCreated
            ? 'Администратор приложения добавлен.'
            : 'Пользователь уже есть в списке администраторов приложения.');
    }

    public function destroy(Request $request, PortalAppAdmin $appAdmin): RedirectResponse
    {
        $portal = $this->currentPortal($request);
        $user = $this->currentPortalUser($request);

        $this->ensureManager($user->canManagePermissions());
        abort_unless($appAdmin->portal_id === $portal->id, 404);

        $isSelfDelete = $appAdmin->bitrix_user_id === $user->bitrix_user_id;
        if ($isSelfDelete && ! $user->is_admin && ! $user->is_integrator) {
            $hasOtherAppAdmins = PortalAppAdmin::query()
                ->where('portal_id', $portal->id)
                ->where('bitrix_user_id', '!=', $user->bitrix_user_id)
                ->exists();

            $hasOtherSystemManagers = PortalUser::query()
                ->where('portal_id', $portal->id)
                ->where('bitrix_user_id', '!=', $user->bitrix_user_id)
                ->where(static function ($query): void {
                    $query->where('is_admin', true)
                        ->orWhere('is_integrator', true);
                })
                ->exists();

            abort_unless(
                $hasOtherAppAdmins || $hasOtherSystemManagers,
                422,
                'Нельзя удалить последнего администратора приложения.',
            );
        }

        $appAdmin->delete();

        return back()->with('status', 'Администратор приложения удалён.');
    }

    private function ensureManager(bool $condition): void
    {
        abort_unless($condition, 403, 'Недостаточно прав.');
    }
}

