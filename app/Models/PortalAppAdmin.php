<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PortalAppAdmin extends Model
{
    use HasFactory;

    protected $fillable = [
        'portal_id',
        'bitrix_user_id',
        'granted_by_bitrix_user_id',
    ];

    public function portal(): BelongsTo
    {
        return $this->belongsTo(Portal::class);
    }
}

