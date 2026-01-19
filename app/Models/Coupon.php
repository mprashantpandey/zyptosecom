<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Coupon extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'title',
        'description',
        'type',
        'value',
        'min_order_amount',
        'max_discount_amount',
        'usage_limit_total',
        'usage_limit_per_user',
        'used_count',
        'starts_at',
        'ends_at',
        'is_active',
        'applies_to',
        'applicable_categories',
        'applicable_products',
        'rules_json',
        'metadata',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'decimal:2',
            'min_order_amount' => 'decimal:2',
            'max_discount_amount' => 'decimal:2',
            'usage_limit_total' => 'integer',
            'usage_limit_per_user' => 'integer',
            'used_count' => 'integer',
            'is_active' => 'boolean',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'applicable_categories' => 'array',
            'applicable_products' => 'array',
            'rules_json' => 'array',
            'metadata' => 'array',
        ];
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function getStatusAttribute(): string
    {
        if (!$this->is_active) {
            return 'inactive';
        }

        $now = now();
        if ($this->starts_at && $now->lt($this->starts_at)) {
            return 'upcoming';
        }
        if ($this->ends_at && $now->gt($this->ends_at)) {
            return 'expired';
        }
        return 'running';
    }

    public function canBeUsed(): bool
    {
        return $this->status === 'running' 
            && ($this->usage_limit_total === null || $this->used_count < $this->usage_limit_total);
    }
}
