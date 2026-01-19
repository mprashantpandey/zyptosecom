<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('coupons', function (Blueprint $table) {
            // Rename 'name' to 'title' if it exists, or add 'title'
            if (Schema::hasColumn('coupons', 'name')) {
                $table->renameColumn('name', 'title');
            } else {
                $table->string('title')->after('code');
            }
            
            // Add new fields
            $table->enum('applies_to', ['all', 'categories', 'products'])->default('all')->after('is_active');
            $table->json('rules_json')->nullable()->after('metadata');
            $table->foreignId('created_by')->nullable()->after('rules_json')->constrained('users')->onDelete('set null');
            $table->foreignId('updated_by')->nullable()->after('created_by')->constrained('users')->onDelete('set null');
            
            // Rename existing fields to match requirements
            if (Schema::hasColumn('coupons', 'minimum_amount')) {
                $table->renameColumn('minimum_amount', 'min_order_amount');
            }
            if (Schema::hasColumn('coupons', 'maximum_discount')) {
                $table->renameColumn('maximum_discount', 'max_discount_amount');
            }
            if (Schema::hasColumn('coupons', 'usage_limit')) {
                $table->renameColumn('usage_limit', 'usage_limit_total');
            }
        });
    }

    public function down(): void
    {
        Schema::table('coupons', function (Blueprint $table) {
            if (Schema::hasColumn('coupons', 'title')) {
                $table->renameColumn('title', 'name');
            }
            $table->dropColumn(['applies_to', 'rules_json', 'created_by', 'updated_by']);
            if (Schema::hasColumn('coupons', 'min_order_amount')) {
                $table->renameColumn('min_order_amount', 'minimum_amount');
            }
            if (Schema::hasColumn('coupons', 'max_discount_amount')) {
                $table->renameColumn('max_discount_amount', 'maximum_discount');
            }
            if (Schema::hasColumn('coupons', 'usage_limit_total')) {
                $table->renameColumn('usage_limit_total', 'usage_limit');
            }
        });
    }
};
