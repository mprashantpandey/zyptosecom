<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('providers', function (Blueprint $table) {
            $table->id();
            $table->string('type'); // 'payment', 'shipping', 'notification', 'auth', 'storage'
            $table->string('name'); // 'razorpay', 'shiprocket', 'firebase', 'smtp', etc.
            $table->string('driver_class'); // Fully qualified class name
            $table->string('label'); // Display name
            $table->text('description')->nullable();
            $table->boolean('is_enabled')->default(false);
            $table->string('environment')->default('sandbox'); // sandbox, production
            $table->json('config_schema')->nullable(); // JSON schema for configuration UI
            $table->json('metadata')->nullable(); // Additional provider info
            $table->integer('priority')->default(0); // For ordering
            $table->timestamps();
            
            $table->unique(['type', 'name']);
            $table->index(['type', 'is_enabled']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('providers');
    }
};

