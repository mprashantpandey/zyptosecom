<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('cron_heartbeats', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique()->default('schedule');
            $table->timestamp('last_ran_at')->nullable();
            $table->text('last_output')->nullable();
            $table->string('status')->default('ok'); // ok, fail
            $table->timestamps();
            
            $table->index('key');
            $table->index('last_ran_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cron_heartbeats');
    }
};
