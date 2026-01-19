<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_logs', function (Blueprint $table) {
            $table->id();
            $table->string('channel'); // email|sms|push|whatsapp
            $table->string('provider_key');
            $table->string('event_key')->nullable();
            $table->string('recipient'); // email/phone/device token
            $table->string('subject')->nullable();
            $table->json('payload')->nullable(); // Safe, no secrets
            $table->string('status')->default('queued'); // queued|sent|failed
            $table->text('error_message')->nullable();
            $table->string('external_id')->nullable(); // Provider's message ID
            $table->timestamp('sent_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index('event_key');
            $table->index('channel');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_logs');
    }
};
