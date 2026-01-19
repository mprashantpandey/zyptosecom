<?php

namespace App\Filament\Pages\System;

use App\Core\Services\AuditService;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Queue;

class SystemHealth extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $slug = 'system/health';
    protected static ?string $navigationIcon = 'heroicon-o-heart';
    protected static string $view = 'filament.pages.system.system-health';
    protected static ?string $navigationGroup = 'System';
    protected static ?string $navigationLabel = 'System Health';
    protected static ?int $navigationSort = 1;

    public ?string $logContent = '';
    public bool $errorsOnly = false;

    public function mount(): void
    {
        abort_unless(
            auth()->user()?->hasRole('super_admin') || auth()->user()?->can('system.health.view'),
            403
        );
    }

    public function getSystemStatus(): array
    {
        $env = config('app.env');
        $debug = config('app.debug');
        $cacheDriver = config('cache.default');
        $queueDriver = config('queue.default');
        
        // DB connection test
        $dbOk = false;
        try {
            DB::connection()->getPdo();
            $dbOk = true;
        } catch (\Exception $e) {
            $dbOk = false;
        }

        // Storage writable
        $storageWritable = is_writable(storage_path());

        // Last cron run
        $lastCron = DB::table('cron_heartbeats')
            ->where('key', 'schedule')
            ->value('last_ran_at');

        return [
            'env' => $env,
            'debug' => $debug,
            'cache_driver' => $cacheDriver,
            'queue_driver' => $queueDriver,
            'db_ok' => $dbOk,
            'storage_writable' => $storageWritable,
            'last_cron' => $lastCron,
        ];
    }

    public function clearCache(string $type): void
    {
        abort_unless(
            auth()->user()?->hasRole('super_admin') || auth()->user()?->can('system.tools.run'),
            403
        );

        try {
            $before = ['cache_type' => $type];
            
            match($type) {
                'cache' => Artisan::call('cache:clear'),
                'config' => Artisan::call('config:clear'),
                'route' => Artisan::call('route:clear'),
                'view' => Artisan::call('view:clear'),
                'optimize' => Artisan::call('optimize:clear'),
                default => throw new \Exception('Unknown cache type'),
            };

            $output = trim(Artisan::output());
            $after = ['cache_type' => $type, 'output' => $output, 'status' => 'success'];

            AuditService::log("system.{$type}_cleared", null, $before, $after, ['module' => 'system']);

            Notification::make()
                ->title(ucfirst($type) . ' cleared successfully')
                ->success()
                ->send();
        } catch (\Exception $e) {
            AuditService::log("system.{$type}_cleared", null, [], ['status' => 'failed', 'error' => $e->getMessage()], ['module' => 'system']);

            Notification::make()
                ->title('Failed to clear ' . $type)
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    public function loadLogs(): void
    {
        $logPath = storage_path('logs/laravel.log');
        
        if (!File::exists($logPath)) {
            $this->logContent = 'No log file found.';
            return;
        }

        $lines = File::lines($logPath);
        $allLines = $lines->toArray();
        
        // Get last 200 lines
        $lastLines = array_slice($allLines, -200);
        
        if ($this->errorsOnly) {
            $lastLines = array_filter($lastLines, fn($line) => stripos($line, 'ERROR') !== false || stripos($line, 'Exception') !== false);
        }
        
        $this->logContent = implode("\n", $lastLines);
    }

    public function rebuildPermissions(): void
    {
        abort_unless(auth()->user()?->hasRole('super_admin'), 403);

        try {
            Artisan::call('db:seed', ['--class' => 'PermissionSeeder']);
            $output = trim(Artisan::output());

            AuditService::log('system.permissions_rebuilt', null, [], ['status' => 'success'], ['module' => 'system']);

            Notification::make()
                ->title('Permissions rebuilt successfully')
                ->success()
                ->send();
        } catch (\Exception $e) {
            AuditService::log('system.permissions_rebuilt', null, [], ['status' => 'failed', 'error' => $e->getMessage()], ['module' => 'system']);

            Notification::make()
                ->title('Failed to rebuild permissions')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    public function syncIntegrations(): void
    {
        abort_unless(
            auth()->user()?->hasRole('super_admin') || auth()->user()?->can('integrations.sync'),
            403
        );

        try {
            Artisan::call('providers:sync');
            $output = trim(Artisan::output());

            AuditService::log('system.integrations_synced', null, [], ['status' => 'success'], ['module' => 'system']);

            Notification::make()
                ->title('Integrations list updated')
                ->success()
                ->send();
        } catch (\Exception $e) {
            AuditService::log('system.integrations_synced', null, [], ['status' => 'failed', 'error' => $e->getMessage()], ['module' => 'system']);

            Notification::make()
                ->title('Failed to sync integrations')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    public function checkPhpExtensions(): array
    {
        $required = ['curl', 'mbstring', 'openssl', 'gd', 'fileinfo'];
        $results = [];
        
        foreach ($required as $ext) {
            $results[$ext] = extension_loaded($ext);
        }
        
        return $results;
    }

    public function checkServerLimits(): array
    {
        return [
            'upload_max_filesize' => ini_get('upload_max_filesize'),
            'post_max_size' => ini_get('post_max_size'),
            'memory_limit' => ini_get('memory_limit'),
            'max_execution_time' => ini_get('max_execution_time'),
        ];
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->hasRole('super_admin') || auth()->user()?->can('system.health.view') ?? false;
    }
}

