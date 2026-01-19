<?php

namespace App\Core\Services;

use App\Models\Theme;
use App\Models\AppVersion;
use App\Models\Brand;
use App\Models\RuntimeFlag;
use App\Models\HomeSection;
use App\Core\Services\SettingsService;
use App\Core\Services\ModuleService;
use Illuminate\Support\Facades\Storage;

class RemoteConfigService
{
    public function __construct(
        protected SettingsService $settings,
        protected ModuleService $modules
    ) {}

    /**
     * Get complete remote config for app/web
     */
    public function getConfig(string $platform = 'web', ?string $appVersion = null): array
    {
        $homeLayout = $this->getHomeLayout($platform);
        
        return [
            'branding' => $this->getBrandingConfig(),
            'theme' => $this->getThemeConfig(),
            'modules' => $this->getModulesConfig($platform, $appVersion),
            'app_management' => $this->getAppManagementConfig($platform),
            'feature_flags' => $this->getFeatureFlags($platform, $appVersion),
            'home_layout' => $homeLayout['sections'] ?? [],
            'content_strings' => $this->getContentStrings($platform),
            'timestamp' => now()->toIso8601String(),
        ];
    }

    /**
     * Get branding configuration (from published Brand)
     */
    protected function getBrandingConfig(): array
    {
        $brand = Brand::getPublished();
        
        if (!$brand) {
            // Fallback to settings
            return [
                'app_name' => $this->settings->get('app.name', 'ZyptoseComm', true),
                'app_name_short' => $this->settings->get('app.name_short', 'ZC', true),
                'company_name' => $this->settings->get('branding.company_name', '', true),
                'logo' => $this->settings->get('branding.logo', '', true),
                'logo_dark' => $this->settings->get('branding.logo_dark', '', true),
                'icon' => $this->settings->get('branding.icon', '', true),
                'favicon' => $this->settings->get('branding.favicon', '', true),
            ];
        }

        return [
            'app_name' => $brand->name,
            'app_name_short' => $brand->short_name ?? 'ZC',
            'company_name' => $brand->company_name ?? '',
            'logo' => $brand->logo_light_path ? Storage::url($brand->logo_light_path) : '',
            'logo_dark' => $brand->logo_dark_path ? Storage::url($brand->logo_dark_path) : '',
            'icon' => $brand->app_icon_path ? Storage::url($brand->app_icon_path) : '',
            'favicon' => $brand->favicon_path ? Storage::url($brand->favicon_path) : '',
            'splash' => $brand->splash_path ? Storage::url($brand->splash_path) : '',
            'support_email' => $brand->support_email ?? '',
            'support_phone' => $brand->support_phone ?? '',
        ];
    }

    /**
     * Get theme configuration (from published Theme with tokens_json)
     */
    protected function getThemeConfig(): array
    {
        $brand = Brand::getPublished();
        $theme = Theme::getPublished($brand?->id);
        
        if (!$theme) {
            return $this->getDefaultTheme();
        }

        // Use tokens_json if available, otherwise fallback to individual fields
        $tokens = $theme->tokens_json ?? [];
        
        if (empty($tokens)) {
            // Fallback to individual fields
            return [
                'name' => $theme->name,
                'primary_color' => $theme->primary_color,
                'secondary_color' => $theme->secondary_color,
                'accent_color' => $theme->accent_color,
                'background_color' => $theme->background_color,
                'surface_color' => $theme->surface_color,
                'text_color' => $theme->text_color,
                'text_secondary_color' => $theme->text_secondary_color,
                'border_radius' => $theme->border_radius,
                'ui_density' => $theme->ui_density,
                'font_family' => $theme->font_family,
                'font_url' => $theme->font_url,
                'additional_colors' => $theme->additional_colors ?? [],
            ];
        }

        // Return tokens_json structure
        return $tokens;
    }

    /**
     * Get default theme
     */
    protected function getDefaultTheme(): array
    {
        return [
            'name' => 'default',
            'primary_color' => '#007bff',
            'secondary_color' => '#6c757d',
            'accent_color' => '#ffc107',
            'background_color' => '#ffffff',
            'surface_color' => '#f8f9fa',
            'text_color' => '#212529',
            'text_secondary_color' => '#6c757d',
            'border_radius' => '8px',
            'ui_density' => 'normal',
            'font_family' => null,
            'font_url' => null,
            'additional_colors' => [],
        ];
    }

    /**
     * Get modules configuration
     */
    protected function getModulesConfig(string $platform, ?string $appVersion): array
    {
        $allModules = $this->modules->getEnabledModules($platform);
        $config = [];
        
        foreach ($allModules as $moduleName) {
            $config[$moduleName] = [
                'enabled' => $this->modules->isEnabled($moduleName, $platform, $appVersion),
            ];
        }
        
        return $config;
    }

