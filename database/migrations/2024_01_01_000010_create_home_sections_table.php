<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('home_sections', function (Blueprint $table) {
            $table->id();
            $table->string('type'); // 'banner', 'grid', 'carousel', 'categories', 'products', 'custom'
            $table->string('title')->nullable();
            $table->string('platform')->default('all'); // web, app, all
            $table->json('data'); // Section-specific data (images, products, categories, etc.)
            $table->json('style')->nullable(); // Styling configuration
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamps();
            
            $table->index(['platform', 'is_active', 'sort_order']);
            $table->index(['starts_at', 'ends_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('home_sections');
    }
};

