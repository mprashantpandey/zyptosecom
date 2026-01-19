<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Theme extends Model
{
    use HasFactory;

    protected $fillable = [
        'brand_id',
        'name',
        'label',
        'primary_color',
        'secondary_color',
        'accent_color',
        'background_color',
        'surface_color',
        'text_color',
        'text_secondary_color',
        'border_radius',
        'ui_density',
        'font_family',
        'font_url',
        'additional_colors',
        'tokens_json',
        'mode',
        'published_at',
        'is_active',
        'is_default',
    ];

    protected function casts(): array
    {
        return [
            'additional_colors' => 'array',
            'tokens_json' => 'array',
            'published_at' => 'datetime',
            'is_active' => 'boolean',
            'is_default' => 'boolean',
        ];
    }

    /**
     * Get published theme or default
     */
    public static function getPublished(?int $brandId = null): ?self
    {
        $query = static::where('mode', 'published')
            ->where('is_active', true)
            ->whereNotNull('published_at');

        if ($brandId) {
            $query->where('brand_id', $brandId);
        }

        return $query->latest('published_at')->first() 
            ?? static::where('is_default', true)->first()
            ?? static::first();
    }

    /**
     * Get brand relationship
     */
    public function brand()
    {
        return $this->belongsTo(Brand::class);
    }
}

