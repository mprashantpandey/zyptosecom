<?php

namespace App\Models;

use App\Core\Traits\HasAuditLog;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class HomeSection extends Model
{
    use HasFactory, HasAuditLog;

    protected $fillable = [
        'key',
        'title',
        'type',
        'platform_scope',
        'is_enabled',
        'starts_at',
        'ends_at',
        'sort_order',
        'settings_json',
        'style',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'settings_json' => 'array',
            'style' => 'array',
            'is_enabled' => 'boolean',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'sort_order' => 'integer',
        ];
    }

    /**
     * Boot method to auto-generate key from title
     */
    protected static function boot(): void
    {
        parent::boot();

        static::creating(function ($section) {
            if (empty($section->key) && !empty($section->title)) {
                $section->key = Str::slug($section->title);
            }
            if (empty($section->platform_scope)) {
                $section->platform_scope = 'both';
            }
            if (auth()->check()) {
                $section->created_by = auth()->id();
            }
        });

        static::updating(function ($section) {
            if (auth()->check()) {
                $section->updated_by = auth()->id();
            }
        });
    }

    /**
     * Get items relationship
     */
    public function items()
    {
        return $this->hasMany(HomeSectionItem::class)->orderBy('sort_order');
    }

    /**
     * Get enabled items within schedule
     */
    public function getEnabledItems(string $platform = 'web')
    {
        return $this->items()
            ->where(function ($query) use ($platform) {
                $query->where('platform_scope', $platform)
                    ->orWhere('platform_scope', 'both');
            })
            ->where(function ($query) {
                $query->whereNull('starts_at')
                    ->orWhere('starts_at', '<=', now());
            })
            ->where(function ($query) {
                $query->whereNull('ends_at')
                    ->orWhere('ends_at', '>=', now());
            })
            ->orderBy('sort_order')
            ->get();
    }

    /**
     * Check if section is currently active
     */
    public function isCurrentlyActive(): bool
    {
        if (!$this->is_enabled) {
            return false;
        }

        if ($this->starts_at && $this->starts_at->isFuture()) {
            return false;
        }

        if ($this->ends_at && $this->ends_at->isPast()) {
            return false;
        }

        return true;
    }
}
