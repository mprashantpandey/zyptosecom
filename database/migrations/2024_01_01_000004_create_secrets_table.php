<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('secrets', function (Blueprint $table) {
            $table->id();
            $table->string('provider_type', 50); // 'payment', 'shipping', 'notification', 'auth'
            $table->string('provider_name', 50); // 'razorpay', 'shiprocket', 'firebase', etc.
            $table->string('key', 100); // e.g., 'api_key', 'secret_key', 'merchant_id'
            $table->text('encrypted_value'); // Always encrypted
            $table->string('environment')->default('production'); // sandbox, production
            $table->boolean('is_active')->default(true);
            $table->json('metadata')->nullable(); // Additional info
            $table->timestamps();
            $table->softDeletes();
            
            $table->unique(['provider_type', 'provider_name', 'key', 'environment']);
            $table->index(['provider_type', 'provider_name', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('secrets');
    }
};

