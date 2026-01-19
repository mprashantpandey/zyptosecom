<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_templates', function (Blueprint $table) {
            $table->id();
            $table->string('channel'); // email|sms|push|whatsapp
            $table->string('name'); // Friendly name
            $table->string('subject')->nullable(); // For email/push
            $table->longText('body'); // HTML allowed for email
            $table->json('variables')->nullable(); // Array of allowed variables
            $table->string('locale')->nullable()->default('en'); // e.g. en, hi
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['channel', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_templates');
    }
};
