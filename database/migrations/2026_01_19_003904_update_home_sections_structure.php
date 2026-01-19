<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('home_sections', function (Blueprint $table) {
            // Add key (unique slug)
            if (!Schema::hasColumn('home_sections', 'key')) {
                $table->string('key')->unique()->nullable()->after('id');
            }
            
            // Rename platform to platform_scope for clarity
            if (Schema::hasColumn('home_sections', 'platform') && !Schema::hasColumn('home_sections', 'platform_scope')) {
                $table->renameColumn('platform', 'platform_scope');
            } elseif (!Schema::hasColumn('home_sections', 'platform_scope')) {
                $table->string('platform_scope')->default('both')->after('type');
            }
            
            // Rename data to settings_json for clarity
            if (Schema::hasColumn('home_sections', 'data') && !Schema::hasColumn('home_sections', 'settings_json')) {
                $table->renameColumn('data', 'settings_json');
            } elseif (!Schema::hasColumn('home_sections', 'settings_json')) {
                $table->json('settings_json')->nullable()->after('platform_scope');
            }
            
            // Keep style_json if exists, otherwise it's already nullable
            if (!Schema::hasColumn('home_sections', 'style')) {
                $table->json('style')->nullable()->after('settings_json');
            }
            
            // Rename is_active to is_enabled
            if (Schema::hasColumn('home_sections', 'is_active') && !Schema::hasColumn('home_sections', 'is_enabled')) {
                $table->renameColumn('is_active', 'is_enabled');
            } elseif (!Schema::hasColumn('home_sections', 'is_enabled')) {
                $table->boolean('is_enabled')->default(true)->after('sort_order');
            }
            
            // Add created_by, updated_by
            if (!Schema::hasColumn('home_sections', 'created_by')) {
                $table->foreignId('created_by')->nullable()->after('ends_at')->constrained('users')->onDelete('set null');
            }
            if (!Schema::hasColumn('home_sections', 'updated_by')) {
                $table->foreignId('updated_by')->nullable()->after('created_by')->constrained('users')->onDelete('set null');
            }
        });
    }

    public function down(): void
    {
        Schema::table('home_sections', function (Blueprint $table) {
            $columns = ['key', 'platform_scope', 'settings_json', 'is_enabled', 'created_by', 'updated_by'];
            foreach ($columns as $column) {
                if (Schema::hasColumn('home_sections', $column)) {
                    if (in_array($column, ['created_by', 'updated_by'])) {
                        $table->dropForeign(['created_by']);
                        $table->dropForeign(['updated_by']);
                    }
                    $table->dropColumn($column);
                }
            }
        });
    }
};
