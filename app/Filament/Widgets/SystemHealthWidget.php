<?php

namespace App\Filament\Widgets;

use Filament\Widgets\Widget;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\DB;

class SystemHealthWidget extends Widget
{
    protected static string $view = 'filament.widgets.system-health-widget';
    
    protected int | string | array $columnSpan = 'full';

    public function getViewData(): array
    {
        // Check queue status
        $queueSize = 0;
        try {
            $queueSize = DB::table('jobs')->count();
        } catch (\Exception $e) {
            // Table might not exist
        }

        // Check failed jobs
        $failedJobs = 0;
        try {
            $failedJobs = DB::table('failed_jobs')->count();
        } catch (\Exception $e) {
            // Table might not exist
        }

        // Check database connection
        $dbStatus = 'healthy';
        try {
            DB::connection()->getPdo();
        } catch (\Exception $e) {
            $dbStatus = 'error';
        }

        // Check webhook failures (last 24 hours)
        $webhookFailures = 0;
        try {
            $webhookFailures = DB::table('webhooks')
                ->where('status', 'failed')
                ->where('created_at', '>=', now()->subDay())
                ->count();
        } catch (\Exception $e) {
            // Table might not exist
        }

        return [
            'queueSize' => $queueSize,
            'failedJobs' => $failedJobs,
            'dbStatus' => $dbStatus,
            'webhookFailures' => $webhookFailures,
        ];
    }
}

