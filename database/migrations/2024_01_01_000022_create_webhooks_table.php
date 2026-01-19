<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('webhooks', function (Blueprint $table) {
            $table->id();
            $table->string('provider_type'); // 'payment', 'shipping', 'notification'
            $table->string('provider_name'); // 'razorpay', 'shiprocket', etc.
            $table->string('event_type'); // 'payment.success', 'order.shipped', etc.
            $table->string('status')->default('pending'); // pending, processed, failed
            $table->json('payload'); // Raw webhook payload
            $table->json('processed_data')->nullable(); // Processed/transformed data
            $table->text('error_message')->nullable();
            $table->integer('retry_count')->default(0);
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
            
            $table->index(['provider_type', 'provider_name', 'status']);
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhooks');
    }
};

