<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('modules', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique(); // e.g., 'payments', 'shipping', 'wallet'
            $table->string('label'); // Display name
            $table->text('description')->nullable();
            $table->string('version')->default('1.0.0');
            $table->boolean('is_enabled')->default(true);
            $table->json('platforms'); // web, app, both
            $table->string('min_app_version')->nullable(); // e.g., '1.2.0' for app-only features
            $table->timestamp('enabled_at')->nullable();
            $table->timestamp('disabled_at')->nullable();
            $table->json('metadata')->nullable(); // Additional config
            $table->timestamps();
            $table->softDeletes();
            
            $table->index('is_enabled');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('modules');
    }
};

