<?php

namespace App\Filament\Pages\System;

use App\Core\Services\AuditService;
use App\Models\CronHeartbeat;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Artisan;

class CronSetup extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $slug = 'system/cron';
    protected static ?string $navigationIcon = 'heroicon-o-clock';
    protected static string $view = 'filament.pages.system.cron-setup';
    protected static ?string $navigationGroup = 'System';
    protected static ?string $navigationLabel = 'Cron Setup';
    protected static ?int $navigationSort = 2;

    public function mount(): void
    {
        abort_unless(
            auth()->user()?->hasRole('super_admin') || auth()->user()?->can('system.cron.view'),
            403
        );
    }

    public function getProjectPath(): string
    {
        return base_path();
    }

    public function getCronCommand(): string
    {
        return "* * * * * cd {$this->getProjectPath()} && php artisan schedule:run >> /dev/null 2>&1";
    }

    public function getQueueCommand(): string
    {
        return "php artisan queue:work --sleep=3 --tries=3 --timeout=90";
    }

    public function getCronStatus(): array
    {
        $heartbeat = CronHeartbeat::where('key', 'schedule')->first();
        
        if (!$heartbeat || !$heartbeat->last_ran_at) {
            return [
                'status' => 'not_running',
                'message' => 'Cron is not running',
                'last_ran' => null,
            ];
        }

        $lastRan = $heartbeat->last_ran_at;
        $minutesAgo = now()->diffInMinutes($lastRan);

        if ($minutesAgo <= 3) {
            return [
                'status' => 'working',
                'message' => 'Cron is working ✅',
                'last_ran' => $lastRan,
                'minutes_ago' => $minutesAgo,
            ];
        }

        return [
            'status' => 'stale',
            'message' => 'Cron may not be running ❌',
            'last_ran' => $lastRan,
            'minutes_ago' => $minutesAgo,
        ];
    }

    public function runSchedulerNow(): void
    {
        abort_unless(
            auth()->user()?->hasRole('super_admin') || auth()->user()?->can('system.cron.test'),
            403
        );

        try {
            Artisan::call('schedule:run');
            $output = trim(Artisan::output());

            AuditService::log('system.schedule_run_triggered', null, [], ['output' => $output], ['module' => 'system']);

            Notification::make()
                ->title('Scheduler executed successfully')
                ->body('Scheduled tasks have been run.')
                ->success()
                ->send();
        } catch (\Exception $e) {
            AuditService::log('system.schedule_run_triggered', null, [], ['status' => 'failed', 'error' => $e->getMessage()], ['module' => 'system']);

            Notification::make()
                ->title('Scheduler execution failed')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    public function runHeartbeatNow(): void
    {
        abort_unless(
            auth()->user()?->hasRole('super_admin') || auth()->user()?->can('system.cron.test'),
            403
        );

        try {
            Artisan::call('system:cron-heartbeat');
            $output = trim(Artisan::output());

            AuditService::log('system.cron_heartbeat_triggered', null, [], ['output' => $output], ['module' => 'system']);

            Notification::make()
                ->title('Heartbeat updated successfully')
                ->success()
                ->send();
        } catch (\Exception $e) {
            AuditService::log('system.cron_heartbeat_triggered', null, [], ['status' => 'failed', 'error' => $e->getMessage()], ['module' => 'system']);

            Notification::make()
                ->title('Heartbeat update failed')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    public static function canAccess(): bool
    {
        return auth()->user()?->hasRole('super_admin') || auth()->user()?->can('system.cron.view') ?? false;
    }
}

