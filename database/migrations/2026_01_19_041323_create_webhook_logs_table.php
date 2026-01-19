<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('webhook_logs', function (Blueprint $table) {
            $table->id();
            $table->string('provider'); // razorpay, stripe, shiprocket, etc.
            $table->string('event_type')->nullable(); // payment.captured, shipment.created, etc.
            $table->json('payload'); // Full webhook payload (sanitized, no secrets)
            $table->string('status')->default('received'); // received|processed|failed|ignored
            $table->text('error_message')->nullable();
            $table->string('ip_address')->nullable();
            $table->text('user_agent')->nullable();
            $table->string('signature')->nullable(); // For verification
            $table->boolean('signature_valid')->default(false);
            $table->timestamps();

            $table->index(['provider', 'status', 'created_at']);
            $table->index(['event_type', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_logs');
    }
};
