<?php

use Illuminate\Support\Facades\Schedule;

// Register cron heartbeat to verify scheduler is running
Schedule::command('system:cron-heartbeat')->everyMinute();

