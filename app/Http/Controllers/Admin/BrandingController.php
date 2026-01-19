<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Core\Services\SettingsService;
use App\Models\Theme;
use App\Models\AppVersion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BrandingController extends Controller
{
    public function __construct(
        protected SettingsService $settings
    ) {}

    /**
     * Get all branding settings
     */
    public function index(): JsonResponse
    {
        $branding = $this->settings->getByGroup('branding');
        $app = $this->settings->getByGroup('app');
        $themes = Theme::where('is_active', true)->get();
        $appVersions = AppVersion::orderBy('platform')->orderBy('created_at', 'desc')->get();
        
        return response()->json([
            'success' => true,
            'data' => [
                'branding' => $branding,
                'app' => $app,
                'themes' => $themes,
                'app_versions' => $appVersions,
            ],
        ]);
    }

    /**
     * Update branding settings
     */
    public function updateBranding(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'app_name' => 'sometimes|string|max:255',
            'app_name_short' => 'sometimes|string|max:50',
            'company_name' => 'sometimes|string|max:255',
            'logo' => 'sometimes|string',
            'logo_dark' => 'sometimes|string',
            'icon' => 'sometimes|string',
            'favicon' => 'sometimes|string',
        ]);
        
        foreach ($validated as $key => $value) {
            if ($key === 'app_name') {
                $this->settings->set("app.name", $value, 'app', 'string', true);
            } elseif ($key === 'app_name_short') {
                $this->settings->set("app.name_short", $value, 'app', 'string', true);
            } else {
                $this->settings->set("branding.{$key}", $value, 'branding', 'string', true);
            }
        }
        
        return response()->json([
            'success' => true,
            'message' => 'Branding updated successfully',
        ]);
    }

    /**
     * Update theme
     */
    public function updateTheme(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'theme_id' => 'required|exists:themes,id',
        ]);
        
        $theme = Theme::findOrFail($validated['theme_id']);
        
        // Remove default from all themes
        Theme::where('is_default', true)->update(['is_default' => false]);
        
        // Set new default
        $theme->is_default = true;
        $theme->save();
        
        $this->settings->set('theme.active', $theme->name, 'theme', 'string', true);
        
        return response()->json([
            'success' => true,
            'message' => 'Theme updated successfully',
        ]);
    }

    /**
     * Create or update app version
     */
    public function updateAppVersion(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'platform' => 'required|in:android,ios,web',
            'version' => 'required|string',
            'build_number' => 'nullable|string',
            'update_type' => 'required|in:none,optional,force',
            'update_message' => 'nullable|string',
            'store_url' => 'nullable|url',
            'download_url' => 'nullable|url',
            'is_minimum_supported' => 'sometimes|boolean',
            'maintenance_mode' => 'sometimes|boolean',
            'maintenance_message' => 'nullable|string',
        ]);
        
        $appVersion = AppVersion::updateOrCreate(
            [
                'platform' => $validated['platform'],
                'version' => $validated['version'],
            ],
            $validated
        );
        
        return response()->json([
            'success' => true,
            'data' => $appVersion,
            'message' => 'App version updated successfully',
        ]);
    }
}

