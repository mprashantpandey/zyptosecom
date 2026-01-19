<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('cms_pages', function (Blueprint $table) {
            // Visibility controls
            $table->boolean('show_in_web')->default(true)->after('platform');
            $table->boolean('show_in_app')->default(true)->after('show_in_web');
            $table->boolean('show_in_footer')->default(false)->after('show_in_app');
            $table->boolean('show_in_header')->default(false)->after('show_in_footer');
            $table->boolean('requires_login')->default(false)->after('show_in_header');
            
            // SEO fields (stored in metadata JSON, but also as separate columns for easier access)
            $table->string('seo_title')->nullable()->after('title');
            $table->text('seo_description')->nullable()->after('seo_title');
            $table->text('seo_keywords')->nullable()->after('seo_description');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cms_pages', function (Blueprint $table) {
            $table->dropColumn([
                'show_in_web',
                'show_in_app',
                'show_in_footer',
                'show_in_header',
                'requires_login',
                'seo_title',
                'seo_description',
                'seo_keywords',
            ]);
        });
    }
};
