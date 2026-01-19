<?php

namespace App\Http\Controllers\Api\v1;

use App\Http\Controllers\Controller;
use App\Core\Services\RemoteConfigService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RemoteConfigController extends Controller
{
    public function __construct(
        protected RemoteConfigService $remoteConfig
    ) {}

    /**
     * Get complete remote configuration for app/web
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function getConfig(Request $request): JsonResponse
    {
        // Platform detection: header X-Platform or query param, default web
        $platform = $request->header('X-Platform') 
            ?? $request->input('platform', 'web'); // app, web
        $appVersion = $request->input('version'); // e.g., '1.2.0'
        
        $config = $this->remoteConfig->getConfig($platform, $appVersion);
        
        return response()->json([
            'success' => true,
            'data' => $config,
        ]);
    }

    /**
     * Get only branding configuration
     */
    public function getBranding(): JsonResponse
    {
        $config = $this->remoteConfig->getConfig();
        
        return response()->json([
            'success' => true,
            'data' => $config['branding'],
        ]);
    }

    /**
     * Get only theme configuration
     */
    public function getTheme(): JsonResponse
    {
        $config = $this->remoteConfig->getConfig();
        
        return response()->json([
            'success' => true,
            'data' => $config['theme'],
        ]);
    }

    /**
     * Get only modules configuration
     */
    public function getModules(Request $request): JsonResponse
    {
        $platform = $request->input('platform', 'app');
        $appVersion = $request->input('version');
        
        $config = $this->remoteConfig->getConfig($platform, $appVersion);
        
        return response()->json([
            'success' => true,
            'data' => $config['modules'],
        ]);
    }

    /**
     * Get app management configuration (version, update, maintenance)
     */
    public function getAppManagement(Request $request): JsonResponse
    {
        $platform = $request->input('platform', 'app');
        
        $config = $this->remoteConfig->getConfig($platform);
        
        return response()->json([
            'success' => true,
            'data' => $config['app_management'],
        ]);
    }

    /**
     * Get home layout sections
     */
    public function getHomeLayout(Request $request): JsonResponse
    {
        $platform = $request->input('platform', 'app');
        
        $config = $this->remoteConfig->getConfig($platform);
        
        return response()->json([
            'success' => true,
            'data' => $config['home_layout'],
        ]);
    }
}

