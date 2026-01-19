<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique(); // e.g., 'app.name', 'theme.primary_color'
            $table->text('value')->nullable(); // Can be JSON, string, number
            $table->string('type')->default('string'); // string, json, number, boolean, file
            $table->string('group')->default('general'); // general, branding, app, payments, etc.
            $table->text('description')->nullable();
            $table->boolean('is_public')->default(false); // Can be exposed to public APIs
            $table->boolean('is_encrypted')->default(false); // Should value be encrypted
            $table->json('metadata')->nullable(); // Validation rules, options, etc.
            $table->timestamps();
            
            $table->index(['group', 'is_public']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};

