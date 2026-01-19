<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SystemCronHeartbeatCommand extends Command
{
    protected $signature = 'system:cron-heartbeat {--key=schedule : Heartbeat key}';
    protected $description = 'Update cron heartbeat to verify scheduler is running';

    public function handle(): int
    {
        $key = $this->option('key');
        
        try {
            DB::table('cron_heartbeats')->updateOrInsert(
                ['key' => $key],
                [
                    'last_ran_at' => now(),
                    'status' => 'ok',
                    'last_output' => 'Heartbeat updated successfully',
                    'updated_at' => now(),
                ]
            );
            
            $this->info("Heartbeat updated for key: {$key}");
            return 0;
        } catch (\Exception $e) {
            DB::table('cron_heartbeats')->updateOrInsert(
                ['key' => $key],
                [
                    'last_ran_at' => now(),
                    'status' => 'fail',
                    'last_output' => $e->getMessage(),
                    'updated_at' => now(),
                ]
            );
            
            $this->error("Heartbeat update failed: " . $e->getMessage());
            return 1;
        }
    }
}
