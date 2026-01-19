<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class CronHeartbeat extends Model
{
    use HasFactory;

    protected $fillable = [
        'key',
        'last_ran_at',
        'last_output',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'last_ran_at' => 'datetime',
        ];
    }
}
