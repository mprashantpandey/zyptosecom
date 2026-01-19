<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cms_pages', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('title');
            $table->text('content')->nullable(); // HTML or Markdown
            $table->string('locale', 10)->default('en');
            $table->string('platform')->default('all'); // web, app, all
            $table->enum('type', ['page', 'terms', 'privacy', 'about', 'help', 'custom'])->default('page');
            $table->boolean('is_active')->default(true);
            $table->json('metadata')->nullable(); // SEO, additional data
            $table->timestamps();
            
            $table->index(['slug', 'locale', 'platform']);
            $table->index(['type', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cms_pages');
    }
};

