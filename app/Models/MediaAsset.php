<?php

namespace App\Models;

use App\Core\Traits\HasAuditLog;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class MediaAsset extends Model
{
    use HasFactory, HasAuditLog, SoftDeletes;

    protected $fillable = [
        'disk',
        'path',
        'type',
        'mime',
        'size',
        'width',
        'height',
        'alt_text',
        'tags_json',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'tags_json' => 'array',
            'size' => 'integer',
            'width' => 'integer',
            'height' => 'integer',
        ];
    }

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function ($asset) {
            if (auth()->check()) {
                $asset->created_by = auth()->id();
            }
            if (empty($asset->disk)) {
                $asset->disk = 'public';
            }
        });
    }

    /**
     * Get full URL to media asset
     */
    public function getUrlAttribute(): string
    {
        return Storage::disk($this->disk)->url($this->path);
    }

    /**
     * Get file size in human readable format
     */
    public function getSizeHumanAttribute(): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $size = $this->size ?? 0;
        $unitIndex = 0;
        
        while ($size >= 1024 && $unitIndex < count($units) - 1) {
            $size /= 1024;
            $unitIndex++;
        }
        
        return round($size, 2) . ' ' . $units[$unitIndex];
    }
}
