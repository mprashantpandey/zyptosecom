<?php

namespace App\Http\Controllers\Api\v1;

use App\Models\Language;
use App\Models\Translation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class TranslationController
{
    /**
     * Get translations for a locale
     * GET /api/v1/translations?locale=hi
     */
    public function getTranslations(Request $request): JsonResponse
    {
        $locale = $request->query('locale', 'en');
        $group = $request->query('group', 'app');
        
        // Validate locale exists and is active
        $language = Language::where('code', $locale)
            ->where('is_active', true)
            ->first();
        
        if (!$language) {
            return response()->json([
                'error' => 'Locale not found or inactive',
            ], 404);
        }
        
        $default = Language::getDefault();
        $fallback = $default?->code ?? 'en';
        
        $cacheKey = "translations:v1:{$locale}:{$group}";
        
        $strings = Cache::remember($cacheKey, 3600, function () use ($locale, $group, $fallback) {
            // Get translations for requested locale
            $translations = Translation::where('locale', $locale)
                ->where('group', $group)
                ->get();
            
            $result = [];
            foreach ($translations as $translation) {
                $result[$translation->key] = $translation->value;
            }
            
            // Fill missing keys from fallback locale
            if ($locale !== $fallback) {
                $fallbackTranslations = Translation::where('locale', $fallback)
                    ->where('group', $group)
                    ->get();
                
                foreach ($fallbackTranslations as $fallbackTranslation) {
                    if (!isset($result[$fallbackTranslation->key])) {
                        $result[$fallbackTranslation->key] = $fallbackTranslation->value;
                    }
                }
            }
            
            return $result;
        });
        
        return response()->json([
            'locale' => $locale,
            'fallback' => $fallback,
            'group' => $group,
            'strings' => $strings,
        ]);
    }
}

