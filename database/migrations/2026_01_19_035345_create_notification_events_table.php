<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_events', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique(); // order_placed, order_shipped, otp, etc.
            $table->string('name'); // Friendly name
            $table->text('description')->nullable();
            $table->boolean('is_system')->default(false); // Protected defaults
            $table->boolean('is_critical')->default(false); // Ignore quiet hours
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_events');
    }
};
