<?php

use App\Http\Controllers\Api\v1\AuthController;
use App\Http\Controllers\Api\v1\OrderController;
use App\Http\Controllers\Api\v1\PaymentController;
use App\Http\Controllers\Api\v1\ProductController;
use App\Http\Controllers\Api\v1\RemoteConfigController;
use App\Http\Controllers\Api\v1\UserController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Versioned API routes for the ecommerce platform
|
*/

// Public APIs (no authentication required)
Route::prefix('v1')->group(function () {
    
    // Remote Configuration APIs
    Route::prefix('config')->group(function () {
        Route::get('/', [RemoteConfigController::class, 'getConfig']);
        Route::get('/branding', [RemoteConfigController::class, 'getBranding']);
        Route::get('/theme', [RemoteConfigController::class, 'getTheme']);
        Route::get('/modules', [RemoteConfigController::class, 'getModules']);
        Route::get('/app-management', [RemoteConfigController::class, 'getAppManagement']);
        Route::get('/home-layout', [RemoteConfigController::class, 'getHomeLayout']);
    });
    
    // Translations API (Public)
    Route::get('/translations', [\App\Http\Controllers\Api\v1\TranslationController::class, 'getTranslations']);
    
    // Auth APIs (Public)
    Route::prefix('auth')->group(function () {
        Route::post('/login', [AuthController::class, 'login']);
        Route::post('/register', [AuthController::class, 'register']);
    });
    
    // Protected APIs (require authentication)
    Route::middleware('auth:sanctum')->group(function () {
        
        // Auth (for authenticated users)
        Route::prefix('auth')->group(function () {
            Route::post('/logout', [AuthController::class, 'logout']);
            Route::get('/user', [AuthController::class, 'user']);
        });
        
        // User Profile
        Route::prefix('user')->group(function () {
            Route::get('/profile', [UserController::class, 'profile']);
            Route::put('/profile', [UserController::class, 'updateProfile']);
            Route::put('/password', [UserController::class, 'updatePassword']);
        });
        
        // Products & Catalog
        Route::prefix('products')->group(function () {
            Route::get('/', [ProductController::class, 'index']);
            Route::get('/{id}', [ProductController::class, 'show']);
        });
        
        // Orders
        Route::prefix('orders')->group(function () {
            Route::get('/', [OrderController::class, 'index']);
            Route::post('/', [OrderController::class, 'store']);
            Route::get('/{id}', [OrderController::class, 'show']);
        });
        
        // Payments
        Route::prefix('payments')->group(function () {
            Route::post('/initiate', [PaymentController::class, 'initiate']);
            Route::post('/verify', [PaymentController::class, 'verify']);
        });
        
        // Device Tokens (for push notifications)
        Route::prefix('devices')->group(function () {
            Route::post('/register-token', [\App\Http\Controllers\Api\v1\DeviceTokenController::class, 'registerToken']);
            Route::post('/unregister-token', [\App\Http\Controllers\Api\v1\DeviceTokenController::class, 'unregisterToken']);
        });
        
    });
});

// Webhook routes (public, but verified with signatures)
Route::prefix('webhooks')->name('api.webhooks')->group(function () {
    Route::post('/{provider}', [\App\Http\Controllers\Api\v1\WebhookController::class, 'handle']);
});

