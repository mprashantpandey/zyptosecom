<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class AuditLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'auditable_type',
        'auditable_id',
        'user_id',
        'event',
        'action_type',
        'description',
        'old_values',
        'new_values',
        'ip_address',
        'user_agent',
        'module',
    ];

    protected function casts(): array
    {
        return [
            'old_values' => 'array',
            'new_values' => 'array',
        ];
    }

    /**
     * Get the model that owns this audit log
     */
    public function auditable()
    {
        return $this->morphTo();
    }

    /**
     * Get the user who made this change
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}

