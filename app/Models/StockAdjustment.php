<?php

namespace App\Models;

use App\Core\Traits\HasAuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class StockAdjustment extends Model
{
    use HasFactory, HasAuditLog;

    protected $fillable = [
        'adjustment_number',
        'warehouse_id',
        'type',
        'reason',
        'status',
        'created_by',
        'approved_by',
        'approved_at',
        'completed_at',
        'items',
    ];

    protected function casts(): array
    {
        return [
            'items' => 'array',
            'approved_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
