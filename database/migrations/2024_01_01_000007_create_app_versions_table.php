<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('app_versions', function (Blueprint $table) {
            $table->id();
            $table->string('platform'); // 'android', 'ios', 'web'
            $table->string('version'); // e.g., '1.2.0'
            $table->string('build_number')->nullable(); // e.g., '123' for mobile
            $table->enum('update_type', ['none', 'optional', 'force'])->default('none');
            $table->text('update_message')->nullable(); // Message shown to users
            $table->string('store_url')->nullable(); // App Store / Play Store URL
            $table->string('download_url')->nullable(); // Direct download URL (if any)
            $table->boolean('is_minimum_supported')->default(false); // Force update if below this
            $table->boolean('maintenance_mode')->default(false);
            $table->text('maintenance_message')->nullable();
            $table->timestamp('released_at')->nullable();
            $table->timestamps();
            
            $table->unique(['platform', 'version']);
            $table->index(['platform', 'is_minimum_supported']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('app_versions');
    }
};

