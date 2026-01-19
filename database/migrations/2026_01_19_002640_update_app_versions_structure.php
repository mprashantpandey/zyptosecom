<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('app_versions', function (Blueprint $table) {
            // Rename version to latest_version for clarity
            if (Schema::hasColumn('app_versions', 'version') && !Schema::hasColumn('app_versions', 'latest_version')) {
                $table->renameColumn('version', 'latest_version');
            }
            if (Schema::hasColumn('app_versions', 'build_number') && !Schema::hasColumn('app_versions', 'latest_build')) {
                $table->renameColumn('build_number', 'latest_build');
            }
            // Add min_version and min_build
            if (!Schema::hasColumn('app_versions', 'min_version')) {
                $table->string('min_version')->nullable()->after('latest_version');
            }
            if (!Schema::hasColumn('app_versions', 'min_build')) {
                $table->string('min_build')->nullable()->after('min_version');
            }
            // Rename is_minimum_supported to track separately
        });
    }

    public function down(): void
    {
        Schema::table('app_versions', function (Blueprint $table) {
            if (Schema::hasColumn('app_versions', 'latest_version') && !Schema::hasColumn('app_versions', 'version')) {
                $table->renameColumn('latest_version', 'version');
            }
            if (Schema::hasColumn('app_versions', 'latest_build') && !Schema::hasColumn('app_versions', 'build_number')) {
                $table->renameColumn('latest_build', 'build_number');
            }
            $columns = ['min_version', 'min_build'];
            foreach ($columns as $column) {
                if (Schema::hasColumn('app_versions', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
