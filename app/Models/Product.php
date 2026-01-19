<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'sku',
        'slug',
        'description',
        'short_description',
        'category_id',
        'brand_id',
        'price',
        'compare_at_price',
        'cost_price',
        'currency',
        'stock_quantity',
        'stock_status',
        'track_inventory',
        'low_stock_threshold',
        'weight',
        'weight_unit',
        'is_active',
        'is_featured',
        'images',
        'attributes',
        'variants',
        'metadata',
        'tax_category',
        'tax_rate_id',
        'tax_override',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'compare_at_price' => 'decimal:2',
            'cost_price' => 'decimal:2',
            'weight' => 'decimal:2',
            'stock_quantity' => 'integer',
            'low_stock_threshold' => 'integer',
            'is_active' => 'boolean',
            'is_featured' => 'boolean',
            'track_inventory' => 'boolean',
            'tax_override' => 'boolean',
            'images' => 'array',
            'attributes' => 'array',
            'variants' => 'array',
            'metadata' => 'array',
        ];
    }

    /**
     * Get category
     */
    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * Get brand
     */
    public function brand()
    {
        return $this->belongsTo(Brand::class);
    }

    /**
     * Get tax rate
     */
    public function taxRate()
    {
        return $this->belongsTo(TaxRate::class);
    }
}

