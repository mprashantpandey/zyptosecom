<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('brands', function (Blueprint $table) {
            if (!Schema::hasColumn('brands', 'short_name')) {
                $table->string('short_name')->nullable()->after('name');
            }
            if (!Schema::hasColumn('brands', 'company_name')) {
                $table->string('company_name')->nullable()->after('short_name');
            }
            if (!Schema::hasColumn('brands', 'support_email')) {
                $table->string('support_email')->nullable()->after('favicon');
            }
            if (!Schema::hasColumn('brands', 'support_phone')) {
                $table->string('support_phone')->nullable()->after('support_email');
            }
            if (!Schema::hasColumn('brands', 'published_at')) {
                $table->timestamp('published_at')->nullable()->after('sort_order');
            }
            if (!Schema::hasColumn('brands', 'is_published')) {
                $table->boolean('is_published')->default(false)->after('published_at');
            }
            // Rename logo fields to match requirements
            if (Schema::hasColumn('brands', 'logo') && !Schema::hasColumn('brands', 'logo_light_path')) {
                $table->renameColumn('logo', 'logo_light_path');
            }
            if (Schema::hasColumn('brands', 'logo_dark') && !Schema::hasColumn('brands', 'logo_dark_path')) {
                $table->renameColumn('logo_dark', 'logo_dark_path');
            }
            if (Schema::hasColumn('brands', 'icon') && !Schema::hasColumn('brands', 'app_icon_path')) {
                $table->renameColumn('icon', 'app_icon_path');
            }
            if (Schema::hasColumn('brands', 'favicon') && !Schema::hasColumn('brands', 'favicon_path')) {
                $table->renameColumn('favicon', 'favicon_path');
            }
            if (!Schema::hasColumn('brands', 'splash_path')) {
                $table->string('splash_path')->nullable()->after('favicon_path');
            }
        });
    }

    public function down(): void
    {
        Schema::table('brands', function (Blueprint $table) {
            $columns = ['short_name', 'company_name', 'support_email', 'support_phone', 'published_at', 'is_published', 'splash_path'];
            foreach ($columns as $column) {
                if (Schema::hasColumn('brands', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
