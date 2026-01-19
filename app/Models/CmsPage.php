<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CmsPage extends Model
{
    use HasFactory;

    protected $fillable = [
        'slug',
        'title',
        'content',
        'locale',
        'platform',
        'type',
        'is_active',
        'metadata',
        'show_in_web',
        'show_in_app',
        'show_in_footer',
        'show_in_header',
        'requires_login',
        'seo_title',
        'seo_description',
        'seo_keywords',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'metadata' => 'array',
            'show_in_web' => 'boolean',
            'show_in_app' => 'boolean',
            'show_in_footer' => 'boolean',
            'show_in_header' => 'boolean',
            'requires_login' => 'boolean',
        ];
    }

    /**
     * Check if this is a system page (cannot be deleted)
     */
    public function isSystemPage(): bool
    {
        return in_array($this->type, ['terms', 'privacy']);
    }
}
