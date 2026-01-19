<?php

namespace App\Models;

use App\Core\Traits\HasAuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class Secret extends Model
{
    use HasFactory, HasAuditLog, SoftDeletes;

    protected $fillable = [
        'provider_type',
        'provider_name',
        'key',
        'encrypted_value',
        'environment',
        'is_active',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'metadata' => 'array',
        ];
    }
}

