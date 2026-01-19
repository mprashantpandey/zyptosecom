<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class AppVersion extends Model
{
    use HasFactory;

    protected $fillable = [
        'platform',
        'latest_version',
        'latest_build',
        'min_version',
        'min_build',
        'update_type',
        'update_message',
        'store_url',
        'download_url',
        'is_minimum_supported',
        'maintenance_mode',
        'maintenance_message',
        'released_at',
    ];

    protected function casts(): array
    {
        return [
            'released_at' => 'datetime',
            'is_minimum_supported' => 'boolean',
            'maintenance_mode' => 'boolean',
        ];
    }

    /**
     * Get version for platform
     */
    public static function getForPlatform(string $platform): ?self
    {
        return static::where('platform', $platform)
            ->latest('created_at')
            ->first();
    }
}

