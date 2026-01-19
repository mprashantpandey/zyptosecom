<?php

namespace App\Core\Services;

use App\Models\Module;
use App\Models\ModuleRule;
use Illuminate\Support\Facades\Cache;

class ModuleService
{
    /**
     * Check if module is enabled
     */
    public function isEnabled(string $moduleName, ?string $platform = null, ?string $appVersion = null): bool
    {
        $cacheKey = "module:enabled:{$moduleName}:" . ($platform ?? 'all') . ':' . ($appVersion ?? 'any');
        
        return Cache::remember($cacheKey, 3600, function () use ($moduleName, $platform, $appVersion) {
            $module = Module::where('name', $moduleName)
                ->where('is_enabled', true)
                ->first();
            
            if (!$module) {
                return false;
            }
            
            // Check platform compatibility
            if ($platform) {
                $platforms = is_array($module->platforms) ? $module->platforms : json_decode($module->platforms, true);
                if (!in_array($platform, $platforms) && !in_array('all', $platforms)) {
                    return false;
                }
            }
            
            // Check minimum app version
            if ($appVersion && $module->min_app_version) {
                if (version_compare($appVersion, $module->min_app_version, '<')) {
                    return false;
                }
            }
            
            // Check time-based rules
            if ($module->enabled_at && now()->lt($module->enabled_at)) {
                return false;
            }
            
            if ($module->disabled_at && now()->gte($module->disabled_at)) {
                return false;
            }
            
            return true;
        });
    }

    /**
     * Get module rule value
     */
    public function getRule(string $moduleName, string $ruleKey, $default = null)
    {
        $cacheKey = "module:rule:{$moduleName}:{$ruleKey}";
        
        return Cache::remember($cacheKey, 3600, function () use ($moduleName, $ruleKey, $default) {
            $module = Module::where('name', $moduleName)->first();
            
            if (!$module) {
                return $default;
            }
            
            $rule = ModuleRule::where('module_id', $module->id)
                ->where('rule_key', $ruleKey)
                ->where('is_active', true)
                ->where(function ($query) {
                    $query->whereNull('starts_at')
                        ->orWhere('starts_at', '<=', now());
                })
                ->where(function ($query) {
                    $query->whereNull('ends_at')
                        ->orWhere('ends_at', '>=', now());
                })
                ->first();
            
            if (!$rule) {
                return $default;
            }
            
            // Try to decode as JSON, fallback to string
            $decoded = json_decode($rule->rule_value, true);
            return json_last_error() === JSON_ERROR_NONE ? $decoded : $rule->rule_value;
        });
    }

    /**
     * Set module rule
     */
    public function setRule(string $moduleName, string $ruleKey, $value, string $ruleType = 'condition_based'): ModuleRule
    {
        $module = Module::where('name', $moduleName)->firstOrFail();
        
        $serializedValue = is_array($value) || is_object($value) 
            ? json_encode($value) 
            : (string) $value;
        
        $rule = ModuleRule::updateOrCreate(
            [
                'module_id' => $module->id,
                'rule_key' => $ruleKey,
            ],
            [
                'rule_type' => $ruleType,
                'rule_value' => $serializedValue,
                'is_active' => true,
            ]
        );
        
        Cache::forget("module:rule:{$moduleName}:{$ruleKey}");
        
        return $rule;
    }

    /**
     * Get all enabled modules
     */
    public function getEnabledModules(?string $platform = null): array
    {
        $cacheKey = "modules:enabled:" . ($platform ?? 'all');
        
        return Cache::remember($cacheKey, 3600, function () use ($platform) {
            $query = Module::where('is_enabled', true);
            
            if ($platform) {
                $query->whereJsonContains('platforms', $platform)
                    ->orWhereJsonContains('platforms', 'all');
            }
            
            return $query->get()->pluck('name')->toArray();
        });
    }

    /**
     * Enable/disable module
     */
    public function toggleModule(string $moduleName, bool $enabled): Module
    {
        $module = Module::where('name', $moduleName)->firstOrFail();
        $module->is_enabled = $enabled;
        $module->enabled_at = $enabled ? now() : null;
        $module->disabled_at = $enabled ? null : now();
        $module->save();
        
        Cache::forget("modules:enabled:*");
        Cache::forget("module:enabled:{$moduleName}:*");
        
        return $module;
    }

    /**
     * Clear module cache
     */
    public function clearCache(?string $moduleName = null): void
    {
        if ($moduleName) {
            Cache::forget("module:enabled:{$moduleName}:*");
            Cache::forget("module:rule:{$moduleName}:*");
        } else {
            Cache::forget("modules:enabled:*");
        }
    }
}

