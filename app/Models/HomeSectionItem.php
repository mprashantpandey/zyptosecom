<?php

namespace App\Models;

use App\Core\Traits\HasAuditLog;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class HomeSectionItem extends Model
{
    use HasFactory, HasAuditLog;

    protected $fillable = [
        'home_section_id',
        'title',
        'subtitle',
        'image_path',
        'badge_text',
        'cta_text',
        'action_type',
        'action_payload',
        'platform_scope',
        'starts_at',
        'ends_at',
        'sort_order',
        'meta_json',
    ];

    protected function casts(): array
    {
        return [
            'action_payload' => 'array', // Store as JSON array
            'meta_json' => 'array',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'sort_order' => 'integer',
        ];
    }

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function ($item) {
            if (empty($item->platform_scope)) {
                $item->platform_scope = 'both';
            }
            if (empty($item->action_type)) {
                $item->action_type = 'none';
            }
        });
    }

    /**
     * Get home section relationship
     */
    public function homeSection()
    {
        return $this->belongsTo(HomeSection::class);
    }

    /**
     * Check if item is currently active
     */
    public function isCurrentlyActive(): bool
    {
        if ($this->starts_at && $this->starts_at->isFuture()) {
            return false;
        }

        if ($this->ends_at && $this->ends_at->isPast()) {
            return false;
        }

        return true;
    }

    /**
     * Get action URL/path based on action_type
     */
    public function getActionUrl(): ?string
    {
        $payload = $this->action_payload ?? [];
        
        return match ($this->action_type) {
            'product' => '/products/' . ($payload['product_id'] ?? ''),
            'category' => '/categories/' . ($payload['category_id'] ?? ''),
            'search' => '/search?q=' . urlencode($payload['query'] ?? ''),
            'url' => $payload['url'] ?? null,
            default => null,
        };
    }
}
