<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('runtime_flags', function (Blueprint $table) {
            $table->id();
            $table->string('platform')->default('all'); // android, ios, web, all
            $table->boolean('maintenance_enabled')->default(false);
            $table->text('maintenance_message')->nullable();
            $table->timestamp('maintenance_starts_at')->nullable();
            $table->timestamp('maintenance_ends_at')->nullable();
            $table->boolean('kill_switch_enabled')->default(false);
            $table->text('kill_switch_message')->nullable();
            $table->timestamp('kill_switch_until')->nullable();
            $table->timestamps();
            
            $table->unique('platform');
            $table->index('maintenance_enabled');
            $table->index('kill_switch_enabled');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('runtime_flags');
    }
};
