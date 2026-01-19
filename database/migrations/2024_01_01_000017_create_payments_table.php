<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->onDelete('cascade');
            $table->string('transaction_id')->unique();
            $table->string('provider'); // razorpay, payu, stripe, etc.
            $table->string('provider_transaction_id')->nullable(); // External provider ID
            $table->enum('method', ['cod', 'razorpay', 'payu', 'stripe', 'cashfree', 'phonepe'])->default('cod');
            $table->enum('status', ['pending', 'processing', 'completed', 'failed', 'refunded'])->default('pending');
            $table->decimal('amount', 10, 2);
            $table->string('currency', 3)->default('INR');
            $table->json('provider_response')->nullable(); // Raw response from provider
            $table->json('metadata')->nullable(); // Additional payment data
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
            
            $table->index(['order_id', 'status']);
            $table->index('transaction_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};

