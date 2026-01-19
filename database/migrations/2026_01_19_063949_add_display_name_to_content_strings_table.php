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
        Schema::table('content_strings', function (Blueprint $table) {
            $table->string('display_name')->nullable()->after('key');
            $table->text('usage_hint')->nullable()->after('value');
            $table->boolean('is_system')->default(false)->after('group');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('content_strings', function (Blueprint $table) {
            $table->dropColumn(['display_name', 'usage_hint', 'is_system']);
        });
    }
};
