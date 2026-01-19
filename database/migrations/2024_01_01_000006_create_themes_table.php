<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('themes', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique(); // e.g., 'default', 'dark', 'corporate'
            $table->string('label'); // Display name
            $table->string('primary_color')->default('#007bff');
            $table->string('secondary_color')->default('#6c757d');
            $table->string('accent_color')->default('#ffc107');
            $table->string('background_color')->default('#ffffff');
            $table->string('surface_color')->default('#f8f9fa');
            $table->string('text_color')->default('#212529');
            $table->string('text_secondary_color')->default('#6c757d');
            $table->string('border_radius')->default('8px'); // e.g., '8px', '12px', 'rounded-full'
            $table->string('ui_density')->default('normal'); // compact, normal, comfortable
            $table->string('font_family')->nullable(); // Google Font name or custom
            $table->string('font_url')->nullable(); // For Google Fonts or custom fonts
            $table->json('additional_colors')->nullable(); // success, error, warning, info colors
            $table->boolean('is_active')->default(true);
            $table->boolean('is_default')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('themes');
    }
};

