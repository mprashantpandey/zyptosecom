<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RuntimeFlag extends Model
{
    use HasFactory;

    protected $fillable = [
        'platform',
        'maintenance_enabled',
        'maintenance_message',
        'maintenance_starts_at',
        'maintenance_ends_at',
        'kill_switch_enabled',
        'kill_switch_message',
        'kill_switch_until',
    ];

    protected function casts(): array
    {
        return [
            'maintenance_enabled' => 'boolean',
            'kill_switch_enabled' => 'boolean',
            'maintenance_starts_at' => 'datetime',
            'maintenance_ends_at' => 'datetime',
            'kill_switch_until' => 'datetime',
        ];
    }

    /**
     * Get runtime flag for platform
     */
    public static function getForPlatform(string $platform): ?self
    {
        return static::where('platform', $platform)
            ->orWhere('platform', 'all')
            ->first();
    }
}
