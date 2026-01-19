<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('home_section_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('home_section_id')->constrained('home_sections')->onDelete('cascade');
            $table->string('title')->nullable();
            $table->string('subtitle')->nullable();
            $table->string('image_path')->nullable();
            $table->string('badge_text')->nullable();
            $table->string('cta_text')->nullable();
            $table->string('action_type')->default('none'); // product|category|collection|search|url|none
            $table->text('action_payload')->nullable(); // product_id/category_id/query/url
            $table->string('platform_scope')->default('both'); // web|app|both (optional override)
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->integer('sort_order')->default(0);
            $table->json('meta_json')->nullable(); // extra style fields
            $table->timestamps();
            
            $table->index(['home_section_id', 'sort_order']);
            $table->index(['action_type', 'platform_scope']);
            $table->index(['starts_at', 'ends_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('home_section_items');
    }
};
