<?php

namespace App\Core\Services;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;

class SettingsService
{
    /**
     * Get setting value by key
     */
    public function get(string $key, $default = null, bool $publicOnly = false)
    {
        $cacheKey = "setting:{$key}";
        
        return Cache::remember($cacheKey, 3600, function () use ($key, $default, $publicOnly) {
            $query = Setting::where('key', $key);
            
            if ($publicOnly) {
                $query->where('is_public', true);
            }
            
            $setting = $query->first();
            
            if (!$setting) {
                return $default;
            }
            
            return $this->castValue($setting);
        });
    }

    /**
     * Set setting value
     */
    public function set(string $key, $value, string $group = 'general', string $type = 'string', bool $isPublic = false): Setting
    {
        $setting = Setting::updateOrCreate(
            ['key' => $key],
            [
                'value' => $this->serializeValue($value, $type),
                'type' => $type,
                'group' => $group,
                'is_public' => $isPublic,
            ]
        );
        
        Cache::forget("setting:{$key}");
        
        return $setting;
    }

    /**
     * Get all settings by group
     */
    public function getByGroup(string $group, bool $publicOnly = false): array
    {
        $cacheKey = "settings:group:{$group}:" . ($publicOnly ? 'public' : 'all');
        
        return Cache::remember($cacheKey, 3600, function () use ($group, $publicOnly) {
            $query = Setting::where('group', $group);
            
            if ($publicOnly) {
                $query->where('is_public', true);
            }
            
            $settings = $query->get();
            $result = [];
            
            foreach ($settings as $setting) {
                $result[$setting->key] = $this->castValue($setting);
            }
            
            return $result;
        });
    }

    /**
     * Get all public settings (for remote config)
     */
    public function getPublicSettings(): array
    {
        return Cache::remember('settings:public', 3600, function () {
            $settings = Setting::where('is_public', true)->get();
            $result = [];
            
            foreach ($settings as $setting) {
                $result[$setting->key] = $this->castValue($setting);
            }
            
            return $result;
        });
    }

    /**
     * Cast value based on type
     */
    protected function castValue(Setting $setting)
    {
        return match($setting->type) {
            'json' => json_decode($setting->value, true),
            'number' => (float) $setting->value,
            'boolean' => filter_var($setting->value, FILTER_VALIDATE_BOOLEAN),
            'integer' => (int) $setting->value,
            default => $setting->value,
        };
    }

    /**
     * Serialize value based on type
     */
    protected function serializeValue($value, string $type): string
    {
        return match($type) {
            'json' => json_encode($value),
            'number', 'integer' => (string) $value,
            'boolean' => $value ? '1' : '0',
            default => (string) $value,
        };
    }

    /**
     * Clear settings cache
     */
    public function clearCache(?string $key = null): void
    {
        if ($key) {
            Cache::forget("setting:{$key}");
        } else {
            Cache::flush();
        }
    }

    /**
     * Get multiple settings at once
     */
    public function getMany(array $keys, array $defaults = []): array
    {
        $result = [];
        foreach ($keys as $key) {
            $result[$key] = $this->get($key, $defaults[$key] ?? null);
        }
        return $result;
    }

    /**
     * Set multiple settings at once
     */
    public function setMany(array $settings, string $group = 'general'): void
    {
        foreach ($settings as $key => $config) {
            $value = $config['value'] ?? $config;
            $type = $config['type'] ?? 'string';
            $isPublic = $config['is_public'] ?? false;
            $description = $config['description'] ?? null;
            
            $this->set($key, $value, $group, $type, $isPublic);
            
            if ($description) {
                Setting::where('key', $key)->update(['description' => $description]);
            }
        }
    }

    /**
     * Get snapshot of current settings for audit
     */
    public function snapshot(array $keys): array
    {
        return $this->getMany($keys);
    }
}

