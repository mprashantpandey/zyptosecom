<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Brand extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'short_name',
        'company_name',
        'slug',
        'description',
        'logo_light_path',
        'logo_dark_path',
        'app_icon_path',
        'favicon_path',
        'splash_path',
        'support_email',
        'support_phone',
        'is_active',
        'is_published',
        'published_at',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_published' => 'boolean',
            'published_at' => 'datetime',
        ];
    }

    /**
     * Get published brand or default
     */
    public static function getPublished(): ?self
    {
        return static::where('is_published', true)
            ->where('is_active', true)
            ->whereNotNull('published_at')
            ->latest('published_at')
            ->first() ?? static::first();
    }
}

