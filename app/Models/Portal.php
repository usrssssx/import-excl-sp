<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Portal extends Model
{
    use HasFactory;

    protected $fillable = [
        'member_id',
        'domain',
        'protocol',
        'access_token',
        'refresh_token',
        'access_expires_at',
        'application_token',
        'settings',
    ];

    protected function casts(): array
    {
        return [
            'settings' => 'array',
            'access_expires_at' => 'integer',
        ];
    }

    public function users(): HasMany
    {
        return $this->hasMany(PortalUser::class);
    }

    public function permissions(): HasMany
    {
        return $this->hasMany(SmartProcessPermission::class);
    }

    public function importJobs(): HasMany
    {
        return $this->hasMany(ImportJob::class);
    }

    public function baseUrl(): string
    {
        $domain = preg_replace('#^https?://#', '', (string) $this->domain);

        return sprintf('%s://%s', $this->protocol ?: 'https', $domain);
    }
}
