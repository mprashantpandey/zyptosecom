<?php

namespace App\Models;

use App\Core\Traits\HasAuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class TaxRule extends Model
{
    use HasFactory, HasAuditLog;

    protected $fillable = [
        'name',
        'priority',
        'country',
        'state',
        'category_id',
        'min_price',
        'max_price',
        'apply_type',
        'tax_rate_id',
        'cgst_rate',
        'sgst_rate',
        'igst_rate',
        'is_active',
        'starts_at',
        'ends_at',
        'description',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'priority' => 'integer',
            'is_active' => 'boolean',
            'min_price' => 'decimal:2',
            'max_price' => 'decimal:2',
            'cgst_rate' => 'decimal:3',
            'sgst_rate' => 'decimal:3',
            'igst_rate' => 'decimal:3',
            'starts_at' => 'date',
            'ends_at' => 'date',
        ];
    }

    public function taxRate()
    {
        return $this->belongsTo(TaxRate::class, 'tax_rate_id');
    }

    public function category()
    {
        return $this->belongsTo(\App\Models\Category::class);
    }
}
