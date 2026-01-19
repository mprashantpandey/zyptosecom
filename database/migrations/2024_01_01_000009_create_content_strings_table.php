<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('content_strings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique(); // e.g., 'app.welcome_message', 'checkout.title'
            $table->string('locale', 10)->default('en');
            $table->text('value'); // Translated text
            $table->string('group')->default('general'); // app, checkout, product, etc.
            $table->string('platform')->default('all'); // web, app, all
            $table->json('variables')->nullable(); // Available variables for templating
            $table->timestamps();
            
            $table->index(['key', 'locale', 'platform']);
            $table->index('group');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('content_strings');
    }
};

