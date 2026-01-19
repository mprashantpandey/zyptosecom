<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use App\Models\Module;
use App\Models\Theme;
use App\Models\Setting;
use App\Models\Language;
use App\Models\Currency;
use App\Models\Translation;
use App\Core\Services\SettingsService;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Seed Default Admin User
        $this->seedAdminUser();
        
        // Seed Default Modules
        $this->seedModules();
        
        // Seed Default Theme
        $this->seedTheme();
        
        // Seed Default Settings
        $this->seedSettings();
        
        // Seed Languages
        $this->seedLanguages();
        
        // Seed Default Translations
        $this->seedDefaultTranslations();
        
        // Seed Currencies
        $this->seedCurrencies();
        
        // Sync providers from registry
        $this->command->info('Syncing providers from registry...');
        \Illuminate\Support\Facades\Artisan::call('providers:sync');
        $this->command->info(\Illuminate\Support\Facades\Artisan::output());
    }
    
    private function seedAdminUser(): void
    {
        User::firstOrCreate(
            ['email' => 'admin@zyptosecomm.com'],
            [
                'name' => 'Administrator',
                'email' => 'admin@zyptosecomm.com',
                'password' => Hash::make('password'),
                'type' => 'admin',
                'email_verified_at' => now(),
                'is_active' => true,
            ]
        );
        
        $this->command->info('Admin user created: admin@zyptosecomm.com / password');
    }
    
    private function seedModules(): void
    {
        $modules = [
            [
                'name' => 'payments',
                'label' => 'Payments',
                'description' => 'Payment processing module with multiple provider support',
                'version' => '1.0.0',
                'is_enabled' => true,
                'platforms' => ['web', 'app'],
            ],
            [
                'name' => 'shipping',
                'label' => 'Shipping',
                'description' => 'Shipping and delivery management',
                'version' => '1.0.0',
                'is_enabled' => true,
                'platforms' => ['web', 'app'],
            ],
            [
                'name' => 'wallet',
                'label' => 'Wallet',
                'description' => 'User wallet and cashback system',
                'version' => '1.0.0',
                'is_enabled' => false,
                'platforms' => ['app'],
                'min_app_version' => '1.2.0',
            ],
            [
                'name' => 'notifications',
                'label' => 'Notifications',
                'description' => 'Multi-channel notification system',
                'version' => '1.0.0',
                'is_enabled' => true,
                'platforms' => ['web', 'app'],
            ],
            [
                'name' => 'auth',
                'label' => 'Authentication',
                'description' => 'Pluggable authentication system',
                'version' => '1.0.0',
                'is_enabled' => true,
                'platforms' => ['web', 'app'],
            ],
            [
                'name' => 'catalog',
                'label' => 'Catalog',
                'description' => 'Product catalog and inventory management',
                'version' => '1.0.0',
                'is_enabled' => true,
                'platforms' => ['web', 'app'],
            ],
            [
                'name' => 'orders',
                'label' => 'Orders',
                'description' => 'Order management and fulfillment',
                'version' => '1.0.0',
                'is_enabled' => true,
                'platforms' => ['web', 'app'],
            ],
            [
                'name' => 'promotions',
                'label' => 'Promotions',
                'description' => 'Coupons, offers, and loyalty programs',
                'version' => '1.0.0',
                'is_enabled' => true,
                'platforms' => ['web', 'app'],
            ],
            [
                'name' => 'cms',
                'label' => 'CMS',
                'description' => 'Content management system',
                'version' => '1.0.0',
                'is_enabled' => true,
                'platforms' => ['web', 'app'],
            ],
        ];
        
        foreach ($modules as $module) {
            Module::firstOrCreate(
                ['name' => $module['name']],
                $module
            );
        }
        
        $this->command->info('Modules seeded successfully');
    }
    
    private function seedTheme(): void
    {
        // Check if default theme already exists
        $existingTheme = Theme::where('name', 'default')->first();
        
        if ($existingTheme) {
            // Update existing theme
            $existingTheme->update([
                'label' => 'Default Theme',
                'primary_color' => '#007bff',
                'secondary_color' => '#6c757d',
                'accent_color' => '#ffc107',
                'background_color' => '#ffffff',
                'surface_color' => '#f8f9fa',
                'text_color' => '#212529',
                'text_secondary_color' => '#6c757d',
                'border_radius' => '8px',
                'ui_density' => 'normal',
                'font_family' => 'Roboto',
                'font_url' => 'https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap',
                'is_active' => true,
                'is_default' => true,
            ]);
            $this->command->info('Default theme updated');
        } else {
            // Create new theme
            Theme::create([
                'name' => 'default',
                'label' => 'Default Theme',
                'primary_color' => '#007bff',
                'secondary_color' => '#6c757d',
                'accent_color' => '#ffc107',
                'background_color' => '#ffffff',
                'surface_color' => '#f8f9fa',
                'text_color' => '#212529',
                'text_secondary_color' => '#6c757d',
                'border_radius' => '8px',
                'ui_density' => 'normal',
                'font_family' => 'Roboto',
                'font_url' => 'https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap',
                'is_active' => true,
                'is_default' => true,
            ]);
            $this->command->info('Default theme created');
        }
    }
    
    private function seedSettings(): void
    {
        $settingsService = app(SettingsService::class);
        
        $defaultSettings = [
            ['key' => 'app.name', 'value' => 'ZyptoseComm', 'group' => 'app', 'is_public' => true],
            ['key' => 'app.name_short', 'value' => 'ZC', 'group' => 'app', 'is_public' => true],
            ['key' => 'branding.company_name', 'value' => 'ZyptoseComm', 'group' => 'branding', 'is_public' => true],
            ['key' => 'theme.active', 'value' => 'default', 'group' => 'theme', 'is_public' => true],
        ];
        
        foreach ($defaultSettings as $setting) {
            $settingsService->set(
                $setting['key'],
                $setting['value'],
                $setting['group'],
                'string',
                $setting['is_public']
            );
        }
        
        $this->command->info('Default settings created');
    }
    
    private function seedLanguages(): void
    {
        $languages = [
            [
                'code' => 'en',
                'name' => 'English',
                'native_name' => 'English',
                'is_rtl' => false,
                'is_active' => true,
                'is_default' => true,
                'sort_order' => 0,
            ],
            [
                'code' => 'hi',
                'name' => 'Hindi',
                'native_name' => 'हिन्दी',
                'is_rtl' => false,
                'is_active' => true,
                'is_default' => false,
                'sort_order' => 1,
            ],
        ];
        
        // Ensure at least one default language exists
        $hasDefault = Language::where('is_default', true)->exists();
        
        foreach ($languages as $lang) {
            Language::firstOrCreate(
                ['code' => $lang['code']],
                $lang
            );
        }
        
        // If no default exists, set English as default
        if (!$hasDefault) {
            $english = Language::where('code', 'en')->first();
            if ($english) {
                $english->update([
                    'is_default' => true,
                    'is_active' => true,
                ]);
            }
        }
        
        // Ensure default language is always active
        Language::where('is_default', true)->update(['is_active' => true]);
        
        $this->command->info('Languages seeded successfully');
    }
    
    private function seedDefaultTranslations(): void
    {
        $default = Language::where('code', 'en')->where('is_default', true)->first();
        
        if (!$default) {
            $this->command->warn('No default language found. Skipping translation seeding.');
            return;
        }
        
        $translations = [
            // App general
            ['group' => 'app', 'key' => 'welcome', 'value' => 'Welcome'],
            ['group' => 'app', 'key' => 'hello', 'value' => 'Hello'],
            ['group' => 'app', 'key' => 'goodbye', 'value' => 'Goodbye'],
            ['group' => 'app', 'key' => 'loading', 'value' => 'Loading...'],
            ['group' => 'app', 'key' => 'error', 'value' => 'An error occurred'],
            ['group' => 'app', 'key' => 'success', 'value' => 'Success'],
            ['group' => 'app', 'key' => 'cancel', 'value' => 'Cancel'],
            ['group' => 'app', 'key' => 'save', 'value' => 'Save'],
            ['group' => 'app', 'key' => 'delete', 'value' => 'Delete'],
            ['group' => 'app', 'key' => 'edit', 'value' => 'Edit'],
            ['group' => 'app', 'key' => 'search', 'value' => 'Search'],
            ['group' => 'app', 'key' => 'filter', 'value' => 'Filter'],
            ['group' => 'app', 'key' => 'no_results', 'value' => 'No results found'],
            
            // Auth
            ['group' => 'auth', 'key' => 'login', 'value' => 'Login'],
            ['group' => 'auth', 'key' => 'logout', 'value' => 'Logout'],
            ['group' => 'auth', 'key' => 'register', 'value' => 'Register'],
            ['group' => 'auth', 'key' => 'email', 'value' => 'Email'],
            ['group' => 'auth', 'key' => 'password', 'value' => 'Password'],
            ['group' => 'auth', 'key' => 'forgot_password', 'value' => 'Forgot Password?'],
            ['group' => 'auth', 'key' => 'remember_me', 'value' => 'Remember Me'],
            ['group' => 'auth', 'key' => 'login_success', 'value' => 'Login successful'],
            ['group' => 'auth', 'key' => 'login_failed', 'value' => 'Invalid credentials'],
            
            // Checkout
            ['group' => 'checkout', 'key' => 'place_order', 'value' => 'Place Order'],
            ['group' => 'checkout', 'key' => 'cart', 'value' => 'Cart'],
            ['group' => 'checkout', 'key' => 'checkout', 'value' => 'Checkout'],
            ['group' => 'checkout', 'key' => 'shipping_address', 'value' => 'Shipping Address'],
            ['group' => 'checkout', 'key' => 'billing_address', 'value' => 'Billing Address'],
            ['group' => 'checkout', 'key' => 'payment_method', 'value' => 'Payment Method'],
            ['group' => 'checkout', 'key' => 'order_summary', 'value' => 'Order Summary'],
            ['group' => 'checkout', 'key' => 'subtotal', 'value' => 'Subtotal'],
            ['group' => 'checkout', 'key' => 'shipping', 'value' => 'Shipping'],
            ['group' => 'checkout', 'key' => 'tax', 'value' => 'Tax'],
            ['group' => 'checkout', 'key' => 'total', 'value' => 'Total'],
            ['group' => 'checkout', 'key' => 'order_placed', 'value' => 'Order placed successfully'],
            
            // Products
            ['group' => 'products', 'key' => 'add_to_cart', 'value' => 'Add to Cart'],
            ['group' => 'products', 'key' => 'buy_now', 'value' => 'Buy Now'],
            ['group' => 'products', 'key' => 'out_of_stock', 'value' => 'Out of Stock'],
            ['group' => 'products', 'key' => 'in_stock', 'value' => 'In Stock'],
            ['group' => 'products', 'key' => 'price', 'value' => 'Price'],
            ['group' => 'products', 'key' => 'description', 'value' => 'Description'],
            ['group' => 'products', 'key' => 'specifications', 'value' => 'Specifications'],
            ['group' => 'products', 'key' => 'reviews', 'value' => 'Reviews'],
            ['group' => 'products', 'key' => 'related_products', 'value' => 'Related Products'],
            
            // Orders
            ['group' => 'orders', 'key' => 'order_id', 'value' => 'Order ID'],
            ['group' => 'orders', 'key' => 'order_date', 'value' => 'Order Date'],
            ['group' => 'orders', 'key' => 'order_status', 'value' => 'Order Status'],
            ['group' => 'orders', 'key' => 'track_order', 'value' => 'Track Order'],
            ['group' => 'orders', 'key' => 'view_order', 'value' => 'View Order'],
            ['group' => 'orders', 'key' => 'cancel_order', 'value' => 'Cancel Order'],
            ['group' => 'orders', 'key' => 'order_items', 'value' => 'Order Items'],
            ['group' => 'orders', 'key' => 'pending', 'value' => 'Pending'],
            ['group' => 'orders', 'key' => 'processing', 'value' => 'Processing'],
            ['group' => 'orders', 'key' => 'shipped', 'value' => 'Shipped'],
            ['group' => 'orders', 'key' => 'delivered', 'value' => 'Delivered'],
            ['group' => 'orders', 'key' => 'cancelled', 'value' => 'Cancelled'],
        ];
        
        $created = 0;
        $updated = 0;
        
        foreach ($translations as $translation) {
            $existing = Translation::where('group', $translation['group'])
                ->where('key', $translation['key'])
                ->where('locale', $default->code)
                ->first();
            
            if ($existing) {
                $existing->update(['value' => $translation['value']]);
                $updated++;
            } else {
                Translation::create([
                    'group' => $translation['group'],
                    'key' => $translation['key'],
                    'locale' => $default->code,
                    'value' => $translation['value'],
                ]);
                $created++;
            }
        }
        
        $this->command->info("Default translations seeded: {$created} created, {$updated} updated");
    }
    
    private function seedCurrencies(): void
    {
        $currencies = [
            [
                'code' => 'INR',
                'name' => 'Indian Rupee',
                'symbol' => '₹',
                'symbol_position' => 'before',
                'decimals' => 2,
                'thousand_separator' => ',',
                'decimal_separator' => '.',
                'is_active' => true,
                'is_default' => true,
                'sort_order' => 0,
            ],
            [
                'code' => 'USD',
                'name' => 'US Dollar',
                'symbol' => '$',
                'symbol_position' => 'before',
                'decimals' => 2,
                'thousand_separator' => ',',
                'decimal_separator' => '.',
                'is_active' => true,
                'is_default' => false,
                'sort_order' => 1,
            ],
            [
                'code' => 'EUR',
                'name' => 'Euro',
                'symbol' => '€',
                'symbol_position' => 'before',
                'decimals' => 2,
                'thousand_separator' => '.',
                'decimal_separator' => ',',
                'is_active' => true,
                'is_default' => false,
                'sort_order' => 2,
            ],
            [
                'code' => 'GBP',
                'name' => 'British Pound',
                'symbol' => '£',
                'symbol_position' => 'before',
                'decimals' => 2,
                'thousand_separator' => ',',
                'decimal_separator' => '.',
                'is_active' => true,
                'is_default' => false,
                'sort_order' => 3,
            ],
        ];
        
        // Ensure at least one default currency exists
        $hasDefault = Currency::where('is_default', true)->exists();
        
        foreach ($currencies as $currency) {
            Currency::firstOrCreate(
                ['code' => $currency['code']],
                $currency
            );
        }
        
        // If no default exists, set INR as default
        if (!$hasDefault) {
            $inr = Currency::where('code', 'INR')->first();
            if ($inr) {
                $inr->update([
                    'is_default' => true,
                    'is_active' => true,
                ]);
            }
        }
        
        // Ensure default currency is always active
        Currency::where('is_default', true)->update(['is_active' => true]);
        
        $this->command->info('Currencies seeded successfully');
    }
}

