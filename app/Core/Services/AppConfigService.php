<?php

namespace App\Core\Services;

use App\Models\Module;
use App\Models\Setting;
use App\Models\Brand;
use App\Models\Theme;
use App\Models\RuntimeFlag;
use App\Models\Language;
use App\Models\Currency;
use App\Core\Services\CurrencyService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

class AppConfigService
{
    protected const CACHE_KEY = 'app_config:v1';

    /**
     * Get complete app configuration (branding + settings + modules)
     */
    public function getConfig(): array
    {
        return Cache::remember(self::CACHE_KEY, 3600, function () {
            return [
                'branding' => $this->getBrandingConfig(),
                'settings' => $this->getPublicSettings(),
                'modules' => $this->getModuleFlags(),
                'localization' => $this->getLocalizationConfig(),
                'currency' => $this->getCurrencyConfig(),
            ];
        });
    }

    /**
     * Get branding configuration (from published Brand)
     */
    protected function getBrandingConfig(): array
    {
        $brand = Brand::getPublished();
        
        if (!$brand) {
            return [
                'app_name' => 'ZyptoseComm',
                'app_short_name' => 'ZC',
                'logo_light' => null,
                'logo_dark' => null,
                'theme' => [],
            ];
        }

        return [
            'app_name' => $brand->name,
            'app_short_name' => $brand->short_name ?? 'ZC',
            'logo_light' => $brand->logo_light_path ? Storage::url($brand->logo_light_path) : null,
            'logo_dark' => $brand->logo_dark_path ? Storage::url($brand->logo_dark_path) : null,
            'icon' => $brand->app_icon_path ? Storage::url($brand->app_icon_path) : null,
            'favicon' => $brand->favicon_path ? Storage::url($brand->favicon_path) : null,
            'splash' => $brand->splash_path ? Storage::url($brand->splash_path) : null,
        ];
    }

    /**
     * Get public settings
     */
    protected function getPublicSettings(): array
    {
        $settingsService = app(SettingsService::class);
        return $settingsService->getPublicSettings();
    }

    /**
     * Get module flags (enabled modules per platform)
     */
    protected function getModuleFlags(): array
    {
        $modules = Module::where('is_enabled', true)->get();
        $flags = [];

        foreach ($modules as $module) {
            $platforms = $module->platforms ?? ['both'];
            foreach ($platforms as $platform) {
                if (!isset($flags[$platform])) {
                    $flags[$platform] = [];
                }
                $flags[$platform][$module->name] = [
                    'enabled' => $module->is_enabled,
                    'min_app_version' => $module->min_app_version,
                    'metadata' => $module->metadata ?? [],
                ];
            }
        }

        return $flags;
    }

    /**
     * Get runtime flags (maintenance, kill switch)
     */
    protected function getRuntimeFlags(string $platform = 'all'): array
    {
        $flag = RuntimeFlag::getForPlatform($platform);
        
        return [
            'maintenance' => [
                'enabled' => $flag->maintenance_enabled ?? false,
                'message' => $flag->maintenance_message ?? null,
            ],
            'kill_switch' => [
                'enabled' => $flag->kill_switch_enabled ?? false,
                'message' => $flag->kill_switch_message ?? null,
                'until' => $flag->kill_switch_until?->toIso8601String(),
            ],
        ];
    }

    /**
     * Get localization configuration
     */
    protected function getLocalizationConfig(): array
    {
        $languages = Language::where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
        
        $default = Language::getDefault();
        
        return [
            'supported_locales' => $languages->map(function ($lang) {
                return [
                    'code' => $lang->code,
                    'name' => $lang->name,
                    'native_name' => $lang->native_name,
                    'is_rtl' => $lang->is_rtl,
                ];
            })->toArray(),
            'default_locale' => $default?->code ?? 'en',
            'locale_fallback' => $default?->code ?? 'en',
        ];
    }

    /**
     * Get currency configuration
     */
    protected function getCurrencyConfig(): array
    {
        $currencyService = app(CurrencyService::class);
        $default = $currencyService->getDefaultCurrency();
        $active = $currencyService->getActiveCurrencies();
        
        $settingsService = app(SettingsService::class);
        $selectionEnabled = $settingsService->get('currency_selection_enabled', false);
        $autoConvert = $settingsService->get('currency_auto_convert', false);
        
        $config = [
            'default_currency' => null,
            'supported_currencies' => [],
            'currency_selection_enabled' => $selectionEnabled,
            'currency_auto_convert' => $autoConvert,
        ];
        
        if ($default) {
            $config['default_currency'] = [
                'code' => $default->code,
                'symbol' => $default->symbol,
                'decimals' => $default->decimals,
                'symbol_position' => $default->symbol_position,
                'thousand_separator' => $default->thousand_separator,
                'decimal_separator' => $default->decimal_separator,
            ];
        }
        
        $config['supported_currencies'] = $active->map(function ($currency) {
            return [
                'code' => $currency->code,
                'name' => $currency->name,
                'symbol' => $currency->symbol,
                'decimals' => $currency->decimals,
                'symbol_position' => $currency->symbol_position,
                'thousand_separator' => $currency->thousand_separator,
                'decimal_separator' => $currency->decimal_separator,
            ];
        })->toArray();
        
        return $config;
    }

    /**
     * Clear app config cache
     */
    public function clearCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }
}

