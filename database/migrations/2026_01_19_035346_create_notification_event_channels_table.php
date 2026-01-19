<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_event_channels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('notification_event_id')->constrained('notification_events')->onDelete('cascade');
            $table->string('channel'); // email|sms|push|whatsapp
            $table->boolean('enabled')->default(false);
            $table->boolean('quiet_hours_respect')->default(true);
            $table->foreignId('template_id')->nullable(); // Will add constraint after templates table is created
            $table->timestamps();

            $table->unique(['notification_event_id', 'channel']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_event_channels');
    }
};
