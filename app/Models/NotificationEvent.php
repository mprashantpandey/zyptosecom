<?php

namespace App\Models;

use App\Core\Traits\HasAuditLog;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class NotificationEvent extends Model
{
    use HasFactory, HasAuditLog;

    protected $fillable = [
        'key',
        'name',
        'description',
        'is_system',
        'is_critical',
    ];

    protected function casts(): array
    {
        return [
            'is_system' => 'boolean',
            'is_critical' => 'boolean',
        ];
    }

    public function channels(): HasMany
    {
        return $this->hasMany(NotificationEventChannel::class);
    }
}
