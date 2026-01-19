<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;

class AppHealthCommand extends Command
{
    protected $signature = 'app:health';
    protected $description = 'Check application health and feature completeness';

    public function handle(): int
    {
        $this->info('🏥 Application Health Check');
        $this->newLine();

        $this->checkDatabase();
        $this->checkCache();
        $this->checkStorage();
        $this->checkFeatureCompleteness();

        return 0;
    }

    protected function checkDatabase(): void
    {
        $this->line('📊 Database Connection...');
        try {
            DB::connection()->getPdo();
            $this->info('   ✅ Database connection OK');
        } catch (\Exception $e) {
            $this->error('   ❌ Database connection failed: ' . $e->getMessage());
        }
        $this->newLine();
    }

    protected function checkCache(): void
    {
        $this->line('💾 Cache System...');
        try {
            $key = 'health_check_' . time();
            Cache::put($key, 'test', 60);
            $value = Cache::get($key);
            if ($value === 'test') {
                Cache::forget($key);
                $this->info('   ✅ Cache system working');
            } else {
                $this->warn('   ⚠️  Cache may not be working correctly');
            }
        } catch (\Exception $e) {
            $this->error('   ❌ Cache system failed: ' . $e->getMessage());
        }
        $this->newLine();
    }

    protected function checkStorage(): void
    {
        $this->line('📁 Storage...');
        $storagePath = storage_path('app');
        $logsPath = storage_path('logs');
        $publicStorage = public_path('storage');

        $checks = [
            'Storage directory writable' => is_writable($storagePath),
            'Logs directory writable' => is_writable($logsPath),
            'Storage link exists' => is_link($publicStorage) || File::exists($publicStorage),
        ];

        $allOk = true;
        foreach ($checks as $check => $result) {
            if ($result) {
                $this->info("   ✅ {$check}");
            } else {
                $this->error("   ❌ {$check}");
                $allOk = false;
            }
        }

        if (!$allOk) {
            $this->warn('   Run: php artisan storage:link');
        }
        $this->newLine();
    }

    protected function checkFeatureCompleteness(): void
    {
        $this->line('🎯 Feature Completeness...');
        $this->newLine();

        $reportPath = storage_path('app/reports/features-status.json');
        if (!File::exists($reportPath)) {
            $this->warn('   ⚠️  Feature status report not found. Run: php artisan app:features');
            $this->newLine();
            return;
        }

        try {
            $report = json_decode(File::get($reportPath), true);
            $modules = $report['modules'] ?? [];

            // Overall completion
            $allTotal = array_sum(array_column($modules, 'total'));
            $allImplemented = array_sum(array_column($modules, 'implemented'));
            $allHidden = array_sum(array_column($modules, 'hidden'));
            $allVisible = $allTotal - $allHidden;
            $allCompletion = $allVisible > 0 ? round(($allImplemented / $allVisible) * 100, 1) : 100;

            $this->line("   📊 Overall Completion: {$allCompletion}% ({$allImplemented}/{$allVisible} visible items)");

            // MVP completion
            $mvpModules = array_filter($modules, fn($m) => ($m['for_mvp'] ?? false));
            $mvpTotal = array_sum(array_column($mvpModules, 'total'));
            $mvpImplemented = array_sum(array_column($mvpModules, 'implemented'));
            $mvpHidden = array_sum(array_column($mvpModules, 'hidden'));
            $mvpVisible = $mvpTotal - $mvpHidden;
            $mvpCompletion = $mvpVisible > 0 ? round(($mvpImplemented / $mvpVisible) * 100, 1) : 100;

            $this->line("   🎯 MVP Completion: {$mvpCompletion}% ({$mvpImplemented}/{$mvpVisible} visible items)");
            $this->newLine();

            // MVP modules not complete
            $incompleteMvp = array_filter($mvpModules, fn($m) => ($m['completion'] ?? 0) < 100);
            if (!empty($incompleteMvp)) {
                $this->warn('   ⚠️  MVP Modules Not Complete:');
                foreach ($incompleteMvp as $key => $module) {
                    $remaining = ($module['not_implemented'] ?? 0) + ($module['partial'] ?? 0);
                    $this->line("      - {$module['label']}: {$remaining} items remaining ({$module['completion']}% complete)");
                }
                $this->newLine();
            }

            // CodeCanyon readiness
            if ($mvpCompletion >= 100) {
                $this->info('   ✅ Ready for CodeCanyon: YES');
            } else {
                $remaining = $mvpVisible - $mvpImplemented;
                $this->error('   ❌ Ready for CodeCanyon: NO');
                $this->line("      Complete {$remaining} more MVP items to reach 100%");
            }
            $this->newLine();

            // Report age
            $generatedAt = $report['generated_at'] ?? null;
            if ($generatedAt) {
                $age = now()->diffInHours(\Carbon\Carbon::parse($generatedAt));
                if ($age > 24) {
                    $this->warn("   ⚠️  Report is {$age} hours old. Run: php artisan app:features");
                } else {
                    $this->info("   📅 Report generated {$age} hours ago");
                }
            }

        } catch (\Exception $e) {
            $this->error('   ❌ Failed to read feature status: ' . $e->getMessage());
        }
        $this->newLine();
    }
}

