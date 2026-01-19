<?php

namespace App\Filament\Pages;

use App\Core\Services\AppConfigService;
use App\Core\Services\AuditService;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class SystemTools extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-cog-6-tooth';
    protected static string $view = 'filament.pages.system-tools';
    protected static ?string $navigationGroup = 'System';
    protected static ?string $navigationLabel = 'System Tools';
    protected static ?int $navigationSort = 1;

    public ?array $data = [];

    public function mount(): void
    {
        abort_unless(auth()->user()?->hasRole('super_admin'), 403);
        abort_unless(auth()->user()?->can('system.tools.view'), 403);
        
        $this->loadSystemInfo();
    }

    protected function loadSystemInfo(): void
    {
        $this->data = [
            'app_url' => config('app.url'),
            'app_env' => config('app.env'),
            'timezone' => config('app.timezone'),
            'queue_connection' => config('queue.default'),
            'cache_driver' => config('cache.default'),
        ];
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('System Information')
                    ->schema([
                        Forms\Components\Placeholder::make('app_url')
                            ->label('Application URL')
                            ->content($this->data['app_url'] ?? 'N/A'),
                        Forms\Components\Placeholder::make('app_env')
                            ->label('Environment')
                            ->content(strtoupper($this->data['app_env'] ?? 'N/A')),
                        Forms\Components\Placeholder::make('timezone')
                            ->label('Timezone')
                            ->content($this->data['timezone'] ?? 'N/A'),
                        Forms\Components\Placeholder::make('queue_connection')
                            ->label('Queue Connection')
                            ->content($this->data['queue_connection'] ?? 'N/A'),
                        Forms\Components\Placeholder::make('cache_driver')
                            ->label('Cache Driver')
                            ->content($this->data['cache_driver'] ?? 'N/A'),
                    ])
                    ->columns(3),

                Forms\Components\Section::make('Queue Status')
                    ->schema([
                        Forms\Components\Placeholder::make('queue_pending')
                            ->label('Pending Jobs')
                            ->content(fn () => DB::table('jobs')->count()),
                        Forms\Components\Placeholder::make('queue_failed')
                            ->label('Failed Jobs')
                            ->content(fn () => DB::table('failed_jobs')->count()),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Quick Links')
                    ->schema([
                        Forms\Components\Placeholder::make('links')
                            ->label('')
                            ->content('Use the navigation menu to access: Maintenance Mode, Kill Switch, App Version Control'),
                    ]),
            ])
            ->statePath('data');
    }

    public function clearAppConfigCache(): void
    {
        app(AppConfigService::class)->clearCache();
        Cache::forget('app_config:v1');
        
        AuditService::log('system.cache_cleared', null, [], ['cache' => 'app_config'], ['module' => 'system']);
        
        Notification::make()
            ->title('App config cache cleared')
            ->success()
            ->send();
    }

    public function clearHomeLayoutCache(): void
    {
        Cache::forget('home_layout:v1:web');
        Cache::forget('home_layout:v1:app');
        
        AuditService::log('system.cache_cleared', null, [], ['cache' => 'home_layout'], ['module' => 'system']);
        
        Notification::make()
            ->title('Home layout cache cleared')
            ->success()
            ->send();
    }

    public function clearGeneralCache(): void
    {
        Cache::flush();
        
        AuditService::log('system.cache_cleared', null, [], ['cache' => 'all'], ['module' => 'system']);
        
        Notification::make()
            ->title('All cache cleared')
            ->success()
            ->send();
    }

    public function optimizeCache(): void
    {
        Artisan::call('config:cache');
        Artisan::call('route:cache');
        Artisan::call('view:cache');
        
        AuditService::log('system.cache_optimized', null, [], [], ['module' => 'system']);
        
        Notification::make()
            ->title('Cache optimized')
            ->body('Config, route, and view caches have been regenerated')
            ->success()
            ->send();
    }

    protected function getFormActions(): array
    {
        return [
            Forms\Components\Actions\Action::make('clear_app_config')
                ->label('Clear App Config Cache')
                ->color('warning')
                ->action('clearAppConfigCache')
                ->requiresConfirmation(),
            Forms\Components\Actions\Action::make('clear_home_layout')
                ->label('Clear Home Layout Cache')
                ->color('warning')
                ->action('clearHomeLayoutCache')
                ->requiresConfirmation(),
            Forms\Components\Actions\Action::make('clear_general')
                ->label('Clear All Cache')
                ->color('danger')
                ->action('clearGeneralCache')
                ->requiresConfirmation(),
            Forms\Components\Actions\Action::make('optimize')
                ->label('Optimize Cache')
                ->color('success')
                ->action('optimizeCache')
                ->requiresConfirmation(),
        ];
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->hasRole('super_admin') && auth()->user()?->can('system.tools.view') ?? false;
    }
}
