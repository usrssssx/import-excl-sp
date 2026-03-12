<?php

namespace App\Services\Permissions;

use App\Models\Portal;
use App\Models\PortalUser;
use App\Models\SmartProcessPermission;

class SmartProcessPermissionService
{
    /**
     * @param  array<int, array{entityTypeId:int,title:string}>  $smartProcesses
     * @return array<int, array{entityTypeId:int,title:string}>
     */
    public function filterAllowedProcesses(Portal $portal, PortalUser $user, array $smartProcesses): array
    {
        if ($user->canManagePermissions()) {
            return array_values(array_filter($smartProcesses, function (array $process) use ($portal): bool {
                $permission = SmartProcessPermission::query()
                    ->where('portal_id', $portal->id)
                    ->where('entity_type_id', (int) $process['entityTypeId'])
                    ->first();

                return ! $permission || $permission->is_enabled;
            }));
        }

        $permissions = SmartProcessPermission::query()
            ->where('portal_id', $portal->id)
            ->where('is_enabled', true)
            ->with(['users', 'departments'])
            ->get()
            ->keyBy('entity_type_id');

        $departmentIds = array_map('intval', $user->department_ids ?? []);

        return array_values(array_filter($smartProcesses, static function (array $process) use ($permissions, $user, $departmentIds): bool {
            $permission = $permissions->get((int) $process['entityTypeId']);

            if (! $permission) {
                return false;
            }

            if ($permission->allow_all_users) {
                return true;
            }

            if ($permission->users->contains('bitrix_user_id', $user->bitrix_user_id)) {
                return true;
            }

            if ($departmentIds === []) {
                return false;
            }

            $allowedDepartments = $permission->departments->pluck('department_id')->map(static fn ($value): int => (int) $value);

            return $allowedDepartments->intersect($departmentIds)->isNotEmpty();
        }));
    }

    public function canUpload(PortalUser $user, int $entityTypeId): bool
    {
        if ($user->canManagePermissions()) {
            return true;
        }

        $permission = SmartProcessPermission::query()
            ->where('portal_id', $user->portal_id)
            ->where('entity_type_id', $entityTypeId)
            ->where('is_enabled', true)
            ->with(['users', 'departments'])
            ->first();

        if (! $permission) {
            return false;
        }

        if ($permission->allow_all_users) {
            return true;
        }

        if ($permission->users->contains('bitrix_user_id', $user->bitrix_user_id)) {
            return true;
        }

        $departmentIds = array_map('intval', $user->department_ids ?? []);

        if ($departmentIds === []) {
            return false;
        }

        return $permission->departments
            ->pluck('department_id')
            ->map(static fn ($id): int => (int) $id)
            ->intersect($departmentIds)
            ->isNotEmpty();
    }

    /**
     * @param  array{title:string,is_enabled:bool,allow_all_users:bool,user_ids:array<int,int>,department_ids:array<int,int>}  $payload
     */
    public function savePermission(Portal $portal, int $entityTypeId, array $payload): SmartProcessPermission
    {
        $permission = SmartProcessPermission::query()->firstOrCreate(
            [
                'portal_id' => $portal->id,
                'entity_type_id' => $entityTypeId,
            ],
            [
                'title' => $payload['title'],
            ],
        );

        $permission->fill([
            'title' => $payload['title'],
            'is_enabled' => $payload['is_enabled'],
            'allow_all_users' => $payload['allow_all_users'],
        ])->save();

        $permission->users()->delete();
        foreach ($payload['user_ids'] as $userId) {
            $permission->users()->create(['bitrix_user_id' => $userId]);
        }

        $permission->departments()->delete();
        foreach ($payload['department_ids'] as $departmentId) {
            $permission->departments()->create(['department_id' => $departmentId]);
        }

        return $permission->fresh(['users', 'departments']);
    }
}
