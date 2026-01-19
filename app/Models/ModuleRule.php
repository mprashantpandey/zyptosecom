<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ModuleRule extends Model
{
    use HasFactory;

    protected $fillable = [
        'module_id',
        'rule_type',
        'rule_key',
        'rule_value',
        'conditions',
        'starts_at',
        'ends_at',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'conditions' => 'array',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Get module
     */
    public function module()
    {
        return $this->belongsTo(Module::class);
    }
}

