<?php

namespace App\Models;

use App\Core\Traits\HasAuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class Module extends Model
{
    use HasFactory, HasAuditLog, SoftDeletes;

    protected $fillable = [
        'name',
        'label',
        'description',
        'version',
        'is_enabled',
        'platforms',
        'min_app_version',
        'enabled_at',
        'disabled_at',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'is_enabled' => 'boolean',
            'platforms' => 'array',
            'enabled_at' => 'datetime',
            'disabled_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    /**
     * Get module rules
     */
    public function rules()
    {
        return $this->hasMany(ModuleRule::class);
    }
}

