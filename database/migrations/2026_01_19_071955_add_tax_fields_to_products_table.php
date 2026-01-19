<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('tax_category')->nullable()->after('cost_price'); // e.g., 'food', 'electronics'
            $table->foreignId('tax_rate_id')->nullable()->after('tax_category')->constrained('tax_rates')->onDelete('set null');
            $table->boolean('tax_override')->default(false)->after('tax_rate_id'); // Allow override of default tax rules
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropForeign(['tax_rate_id']);
            $table->dropColumn(['tax_category', 'tax_rate_id', 'tax_override']);
        });
    }
};
