<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Schema;

class PortalUser extends Model
{
    use HasFactory;

    private static ?bool $hasPortalAppAdminsTable = null;

    protected $fillable = [
        'portal_id',
        'bitrix_user_id',
        'name',
        'is_admin',
        'is_integrator',
        'department_ids',
        'last_seen_at',
    ];

    protected function casts(): array
    {
        return [
            'is_admin' => 'boolean',
            'is_integrator' => 'boolean',
            'department_ids' => 'array',
            'last_seen_at' => 'datetime',
        ];
    }

    public function portal(): BelongsTo
    {
        return $this->belongsTo(Portal::class);
    }

    public function canManagePermissions(): bool
    {
        if ($this->is_admin || $this->is_integrator) {
            return true;
        }

        if (self::$hasPortalAppAdminsTable === null) {
            self::$hasPortalAppAdminsTable = Schema::hasTable('portal_app_admins');
        }

        if (! self::$hasPortalAppAdminsTable) {
            return false;
        }

        return PortalAppAdmin::query()
            ->where('portal_id', $this->portal_id)
            ->where('bitrix_user_id', $this->bitrix_user_id)
            ->exists();
    }
}
