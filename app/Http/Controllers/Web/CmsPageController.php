<?php

namespace App\Http\Controllers\Web;

use App\Core\Services\SettingsService;
use App\Core\Settings\SettingKeys;
use App\Http\Controllers\Controller;
use App\Models\CmsPage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CmsPageController extends Controller
{
    /**
     * Display a CMS page by slug
     */
    public function show(Request $request, string $slug = null)
    {
        // Get slug from route parameter, request, or URL segment
        $slug = $slug ?? $request->route('slug') ?? $request->segment(1);
        
        // Exclude certain paths that should not be CMS pages
        $excludedPaths = ['admin', 'api', 'livewire', '_', 'storage', 'css', 'js', 'images', 'fonts'];
        if (empty($slug) || in_array($slug, $excludedPaths) || str_starts_with($slug, '_')) {
            abort(404, 'Page not found');
        }
        
        // Find the page - check if it's active and visible on web
        $page = CmsPage::where('slug', $slug)
            ->where('is_active', true)
            ->where(function ($query) {
                // Show if show_in_web is true (or null for legacy compatibility)
                // AND platform is 'all' or 'web' (or null for legacy)
                $query->where(function ($q) {
                    $q->where('show_in_web', true)
                      ->orWhereNull('show_in_web');
                })
                ->where(function ($q) {
                    $q->whereIn('platform', ['all', 'web'])
                      ->orWhereNull('platform');
                });
            })
            ->first();

        if (!$page) {
            abort(404, 'Page not found');
        }

        // Check if login is required
        if ($page->requires_login && !Auth::check()) {
            return redirect()->route('filament.admin.auth.login')->with('error', 'Please login to view this page');
        }

        // Get SEO data
        $seoTitle = $page->seo_title ?: $page->title;
        $seoDescription = $page->seo_description ?: strip_tags($page->content);
        $seoKeywords = $page->seo_keywords;

        // Get web URL from settings
        $settings = app(SettingsService::class);
        $webUrl = $settings->get(SettingKeys::WEB_URL, config('app.url'));
        
        return view('web.cms-page', [
            'page' => $page,
            'seoTitle' => $seoTitle,
            'seoDescription' => $seoDescription,
            'seoKeywords' => $seoKeywords,
            'webUrl' => rtrim($webUrl, '/'), // Remove trailing slash
        ]);
    }
}
