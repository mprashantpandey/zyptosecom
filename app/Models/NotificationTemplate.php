<?php

namespace App\Models;

use App\Core\Traits\HasAuditLog;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class NotificationTemplate extends Model
{
    use HasFactory, HasAuditLog;

    protected $fillable = [
        'channel',
        'name',
        'subject',
        'body',
        'variables',
        'locale',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'variables' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function eventChannels(): HasMany
    {
        return $this->hasMany(NotificationEventChannel::class, 'template_id');
    }
}
