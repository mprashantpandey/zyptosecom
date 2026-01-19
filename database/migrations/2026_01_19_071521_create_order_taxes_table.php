<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_taxes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->unique()->constrained('orders')->onDelete('cascade');
            $table->enum('pricing_mode', ['inclusive', 'exclusive'])->default('exclusive');
            $table->decimal('taxable_amount', 10, 2); // Amount on which tax is calculated
            $table->decimal('tax_amount', 10, 2); // Total tax amount
            $table->decimal('cgst_amount', 10, 2)->nullable();
            $table->decimal('sgst_amount', 10, 2)->nullable();
            $table->decimal('igst_amount', 10, 2)->nullable();
            $table->foreignId('applied_rule_id')->nullable()->constrained('tax_rules')->onDelete('set null');
            $table->string('applied_rate_label')->nullable(); // Human-readable label
            $table->json('meta_json')->nullable(); // Internal metadata
            $table->timestamps();
            
            $table->index('order_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_taxes');
    }
};
