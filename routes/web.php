<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\TranslationExportController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Admin panel and web storefront routes
|
*/

// Authentication Routes - Using Filament authentication
// Old custom login routes removed - use /admin/login for admin access
// Filament handles all authentication for /admin routes

// Admin Panel Routes are handled by Filament
// All /admin routes are managed by Filament Admin Panel Provider

// Web Storefront Routes (to be implemented)
// Route::get('/', [StorefrontController::class, 'index']);
// Route::get('/product/{slug}', [StorefrontController::class, 'product']);

// Root route - redirect to web URL or show coming soon
Route::get('/', function () {
    $settings = app(\App\Core\Services\SettingsService::class);
    $webUrl = $settings->get(\App\Core\Settings\SettingKeys::WEB_URL, config('app.url'));
    
    // If web URL is different from current domain, redirect
    if ($webUrl !== config('app.url')) {
        return redirect($webUrl);
    }
    
    // Otherwise show a simple coming soon page
    return view('web.home', ['webUrl' => $webUrl]);
})->name('home');

// CMS Pages - Dynamic routes for all CMS pages
// This must be the last route to avoid conflicts with other routes
Route::get('/{slug}', [\App\Http\Controllers\Web\CmsPageController::class, 'show'])
    ->where('slug', '[a-z0-9\-]+') // Only alphanumeric and hyphens
    ->name('cms.page.show');

