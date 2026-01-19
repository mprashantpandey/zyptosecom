<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Translation extends Model
{
    use HasFactory;

    protected $fillable = [
        'group',
        'key',
        'locale',
        'value',
        'is_locked',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'is_locked' => 'boolean',
        ];
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public static function getForLocale(string $locale, string $group = 'app'): array
    {
        $translations = static::where('locale', $locale)
            ->where('group', $group)
            ->get();

        $result = [];
        foreach ($translations as $translation) {
            $result[$translation->key] = $translation->value;
        }

        return $result;
    }

    public static function getAllForLocale(string $locale): array
    {
        $translations = static::where('locale', $locale)->get();

        $result = [];
        foreach ($translations as $translation) {
            if (!isset($result[$translation->group])) {
                $result[$translation->group] = [];
            }
            $result[$translation->group][$translation->key] = $translation->value;
        }

        return $result;
    }
}
