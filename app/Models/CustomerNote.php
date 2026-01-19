<?php

namespace App\Models;

use App\Core\Traits\HasAuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class CustomerNote extends Model
{
    use HasFactory, HasAuditLog;

    protected $fillable = [
        'user_id',
        'note',
        'tags',
        'is_important',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'tags' => 'array',
            'is_important' => 'boolean',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
