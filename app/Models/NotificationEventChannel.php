<?php

namespace App\Models;

use App\Core\Traits\HasAuditLog;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotificationEventChannel extends Model
{
    use HasFactory, HasAuditLog;

    protected $fillable = [
        'notification_event_id',
        'channel',
        'enabled',
        'quiet_hours_respect',
        'template_id',
    ];

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'quiet_hours_respect' => 'boolean',
        ];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(NotificationEvent::class);
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(NotificationTemplate::class, 'template_id');
    }
}
