<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->string('type'); // 'order_placed', 'payment_received', etc.
            $table->string('channel'); // 'push', 'sms', 'email', 'whatsapp'
            $table->morphs('notifiable'); // User, Order, etc.
            $table->text('subject')->nullable();
            $table->text('message');
            $table->json('data')->nullable(); // Additional data
            $table->enum('status', ['pending', 'sent', 'failed'])->default('pending');
            $table->string('provider')->nullable(); // Firebase, Twilio, SendGrid, etc.
            $table->string('provider_message_id')->nullable(); // External provider ID
            $table->text('error_message')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
            
            $table->index(['notifiable_type', 'notifiable_id', 'status']);
            $table->index(['channel', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};

