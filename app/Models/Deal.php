<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Deal extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'type',
        'starts_at',
        'ends_at',
        'is_active',
        'priority',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'priority' => 'integer',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }

    public function items()
    {
        return $this->hasMany(DealItem::class);
    }

    public function products()
    {
        return $this->belongsToMany(Product::class, 'deal_items')
            ->withPivot('deal_price', 'stock_limit', 'sort_order')
            ->withTimestamps();
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
}
