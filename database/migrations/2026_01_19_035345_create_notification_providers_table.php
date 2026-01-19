<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_providers', function (Blueprint $table) {
            $table->id();
            $table->string('channel')->index(); // email|sms|push|whatsapp
            $table->string('provider_key'); // smtp|sendgrid|mailgun|twilio|msg91|firebase|gupshup|wati|interakt
            $table->string('name'); // Friendly name
            $table->boolean('is_enabled')->default(false);
            $table->string('environment')->default('sandbox'); // sandbox|production
            $table->foreignId('secret_id')->nullable()->constrained('secrets')->onDelete('set null');
            $table->json('config')->nullable(); // Non-secret options like sender_id, from_name
            $table->timestamps();

            $table->unique(['channel', 'provider_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_providers');
    }
};
