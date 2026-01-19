<?php

namespace App\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class IntegrationTest extends Model
{
    use HasFactory;

    protected $fillable = [
        'provider_id',
        'provider_key',
        'status',
        'message',
        'response',
        'tested_at',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'response' => 'array',
            'tested_at' => 'datetime',
        ];
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(Provider::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
