<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class OrderTax extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'taxable_amount',
        'tax_amount',
        'breakdown',
        'applied_rule_id',
        'applied_rate_id',
    ];

    protected function casts(): array
    {
        return [
            'taxable_amount' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'breakdown' => 'array',
        ];
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function appliedRule()
    {
        return $this->belongsTo(TaxRule::class, 'applied_rule_id');
    }

    public function appliedRate()
    {
        return $this->belongsTo(TaxRate::class, 'applied_rate_id');
    }
}
