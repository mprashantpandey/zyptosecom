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
            $table->foreignId('order_id')->constrained('orders')->onDelete('cascade');
            $table->decimal('taxable_amount', 10, 2); // Amount on which tax is calculated
            $table->decimal('tax_amount', 10, 2); // Total tax amount
            $table->json('breakdown')->nullable(); // { "cgst": 50, "sgst": 50, "igst": 100 } - stored but not editable
            $table->foreignId('applied_rule_id')->nullable()->constrained('tax_rules')->onDelete('set null');
            $table->foreignId('applied_rate_id')->nullable()->constrained('tax_rates')->onDelete('set null');
            $table->timestamps();
            
            $table->index('order_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_taxes');
    }
};
