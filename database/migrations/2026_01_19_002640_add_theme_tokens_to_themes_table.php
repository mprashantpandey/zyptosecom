<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('themes', function (Blueprint $table) {
            if (!Schema::hasColumn('themes', 'brand_id')) {
                $table->foreignId('brand_id')->nullable()->after('id')->constrained('brands')->onDelete('set null');
            }
            if (!Schema::hasColumn('themes', 'tokens_json')) {
                $table->json('tokens_json')->nullable()->after('additional_colors');
            }
            if (!Schema::hasColumn('themes', 'mode')) {
                $table->enum('mode', ['draft', 'published'])->default('draft')->after('tokens_json');
            }
            if (!Schema::hasColumn('themes', 'published_at')) {
                $table->timestamp('published_at')->nullable()->after('mode');
            }
        });
    }

    public function down(): void
    {
        Schema::table('themes', function (Blueprint $table) {
            if (Schema::hasColumn('themes', 'brand_id')) {
                $table->dropForeign(['brand_id']);
                $table->dropColumn('brand_id');
            }
            $columns = ['tokens_json', 'mode', 'published_at'];
            foreach ($columns as $column) {
                if (Schema::hasColumn('themes', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
