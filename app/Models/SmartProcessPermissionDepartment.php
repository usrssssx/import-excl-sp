<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SmartProcessPermissionDepartment extends Model
{
    use HasFactory;

    protected $fillable = [
        'smart_process_permission_id',
        'department_id',
    ];

    public function permission(): BelongsTo
    {
        return $this->belongsTo(SmartProcessPermission::class, 'smart_process_permission_id');
    }
}
