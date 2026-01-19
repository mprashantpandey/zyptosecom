<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class DealItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'deal_id',
        'product_id',
        'deal_price',
        'stock_limit',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'deal_price' => 'decimal:2',
            'stock_limit' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    public function deal()
    {
        return $this->belongsTo(Deal::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