    /**
     * Get app management config (version, update, maintenance, kill switch)
     */
    protected function getAppManagementConfig(string $platform): array
    {
        $appVersion = AppVersion::getForPlatform($platform);
        $runtimeFlag = RuntimeFlag::getForPlatform($platform);
        
        $config = [
            'latest_version' => $appVersion->latest_version ?? '1.0.0',
            'latest_build' => $appVersion->latest_build ?? null,
            'min_version' => $appVersion->min_version ?? null,
            'min_build' => $appVersion->min_build ?? null,
            'update_type' => $appVersion->update_type ?? 'none',
            'update_message' => $appVersion->update_message ?? null,
            'store_url' => $appVersion->store_url ?? null,
            'download_url' => $appVersion->download_url ?? null,
            'is_minimum_supported' => $appVersion->is_minimum_supported ?? false,
        ];

        // Add maintenance mode (from RuntimeFlag, takes priority over AppVersion)
        if ($runtimeFlag) {
            $config['maintenance_mode'] = $runtimeFlag->maintenance_enabled;
            $config['maintenance_message'] = $runtimeFlag->maintenance_message;
        } else {
            $config['maintenance_mode'] = $appVersion->maintenance_mode ?? false;
            $config['maintenance_message'] = $appVersion->maintenance_message ?? null;
        }

        // Add kill switch (from RuntimeFlag)
        if ($runtimeFlag) {
            $config['kill_switch'] = [
                'enabled' => $runtimeFlag->kill_switch_enabled,
                'message' => $runtimeFlag->kill_switch_message,
                'until' => $runtimeFlag->kill_switch_until?->toIso8601String(),
            ];
        } else {
            $config['kill_switch'] = [
                'enabled' => false,
                'message' => null,
                'until' => null,
            ];
        }

        return $config;
    }

    /**
     * Get feature flags
     */
    protected function getFeatureFlags(string $platform, ?string $appVersion): array
    {
        // Get module-specific rules as feature flags
        $moduleNames = $this->modules->getEnabledModules($platform);
        $flags = [];
        
        foreach ($moduleNames as $moduleName) {
            // Get common rules/features for each module
            $commonRules = ['enabled', 'min_amount', 'max_amount', 'cod_limit'];
            
            foreach ($commonRules as $ruleKey) {
                $value = $this->modules->getRule($moduleName, $ruleKey);
                if ($value !== null) {
                    $flags["{$moduleName}.{$ruleKey}"] = $value;
                }
            }
        }
        
        return $flags;
    }

    /**
     * Get home layout sections with items (filtered by platform and schedule)
     * Cached per platform
     */
    protected function getHomeLayout(string $platform): array
    {
        return Cache::remember("home_layout:v1:{$platform}", 3600, function () use ($platform) {
            $now = now();
            
            // Get enabled sections that match platform scope and are within schedule
            $sections = HomeSection::where('is_enabled', true)
                ->where(function ($query) use ($platform) {
                    $query->where('platform_scope', $platform)
                        ->orWhere('platform_scope', 'both');
                })
                ->where(function ($query) use ($now) {
                    $query->whereNull('starts_at')
                        ->orWhere('starts_at', '<=', $now);
                })
                ->where(function ($query) use ($now) {
                    $query->whereNull('ends_at')
                        ->orWhere('ends_at', '>=', $now);
                })
                ->orderBy('sort_order')
                ->get();
            
            $result = [];
            
            foreach ($sections as $section) {
                // Get enabled items for this section
                $items = HomeSectionItem::where('home_section_id', $section->id)
                    ->where(function ($query) use ($platform, $section) {
                        // Check if item platform_scope matches or inherits
                        $query->where(function ($q) use ($platform) {
                            $q->where('platform_scope', $platform)
                                ->orWhere('platform_scope', 'both');
                        })->orWhere(function ($q) use ($section, $platform) {
                            // Inherit: check if section platform matches
                            $q->whereNull('platform_scope')
                                ->where(function ($sq) use ($section, $platform) {
                                    $sq->where('platform_scope', $platform)
                                        ->orWhere('platform_scope', 'both');
                                });
                        });
                    })
                    ->where(function ($query) use ($now) {
                        $query->whereNull('starts_at')
                            ->orWhere('starts_at', '<=', $now);
                    })
                    ->where(function ($query) use ($now) {
                        $query->whereNull('ends_at')
                            ->orWhere('ends_at', '>=', $now);
                    })
                    ->orderBy('sort_order')
                    ->get()
                    ->filter(function ($item) use ($platform, $section) {
                        // Additional filter: if item platform_scope is null/inherit, use section's
                        $itemPlatform = $item->platform_scope;
                        if (empty($itemPlatform) || $itemPlatform === 'inherit') {
                            return in_array($section->platform_scope, [$platform, 'both']);
                        }
                        return in_array($itemPlatform, [$platform, 'both']);
                    })
                    ->map(function ($item) {
                        $imageUrl = null;
                        if ($item->image_path) {
                            $imageUrl = Storage::disk('public')->url($item->image_path);
                        }
                        
                        return [
                            'title' => $item->title,
                            'subtitle' => $item->subtitle,
                            'image_url' => $imageUrl,
                            'badge_text' => $item->badge_text,
                            'cta_text' => $item->cta_text,
                            'action' => [
                                'type' => $item->action_type,
                                'payload' => $item->action_payload ?? [],
                            ],
                        ];
                    })
                    ->values()
                    ->toArray();
                
                $result[] = [
                    'key' => $section->key,
                    'title' => $section->title,
                    'type' => $section->type,
                    'platform_scope' => $section->platform_scope,
                    'settings' => $section->settings_json ?? [],
                    'items' => $items,
                ];
            }
            
            return ['sections' => $result];
        });
    }

    /**
     * Get content strings
     */
    protected function getContentStrings(string $platform): array
    {
        // This would typically be cached and fetched from ContentString model
        // For now, return empty array - will be implemented in CMS module
        return [];
    }
}

