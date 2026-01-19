<?php

namespace App\Models;

use App\Core\Traits\HasAuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Payment extends Model
{
    use HasFactory, HasAuditLog;

    protected $fillable = [
        'order_id',
        'transaction_id',
        'provider',
        'provider_transaction_id',
        'method',
        'status',
        'amount',
        'currency',
        'provider_response',
        'metadata',
        'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'paid_at' => 'datetime',
            'provider_response' => 'array',
            'metadata' => 'array',
        ];
    }

    /**
     * Get order
     */
    public function order()
    {
        return $this->belongsTo(Order::class);
    }
}

