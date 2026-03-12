<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SmartProcessPermission extends Model
{
    use HasFactory;

    protected $fillable = [
        'portal_id',
        'entity_type_id',
        'title',
        'is_enabled',
        'allow_all_users',
    ];

    protected function casts(): array
    {
        return [
            'is_enabled' => 'boolean',
            'allow_all_users' => 'boolean',
        ];
    }

    public function portal(): BelongsTo
    {
        return $this->belongsTo(Portal::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(SmartProcessPermissionUser::class);
    }

    public function departments(): HasMany
    {
        return $this->hasMany(SmartProcessPermissionDepartment::class);
    }
}
