<?php

namespace App\Models;

use App\Core\Traits\HasAuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Setting extends Model
{
    use HasFactory, HasAuditLog;

    protected $fillable = [
        'key',
        'value',
        'type',
        'group',
        'description',
        'is_public',
        'is_encrypted',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'is_public' => 'boolean',
            'is_encrypted' => 'boolean',
            'metadata' => 'array',
        ];
    }

    /**
     * Get typed value
     */
    public static function getTyped(string $key, $default = null)
    {
        $setting = static::where('key', $key)->first();
        if (!$setting) {
            return $default;
        }

        return match($setting->type) {
            'json' => json_decode($setting->value, true),
            'number', 'float' => (float) $setting->value,
            'integer', 'int' => (int) $setting->value,
            'boolean', 'bool' => filter_var($setting->value, FILTER_VALIDATE_BOOLEAN),
            default => $setting->value,
        };
    }

    /**
     * Set typed value
     */
    public static function setTyped(string $key, $value, string $group = 'general', string $type = 'string', ?string $description = null): Setting
    {
        $serialized = match($type) {
            'json' => json_encode($value),
            'number', 'float', 'integer', 'int', 'boolean', 'bool' => (string) $value,
            default => (string) $value,
        };

        return static::updateOrCreate(
            ['key' => $key],
            [
                'value' => $serialized,
                'type' => $type,
                'group' => $group,
                'description' => $description,
            ]
        );
    }
}

